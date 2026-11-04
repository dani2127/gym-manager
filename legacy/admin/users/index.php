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

// Version check
$latest_version = @file_get_contents('https://api.gymoneglobal.com/latest/version.txt');
$current_version = $version;
$is_new_version_available = is_string($latest_version) && version_compare(trim($latest_version), $current_version) > 0;

// Get admin name
$sql = "SELECT lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

// Pagination
$per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $per_page;

$search_name = isset($_GET['search_name']) ? $_GET['search_name'] : '';
$search_email = isset($_GET['search_email']) ? $_GET['search_email'] : '';

$sql = "SELECT * FROM users";
$conditions = [];
$params = [];
$types = "";

if (!empty($search_name)) {
    $conditions[] = "(firstname LIKE ? OR lastname LIKE ?)";
    $like = "%$search_name%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}
if (!empty($search_email)) {
    $conditions[] = "email LIKE ?";
    $params[] = "%$search_email%";
    $types .= "s";
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " LIMIT ?, ?";
$params[] = (int)$start_from;
$params[] = (int)$per_page;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$page_title = $translations["users"];
include __DIR__ . '/../includes/head.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="admin-content">
        <!-- Search Form -->
        <div class="card animate-in" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                        <i class="bi bi-search"></i>
                    </div>
                    <div>
                        <div class="card-title">Search Members</div>
                        <div class="card-subtitle">Find members by name or email</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="GET">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["name-search"]; ?>
                            </label>
                            <input type="text" name="search_name" 
                                value="<?php echo htmlspecialchars($search_name); ?>"
                                placeholder="<?php echo $translations["name-search"]; ?>"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["email-search"]; ?>
                            </label>
                            <input type="text" name="search_email" 
                                value="<?php echo htmlspecialchars($search_email); ?>"
                                placeholder="<?php echo $translations["email-search"]; ?>"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                            <?php echo $translations["search"]; ?>
                        </button>
                        <a href="index.php" class="btn btn-ghost">
                            <i class="bi bi-arrow-clockwise"></i>
                            <?php echo $translations["resetbtn"]; ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card animate-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["users"]; ?></div>
                        <div class="card-subtitle">Manage all registered members</div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $translations["firstname"]; ?></th>
                            <th><?php echo $translations["lastname"]; ?></th>
                            <th><?php echo $translations["email"]; ?></th>
                            <th><?php echo $translations["expiredate"]; ?></th>
                            <th><?php echo $translations["action"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $initials = strtoupper(substr($row["firstname"], 0, 1) . substr($row["lastname"], 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <div class="table-user">
                                            <div class="table-avatar"><?php echo $initials; ?></div>
                                            <div class="table-user-info">
                                                <h4><?php echo htmlspecialchars($row["firstname"]); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row["lastname"]); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($row["email"]); ?>
                                        <?php if ($row["confirmed"] == "No"): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--accent-yellow); background: rgba(251, 191, 36, 0.1); padding: 2px 8px; border-radius: 12px; margin-left: 8px;">
                                                <i class="bi bi-exclamation-triangle-fill"></i>
                                                <?php echo $translations["waitingconfirm"]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $uid = $row["userid"];
                                        $ticket_sql = "SELECT expiredate FROM current_tickets WHERE userid = ? ORDER BY expiredate DESC LIMIT 1";
                                        $ticket_stmt = $conn->prepare($ticket_sql);
                                        $ticket_stmt->bind_param("i", $uid);
                                        $ticket_stmt->execute();
                                        $ticket_result = $ticket_stmt->get_result();

                                        if ($ticket_result->num_rows > 0) {
                                            $ticket = $ticket_result->fetch_assoc();
                                            $expiredate = $ticket["expiredate"];
                                            $today = new DateTime();
                                            $expire = new DateTime($expiredate);
                                            $expire->modify('+1 day');
                                            $originalExpire = new DateTime($expiredate);
                                            $diff = $today->diff($expire)->days;

                                            if ($expire > $today) {
                                                if ($originalExpire->format("Y-m-d") === $today->format("Y-m-d")) {
                                                    $diff = 1;
                                                }
                                                echo '<span style="color: var(--accent-green); font-weight: 600;">' . $diff . ' ' . $translations["day"] . '</span>';
                                            } else {
                                                echo '<span style="color: var(--accent-red); font-weight: 600;">' . $translations["expired"] . ' (' . $originalExpire->format("Y-m-d") . ')</span>';
                                            }
                                        } else {
                                            echo '<span style="color: var(--text-muted);">' . $translations["youdonthaveticket"] . '</span>';
                                        }
                                        $ticket_stmt->close();
                                        ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-primary btn-sm" href="edit/?user=<?php echo $row["userid"]; ?>">
                                            <i class="bi bi-eye"></i>
                                            <?php echo $translations["profilesee"]; ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-people"></i></div><h3>No members found</h3><p>Try adjusting your search criteria</p></div></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
        // Pagination
        $sql = "SELECT COUNT(*) AS total FROM users";
        $conditions = [];
        $params = [];
        $types = "";
        if (!empty($search_name)) {
            $conditions[] = "(firstname LIKE ? OR lastname LIKE ?)";
            $like = "%$search_name%";
            $params[] = $like;
            $params[] = $like;
            $types .= "ss";
        }
        if (!empty($search_email)) {
            $conditions[] = "email LIKE ?";
            $params[] = "%$search_email%";
            $types .= "s";
        }
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_pages = ceil($row["total"] / $per_page);

        if ($total_pages > 1):
        ?>
        <div style="display: flex; justify-content: center; margin-top: 24px;">
            <div style="display: flex; gap: 8px;">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php if (!empty($search_name)) echo '&search_name=' . urlencode($search_name); ?><?php if (!empty($search_email)) echo '&search_email=' . urlencode($search_email); ?>"
                    class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-ghost'; ?> btn-sm">
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
