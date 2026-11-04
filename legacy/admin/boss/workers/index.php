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

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;
$langDir = __DIR__ . "/../../../assets/lang/";
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

$alerts_html = '';

// Handle add worker
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"])) {
    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $username_post = $_POST["username"];
    $password = $_POST["password"];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_this_boss = isset($_POST["is_boss"]) ? 1 : 0;
    $newuserid = mt_rand(1000000000, 9999999994);

    $sql = "INSERT INTO workers (userid, Firstname, Lastname, username, password_hash, is_boss) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssi", $newuserid, $firstname, $lastname, $username_post, $hashed_password, $is_this_boss);

    if ($stmt->execute()) {
        $alerts_html .= "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> " . $translations["success-add"] . "</div>";
    } else {
        $alerts_html .= "<div class='alert alert-danger'><i class='bi bi-x-circle-fill'></i> Error: " . $conn->error . "</div>";
    }
    $stmt->close();
}

// Handle delete worker
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_user"])) {
    $deleteuserid = (int)$_POST["userid"];
    if ($deleteuserid != 1) {
        $sql = "DELETE FROM workers WHERE userid = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $deleteuserid);
        if ($stmt->execute()) {
            $alerts_html .= "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> " . $translations["success-delete"] . "</div>";
            header("Refresh:2");
        }
        $stmt->close();
    } else {
        $alerts_html .= "<div class='alert alert-warning'><i class='bi bi-exclamation-triangle-fill'></i> " . $translations["cant-delete-main"] . "</div>";
        header("Refresh:2");
    }
}

// Get all workers
$sql = "SELECT userid, Firstname, Lastname, username, is_boss FROM workers";
$result = $conn->query($sql);

$page_title = $translations["workers"];
include __DIR__ . '/../../../admin/includes/head.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="admin-main">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="admin-content">
        <?php echo $alerts_html; ?>

        <?php if ($is_boss == 1): ?>
        <!-- Workers Table -->
        <div class="card animate-in" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--accent-purple);">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["workers"]; ?></div>
                        <div class="card-subtitle">Manage staff members</div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?php echo $translations["firstname"]; ?></th>
                            <th><?php echo $translations["lastname"]; ?></th>
                            <th><?php echo $translations["username"]; ?></th>
                            <th><?php echo $translations["position"]; ?></th>
                            <th><?php echo $translations["action"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $initials = strtoupper(substr($row["Firstname"], 0, 1) . substr($row["Lastname"], 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <div class="table-user">
                                            <div class="table-avatar"><?php echo $initials; ?></div>
                                            <div class="table-user-info">
                                                <h4><?php echo htmlspecialchars($row["Firstname"]); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row["Lastname"]); ?></td>
                                    <td><?php echo htmlspecialchars($row["username"]); ?></td>
                                    <td>
                                        <?php if ($row["is_boss"] == 1): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--accent-orange); background: rgba(249, 115, 22, 0.1); padding: 4px 12px; border-radius: 20px;">
                                                <i class="bi bi-shield-fill"></i>
                                                <?php echo $translations["boss"]; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--accent-blue); background: rgba(59, 130, 246, 0.1); padding: 4px 12px; border-radius: 20px;">
                                                <i class="bi bi-person"></i>
                                                <?php echo $translations["worker"]; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="userid" value="<?php echo $row["userid"]; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" name="delete_user" onclick="return confirm('Are you sure?');">
                                                <i class="bi bi-trash"></i>
                                                <?php echo $translations["delete"]; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-people"></i></div><h3>No workers found</h3><p>Add your first staff member below</p></div></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Worker Form -->
        <div class="card animate-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--accent-green);">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["register"]; ?></div>
                        <div class="card-subtitle">Add new staff member</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["firstname"]; ?>
                            </label>
                            <input type="text" name="firstname" required
                                placeholder="First name"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["lastname"]; ?>
                            </label>
                            <input type="text" name="lastname" required
                                placeholder="Last name"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["username"]; ?>
                            </label>
                            <input type="text" name="username" required
                                placeholder="Username"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["password"]; ?>
                            </label>
                            <input type="password" name="password" required
                                placeholder="Password"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                    </div>
                    <div style="margin-top: 16px; display: flex; align-items: center; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; color: var(--text-secondary);">
                            <input type="checkbox" name="is_boss" value="1" style="width: 18px; height: 18px; accent-color: var(--accent-orange);">
                            <?php echo $translations["isboss-or-not"]; ?>
                        </label>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-success" style="margin-top: 16px;">
                        <i class="bi bi-person-plus"></i>
                        <?php echo $translations["register"]; ?>
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo $translations["dont-access"]; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="../../../assets/js/date-time.js"></script>
</body>
</html>

<?php $conn->close(); ?>
