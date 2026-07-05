<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

function read_env_file($file_path)
{
    $env_file = file_get_contents($file_path);
    $env_lines = explode("\n", $env_file);
    $env_data = [];

    foreach ($env_lines as $line) {
        $line_parts = explode('=', $line);
        if (count($line_parts) == 2) {
            $key = trim($line_parts[0]);
            $value = trim($line_parts[1]);
            $env_data[$key] = $value;
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
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

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

// API!
$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

$conn->close();
?>


<?php $page_title = $translations["updatepage"]; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<body>
    <nav class="navbar navbar-inverse visible-xs">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#myNavbar">
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#"><img src="../../assets/img/logo.png" width="50px" alt="Logo"></a>
            </div>
            <div class="collapse navbar-collapse" id="myNavbar">
                <ul class="nav navbar-nav">
                    <li><a href="../dashboard"><i class="bi bi-speedometer"></i> <?php echo $translations["mainpage"]; ?></a></li>
                    <li><a href="../users"><i class="bi bi-people"></i> <?php echo $translations["users"]; ?></a></li>
                    <li><a href="../statistics"><i class="bi bi-bar-chart"></i> <?php echo $translations["statspage"]; ?></a></li>
                    <li><a href="../boss/sell"><i class="bi bi-shop"></i> <?php echo $translations["sellpage"]; ?></a></li>
                    <li><a href="../invoices"><i class="bi bi-receipt"></i> <?php echo $translations["invoicepage"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#"><i class="bi bi-gear"></i> <?php echo $translations["settings"]; ?> <span class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="../boss/mainsettings"><?php echo $translations["businesspage"]; ?></a></li>
                                <li><a href="../boss/workers"><?php echo $translations["workers"]; ?></a></li>
                                <li><a href="../boss/packages"><?php echo $translations["packagepage"]; ?></a></li>
                                <li><a href="../boss/hours"><?php echo $translations["openhourspage"]; ?></a></li>
                                <li><a href="../boss/smtp"><?php echo $translations["mailpage"]; ?></a></li>
                                <li><a href="../boss/chroom"><?php echo $translations["chroompage"]; ?></a></li>
                                <li><a href="../boss/rule"><?php echo $translations["rulepage"]; ?></a></li>
                            </ul>
                        </li>
                    <?php } ?>
                    <li><a href="../shop/tickets"><i class="bi bi-ticket"></i> <?php echo $translations["ticketspage"]; ?></a></li>
                    <li><a href="../trainers/timetable"><i class="bi bi-calendar-event"></i> <?php echo $translations["timetable"]; ?></a></li>
                    <li><a href="../trainers/personal"><i class="bi bi-award"></i> <?php echo $translations["trainers"]; ?></a></li>
                    <?php if ($is_boss === 1) { ?>
                        <li class="active"><a href="#"><i class="bi bi-cloud-download"></i> <?php echo $translations["updatepage"]; ?>
                                <?php if ($is_new_version_available) : ?>
                                    <span class="badge badge-warning"><i class="bi bi-exclamation-circle"></i></span>
                                <?php endif; ?>
                            </a></li>
                    <?php } ?>
                    <li><a href="../log"><i class="bi bi-clock-history"></i> <?php echo $translations["logpage"]; ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row content">
            <?php include __DIR__ . '/../includes/sidebar.php'; ?>
            <br>
            <div class="col-sm-10">
                <?php include __DIR__ . '/../includes/topbar.php'; ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php if ($is_new_version_available) { ?>
                            <div class="card fireworks shadow mb-4">
                                <div class="card-body">
                                    <h2><?php echo $translations["updateavilable"]; ?></h2>
                                    <div class="alert alert-warning mt-3" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <?php echo $translations["makebackup"]; ?>
                                    </div>
                                    <p>
                                        <?php echo $translations["nowusedversion"]; ?>
                                        <code><?php echo $current_version; ?></code>
                                        <?php echo $translations["newversion"]; ?>
                                        <code><?php echo $latest_version; ?></code>
                                    </p>
                                    <p><?php echo $translations["readytoupdate"]; ?></p>
                                    <a href="updater.php" class="btn btn-primary"><?php echo $translations["downloadpage"]; ?></a>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <h2><?php echo $translations["thisislatest"]; ?></h2>
                                    <p>
                                        <?php echo $translations["latest-text"]; ?> -
                                        <a class="blacka" target="_blank" href="https://github.com/mayerbalintdev/GYM-One/releases/">
                                            <?php echo $translations["changelog"]; ?>
                                        </a>
                                        <code><?php echo $latest_version; ?></code>
                                    </p>
                                </div>
                            </div>
                            <div class="embed-responsive embed-responsive-16by9" style="height: 100vh;">
                                <iframe class="embed-responsive-item" src="https://gymoneglobal.com/CHANGELOG/" allowfullscreen></iframe>
                            </div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="../../assets/js/date-time.js"></script>
</body>

</html>


