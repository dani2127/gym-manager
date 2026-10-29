<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

function read_env_file($file_path)
{
    if (!is_readable($file_path)) {
        return [];
    }
    $env_data = [];
    foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($file_path)) as $line) {
        if (trim($line) === '' || strpos(ltrim($line), '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env_data[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $env_data;
}

$env_data = read_env_file('../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;
$langDir = __DIR__ . "/../../assets/lang/";
$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("Language file not found: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Check if boss
$sql = "SELECT is_boss FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->store_result();
$is_boss = null;
if ($stmt->num_rows > 0) {
    $stmt->bind_result($is_boss);
    $stmt->fetch();
}
$stmt->close();

// Get admin name
$sql = "SELECT lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

// Version check
$latest_version = @file_get_contents('https://api.gymoneglobal.com/latest/version.txt');
$current_version = $version;
$is_new_version_available = is_string($latest_version) && version_compare(trim($latest_version), $current_version) > 0;

// Fetch all logs
$sql = "SELECT logs.id, logs.userid, workers.username as username, logs.action, logs.actioncolor, logs.time, logs.details
        FROM logs 
        LEFT JOIN workers ON logs.userid = workers.userid 
        ORDER BY logs.time DESC";
$result = $conn->query($sql);

$logs = [];
while ($row = $result->fetch_assoc()) {
    $row['details'] = $row['details'] ? json_decode($row['details'], true) : [];
    if (!$row['username']) {
        $row['username'] = "System";
    }
    $logs[] = $row;
}

// Handle delete old logs
$delete_message = "";
if (isset($_POST['delete_old_logs'])) {
    $date_limit = date('Y-m-d', strtotime('-15 days'));
    $sql = "DELETE FROM logs WHERE time < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date_limit);
    if ($stmt->execute()) {
        $delete_message = $translations["success-log-delete"];
        header("Refresh:2");
    }
    $stmt->close();
}

$conn->close();

$page_title = $translations["logpage"];
include __DIR__ . '/../includes/head.php';
?>

<style>
    .log-details { display: none; margin-top: 10px; padding: 16px; background: rgba(255,255,255,0.02); border-radius: 10px; border-left: 3px solid var(--accent-blue); }
    .log-details.show { display: block; }
    .detail-row { padding: 8px 0; border-bottom: 1px solid var(--border-color); }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 600; color: var(--text-secondary); display: inline-block; min-width: 150px; }
    .detail-value { color: var(--text-primary); }
    .log-row { cursor: pointer; transition: var(--transition-fast); }
    .log-row:hover { background: rgba(255,255,255,0.02); }
    .expand-icon { transition: transform 0.2s; }
    .expand-icon.rotated { transform: rotate(90deg); }
</style>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="admin-content">
        <?php if ($delete_message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?php echo $delete_message; ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card animate-in" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                        <i class="bi bi-funnel"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["filters"]; ?></div>
                        <div class="card-subtitle">Filter logs by user, type, or date</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                            <?php echo $translations["username"]; ?>
                        </label>
                        <input type="text" id="userFilter" placeholder="Search by user..."
                            style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                            <?php echo $translations["type"]; ?>
                        </label>
                        <select id="typeFilter"
                            style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                            <option value=""><?php echo $translations["alloption"]; ?></option>
                            <option value="success"><?php echo $translations["successoption"]; ?></option>
                            <option value="warning"><?php echo $translations["warningoption"]; ?></option>
                            <option value="danger"><?php echo $translations["dangeroption"]; ?></option>
                            <option value="info"><?php echo $translations["infooption"]; ?></option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                            <?php echo $translations["from-date"]; ?>
                        </label>
                        <input type="date" id="dateFrom"
                            style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                            <?php echo $translations["to-date"]; ?>
                        </label>
                        <input type="date" id="dateTo"
                            style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card animate-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(249, 115, 22, 0.1); color: var(--accent-orange);">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["logpage"]; ?></div>
                        <div class="card-subtitle">System activity logs</div>
                    </div>
                </div>
                <form method="POST">
                    <button type="submit" name="delete_old_logs" class="btn btn-danger btn-sm" onclick="return confirm('Delete logs older than 15 days?');">
                        <i class="bi bi-trash"></i>
                        <?php echo $translations["deletelog"]; ?>
                    </button>
                </form>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo $translations["username"]; ?></th>
                            <th><?php echo $translations["action-log"]; ?></th>
                            <th><?php echo $translations["date-log"]; ?></th>
                            <th><?php echo $translations["details"]; ?></th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                    </tbody>
                </table>
                <div id="noLogsMessage" class="empty-state" style="display: none;">
                    <div class="empty-state-icon"><i class="bi bi-clock-history"></i></div>
                    <h3><?php echo $translations["notexist-log"]; ?></h3>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="../../assets/js/date-time.js"></script>

<script>
let allLogs = <?php echo json_encode($logs, JSON_UNESCAPED_UNICODE); ?>;

function renderLogDetails(details) {
    if (!details || Object.keys(details).length === 0) {
        return '<p style="color: var(--text-muted);">No details available</p>';
    }
    let html = '';
    for (const [key, value] of Object.entries(details)) {
        html += `<div class="detail-row"><span class="detail-label">${key}:</span><span class="detail-value">${value}</span></div>`;
    }
    return html;
}

function toggleDetails(logId) {
    const detailsDiv = document.getElementById('details-' + logId);
    const icon = document.getElementById('icon-' + logId);
    detailsDiv.classList.toggle('show');
    icon.classList.toggle('rotated');
}

function renderLogs(logs) {
    const tbody = document.getElementById('logsTableBody');
    const noLogsMessage = document.getElementById('noLogsMessage');
    
    if (logs.length === 0) {
        tbody.innerHTML = '';
        noLogsMessage.style.display = 'block';
        return;
    }
    
    noLogsMessage.style.display = 'none';
    
    tbody.innerHTML = logs.map(log => {
        const colorMap = { success: 'var(--accent-green)', warning: 'var(--accent-yellow)', danger: 'var(--accent-red)', info: 'var(--accent-blue)' };
        const color = colorMap[log.actioncolor] || 'var(--text-secondary)';
        return `
            <tr class="log-row" onclick="toggleDetails(${log.id})">
                <td style="font-weight: 600; color: var(--text-muted);">#${log.id}</td>
                <td>${log.username} <span style="color: var(--text-muted); font-size: 12px;">(ID: ${log.userid})</span></td>
                <td><span style="color: ${color}; font-weight: 500;">${log.action}</span></td>
                <td style="color: var(--text-muted);">${log.time}</td>
                <td><i class="bi bi-chevron-right expand-icon" id="icon-${log.id}"></i></td>
            </tr>
            <tr><td colspan="5" style="padding: 0;"><div class="log-details" id="details-${log.id}">${renderLogDetails(log.details)}</div></td></tr>
        `;
    }).join('');
}

function applyFilters() {
    const userFilter = document.getElementById('userFilter').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;

    const filtered = allLogs.filter(log => {
        const userMatch = !userFilter || log.username.toLowerCase().includes(userFilter) || log.userid.toString().includes(userFilter);
        const typeMatch = !typeFilter || log.actioncolor === typeFilter;
        const logDate = new Date(log.time);
        const fromMatch = !dateFrom || logDate >= new Date(dateFrom);
        const toMatch = !dateTo || logDate <= new Date(dateTo + ' 23:59:59');
        return userMatch && typeMatch && fromMatch && toMatch;
    });

    renderLogs(filtered);
}

document.getElementById('userFilter').addEventListener('input', applyFilters);
document.getElementById('typeFilter').addEventListener('change', applyFilters);
document.getElementById('dateFrom').addEventListener('change', applyFilters);
document.getElementById('dateTo').addEventListener('change', applyFilters);

renderLogs(allLogs);
</script>
</body>
</html>
