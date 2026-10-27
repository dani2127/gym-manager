<?php
session_start();

if (!isset($_SESSION['adminuser'])) {
    header("Location: ../");
    exit();
}

$userid = $_SESSION['adminuser'];

function read_env_file($file_path)
{
    if (!is_readable($file_path)) return [];
    $env_data = [];
    foreach (preg_split("/\r\n|\n|\r/", (string) file_get_contents($file_path)) as $line) {
        if (trim($line) === '' || strpos(ltrim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) $env_data[trim($parts[0])] = trim($parts[1]);
    }
    return $env_data;
}

function http_get($url, $timeout = 4)
{
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'ignore_errors' => true]]);
    $data = @file_get_contents($url, false, $ctx);
    return $data === false ? null : $data;
}

$env_data = read_env_file('../../../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$currency = $env_data['CURRENCY'] ?? '';
$business_name = $env_data['BUSINESS_NAME'] ?? '';
$lang_code = $env_data['LANG_CODE'] ?? '';
$version = $env_data["APP_VERSION"] ?? '';
$lang = $lang_code;

$langFile = __DIR__ . "/../../../assets/lang/{$lang}.json";
if (!file_exists($langFile)) {
    die("Language file not found: $langFile");
}
$translations = json_decode(file_get_contents($langFile), true);

$conn = new mysqli($db_host, $db_username, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection error: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function sp_initials($fn, $ln)
{
    $a = mb_substr(trim((string) $fn), 0, 1, 'UTF-8');
    $b = mb_substr(trim((string) $ln), 0, 1, 'UTF-8');
    $i = mb_strtoupper($a . $b, 'UTF-8');
    return $i !== '' ? $i : '?';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    header('Content-Type: text/html; charset=utf-8');
    $q = trim($_POST['q']);
    if (mb_strlen($q, 'UTF-8') < 1) {
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT userid, firstname, lastname
           FROM users
          WHERE CONCAT(firstname, ' ', lastname) LIKE ?
          ORDER BY firstname, lastname
          LIMIT 12"
    );
    $like = '%' . $q . '%';
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        while ($r = $res->fetch_assoc()) {
            $uid = htmlspecialchars($r['userid'], ENT_QUOTES);
            $fn  = htmlspecialchars($r['firstname'], ENT_QUOTES);
            $ln  = htmlspecialchars($r['lastname'], ENT_QUOTES);
            $ini = htmlspecialchars(sp_initials($r['firstname'], $r['lastname']), ENT_QUOTES);
            echo '<button type="button" class="sp-item" data-userid="' . $uid . '" data-firstname="' . $fn . '" data-lastname="' . $ln . '">'
                . '<span class="sp-item-ava">' . $ini . '</span>'
                . '<span class="sp-item-name">' . $fn . ' ' . $ln . '</span>'
                . '<i class="bi bi-chevron-right"></i>'
                . '</button>';
        }
    } else {
        echo '<div class="sp-empty">' . htmlspecialchars($translations['user-notexist'] ?? 'No results', ENT_QUOTES) . '</div>';
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim($_POST['lookup']);
    if ($id === '' || !ctype_digit($id)) {
        echo json_encode(['found' => false]);
        $conn->close();
        exit;
    }
    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE userid = ? LIMIT 1");
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode($u
        ? ['found' => true, 'userid' => $id, 'firstname' => $u['firstname'], 'lastname' => $u['lastname']]
        : ['found' => false]);
    $conn->close();
    exit;
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

// Get admin name
$sql = "SELECT lastname FROM workers WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userid);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

$latest_version = http_get('https://api.gymoneglobal.com/latest/version.txt', 4);
$current_version = $version;
$is_new_version_available = is_string($latest_version)
    && version_compare(trim($latest_version), $current_version) > 0;

$conn->close();

$page_title = $translations["sellpage"];
include __DIR__ . '/../../../admin/includes/head.php';
?>

<style>
    :root {
        --sp-accent: #F97316;
        --sp-accent2: #FB923C;
    }

    .sellpick { margin-top: 10px; }
    .sellpick * { box-sizing: border-box; }

    .sp-title { display: flex; align-items: center; gap: 14px; margin: 6px 0 18px; }
    .sp-title-icon {
        width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.2), rgba(249, 115, 22, 0.05)); color: var(--sp-accent); font-size: 26px;
        border: 1px solid rgba(249, 115, 22, 0.3);
    }
    .sp-title h3 { margin: 0; font-weight: 800; color: var(--text-primary); font-size: 20px; }
    .sp-title p { margin: 6px 0 0; color: var(--text-secondary); font-size: 14px; }

    .sp-card {
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 18px;
        padding: 20px; height: 100%; transition: var(--transition-normal);
    }
    .sp-card:hover { border-color: var(--border-light); }
    .sp-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .sp-card-icon {
        width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
        background: rgba(249, 115, 22, 0.1); color: var(--sp-accent); font-size: 20px;
    }
    .sp-card-head h5 { margin: 0; font-weight: 700; color: var(--text-primary); font-size: 15px; }

    #video-container { position: relative; width: 100%; aspect-ratio: 4/3; border-radius: 16px; overflow: hidden; background: #0b1020; border: 1px solid var(--border-color); }
    #video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .scan-frame { position: absolute; inset: 0; pointer-events: none; }
    .scan-frame span { position: absolute; width: 34px; height: 34px; border: 3px solid var(--sp-accent); }
    .scan-frame span:nth-child(1) { top: 16%; left: 16%; border-right: none; border-bottom: none; border-radius: 8px 0 0 0; }
    .scan-frame span:nth-child(2) { top: 16%; right: 16%; border-left: none; border-bottom: none; border-radius: 0 8px 0 0; }
    .scan-frame span:nth-child(3) { bottom: 16%; left: 16%; border-right: none; border-top: none; border-radius: 0 0 0 8px; }
    .scan-frame span:nth-child(4) { bottom: 16%; right: 16%; border-left: none; border-top: none; border-radius: 0 0 8px 0; }
    #checkmark, #error { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 62px; z-index: 3; text-shadow: 0 4px 16px rgba(0, 0, 0, .4); }
    #checkmark { color: var(--accent-green); }
    #error { color: var(--accent-red); }
    #video.scanned { filter: brightness(.6) saturate(1.2); }
    #video.error { filter: brightness(.6) sepia(1) hue-rotate(-30deg); }
    #result { margin: 14px 0 0; text-align: center; color: var(--text-secondary); font-size: 14px; min-height: 21px; }
    .sp-spin { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--border-light); border-top-color: var(--sp-accent); border-radius: 50%; vertical-align: -2px; animation: sp-rot .7s linear infinite; }
    @keyframes sp-rot { to { transform: rotate(360deg) } }

    .sp-search { position: relative; margin: 0 0 12px; }
    .sp-search i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    #sp-q {
        width: 100%; padding: 13px 16px 13px 46px; border-radius: 13px; border: 1px solid var(--border-light);
        background: rgba(255, 255, 255, 0.04); font-size: 15px; color: var(--text-primary); font-family: inherit; outline: none; transition: .15s;
    }
    #sp-q:focus { border-color: var(--sp-accent); background: rgba(255, 255, 255, 0.06); box-shadow: 0 0 0 4px rgba(249, 115, 22, .12); }
    #sp-q::placeholder { color: var(--text-muted); }

    #sp-results { display: flex; flex-direction: column; gap: 8px; max-height: 340px; overflow: auto; padding-right: 4px; }
    .sp-item {
        display: flex; align-items: center; gap: 12px; width: 100%; text-align: left;
        border: 1px solid var(--border-color); background: rgba(255, 255, 255, 0.02); border-radius: 13px;
        padding: 10px 14px; cursor: pointer; transition: var(--transition-fast);
    }
    .sp-item:hover { border-color: var(--sp-accent); background: rgba(249, 115, 22, 0.08); transform: translateY(-1px); }
    .sp-item-ava {
        width: 38px; height: 38px; flex: 0 0 38px; border-radius: 50%;
        background: linear-gradient(135deg, var(--sp-accent), var(--sp-accent2)); color: #fff;
        display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px;
    }
    .sp-item-name { flex: 1; font-weight: 600; color: var(--text-primary); }
    .sp-item i { color: var(--text-muted); }
    .sp-empty { padding: 16px; text-align: center; color: var(--text-muted); font-size: 14px; background: rgba(255, 255, 255, 0.02); border: 1px dashed var(--border-color); border-radius: 12px; }

    .sp-selected {
        display: none; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
        background: linear-gradient(135deg, rgba(249, 115, 22, 0.1), rgba(249, 115, 22, 0.03));
        border: 1.5px solid rgba(249, 115, 22, 0.3); border-radius: 18px; padding: 18px 22px; margin-bottom: 22px;
        box-shadow: var(--shadow-md);
    }
    .sp-selected-left { display: flex; align-items: center; gap: 16px; }
    .sp-avatar {
        width: 64px; height: 64px; border-radius: 50%;
        background: linear-gradient(135deg, var(--sp-accent), var(--sp-accent2)); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800;
        box-shadow: 0 8px 24px rgba(249, 115, 22, .3); position: relative; flex: 0 0 64px;
    }
    .sp-avatar .sp-ava-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .sp-avatar--clickable { cursor: zoom-in; }
    .sp-avatar--clickable:hover { transform: scale(1.04); transition: transform .15s; }
    .sp-ava-zoom {
        position: absolute; right: -2px; bottom: -2px; width: 24px; height: 24px; border-radius: 50%;
        background: var(--sp-accent); color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: 11px; border: 2px solid var(--bg-card); box-shadow: 0 2px 8px rgba(0, 0, 0, .3); z-index: 2;
    }
    .sp-ava-ini { position: relative; z-index: 1; }
    .sp-selected-cap { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--text-muted); font-weight: 700; }
    .sp-selected-name { font-size: 22px; font-weight: 800; color: var(--text-primary); margin-top: 4px; }

    .sp-verify { margin-top: 8px; font-size: 14px; font-weight: 700; }
    .sp-verify-cta {
        display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
        background: linear-gradient(135deg, var(--sp-accent), var(--sp-accent2)); color: #fff;
        padding: 10px 18px; border-radius: 999px; box-shadow: 0 8px 20px rgba(249, 115, 22, .3);
        animation: sp-cta-pulse 1.5s ease-in-out infinite; font-family: inherit; font-weight: 700;
    }
    .sp-verify-cta:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .sp-verify-done { display: none; align-items: center; gap: 7px; color: var(--accent-green); }
    .sp-selected.is-verified .sp-verify-cta { display: none; }
    .sp-selected.is-verified .sp-verify-done { display: inline-flex; }
    @keyframes sp-cta-pulse { 0%, 100% { box-shadow: 0 8px 20px rgba(249, 115, 22, .28) } 50% { box-shadow: 0 10px 30px rgba(249, 115, 22, .55) } }
    .sp-selected:not(.is-verified) .sp-avatar::after {
        content: ""; position: absolute; inset: -6px; border-radius: 50%; border: 3px solid var(--sp-accent);
        animation: sp-ring 1.5s ease-out infinite; pointer-events: none;
    }
    @keyframes sp-ring { 0% { transform: scale(1); opacity: .75 } 100% { transform: scale(1.3); opacity: 0 } }

    .sp-selected-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .sp-btn {
        display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 12px;
        padding: 12px 20px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; transition: .15s; font-family: inherit;
    }
    .sp-btn-primary { background: linear-gradient(135deg, var(--sp-accent), var(--sp-accent2)); color: #fff; box-shadow: 0 8px 20px rgba(249, 115, 22, .3); }
    .sp-btn-primary:hover { filter: brightness(1.08); transform: translateY(-1px); color: #fff; text-decoration: none; }
    .sp-btn-check { background: rgba(249, 115, 22, 0.12); color: var(--sp-accent); }
    .sp-btn-check:hover { background: rgba(249, 115, 22, 0.2); color: var(--sp-accent); }
    .sp-btn.sp-disabled { opacity: .5; cursor: not-allowed; box-shadow: none; pointer-events: auto; }
    .sp-btn.sp-disabled:hover { transform: none; filter: none; }
    .sp-btn-ghost { background: rgba(255, 255, 255, 0.05); color: var(--text-secondary); }
    .sp-btn-ghost:hover { background: rgba(255, 255, 255, 0.1); color: var(--text-primary); }

    .sp-lightbox {
        position: fixed; inset: 0; z-index: 20000; display: none; align-items: center; justify-content: center;
        background: rgba(2, 6, 23, .92); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); padding: 24px;
    }
    .sp-lightbox.open { display: flex; animation: sp-fade .18s ease; }
    @keyframes sp-fade { from { opacity: 0 } to { opacity: 1 } }
    .sp-lightbox-fig { margin: 0; text-align: center; max-width: 92vw; }
    .sp-lightbox-imgwrap { display: flex; align-items: center; justify-content: center; }
    .sp-lb-img { max-width: 86vw; max-height: 64vh; border-radius: 18px; border: 3px solid #fff; box-shadow: 0 30px 80px rgba(0, 0, 0, .6); background: #0b1020; }
    .sp-lb-ini {
        width: 200px; height: 200px; border-radius: 50%;
        background: linear-gradient(135deg, var(--sp-accent), var(--sp-accent2)); color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 84px; font-weight: 800;
        border: 3px solid #fff; box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
    }
    .sp-lightbox-name { margin-top: 16px; color: #fff; font-size: 22px; font-weight: 800; text-shadow: 0 2px 10px rgba(0, 0, 0, .5); }
    .sp-lightbox-hint { margin-top: 6px; color: var(--text-secondary); font-size: 14px; }
    .sp-lightbox-confirm { margin-top: 18px; padding: 13px 26px; font-size: 15px; }
    .sp-lightbox-close {
        position: fixed; top: 20px; right: 22px; width: 46px; height: 46px; border: none; border-radius: 50%;
        background: rgba(255, 255, 255, .16); color: #fff; font-size: 18px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: .15s;
    }
    .sp-lightbox-close:hover { background: rgba(255, 255, 255, .3); }

    .sp-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 900px) { .sp-cols { grid-template-columns: 1fr; } }
</style>

<?php include __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<main class="admin-main">
    <?php include __DIR__ . '/../../../admin/includes/topbar.php'; ?>

    <div class="admin-content">
        <div class="sellpick animate-in">
            <div class="sp-title">
                <span class="sp-title-icon"><i class="bi bi-shop"></i></span>
                <div>
                    <h3><?php echo $translations["sellpage"]; ?></h3>
                    <p><?php echo $translations['sell-pick-hint'] ?? 'Select a member by QR scan or name search.'; ?></p>
                </div>
            </div>

            <div id="sp-selected" class="sp-selected">
                <div class="sp-selected-left">
                    <div class="sp-avatar sp-avatar--clickable" id="sp-sel-ava" title="<?php echo $translations['zoom-photo'] ?? 'Click to verify'; ?>">?</div>
                    <div>
                        <div class="sp-selected-cap"><?php echo $translations['selected-member'] ?? 'Selected member'; ?></div>
                        <div class="sp-selected-name" id="sp-sel-name">—</div>
                        <div class="sp-verify">
                            <button type="button" class="sp-verify-cta" id="sp-verify-cta">
                                <i class="bi bi-hand-index-thumb-fill"></i>
                                <?php echo $translations['verify-cta'] ?? 'Click avatar to verify'; ?>
                            </button>
                            <span class="sp-verify-done"><i class="bi bi-patch-check-fill"></i>
                                <?php echo $translations['verify-done'] ?? 'Verified'; ?></span>
                        </div>
                    </div>
                </div>
                <div class="sp-selected-actions">
                    <button type="button" class="sp-btn sp-btn-ghost" id="sp-clear">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        <?php echo $translations['sell-change'] ?? 'Change'; ?>
                    </button>
                    <button type="button" class="sp-btn sp-btn-check" id="sp-verify-btn">
                        <i class="bi bi-person-bounding-box"></i>
                        <?php echo $translations['verify-btn'] ?? 'Verify profile'; ?>
                    </button>
                    <a href="#" class="sp-btn sp-btn-primary sp-disabled" id="sp-start" aria-disabled="true">
                        <i class="bi bi-cart-check"></i>
                        <?php echo $translations['sell-start'] ?? 'Start sale'; ?>
                    </a>
                </div>
            </div>

            <div class="sp-cols">
                <div class="sp-card">
                    <div class="sp-card-head">
                        <span class="sp-card-icon"><i class="bi bi-qr-code-scan"></i></span>
                        <h5><?php echo $translations['qrscann'] ?? 'Scan QR code'; ?></h5>
                    </div>
                    <div id="video-container">
                        <video id="video" autoplay playsinline muted></video>
                        <div class="scan-frame"><span></span><span></span><span></span><span></span></div>
                        <div id="checkmark">✔</div>
                        <div id="error">✘</div>
                    </div>
                    <p id="result"><?php echo $translations["qrscann"] ?? 'Scan member QR code'; ?></p>
                </div>

                <div class="sp-card">
                    <div class="sp-card-head">
                        <span class="sp-card-icon"><i class="bi bi-search"></i></span>
                        <h5><?php echo $translations['name-search'] ?? 'Search by name'; ?></h5>
                    </div>
                    <form class="sp-search" onsubmit="return false;">
                        <i class="bi bi-search"></i>
                        <input id="sp-q" type="search" autocomplete="off"
                            placeholder="<?php echo $translations['name-search'] ?? 'Enter name...'; ?>">
                    </form>
                    <div id="sp-results"></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://unpkg.com/@zxing/browser@0.1.5"></script>
<script src="../../../assets/js/date-time.js"></script>
<script>
    (function () {
        'use strict';
        const T = <?php echo json_encode($translations); ?>;
        const SELF = window.location.pathname;
        const PROFILE_BASE = '../../../assets/img/profiles/';

        let codeReader = null, scanControls = null, scanning = false, scanLock = false;
        let searchTimer = null, searchAbort = null;
        let current = null;
        let verified = false;

        const $video = document.getElementById('video');
        const $result = document.getElementById('result');
        const $check = document.getElementById('checkmark');
        const $err = document.getElementById('error');
        const $sel = document.getElementById('sp-selected');
        const $selAva = document.getElementById('sp-sel-ava');
        const $selName = document.getElementById('sp-sel-name');
        const $start = document.getElementById('sp-start');

        function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
        function initials(fn, ln) { return ((String(fn || '').trim()[0] || '') + (String(ln || '').trim()[0] || '')).toUpperCase() || '?'; }
        function profileSrc(id) { return PROFILE_BASE + encodeURIComponent(id) + '.png'; }

        function setVerified(v) {
            verified = v;
            $sel.classList.toggle('is-verified', v);
            $start.classList.toggle('sp-disabled', !v);
            $start.setAttribute('aria-disabled', v ? 'false' : 'true');
        }

        function selectMember(id, fn, ln) {
            current = { id: id, fn: fn, ln: ln };
            setVerified(false);
            $selAva.innerHTML =
                '<span class="sp-ava-ini">' + esc(initials(fn, ln)) + '</span>' +
                '<img class="sp-ava-img" src="' + esc(profileSrc(id)) + '" alt="" onerror="this.remove()">' +
                '<span class="sp-ava-zoom"><i class="bi bi-zoom-in"></i></span>';
            $selName.textContent = (fn || '') + ' ' + (ln || '');
            $start.setAttribute('href', 'ticket/?userid=' + encodeURIComponent(id));
            $sel.style.display = 'flex';
            $sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        document.getElementById('sp-clear').addEventListener('click', function () {
            $sel.style.display = 'none';
            current = null; setVerified(false);
            $video.classList.remove('scanned', 'error');
            $check.style.display = $err.style.display = 'none';
            scanLock = false;
            $result.textContent = T['qrscann'] || 'Scan member QR code';
        });

        const lb = document.createElement('div');
        lb.className = 'sp-lightbox';
        lb.innerHTML =
            '<button type="button" class="sp-lightbox-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>' +
            '<figure class="sp-lightbox-fig">' +
            '<div class="sp-lightbox-imgwrap" id="spLbWrap"></div>' +
            '<figcaption class="sp-lightbox-name" id="spLbName"></figcaption>' +
            '<div class="sp-lightbox-hint">' + esc(T['verify-hint'] || 'Compare the photo with the person.') + '</div>' +
            '<button type="button" class="sp-btn sp-btn-primary sp-lightbox-confirm" id="spLbConfirm">' +
            '<i class="bi bi-check-lg"></i> ' + esc(T['verify-confirm'] || 'Confirm identity') +
            '</button>' +
            '</figure>';
        document.body.appendChild(lb);
        const $lbWrap = lb.querySelector('#spLbWrap');
        const $lbName = lb.querySelector('#spLbName');

        function openVerify() {
            if (!current) return;
            $lbName.textContent = (current.fn || '') + ' ' + (current.ln || '');
            $lbWrap.innerHTML = '<div class="sp-lb-ini">' + esc(initials(current.fn, current.ln)) + '</div>';
            const probe = new Image();
            const src = profileSrc(current.id);
            probe.onload = function () { $lbWrap.innerHTML = '<img class="sp-lb-img" src="' + esc(src) + '" alt="">'; };
            probe.src = src;
            lb.classList.add('open');
        }
        function closeVerify() { lb.classList.remove('open'); }

        lb.addEventListener('click', function (e) {
            if (e.target === lb || e.target.closest('.sp-lightbox-close')) closeVerify();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && lb.classList.contains('open')) closeVerify(); });
        document.getElementById('spLbConfirm').addEventListener('click', function () {
            setVerified(true);
            closeVerify();
        });

        $selAva.addEventListener('click', openVerify);
        document.getElementById('sp-verify-btn').addEventListener('click', openVerify);
        document.getElementById('sp-verify-cta').addEventListener('click', openVerify);

        $start.addEventListener('click', function (e) {
            if (!verified) {
                e.preventDefault();
                openVerify();
            }
        });

        document.getElementById('sp-results').addEventListener('click', function (e) {
            const item = e.target.closest('.sp-item');
            if (!item) return;
            selectMember(item.dataset.userid, item.dataset.firstname, item.dataset.lastname);
        });

        document.getElementById('sp-q').addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(searchTimer);
            const box = document.getElementById('sp-results');
            if (q.length < 2) { box.innerHTML = ''; return; }
            searchTimer = setTimeout(function () {
                if (searchAbort) searchAbort.abort();
                searchAbort = new AbortController();
                fetch(SELF, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ q }).toString(),
                    signal: searchAbort.signal
                })
                    .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
                    .then(html => box.innerHTML = html)
                    .catch(err => { if (err.name !== 'AbortError') { console.error(err); box.innerHTML = '<div class="sp-empty">' + esc(T['search-unavailable'] || 'Search unavailable now.') + '</div>'; } });
            }, 250);
        });

        async function onScan(text) {
            if (scanLock) return;
            scanLock = true;
            $result.innerHTML = '<span class="sp-spin"></span> ' + esc(T['checking'] || 'Checking...');
            try {
                const r = await fetch(SELF, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ lookup: text }).toString()
                });
                const d = await r.json();
                if (d.found) {
                    $video.classList.remove('error'); $video.classList.add('scanned');
                    $check.style.display = 'block'; $err.style.display = 'none';
                    $result.innerHTML = '<i class="bi bi-check-circle-fill" style="color:var(--accent-green)"></i> ' + esc(d.firstname) + ' ' + esc(d.lastname);
                    selectMember(d.userid, d.firstname, d.lastname);
                } else {
                    $video.classList.remove('scanned'); $video.classList.add('error');
                    $err.style.display = 'block'; $check.style.display = 'none';
                    $result.textContent = T['user-notexist'] || 'Member not found';
                    setTimeout(() => { scanLock = false; }, 1500);
                }
            } catch (e) {
                console.error(e);
                $result.textContent = T['qr-error'] || 'Scan error';
                setTimeout(() => { scanLock = false; }, 1500);
            }
        }

        async function startScanning() {
            if (scanning) return;
            scanning = true;
            try {
                if (!window.ZXingBrowser || !ZXingBrowser.BrowserQRCodeReader) throw new Error('ZXingBrowser not loaded');
                if (!codeReader) codeReader = new ZXingBrowser.BrowserQRCodeReader();
                scanControls = await codeReader.decodeFromVideoDevice(undefined, $video, (result) => {
                    if (result && !scanLock) onScan(result.getText());
                });
            } catch (e) {
                scanning = false;
                console.error('Camera error:', e);
                $result.textContent = T['camera-error'] || 'Camera unavailable. Use search.';
            }
        }

        document.addEventListener('DOMContentLoaded', function () { startScanning(); });
    })();
</script>
</body>
</html>
