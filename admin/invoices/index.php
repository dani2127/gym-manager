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
$currency = $env_data["CURRENCY"] ?? '';

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

// Version check
$latest_version = @file_get_contents('https://api.gymoneglobal.com/latest/version.txt');
$current_version = $version;
$is_new_version_available = is_string($latest_version) && version_compare(trim($latest_version), $current_version) > 0;

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

// Pagination
$records_per_page = 15;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start_from = ($page - 1) * $records_per_page;

$sql = "SELECT * FROM invoices ORDER BY created_at DESC LIMIT $start_from, $records_per_page";
$result = $conn->query($sql);

$total_records_query = "SELECT COUNT(*) AS total FROM invoices";
$total_records_result = $conn->query($total_records_query);
$total_records = $total_records_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$page_title = $translations["invoicepage"];
include __DIR__ . '/../includes/head.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="admin-content">
        <!-- Invoices Table -->
        <div class="card animate-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                        <i class="bi bi-receipt"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["invoicepage"]; ?></div>
                        <div class="card-subtitle">Manage all invoices</div>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <span style="padding: 6px 14px; background: rgba(59, 130, 246, 0.1); color: var(--accent-blue); border-radius: 20px; font-size: 13px; font-weight: 600;">
                        <?php echo $total_records; ?> Total
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $translations["fullname"]; ?></th>
                            <th><?php echo $translations["price"]; ?></th>
                            <th><?php echo $translations["date-log"]; ?></th>
                            <th><?php echo $translations["status"]; ?></th>
                            <th><?php echo $translations["interact"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $isPaid = $row["status"] !== 'unpaid';
                                ?>
                                <tr>
                                    <td>
                                        <div class="table-user">
                                            <div class="table-avatar"><?php echo strtoupper(substr($row['name'], 0, 2)); ?></div>
                                            <div class="table-user-info">
                                                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--text-primary);">
                                            <?php echo $row['price']; ?> <?php echo $currency; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['created_at']; ?></td>
                                    <td>
                                        <?php if ($isPaid): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--accent-green); background: rgba(34, 197, 94, 0.1); padding: 4px 12px; border-radius: 20px;">
                                                <i class="bi bi-check-circle-fill"></i>
                                                <?php echo $translations["paid"]; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--accent-red); background: rgba(239, 68, 68, 0.1); padding: 4px 12px; border-radius: 20px;">
                                                <i class="bi bi-x-circle-fill"></i>
                                                <?php echo $translations["unpaid"]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a target="_blank" href="../../assets/docs/invoices/<?php echo $row['route']; ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye"></i>
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-receipt"></i></div><h3>No invoices found</h3><p>Invoices will appear here when members make purchases</p></div></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; margin-top: 24px;">
            <div style="display: flex; gap: 8px;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-ghost'; ?> btn-sm">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="../../assets/js/date-time.js"></script>
</body>
</html>

<?php $conn->close(); ?>
