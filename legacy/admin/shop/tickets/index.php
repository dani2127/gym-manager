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
$currency = $env_data["CURRENCY"] ?? '';

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

// Handle add ticket
$alerts_html = "";
if (isset($_POST['add_ticket'])) {
    $name = $_POST['name'];
    $expire_days = $_POST['expire_days'] === 'unlimited' ? NULL : $_POST['expire_days'];
    $price = $_POST['price'];
    $occasions = $_POST['occasions'] === '' ? NULL : $_POST['occasions'];

    $sql = "INSERT INTO tickets (name, expire_days, price, occasions) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sidi", $name, $expire_days, $price, $occasions);

    if ($stmt->execute()) {
        $alerts_html .= "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> " . $translations['success-add'] . "</div>";
    } else {
        $alerts_html .= "<div class='alert alert-danger'><i class='bi bi-x-circle-fill'></i> " . $translations['error-add'] . "</div>";
    }
    $stmt->close();
}

// Handle delete ticket
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql_delete = "DELETE FROM tickets WHERE id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id);
    
    if ($stmt_delete->execute()) {
        $alerts_html .= "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> " . $translations['success-delete'] . "</div>";
    } else {
        $alerts_html .= "<div class='alert alert-danger'><i class='bi bi-x-circle-fill'></i> " . $translations['error-delete'] . "</div>";
    }
    $stmt_delete->close();
    header("Refresh:1");
}

// Get all tickets
$sql = "SELECT * FROM tickets";
$result = $conn->query($sql);

$page_title = $translations["ticketspage"];
include __DIR__ . '/../../../admin/includes/head.php';
?>

<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<main class="admin-main">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="admin-content">
        <?php echo $alerts_html; ?>

        <?php if ($is_boss == 1): ?>
        <!-- Add Ticket Form -->
        <div class="card animate-in" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--accent-green);">
                        <i class="bi bi-plus-circle"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["ticketsandpassesadd"]; ?></div>
                        <div class="card-subtitle">Create new membership ticket</div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form method="post">
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["ticketspassname"]; ?>
                            </label>
                            <input type="text" name="name" required
                                placeholder="e.g., Monthly Pass"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["tickettableexpiry"]; ?>
                            </label>
                            <input type="text" name="expire_days"
                                placeholder="Days or 'unlimited'"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["price"]; ?> (<?php echo $currency; ?>)
                            </label>
                            <input type="number" name="price" required step="0.01"
                                placeholder="0.00"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">
                                <?php echo $translations["tickettableoccassion"]; ?>
                            </label>
                            <input type="number" name="occasions"
                                placeholder="Leave empty for unlimited"
                                style="width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 10px; color: var(--text-primary); font-size: 14px; font-family: inherit;">
                        </div>
                    </div>
                    <button type="submit" name="add_ticket" class="btn btn-success" style="margin-top: 16px;">
                        <i class="bi bi-plus-circle"></i>
                        <?php echo $translations["ticketsandpassesadd"]; ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tickets List -->
        <div class="card animate-in">
            <div class="card-header">
                <div class="card-header-left">
                    <div class="card-header-icon">
                        <i class="bi bi-ticket-fill"></i>
                    </div>
                    <div>
                        <div class="card-title"><?php echo $translations["ticketsandpasseslist"]; ?></div>
                        <div class="card-subtitle">All available tickets and passes</div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo $translations["tickettablename"]; ?></th>
                            <th><?php echo $translations["tickettableexpiry"]; ?></th>
                            <th><?php echo $translations["price"]; ?> (<?php echo $currency; ?>)</th>
                            <th><?php echo $translations["tickettableoccassion"]; ?></th>
                            <th><?php echo $translations["interact"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($row['name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (is_null($row['expire_days'])): ?>
                                            <span style="color: var(--accent-green); font-weight: 600;"><?php echo $translations["unlimited"]; ?></span>
                                        <?php else: ?>
                                            <span><?php echo $row['expire_days']; ?> days</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--accent-orange);"><?php echo $row['price']; ?> <?php echo $currency; ?></span>
                                    </td>
                                    <td>
                                        <?php if (is_null($row['occasions'])): ?>
                                            <span style="color: var(--text-muted);">-</span>
                                        <?php else: ?>
                                            <span><?php echo $row['occasions']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_boss == 1): ?>
                                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ticket?');">
                                            <i class="bi bi-trash"></i>
                                            <?php echo $translations["delete"]; ?>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-ticket"></i></div><h3>No tickets created</h3><p>Create your first ticket to get started</p></div></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../../../assets/js/date-time.js"></script>
</body>
</html>

<?php $conn->close(); ?>
