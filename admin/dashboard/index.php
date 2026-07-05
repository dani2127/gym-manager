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

function http_get($url, $timeout = 4)
{
    $ctx = stream_context_create([
        'http' => ['timeout' => $timeout, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? null : $data;
}

$env_data = read_env_file('../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$capacity = $env_data["CAPACITY"] ?? '';

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

// Registration chart data
$months = [
    "01" => $translations["Jan"], "02" => $translations["Feb"], "03" => $translations["Mar"],
    "04" => $translations["Apr"], "05" => $translations["May"], "06" => $translations["Jun"],
    "07" => $translations["Jul"], "08" => $translations["Aug"], "09" => $translations["Sep"],
    "10" => $translations["Oct"], "11" => $translations["Nov"], "12" => $translations["Dec"]
];

$current_month = (int) date('m');
$current_year = (int) date('Y');
$categories = [];
$dataRegistrations = [];

for ($i = 11; $i >= 0; $i--) {
    $timestamp = mktime(0, 0, 0, $current_month - $i, 1, $current_year);
    $year_month = date("Y-m", $timestamp);
    $categories[] = $months[date('m', $timestamp)] . ' ' . date('Y', $timestamp);
    $dataRegistrations[$year_month] = 0;
}

$sqlRegistrations = "SELECT DATE_FORMAT(registration_date, '%Y-%m') as reg_month, COUNT(*) as count 
                     FROM users 
                     WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                     GROUP BY reg_month ORDER BY reg_month";
$resultRegistrations = $conn->query($sqlRegistrations);

if ($resultRegistrations->num_rows > 0) {
    while ($row = $resultRegistrations->fetch_assoc()) {
        $dataRegistrations[$row['reg_month']] = $row['count'];
    }
}

// User count
$sqlUserCount = "SELECT COUNT(*) as count FROM users";
$resultUserCount = $conn->query($sqlUserCount);
$userCount = 0;
if ($resultUserCount->num_rows > 0) {
    $row = $resultUserCount->fetch_assoc();
    $userCount = $row["count"];
}

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

// Version check
$latest_version = http_get('https://api.gymoneglobal.com/latest/version.txt', 4);
$current_version = $version;
$is_new_version_available = is_string($latest_version) && version_compare(trim($latest_version), $current_version) > 0;

// Daily users
$total_people = 0;
$sql = "SELECT COALESCE(SUM(number_of_people), 0) AS total_people FROM temp_dailyworkout";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $r = $result->fetch_assoc();
    $total_people = $r['total_people'];
}

// Current logged users
$sql = "SELECT name, userid, login_date FROM temp_loggeduser";
$result = $conn->query($sql);

$total_count = 0;
$capacityPercent = 0;

$sql_count = "SELECT COUNT(*) AS total_count FROM temp_loggeduser";
$result_count = $conn->query($sql_count);

if ($result_count) {
    $row_count = $result_count->fetch_assoc();
    $total_count = $row_count['total_count'];
    if ($capacity > 0) {
        $capacityPercent = ($total_count / $capacity) * 100;
    }
}

$progresscolor = 'green';
if ($capacityPercent >= 70 && $capacityPercent < 90) {
    $progresscolor = 'yellow';
} elseif ($capacityPercent >= 90) {
    $progresscolor = 'red';
}

// Get admin name
$sql = "SELECT lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

$conn->close();

// Emergency numbers
$countryCode = '';
$ipInfoRaw = http_get('https://ipinfo.io/json', 4);
if ($ipInfoRaw) {
    $ipInfo = json_decode($ipInfoRaw, true);
    $countryCode = $ipInfo['country'] ?? '';
}

$ambulanceNumbers = $translations["unknown"];
$fireNumbers = $translations["unknown"];
$policeNumbers = $translations["unknown"];

if ($countryCode !== '') {
    $jsonData = http_get('https://emergencynumberapi.com/api/data/all', 4);
    $data = $jsonData ? json_decode($jsonData, true) : null;
    if (is_array($data)) {
        foreach ($data as $item) {
            if (isset($item['Country']['ISOCode']) && $item['Country']['ISOCode'] == $countryCode) {
                $ambulanceNumbers = isset($item['Ambulance']['All']) ? implode(', ', $item['Ambulance']['All']) : $translations["unknown"];
                $fireNumbers = isset($item['Fire']['All']) ? implode(', ', $item['Fire']['All']) : $translations["unknown"];
                $policeNumbers = isset($item['Police']['All']) ? implode(', ', $item['Police']['All']) : $translations["unknown"];
                break;
            }
        }
    }
}

// Calculate capacity circle
$circumference = 2 * 45 * M_PI; // radius 45
$offset = $circumference - ($capacityPercent / 100) * $circumference;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $translations["dashboard"]; ?> - <?php echo $business_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../../assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="../../assets/img/logo.png" alt="Logo">
            <div class="logo-text">
                <h1><?php echo $business_name; ?></h1>
                <span>v<?php echo $version; ?></span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <?php echo $translations["mainpage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../users" class="nav-link">
                        <i class="bi bi-people-fill"></i>
                        <?php echo $translations["users"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../statistics" class="nav-link">
                        <i class="bi bi-bar-chart-fill"></i>
                        <?php echo $translations["statspage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/sell" class="nav-link">
                        <i class="bi bi-shop"></i>
                        <?php echo $translations["sellpage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../invoices" class="nav-link">
                        <i class="bi bi-receipt"></i>
                        <?php echo $translations["invoicepage"]; ?>
                    </a>
                </div>
            </div>

            <?php if ($is_boss === 1): ?>
            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <div class="nav-item">
                    <a href="../boss/mainsettings" class="nav-link">
                        <i class="bi bi-gear-fill"></i>
                        <?php echo $translations["businesspage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/workers" class="nav-link">
                        <i class="bi bi-person-gear"></i>
                        <?php echo $translations["workers"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/packages" class="nav-link">
                        <i class="bi bi-box-seam-fill"></i>
                        <?php echo $translations["packagepage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/hours" class="nav-link">
                        <i class="bi bi-clock-fill"></i>
                        <?php echo $translations["openhourspage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/smtp" class="nav-link">
                        <i class="bi bi-envelope-at-fill"></i>
                        <?php echo $translations["mailpage"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../boss/rule" class="nav-link">
                        <i class="bi bi-file-ruled"></i>
                        <?php echo $translations["rulepage"]; ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <div class="nav-section-title">Shop</div>
                <div class="nav-item">
                    <a href="../shop/tickets" class="nav-link">
                        <i class="bi bi-ticket-fill"></i>
                        <?php echo $translations["ticketspage"]; ?>
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Trainers</div>
                <div class="nav-item">
                    <a href="../trainers/timetable" class="nav-link">
                        <i class="bi bi-calendar-event-fill"></i>
                        <?php echo $translations["timetable"]; ?>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../trainers/personal" class="nav-link">
                        <i class="bi bi-award-fill"></i>
                        <?php echo $translations["trainers"]; ?>
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <?php if ($is_boss === 1): ?>
                <div class="nav-item">
                    <a href="../updater" class="nav-link">
                        <i class="bi bi-cloud-download-fill"></i>
                        <?php echo $translations["updatepage"]; ?>
                        <?php if ($is_new_version_available): ?>
                            <span class="nav-badge">!</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="../log" class="nav-link">
                        <i class="bi bi-clock-history"></i>
                        <?php echo $translations["logpage"]; ?>
                    </a>
                </div>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php echo strtoupper(substr($username, 0, 2)); ?>
                </div>
                <div class="sidebar-user-info">
                    <h4><?php echo htmlspecialchars($username); ?></h4>
                    <span><?php echo $is_boss === 1 ? 'Admin' : 'Staff'; ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">
        <!-- Top Bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <h2><?php echo $translations["dashboard"]; ?></h2>
            </div>
            <div class="topbar-right">
                <div class="topbar-time" id="clock"></div>
                <a href="https://gymoneglobal.com/docs" class="topbar-btn topbar-btn-outline" target="_blank">
                    <i class="bi bi-journals"></i>
                    Docs
                </a>
                <button type="button" class="topbar-btn topbar-btn-primary" data-toggle="modal" data-target="#logoutModal">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="admin-content">
            <?php if ($is_boss == 1 && $is_new_version_available): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $translations["newupdate-text"]; ?>
            </div>
            <?php endif; ?>

            <?php if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo $translations['notusehttps']; ?>
            </div>
            <?php endif; ?>

            <?php
            $ruleContent = @file_get_contents('../boss/rule/rule.html');
            if (empty($ruleContent)):
            ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?php echo $translations['gymrulenotset']; ?>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card animate-in" style="--card-accent: var(--accent-orange);">
                    <div class="stat-card-header">
                        <div class="stat-card-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $userCount; ?></div>
                    <div class="stat-card-label"><?php echo $translations["users"]; ?></div>
                </div>

                <div class="stat-card animate-in" style="--card-accent: var(--accent-green);">
                    <div class="stat-card-header">
                        <div class="stat-card-icon green">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $total_people; ?></div>
                    <div class="stat-card-label"><?php echo $translations["dailyusers"]; ?></div>
                </div>

                <div class="stat-card animate-in" style="--card-accent: var(--accent-blue);">
                    <div class="stat-card-header">
                        <div class="stat-card-icon blue">
                            <i class="bi bi-door-open-fill"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $total_count; ?></div>
                    <div class="stat-card-label">Currently In Gym</div>
                </div>

                <div class="stat-card animate-in" style="--card-accent: var(--accent-purple);">
                    <div class="stat-card-header">
                        <div class="stat-card-icon purple">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                    </div>
                    <div class="stat-card-value" style="font-size: 18px; padding-top: 8px;">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#Logginer_MODAL" style="width: 100%;">
                            <i class="bi bi-qr-code"></i>
                            <?php echo $translations["userlogginer"]; ?>
                        </button>
                    </div>
                    <div class="stat-card-label"><?php echo $translations["logginer"]; ?></div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="content-grid">
                <!-- Registration Chart -->
                <div class="card animate-in">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-header-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div>
                                <div class="card-title"><?php echo $translations["new-users"]; ?></div>
                                <div class="card-subtitle">Last 12 months</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="chart" class="chart-container"></div>
                    </div>
                </div>

                <!-- Capacity Widget -->
                <div class="card animate-in">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-header-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--accent-green);">
                                <i class="bi bi-activity"></i>
                            </div>
                            <div>
                                <div class="card-title"><?php echo $translations["capacitytext"]; ?></div>
                                <div class="card-subtitle">Real-time occupancy</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="capacity-widget">
                            <div class="capacity-circle">
                                <svg viewBox="0 0 100 100">
                                    <circle class="bg" cx="50" cy="50" r="45"></circle>
                                    <circle class="progress <?php echo $progresscolor; ?>" cx="50" cy="50" r="45"
                                        stroke-dasharray="<?php echo $circumference; ?>"
                                        stroke-dashoffset="<?php echo $offset; ?>">
                                    </circle>
                                </svg>
                                <div class="capacity-value">
                                    <div class="number"><?php echo number_format($capacityPercent, 0); ?>%</div>
                                    <div class="label">Capacity</div>
                                </div>
                            </div>
                            <div class="capacity-stats">
                                <div class="capacity-stat">
                                    <div class="capacity-stat-value"><?php echo $total_count; ?></div>
                                    <div class="capacity-stat-label">Inside Now</div>
                                </div>
                                <div class="capacity-stat">
                                    <div class="capacity-stat-value"><?php echo $capacity; ?></div>
                                    <div class="capacity-stat-label">Max Capacity</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div class="content-grid">
                <!-- Active Users Table -->
                <div class="card animate-in">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <div>
                                <div class="card-title">Active Users</div>
                                <div class="card-subtitle"><?php echo $total_count; ?> currently training</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $translations["fullname"]; ?></th>
                                    <th><?php echo $translations["logintime"]; ?></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result && $result->num_rows > 0) {
                                    $counter = 1;
                                    while ($row = $result->fetch_assoc()) {
                                        $current_time = new DateTime();
                                        $login_time = new DateTime($row["login_date"]);
                                        $interval = $current_time->diff($login_time);
                                        $elapsed_time = $interval->format('%h:%I:%s');
                                        
                                        $initials = strtoupper(substr($row["name"], 0, 2));
                                        ?>
                                        <tr>
                                            <td><?php echo $counter; ?></td>
                                            <td>
                                                <div class="table-user">
                                                    <div class="table-avatar"><?php echo $initials; ?></div>
                                                    <div class="table-user-info">
                                                        <h4><?php echo htmlspecialchars($row["name"]); ?></h4>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $elapsed_time; ?></td>
                                            <td>
                                                <a class="btn btn-danger btn-sm" href="logout.php?user=<?php echo urlencode($row["userid"]); ?>">
                                                    <i class="bi bi-box-arrow-right"></i>
                                                </a>
                                                <a class="btn btn-ghost btn-sm" href="../users/edit/?user=<?php echo urlencode($row["userid"]); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                        $counter++;
                                    }
                                } else {
                                    echo '<tr><td colspan="4"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-person-x"></i></div><h3>' . $translations["noonetraining"] . '</h3><p>No users are currently logged in</p></div></td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Emergency Numbers -->
                <div class="animate-in">
                    <div class="emergency-card">
                        <h4>
                            <i class="bi bi-shield-fill-check"></i>
                            <?php echo $translations["emernumtext"]; ?>
                        </h4>
                        <div class="emergency-item">
                            <div class="emergency-item-label">
                                <i class="bi bi-heart-pulse-fill text-red"></i>
                                <?php echo $translations["ambulance"]; ?>
                            </div>
                            <div class="emergency-item-value"><?php echo $ambulanceNumbers; ?></div>
                        </div>
                        <div class="emergency-item">
                            <div class="emergency-item-label">
                                <i class="bi bi-fire text-orange"></i>
                                <?php echo $translations["fireresistor"]; ?>
                            </div>
                            <div class="emergency-item-value"><?php echo $fireNumbers; ?></div>
                        </div>
                        <div class="emergency-item">
                            <div class="emergency-item-label">
                                <i class="bi bi-shield-fill text-blue"></i>
                                <?php echo $translations["police"]; ?>
                            </div>
                            <div class="emergency-item-value"><?php echo $policeNumbers; ?></div>
                        </div>
                    </div>

                    <!-- Support Card -->
                    <div class="card" style="margin-top: 20px;">
                        <div class="card-body" style="text-align: center; padding: 32px;">
                            <div style="font-size: 32px; margin-bottom: 12px;">💙</div>
                            <div class="card-title" style="margin-bottom: 8px;">
                                <?php echo $translations["gymonesupport_header"]; ?>
                            </div>
                            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
                                <?php echo $translations["gymonesupport_text_one"]; ?>
                            </p>
                            <a href="https://github.com/sponsors/mayerbalintdev" target="_blank" class="btn btn-success">
                                <i class="bi bi-heart-fill"></i>
                                <?php echo $translations["sponsor-btn"]; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" style="margin-top: 100px;">
            <div class="modal-content">
                <div class="modal-body text-center" style="padding: 40px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-box-arrow-right" style="color: var(--accent-red); font-size: 36px;"></i>
                    </div>
                    <h4 style="font-weight: 700; margin-bottom: 12px; color: var(--text-primary);">
                        <?php echo $translations["exit-modal"]; ?>
                    </h4>
                    <p style="color: var(--text-muted); margin-bottom: 24px;">Are you sure you want to logout?</p>
                    <div>
                        <button type="button" class="btn btn-ghost" data-dismiss="modal" style="margin-right: 12px;">
                            <?php echo $translations["not-yet"]; ?>
                        </button>
                        <a href="../logout.php" class="btn btn-danger">
                            <i class="bi bi-check-circle"></i>
                            <?php echo $translations["confirm"]; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome Modal -->
    <div class="modal fade" id="welcomeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center" style="padding: 48px;">
                    <img src="../../assets/img/brand/logo.png" width="80" style="margin-bottom: 24px;" alt="Logo">
                    <h2 id="modalMessage" style="font-weight: 700; margin-bottom: 12px;"></h2>
                    <p style="color: var(--text-muted);"><?php echo $translations["haveagoodday"]; ?></p>
                    <button type="button" class="btn btn-primary" data-dismiss="modal" style="margin-top: 24px;">
                        <?php echo $translations["next"]; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Check-in Modal -->
    <div class="modal fade" id="Logginer_MODAL" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="card-header-left">
                        <div class="card-header-icon">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <div>
                            <div class="card-title"><?php echo $translations["userlogginer"]; ?></div>
                            <div class="card-subtitle">Scan QR or search member</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-icon btn-ghost" data-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="video-container" style="position: relative; width: 100%; height: 280px; border-radius: 12px; overflow: hidden; background: #000; margin-bottom: 16px;">
                        <video id="video" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover;"></video>
                        <div class="scan-frame" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; border: 2px solid var(--accent-orange); border-radius: 16px;"></div>
                        <div id="checkmark" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 64px; color: var(--accent-green);">✔</div>
                        <div id="error" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 64px; color: var(--accent-red);">✘</div>
                    </div>
                    <p id="result" style="text-align: center; color: var(--text-muted); margin-bottom: 16px;"><?php echo $translations["qrscann"]; ?></p>
                    
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                        <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
                        <span style="font-size: 12px; color: var(--text-muted);"><?php echo $translations["or"]; ?></span>
                        <div style="flex: 1; height: 1px; background: var(--border-color);"></div>
                    </div>

                    <div style="position: relative;">
                        <i class="bi bi-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input id="search" type="search" autocomplete="off" placeholder="<?php echo $translations["name-search"]; ?>"
                            style="width: 100%; padding: 14px 16px 14px 44px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-light); border-radius: 12px; color: var(--text-primary); font-size: 14px; font-family: inherit;"
                            aria-label="Search">
                    </div>
                    <div id="results" style="margin-top: 12px;"></div>
                    <input hidden id="qrcodeContent">
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-ghost" data-dismiss="modal"><?php echo $translations["close"]; ?></button>
                    <a type="button" id="continueButton" class="btn btn-primary" style="display:none;">
                        <?php echo $translations["next"]; ?> <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="UserDetails_MODAL" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="card-header-left">
                        <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <div>
                            <div class="card-title"><?php echo $translations["userinfo"]; ?></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-icon btn-ghost" data-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="userDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-dismiss="modal"><?php echo $translations["close"]; ?></button>
                    <button id="nextButton" class="btn btn-primary" disabled>
                        <?php echo $translations["next"]; ?> <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Details Modal -->
    <div class="modal fade" id="TicketDetails_MODAL" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="card-header-left">
                        <div class="card-header-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--accent-green);">
                            <i class="bi bi-ticket-detailed"></i>
                        </div>
                        <div>
                            <div class="card-title"><?php echo $translations["ticketinfomodal"]; ?></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-icon btn-ghost" data-dismiss="modal">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body" id="ticketDetails"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal" onclick="window.location.reload();">
                        <i class="bi bi-check-lg"></i> <?php echo $translations["close"]; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>window.translations = <?php echo json_encode($translations); ?>;</script>
    <script src="checkin.js"></script>
    <script src="../../assets/js/date-time.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

    <!-- Registration Chart -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let seriesData = Object.values(<?php echo json_encode($dataRegistrations); ?>);

            var options = {
                chart: {
                    type: 'area',
                    height: 280,
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent'
                },
                colors: ['#F97316'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.1,
                        stops: [0, 100]
                    }
                },
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                series: [{
                    name: '<?php echo $translations["reg-number"]; ?>',
                    data: seriesData
                }],
                xaxis: {
                    categories: <?php echo json_encode($categories); ?>,
                    labels: {
                        style: {
                            colors: '#6B7280',
                            fontSize: '11px'
                        }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    tickAmount: Math.max(...seriesData),
                    min: 0,
                    labels: {
                        style: { colors: '#6B7280', fontSize: '11px' },
                        formatter: function (value) { return Math.floor(value); }
                    }
                },
                grid: {
                    borderColor: 'rgba(255,255,255,0.05)',
                    strokeDashArray: 4
                },
                dataLabels: { enabled: false },
                tooltip: {
                    theme: 'dark',
                    style: {
                        fontSize: '12px',
                        fontFamily: 'Plus Jakarta Sans'
                    }
                }
            };

            var chart = new ApexCharts(document.querySelector("#chart"), options);
            chart.render();
        });
    </script>

    <!-- Welcome Modal Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const now = new Date();
            const hours = now.getHours();
            let message = '';

            if (hours < 12) {
                message = '<?php echo $translations["morninghello"]; ?>';
            } else if (hours < 17) {
                message = '<?php echo $translations["dayhello"]; ?>';
            } else {
                message = '<?php echo $translations["nighthello"]; ?>';
            }
            
            const username = "<?php echo $username; ?>";
            const finalMessage = `${message} ${username}!`;
            const today = new Date().toISOString().split('T')[0];

            if (localStorage.getItem('modalShownDate') !== today) {
                document.getElementById('modalMessage').innerText = finalMessage;
                $('#welcomeModal').modal('show');
                localStorage.setItem('modalShownDate', today);
            }
        });
    </script>
</body>
</html>
