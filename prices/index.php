<?php function read_env_file($file_path)
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
$copyright_year = date("Y");

$env_data = read_env_file('../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$country = $env_data['COUNTRY'] ?? '';
$street = $env_data['STREET'] ?? '';
$city = $env_data['CITY'] ?? '';
$hause_no = $env_data['HOUSE_NUMBER'] ?? '';
$description = $env_data['DESCRIPTION'] ?? '';
$metakey = $env_data['META_KEY'] ?? '';
$gkey = $env_data['GOOGLE_KEY'] ?? '';
$mailadress = $env_data['MAIL_USERNAME'] ?? '';
$phoneno = $env_data['PHONE_NO'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$dayNames = [
    1 => $translations["Mon"],
    2 => $translations["Tue"],
    3 => $translations["Wed"],
    4 => $translations["Thu"],
    5 => $translations["Fri"],
    6 => $translations["Sat"],
    7 => $translations["Sun"]
];

$days = [];
$result = $conn->query("SELECT * FROM opening_hours ORDER BY day ASC");
while ($row = $result->fetch_assoc()) {
    $days[] = $row;
}

$today = new DateTime('today');
$maxDate = (new DateTime('today'))->modify('+14 days');

$todayStr = $today->format('Y-m-d');
$maxDateStr = $maxDate->format('Y-m-d');

$exceptions = [];
$stmt = $conn->prepare("
    SELECT * 
    FROM opening_hours_exceptions 
    WHERE date BETWEEN ? AND ?
    ORDER BY date ASC
");
$stmt->bind_param("ss", $todayStr, $maxDateStr);
$stmt->execute();

$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $exceptions[] = $row;
}
$stmt->close();

$months = [
    1 => $translations["Jan"],
    2 => $translations["Feb"],
    3 => $translations["Mar"],
    4 => $translations["Apr"],
    5 => $translations["May"],
    6 => $translations["Jun"],
    7 => $translations["Jul"],
    8 => $translations["Aug"],
    9 => $translations["Sep"],
    10 => $translations["Oct"],
    11 => $translations["Nov"],
    12 => $translations["Dec"],
];


$sql = "SELECT * FROM tickets";
$result = $conn->query($sql);

?>




<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $business_name; ?> - <?php echo $translations["pricespage"]; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
    <meta name="title" content="<?php echo $business_name; ?> - <?php echo $translations["pricespage"]; ?>">
    <meta name="description" content="<?php echo $description; ?>">
    <meta name="keywords" content="<?php echo $metakey; ?>">
    <meta name="robots" content="index, follow">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="author" content="<?php echo $business_name; ?>">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $gkey; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', '<?php echo $gkey; ?>');
    </script>
    <style>
        .prices-hero {
            padding: 8rem 2rem 3rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8)),
                        radial-gradient(circle at 20% 80%, rgba(249, 115, 22, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(34, 197, 94, 0.08) 0%, transparent 50%);
        }
        .prices-hero h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        .prices-hero p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        .prices-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            padding: 2rem;
        }
        .price-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .price-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .price-card:hover::before {
            transform: scaleX(1);
        }
        .price-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border-color: var(--primary);
        }
        .price-card h5 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--primary);
        }
        .price-card .price-value {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--foreground);
            margin-bottom: 0.5rem;
        }
        .price-card .price-value span {
            font-size: 1rem;
            font-weight: 400;
            color: var(--text-muted);
        }
        .price-card .price-detail {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        .price-card .price-detail strong {
            color: var(--foreground);
        }
        .site-footer {
            background: var(--background);
            border-top: 1px solid var(--border);
            padding: 4rem 2rem 2rem;
        }
        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }
        .footer-brand img {
            height: 60px;
            margin-bottom: 1rem;
        }
        .footer-brand p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .footer-hours h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--foreground);
        }
        .hours-item {
            display: flex;
            justify-content: space-between;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        .hours-item:last-child {
            border-bottom: none;
        }
        .hours-item .day {
            font-weight: 600;
            color: var(--foreground);
        }
        .hours-item .time {
            color: var(--primary);
        }
        .footer-bottom {
            max-width: 1200px;
            margin: 3rem auto 0;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .footer-bottom a {
            color: var(--primary);
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .prices-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <a href="../" class="navbar-brand">
            <img src="../assets/img/brand/logo.png" alt="<?php echo $business_name; ?> Logo">
            <span><?php echo $business_name; ?></span>
        </a>
        <ul class="navbar-nav">
            <li><a href="../"><?php echo $translations["mainpage"]; ?></a></li>
            <li><a href="../trainers"><?php echo $translations["trainerspage"]; ?></a></li>
            <li><a href="#" class="active" style="color: var(--primary);"><?php echo $translations["pricespage"]; ?></a></li>
            <li><a href="../contact/"><?php echo $translations["contactpage"]; ?></a></li>
        </ul>
        <div class="d-flex align-center gap-2">
            <a href="../login/" class="btn btn-outline btn-sm">
                <i class="bi bi-person-circle"></i> <?php echo $translations["login"] ?? 'Login'; ?>
            </a>
        </div>
    </nav>

    <!-- Page Content -->
    <div class="page-content">
        <!-- Hero -->
        <section class="prices-hero">
            <h1><?php echo $translations["pricelist"]; ?></h1>
            <p><?php echo $business_name; ?> - <?php echo $translations["pricespage"]; ?></p>
        </section>

        <!-- Pricing Cards -->
        <section style="padding: 2rem;">
            <?php
            if ($result->num_rows > 0) {
                echo "<div class='prices-grid'>";

                while ($row = $result->fetch_assoc()) {
                    echo "<div class='price-card'>";
                    echo "<h5>" . htmlspecialchars($row["name"]) . "</h5>";
                    echo "<div class='price-value'>" . htmlspecialchars($row["price"]) . " " . htmlspecialchars($currency) . "</div>";
                    echo "<p class='price-detail'>" . $translations["tickettableexpiry"] . ": <strong>" . htmlspecialchars($row["expire_days"]) . " " . ($translations["days"] ?? 'days') . "</strong></p>";
                    $occasions = $row["occasions"] === NULL ? $translations["unlimited"] : htmlspecialchars($row["occasions"]);
                    echo "<p class='price-detail'>" . $translations["tickettableoccassion"] . ": <strong>" . $occasions . "</strong></p>";
                    echo "</div>";
                }

                echo "</div>";
            } else {
                echo "<div class='alert alert-warning' style='max-width: 600px; margin: 2rem auto;'>" . $translations["notickets"] . "</div>";
            }
            ?>
        </section>

        <!-- Footer -->
        <footer class="site-footer">
            <div class="footer-grid">
                <div class="footer-brand">
                    <img src="../assets/img/brand/logo.png" alt="<?php echo $business_name; ?> Logo">
                    <p><?php echo $city; ?></p>
                    <p><?php echo $street; ?> <?php echo $hause_no; ?></p>
                </div>
                <div class="footer-hours">
                    <h5><?php echo $translations["openinghours"] ?? 'Opening Hours'; ?></h5>
                    <?php if (!empty($days)): ?>
                        <?php foreach ($days as $day): ?>
                            <div class="hours-item">
                                <span class="day"><?= htmlspecialchars($dayNames[$day['day']]) ?></span>
                                <?php if (is_null($day['open_time']) && is_null($day['close_time'])): ?>
                                    <span class="time" style="color: var(--accent);"><?= $translations["closed"]; ?></span>
                                <?php else: ?>
                                    <span class="time">
                                        <?= date('H:i', strtotime($day['open_time'])) ?> -
                                        <?= date('H:i', strtotime($day['close_time'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($exceptions as $ex): ?>
                            <?php
                            $date = new DateTime($ex['date']);
                            $monthName = $months[(int) $date->format('n')];
                            $dayNum = $date->format('j');
                            ?>
                            <div class="hours-item">
                                <span class="day"><?= $monthName . ' ' . $dayNum . '.' ?></span>
                                <?php if ($ex['is_closed']): ?>
                                    <span class="time" style="color: var(--accent);"><?= $translations["closed"]; ?></span>
                                <?php else: ?>
                                    <span class="time" style="color: var(--primary-light);">
                                        <?= date('H:i', strtotime($ex['open_time'])) ?> -
                                        <?= date('H:i', strtotime($ex['close_time'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-bottom">
                <p>
                    Copyright &copy; <?php echo $copyright_year; ?> <?php echo $business_name; ?> -
                    <?php echo $translations["copyright"]; ?>
                    &nbsp;<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="red"
                        class="bi bi-heart-fill" viewBox="0 0 16 16">
                        <path fill-rule="evenodd"
                            d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314">
                        </path>
                    </svg>
                    <a href="https://www.gymoneglobal.com/?lang=<?php echo $lang_code; ?>">GYM One</a>
                </p>
            </div>
        </footer>
    </div>
</body>

</html>
