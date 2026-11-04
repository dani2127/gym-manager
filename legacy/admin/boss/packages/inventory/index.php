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

$sql = "SELECT * FROM products ORDER BY stock ASC, name ASC";
$result = $conn->query($sql);

$searchResult = null;
$productId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    $barcode = $conn->real_escape_string($_POST['barcode']);
    $searchSql = "SELECT * FROM products WHERE barcode = '$barcode'";
    $searchResult = $conn->query($searchSql)->fetch_assoc();
    if ($searchResult) {
        $productId = $searchResult['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $newStock = (int)$_POST['new_stock'];

    $updateSql = "UPDATE products SET stock = $newStock WHERE id = $id";
    if ($conn->query($updateSql) === TRUE) {
        header("Refresh: 1");
        $action = $translations['newpiece'] . ': ' . $newStock . 'ID:' . $id;
        $actioncolor = 'warning';
        $sql = "INSERT INTO logs (userid, action, actioncolor, time) VALUES (?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userid, $action, $actioncolor);
        $stmt->execute();
        exit;
    } else {
        echo "Hiba a frissítés során: " . $conn->error;
    }
}

?>


<?php $page_title = $translations["packagepage"] ?? 'Inventory'; ?>
<?php include __DIR__ . '/../../../includes/head.php'; ?>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://unpkg.com/quagga@0.12.1/dist/quagga.min.js"></script>
<!-- INLINE STYLE -- Reason: Collision -->
<style>
    #reader {
        position: relative;
        width: 100%;
        height: auto;
        max-width: 600px;
        margin: 0 auto;
        overflow: hidden;
    }

    #reader video {
        width: 100%;
        height: auto;
    }

    #reader canvas {
        display: none;
    }

    @media (max-width: 768px) {
        #reader {
            height: 300px;
        }
    }

    @media (max-width: 480px) {
        #reader {
            height: 200px;
        }
    }
</style>



<body>
<?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
<main class="admin-main">
    <?php include __DIR__ . '/../../../includes/topbar.php'; ?>
    <div class="admin-content">
                <div class="card">
                    <div class="card-header">
                        <h1 class="text-center mb-4"><?php echo $translations["werhousecorrection"]; ?></h1>
                    </div>

                </div>


                <div class="mb-4">
                    <h5><?php echo $translations["barcodescannerwithcamera"]; ?></h5>
                    <div class="row">
                        <div class="col-sm-6">
                            <div id="reader" style="width: 100%; height: 400px;"></div>

                        </div>
                        <div class="col-sm-6">
                            <img src="../../../../assets/img/partner/goupcapi.svg" class="img img-fluid" alt="GoUpCPartner-GymOne-DontRemove">
                            <h1 class="lead"><?php echo $translations["partner_goupcapi_information"]; ?></h1>
                            <a href="https://go-upc.com/" target="_blank" class="btn btn-secondary"><i class="bi bi-info-circle"></i> <?php echo $translations["partner_otherbtn"]; ?></a>
                        </div>
                    </div>
                </div>

                <form method="POST" class="mt-4">
                    <div class="input-group">
                        <input type="text" name="barcode" class="form-control" placeholder="<?php echo $translations["product-barcode"]; ?>" required>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> <?php echo $translations["search"]; ?></button>
                    </div>
                </form>

                <?php if ($searchResult): ?>
                    <div class="alert alert-info">
                        <strong>Product Name: !NOTRANSLATE</strong> <?php echo htmlspecialchars($searchResult['name']); ?><br>
                        <strong>ID:</strong> <?php echo $productId; ?>
                    </div>
                <?php endif; ?>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo $translations["product-name"]; ?></th>
                            <th><?php echo $translations["description"]; ?></th>
                            <th><?php echo $translations["amount"]; ?></th>
                            <th><?php echo $translations["stockmodify"]; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td><?php echo $row['stock']; ?></td>
                                <td>
                                    <form method="POST" class="form-inline">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <div class="form-group">
                                            <input type="number" name="new_stock" class="form-control" value="<?php echo $row['stock']; ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-warning"><i class="bi bi-box-arrow-down"></i> <?php echo $translations["save"]; ?></button>
                                    </form>

                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
 </div>
</main>

    <?php
    $conn->close();
    ?>
    <!-- SCRIPTS! -->
    <script>
        function requestCameraAccessAndStartScanner() {
            navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: "environment"
                    }
                })
                .then(function(stream) {
                    stream.getTracks().forEach(track => track.stop());
                    startBarcodeScanner();
                })
                .catch(function(err) {
                    console.error("Camera permission denied or error occurred:", err);
                    alert("A kamera használatához engedély szükséges.");
                });
        }

        function startBarcodeScanner() {
            Quagga.init({
                inputStream: {
                    type: "LiveStream",
                    constraints: {
                        facingMode: "environment",
                        width: {
                            ideal: 640
                        },
                        height: {
                            ideal: 480
                        }
                    },
                    target: document.getElementById('reader'),
                    willReadFrequently: true
                },
                decoder: {
                    readers: ["ean_reader", "ean_8_reader"]
                }
            }, function(err) {
                if (err) {
                    console.error("The Quagga could not start:", err);
                    alert("Hiba történt a kamera indításakor.");
                    return;
                }
                Quagga.start();
            });

            Quagga.onDetected(function(result) {
                const barcode = result.codeResult.code;
                console.log("Scanned barcode:", barcode);

                fetch("get_BARCODE.php?barcode=" + barcode)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.id) {
                            window.location.href = "../edit?id=" + data.id;
                        } else {
                            console.warn("Invalid barcode, retry...");
                        }
                    })
                    .catch(err => {
                        console.error("An error occurred during the query:", err);
                    });
            });
        }

        window.onload = function() {
            requestCameraAccessAndStartScanner();
        };

        window.addEventListener('resize', function() {
            Quagga.stop();
            requestCameraAccessAndStartScanner();
        });
    </script>

</body>

</html>


