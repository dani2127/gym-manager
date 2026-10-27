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
                     FROM users WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
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

// Average duration
$sql = "SELECT AVG(duration) AS avg_duration FROM workout_stats WHERE duration IS NOT NULL";
$result = $conn->query($sql);
$avgDuration = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $avgDuration = round($row['avg_duration'], 0);
}

// Gender distribution
$sql = "SELECT gender, COUNT(*) as count FROM users GROUP BY gender";
$result = $conn->query($sql);
$maleCount = 0;
$femaleCount = 0;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($row["gender"] == "Male") $maleCount = $row["count"];
        elseif ($row["gender"] == "Female") $femaleCount = $row["count"];
    }
}

// Free lockers
$sql = "SELECT gender, COUNT(*) as free_lockers FROM lockers WHERE user_id IS NULL GROUP BY gender";
$result = $conn->query($sql);
$free_lockers = ['Male' => 0, 'Female' => 0];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $free_lockers[$row['gender']] = $row['free_lockers'];
    }
}

// Revenue data
$sql = "SELECT `date`, COALESCE(SUM(bank_card), 0) AS bank_card, COALESCE(SUM(cash), 0) AS cash 
        FROM `revenu_stats` WHERE `date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY `date` ORDER BY `date` ASC";
$result = $conn->query($sql);

$dates = [];
$bankCardData = [];
$cashData = [];
$formattedDates = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = ['bank_card' => 0, 'cash' => 0];
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dates[$row['date']]['bank_card'] = (float) $row['bank_card'];
        $dates[$row['date']]['cash'] = (float) $row['cash'];
    }
}

foreach ($dates as $date => $values) {
    $formattedDates[] = $date;
    $bankCardData[] = $values['bank_card'];
    $cashData[] = $values['cash'];
}

$conn->close();

$page_title = $translations["statspage"];
include __DIR__ . '/../includes/head.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="admin-main">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="admin-content">
        <?php if ($is_boss == 1 && $is_new_version_available): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?php echo $translations["newupdate-text"]; ?>
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

            <div class="stat-card animate-in" style="--card-accent: var(--accent-blue);">
                <div class="stat-card-header">
                    <div class="stat-card-icon blue">
                        <i class="bi bi-gender-male"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $maleCount; ?></div>
                <div class="stat-card-label">Male Members</div>
            </div>

            <div class="stat-card animate-in" style="--card-accent: var(--accent-purple);">
                <div class="stat-card-header">
                    <div class="stat-card-icon purple">
                        <i class="bi bi-gender-female"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $femaleCount; ?></div>
                <div class="stat-card-label">Female Members</div>
            </div>

            <div class="stat-card animate-in" style="--card-accent: var(--accent-green);">
                <div class="stat-card-header">
                    <div class="stat-card-icon green">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo $avgDuration; ?>m</div>
                <div class="stat-card-label">Avg. Workout</div>
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
                    <div id="registrationChart" class="chart-container"></div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="card animate-in">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-header-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--accent-green);">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div class="card-title">Revenue</div>
                            <div class="card-subtitle">Last 7 days</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="revenueChart" class="chart-container"></div>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="content-grid">
            <!-- Gender Distribution -->
            <div class="card animate-in">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-header-icon" style="background: rgba(168, 85, 247, 0.1); color: var(--accent-purple);">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <div class="card-title">Gender Distribution</div>
                            <div class="card-subtitle">Member breakdown</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="genderChart" class="chart-container" style="min-height: 250px;"></div>
                </div>
            </div>

            <!-- Lockers Status -->
            <div class="card animate-in">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-header-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-blue);">
                            <i class="bi bi-lock"></i>
                        </div>
                        <div>
                            <div class="card-title">Locker Status</div>
                            <div class="card-subtitle">Available lockers</div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div style="text-align: center; padding: 24px; background: rgba(59, 130, 246, 0.05); border-radius: 12px;">
                            <div style="font-size: 48px; font-weight: 800; color: var(--accent-blue);"><?php echo $free_lockers['Male']; ?></div>
                            <div style="font-size: 14px; color: var(--text-secondary); margin-top: 8px;">
                                <i class="bi bi-gender-male"></i> Male Lockers
                            </div>
                        </div>
                        <div style="text-align: center; padding: 24px; background: rgba(168, 85, 247, 0.05); border-radius: 12px;">
                            <div style="font-size: 48px; font-weight: 800; color: var(--accent-purple);"><?php echo $free_lockers['Female']; ?></div>
                            <div style="font-size: 14px; color: var(--text-secondary); margin-top: 8px;">
                                <i class="bi bi-gender-female"></i> Female Lockers
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="../../assets/js/date-time.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Registration Chart
    let regData = Object.values(<?php echo json_encode($dataRegistrations); ?>);
    var regOptions = {
        chart: { type: 'area', height: 280, fontFamily: 'Plus Jakarta Sans, sans-serif', toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
        colors: ['#F97316'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 100] } },
        stroke: { curve: 'smooth', width: 2 },
        series: [{ name: 'Registrations', data: regData }],
        xaxis: { categories: <?php echo json_encode($categories); ?>, labels: { style: { colors: '#6B7280', fontSize: '11px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: '#6B7280', fontSize: '11px' } } },
        grid: { borderColor: 'rgba(255,255,255,0.05)', strokeDashArray: 4 },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark' }
    };
    new ApexCharts(document.querySelector("#registrationChart"), regOptions).render();

    // Revenue Chart
    var revOptions = {
        chart: { type: 'bar', height: 280, fontFamily: 'Plus Jakarta Sans, sans-serif', toolbar: { show: false }, background: 'transparent' },
        colors: ['#22C55E', '#3B82F6'],
        series: [
            { name: 'Cash', data: <?php echo json_encode($cashData); ?> },
            { name: 'Card', data: <?php echo json_encode($bankCardData); ?> }
        ],
        xaxis: { categories: <?php echo json_encode($formattedDates); ?>, labels: { style: { colors: '#6B7280', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#6B7280', fontSize: '11px' } } },
        grid: { borderColor: 'rgba(255,255,255,0.05)', strokeDashArray: 4 },
        plotOptions: { bar: { borderRadius: 6, columnWidth: '60%' } },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark' },
        legend: { position: 'top', labels: { colors: '#9CA3AF' } }
    };
    new ApexCharts(document.querySelector("#revenueChart"), revOptions).render();

    // Gender Chart
    var genderOptions = {
        chart: { type: 'donut', height: 250, fontFamily: 'Plus Jakarta Sans, sans-serif', background: 'transparent' },
        colors: ['#3B82F6', '#A855F7'],
        series: [<?php echo $maleCount; ?>, <?php echo $femaleCount; ?>],
        labels: ['Male', 'Female'],
        plotOptions: { pie: { donut: { size: '70%' } } },
        legend: { position: 'bottom', labels: { colors: '#9CA3AF' } },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark' }
    };
    new ApexCharts(document.querySelector("#genderChart"), genderOptions).render();
});
</script>
</body>
</html>
