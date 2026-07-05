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

$env_data = read_env_file('../../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../../assets/lang/";

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

$message = "";


$file_path = 'https://api.gymoneglobal.com/latest/version.txt';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $file_path);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$latest_version = curl_exec($ch);
curl_close($ch);

$current_version = $version;

$is_new_version_available = version_compare($latest_version, $current_version) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = $conn->real_escape_string($_POST['price']);
    $stock = $conn->real_escape_string($_POST['stock']);
    $barcode = $conn->real_escape_string($_POST['barcode']);


    $insert_sql = "INSERT INTO products (name, description, price, stock, barcode) 
                   VALUES ('$name', '$description', '$price', '$stock', '$barcode')";

    if ($conn->query($insert_sql) === TRUE) {
        $action = $translations['log_newpackage'] . ' ' . $translations["product-name"] . '' . $name . ' ' . $translations["price"] . ':' . $price . ' ' . $translations["product-barcode"] . ':' . $barcode;
        $actioncolor = 'success';
        $sql = "INSERT INTO logs (userid, action, actioncolor, time) VALUES (?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userid, $action, $actioncolor);
        $stmt->execute();
        header("Location: ../");
        exit;
    } else {
        echo "UNEXPECTED ERROR: " . $conn->error;
    }
}

?>


<?php $page_title = $translations["packagepage"] ?? 'Add Package'; ?>
<?php include __DIR__ . '/../../../includes/head.php'; ?>

<body>
    <?php include __DIR__ . '/../../../includes/topbar.php'; ?>

    <div class="container-fluid">
        <div class="row content">
            <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
            <br>
            <div class="col-sm-10">
                <h1 class="text-center mb-4"><?php echo $translations["addpackage"]; ?></h1>

                <form method="POST">
                    <div class="mb-3">
                        <label for="name" class="form-label"><?php echo $translations["product-name"]; ?></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label"><?php echo $translations["description"]; ?>:</label>
                        <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label"><?php echo $translations["price"]; ?> <code><?php echo $currency; ?></code>:</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label"><?php echo $translations["piece"]; ?>:</label>
                        <input type="number" id="stock" name="stock" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="barcode" class="form-label"><?php echo $translations["product-barcode"]; ?>:</label>
                        <input type="number" id="barcode" name="barcode" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-down"></i> <?php echo $translations["add"]; ?></button>
                    <a href="../" class="btn btn-secondary"><i class="bi bi-box-arrow-left"></i> <?php echo $translations["not-yet"]; ?></a>
                </form>
            </div>
        </div>
    </div>

    <?php
    $conn->close();
    ?>
</body>

</html>


