<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

$alerts_html = "";

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

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$capacity = $env_data["CAPACITY"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../../../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("A nyelvi fájl nem található: $langFile");
}
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$translations = json_decode(file_get_contents($langFile), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['business_name'])) {
        $fields = [
            'BUSINESS_NAME' => 'business_name',
            'STREET' => 'street',
            'COUNTRY' => 'country',
            'CITY' => 'city',
            'HOUSE_NUMBER' => 'house_number',
            'DESCRIPTION' => 'description',
            'META_KEY' => 'metakey',
            'LANG_CODE' => 'lang_code',
            'CURRENCY' => 'currency',
            'GOOGLE_KEY' => 'gkey',
            'CAPACITY' => 'capacity',
            'PHONE_NO' => 'phone_no',
            'ABOUT' => 'about'
        ];

        $new_data = [];
        foreach ($fields as $env_key => $post_key) {
            $new_data[$env_key] = $_POST[$post_key] ?? '';
        }

        $old_data = [];
        foreach ($fields as $env_key => $post_key) {
            $old_data[$env_key] = $env_data[$env_key] ?? '';
        }

        $changes = [];
        foreach ($fields as $env_key => $post_key) {
            if ($old_data[$env_key] !== $new_data[$env_key]) {
                $changes["{$env_key}_old"] = $old_data[$env_key];
                $changes["{$env_key}_new"] = $new_data[$env_key];
            }
        }

        foreach ($fields as $env_key => $post_key) {
            $env_data[$env_key] = $new_data[$env_key];
        }

        $env_content = '';
        foreach ($env_data as $key => $value) {
            $env_content .= "$key=$value\n";
        }

        if (file_put_contents('../../../.env', $env_content) !== false) {
            if (!empty($changes)) {
                $log_sql = "INSERT INTO logs (userid, action, actioncolor, details, time) VALUES (?, ?, ?, ?, NOW())";
                $stmt_log = $conn->prepare($log_sql);
                $action = $translations['success-update-env-main'];
                $color = "info";
                $details = json_encode($changes, JSON_UNESCAPED_UNICODE);
                $stmt_log->bind_param("isss", $userid, $action, $color, $details);
                $stmt_log->execute();
                $stmt_log->close();
            }

            $alerts_html .= "<div class='alert alert-success'>{$translations["success-update"]}</div>";
            header("Refresh:2");
        } else {
            $alerts_html .= "<div class='alert alert-danger'>{$translations["error-env"]}</div>";
            header("Refresh:2");
        }
    }
}

function handleFileUpload($fileInputName, $targetFileName, $uploadDir, $submitName)
{
    if (isset($_POST[$submitName]) && isset($_FILES[$fileInputName])) {
        $file_type = strtolower(pathinfo($_FILES[$fileInputName]['name'], PATHINFO_EXTENSION));
        $allowed_types = array('png', 'jpg', 'jpeg');
        if (!in_array($file_type, $allowed_types)) {
            return;
        }
        if ($_FILES[$fileInputName]['size'] > 4000000) {
            return;
        }

        $file_tmp = $_FILES[$fileInputName]['tmp_name'];
        $target_file = $uploadDir . $targetFileName;

        if (move_uploaded_file($file_tmp, $target_file)) {
            header("Refresh:1");
            exit;
        }
    }
}

$upload_dir = '../../../assets/img/brand/';

handleFileUpload('logoFile', 'logo.png', $upload_dir, 'uploadLogo');
handleFileUpload('backgroundFile', 'background.png', $upload_dir, 'uploadBackground');
handleFileUpload('faviconFile', 'favicon.png', $upload_dir, 'uploadFavicon');


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

<?php $page_title = $translations["businesspage"] ?? 'Main Settings'; ?>
<?php include __DIR__ . '/../../includes/head.php'; ?>

    <div class="container-fluid">
        <div class="row content">
            <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
            <br>
            <div class="col-sm-10">
                <?php include __DIR__ . '/../../includes/topbar.php'; ?>
                <div class="row">
                    <div class="col-sm-12">
                        <?php echo $alerts_html; ?>
                        <div class="card shadow">
                            <div class="card-body">

                                <?php
                                if ($is_boss == 1) {
                                    ?>
                                    <form method="POST">
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label for="business_name"><?php echo $translations["gym-name"]; ?>:</label>
                                                <input type="text" class="form-control" id="business_name"
                                                    name="business_name" required
                                                    value="<?= htmlspecialchars($env_data['BUSINESS_NAME'] ?? '') ?>">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="lang_code"><?php echo $translations["lang"] ?>:</label>
                                                <select class="form-control" id="lang_code" name="lang_code">
                                                    <option value="HU" <?= ($env_data['LANG_CODE'] ?? '') == 'HU' ? 'selected' : '' ?>><?php echo $translations["HU"]; ?></option>
                                                    <option value="ES" <?= ($env_data['LANG_CODE'] ?? '') == 'ES' ? 'selected' : '' ?>><?php echo $translations["ES"]; ?></option>
                                                    <option value="GB" <?= ($env_data['LANG_CODE'] ?? '') == 'GB' ? 'selected' : '' ?>><?php echo $translations["GB"]; ?></option>
                                                    <option value="DE" <?= ($env_data['LANG_CODE'] ?? '') == 'DE' ? 'selected' : '' ?>><?php echo $translations["DE"]; ?></option>
                                                    <option value="TR" <?= ($env_data['LANG_CODE'] ?? '') == 'TR' ? 'selected' : '' ?>><?php echo $translations["TR"]; ?></option>

                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="alert alert-danger">
                                                    <?php echo $translations["restartserver"]; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-8">
                                                <label
                                                    for="description"><?php echo $translations["websitedescription"]; ?>:</label>
                                                <input type="text" class="form-control" id="description" name="description"
                                                    required minlength="20"
                                                    value="<?= htmlspecialchars($env_data['DESCRIPTION'] ?? '') ?>">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label for="capacity"><?php echo $translations["capacityenv"]; ?>:</label>
                                                <input type="number" class="form-control" id="capacity" name="capacity"
                                                    min="10" required
                                                    value="<?= htmlspecialchars($env_data['CAPACITY'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-12">
                                                <label for="metakey"><?php echo $translations["metakeys"]; ?>:</label>
                                                <input type="text" class="form-control" id="metakey" name="metakey"
                                                    value="<?= htmlspecialchars($env_data['META_KEY'] ?? '') ?>">
                                                <small id="keywordsInfo"
                                                    class="form-text"><?php echo $translations["metakeys-separeate"]; ?>
                                                    <code>(,)</code></small>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-4">
                                                <label for="country"><?php echo $translations["country"]; ?>:</label>
                                                <input type="text" class="form-control" id="country" name="country" required
                                                    value="<?= htmlspecialchars($env_data['COUNTRY'] ?? '') ?>">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="city"><?php echo $translations["city"]; ?>:</label>
                                                <input type="text" class="form-control" id="city" name="city" required
                                                    value="<?= htmlspecialchars($env_data['CITY'] ?? '') ?>">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label for="street"><?php echo $translations["street"]; ?>:</label>
                                                <input type="text" class="form-control" id="street" name="street" required
                                                    value="<?= htmlspecialchars($env_data['STREET'] ?? '') ?>">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label for="house_number"><?php echo $translations["hause-no"]; ?>:</label>
                                                <input type="number" class="form-control" id="house_number" required
                                                    name="house_number"
                                                    value="<?= htmlspecialchars($env_data['HOUSE_NUMBER'] ?? '') ?>">
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-4">
                                                    <label for="currency"><?php echo $translations["currency"]; ?>:</label>
                                                    <input type="text" class="form-control" id="currency" name="currency"
                                                        required
                                                        value="<?= htmlspecialchars($env_data['CURRENCY'] ?? '') ?>">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label for="phone_no"><?php echo $translations["fno"]; ?>:</label>
                                                    <input type="tel" class="form-control" id="phone_no" name="phone_no"
                                                        required
                                                        value="<?= htmlspecialchars($env_data['PHONE_NO'] ?? '') ?>">
                                                </div>
                                                <div class="form-group col-md-4">
                                                    <label
                                                        for="gkey"><?php echo $translations["googletrakckey"]; ?>:</label>
                                                    <input type="text" class="form-control" id="gkey" name="gkey"
                                                        value="<?= htmlspecialchars($env_data['GOOGLE_KEY'] ?? '') ?>">
                                                    <small><?php echo $translations["googlekeyonly"]; ?></small>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-sm-12">
                                                    <label for="about"><?php echo $translations["aboutdesc"]; ?>:</label>
                                                    <textarea class="form-control" id="about" name="about" required
                                                        minlength="50"
                                                        rows="3"><?= htmlspecialchars($env_data['ABOUT'] ?? '') ?></textarea>
                                                </div>
                                            </div>

                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i>
                                            <?php echo $translations["save"]; ?></button>

                                    </form>
                                    <?php
                                } else {
                                    echo $translations["dont-access"];
                                }

                                ?>

                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <?php
                        if ($is_boss == 1) {
                            ?>
                            <div class="col-md-4">
                                <div class="card shadow">
                                    <div class="card-header">
                                        <?php echo $translations["logo-upload"]; ?>
                                    </div>
                                    <div class="card-body">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <label
                                                    for="logoFile"><?php echo $translations["select-upload-logo"]; ?></label>
                                                <input type="file" class="form-control-file" id="logoFile" name="logoFile"
                                                    accept="image/png, image/jpeg">
                                            </div>
                                            <button type="submit" class="btn btn-primary"
                                                name="uploadLogo"><?php echo $translations["logo-upload"]; ?></button>
                                        </form>
                                        <div class="row text-center">
                                            <div class="col">
                                                <img class="img img-fluid" width="150px"
                                                    src="../../../assets/img/brand/logo.png?<?php echo filemtime("../../../assets/img/brand/logo.png"); ?>"
                                                    alt="Logo Preview">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow">
                                    <div class="card-header">
                                        <?php echo $translations["background-upload"]; ?>
                                    </div>
                                    <div class="card-body">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <label
                                                    for="backgroundFile"><?php echo $translations["select-upload-background"]; ?></label>
                                                <input type="file" class="form-control-file" id="backgroundFile"
                                                    name="backgroundFile" accept="image/png, image/jpeg">
                                            </div>
                                            <button type="submit" class="btn btn-primary"
                                                name="uploadBackground"><?php echo $translations["background-upload"]; ?></button>
                                        </form>
                                        <div class="row text-center">
                                            <div class="col">
                                                <img class="img img-fluid" width="150px"
                                                    src="../../../assets/img/brand/background.png?<?php echo filemtime("../../../assets/img/brand/background.png"); ?>"
                                                    alt="Background Preview">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow">
                                    <div class="card-header">
                                        <?php echo $translations["favicon-upload"]; ?>
                                    </div>
                                    <div class="card-body">
                                        <form action="" method="post" enctype="multipart/form-data">
                                            <div class="form-group">
                                                <label
                                                    for="faviconFile"><?php echo $translations["select-upload-favicon"]; ?></label>
                                                <input type="file" class="form-control-file" id="faviconFile"
                                                    name="faviconFile" accept="image/png, image/jpeg">
                                            </div>
                                            <button type="submit" class="btn btn-primary"
                                                name="uploadFavicon"><?php echo $translations["favicon-upload"]; ?></button>
                                        </form>
                                        <div class="row text-center">
                                            <div class="col">
                                                <img class="img img-fluid" width="150px"
                                                    src="../../../assets/img/brand/favicon.png?<?php echo filemtime("../../../assets/img/brand/favicon.png"); ?>"
                                                    alt="Favicon Preview">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        } else {
                            echo $translations["dont-access"];
                        }
                        ?>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS! -->
    <script src="../../../assets/js/date-time.js"></script>
</body>

</html>


