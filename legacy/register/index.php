<?php
session_start();

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

function save_profile_photo($fileKey, $destPath)
{
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    return false;
  }

  $tmp = $_FILES[$fileKey]['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    return false;
  }

  if (($_FILES[$fileKey]['size'] ?? 0) > 8 * 1024 * 1024) {
    return false;
  }

  $info = @getimagesize($tmp);
  if ($info === false) {
    return false;
  }
  $mime = $info['mime'] ?? '';

  $dir = dirname($destPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }

  if (!function_exists('imagecreatetruecolor')) {
    if ($mime === 'image/png') {
      return @move_uploaded_file($tmp, $destPath);
    }
    return false;
  }

  switch ($mime) {
    case 'image/jpeg':
      $src = @imagecreatefromjpeg($tmp);
      break;
    case 'image/png':
      $src = @imagecreatefrompng($tmp);
      break;
    case 'image/webp':
      $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false;
      break;
    case 'image/gif':
      $src = @imagecreatefromgif($tmp);
      break;
    default:
      return false;
  }

  if (!$src) {
    return false;
  }

  $w = imagesx($src);
  $h = imagesy($src);

  $side = min($w, $h);
  $sx = (int) (($w - $side) / 2);
  $sy = (int) (($h - $side) / 2);

  $target = 512;
  $dst = imagecreatetruecolor($target, $target);

  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
  imagefilledrectangle($dst, 0, 0, $target, $target, $transparent);

  imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $target, $target, $side, $side);

  $ok = imagepng($dst, $destPath);

  imagedestroy($src);
  imagedestroy($dst);

  return $ok;
}

function is_valid_uploaded_image($fileKey)
{
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    return false;
  }
  $tmp = $_FILES[$fileKey]['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    return false;
  }
  if (($_FILES[$fileKey]['size'] ?? 0) > 8 * 1024 * 1024) {
    return false;
  }
  $info = @getimagesize($tmp);
  if ($info === false) {
    return false;
  }
  $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  return in_array($info['mime'] ?? '', $allowed, true);
}

$alerts_html = "";

require_once '../vendor/autoload.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

$host = $_SERVER['HTTP_HOST'];

$domain_url = $protocol . $host;

$env_data = read_env_file('../.env');

$db_host = $env_data['DB_SERVER'] ?? '';
$db_username = $env_data['DB_USERNAME'] ?? '';
$db_password = $env_data['DB_PASSWORD'] ?? '';
$db_name = $env_data['DB_NAME'] ?? '';
$smtp_password = $env_data['MAIL_PASSWORD'] ?? '';
$smtp_port = $env_data['MAIL_PORT'] ?? '';
$smtp_username = $env_data["MAIL_USERNAME"] ?? '';
$smtp_encryption = $env_data['MAIL_ENCRYPTION'] ?? '';
$smtp_host = $env_data['MAIL_HOST'] ?? '';
$autoaccept = $env_data['AUTOACCEPT'] ?? '';

$business_name = $env_data['BUSINESS_NAME'] ?? 'PowerFit Gym';
$lang_code = $env_data['LANG_CODE'] ?? 'EN';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
  die("Language file not found: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $firstname = $_POST['firstname'];
  $lastname = $_POST['lastname'];
  $email = $_POST['email'];
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];
  $gender = $_POST['gender'];
  $birthdate = $_POST['birthdate'];
  $city = $_POST['city'];
  $street = '';
  $house_number = '';
  if ($password !== $confirm_password) {
    $alerts_html .= '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ' . $translations["twopasswordnot"] . '</div>';
    header("Refresh: 5");
  } elseif (!is_valid_uploaded_image('profile_photo')) {
    $alerts_html .= '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ' . ($translations["profilepicturerequired"] ?? 'Profile picture is required!') . '</div>';
    header("Refresh: 5");
  } else {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $userid = rand(pow(10, 9), pow(10, 10) - 1);

    if ($autoaccept === "TRUE") {
      $confirmed = 'YES';
    } else {
      $confirmed = 'NO';
    }

    $registration_date = date('Y-m-d H:i:s');

    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
    }

    $check = $conn->prepare("SELECT userid FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
      $alerts_html .= '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> This email is already registered. Please use a different email or log in.</div>';
      $check->close();
    } else {

    $stmt = $conn->prepare("INSERT INTO users (userid, firstname, lastname, email, password, gender, birthdate, city, street, house_number, registration_date, confirmed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
      die("Error: " . $conn->error);
    }

    $stmt->bind_param("isssssssssss", $userid, $firstname, $lastname, $email, $hashed_password, $gender, $birthdate, $city, $street, $house_number, $registration_date, $confirmed);

    $ConfirmEmailPage_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["confirmemailpage"]);
    $replacements = [
      "{business_name}" => $business_name,
      "{first_name}" => $firstname
    ];
    $ConfirmEmailHeader_PLACEHOLDER = strtr($translations["confirmemailheader"], $replacements);
    $ConfirmEmailFooterWhy_PLACEHOLDER = str_replace("{business_name}", $business_name, $translations["confirmemailfooterwhy"]);


    if ($stmt->execute()) {
      $photo_saved = save_profile_photo('profile_photo', __DIR__ . '/../assets/img/profiles/' . $userid . '.png');

      $alerts_html .= '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Registration successful!</div>';
      header("Refresh: 5");
      $transport = (new Swift_SmtpTransport($smtp_host, $smtp_port, $smtp_encryption))
        ->setUsername($smtp_username)
        ->setPassword($smtp_password);

      $mailer = new Swift_Mailer($transport);

      $successEmailContent = <<<EOD
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #0F172A; color: #F8FAFC; margin: 0; padding: 0; }
    .container { max-width: 600px; margin: 0 auto; padding: 40px 20px; }
    .header { text-align: center; margin-bottom: 30px; }
    .logo { max-width: 150px; margin-bottom: 20px; }
    h1 { color: #F97316; font-size: 24px; margin-bottom: 10px; }
    p { color: #94A3B8; font-size: 16px; line-height: 1.6; }
    .cta-button { display: inline-block; background: linear-gradient(135deg, #F97316, #EA580C); color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 600; margin: 20px 0; }
    .tips { background: #1E293B; padding: 20px; border-radius: 12px; margin: 30px 0; border: 1px solid #334155; }
    .tip { color: #94A3B8; margin-bottom: 10px; padding-left: 20px; position: relative; }
    .tip:before { content: "•"; color: #F97316; font-weight: bold; position: absolute; left: 0; }
    .footer { text-align: center; padding: 20px; color: #64748B; font-size: 12px; border-top: 1px solid #334155; margin-top: 30px; }
    .footer a { color: #F97316; text-decoration: none; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <img src="{$domain_url}/assets/img/brand/logo.png" alt="Logo" class="logo">
      <h1>{$ConfirmEmailHeader_PLACEHOLDER}</h1>
      <p>{$translations["confirmemailheadertext"]}</p>
      <a href="{$domain_url}/register/confirm.php?userid={$userid}" class="cta-button">{$translations["regconfirmbtn"]}</a>
      <p><a href="{$domain_url}" style="color: #F97316;">{$translations["confirmemailorlogin"]} →</a></p>
    </div>
    <div class="tips">
      <h2 style="color: #F8FAFC; font-size: 18px; margin-bottom: 15px;">{$translations["confirmemailfirst"]}</h2>
      <div class="tip">{$translations["confirmemailtipone"]}</div>
      <div class="tip">{$translations["confirmemailtiptwo"]}</div>
    </div>
    <div class="footer">
      <p>{$ConfirmEmailFooterWhy_PLACEHOLDER}</p>
      <p>Engineered with <span style="color:#EF4444;">♥</span> by <a href="https://gymoneglobal.com">GYM One</a></p>
    </div>
  </div>
</body>
</html>
EOD;

      $recipientEmail = $email;
      $subject = $translations["confirmemailmailsub"];

      // Try to send email, but don't fail registration if email fails
      try {
        $message = (new Swift_Message($subject))
          ->setFrom(["{$smtp_username}" => "{$ConfirmEmailPage_PLACEHOLDER}"])
          ->setTo([$recipientEmail])
          ->setBody($successEmailContent, 'text/html');
        $result = $mailer->send($message);
      } catch (Exception $e) {
        // Email failed, but registration still succeeded
        error_log("Email sending failed: " . $e->getMessage());
      }
      
      $alerts_html .= '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Registration successful! You can now login.</div>';
      header("Refresh: 3");
    }

    $stmt->close();
    } // end else (email not duplicate)
    $conn->close();
  }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $business_name; ?> - <?php echo $translations["register"]; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../assets/css/unified-theme.css">
  <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
  <style>
    .auth-page {
      background: var(--background);
      padding: 2rem;
    }

    .auth-page::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: 
        radial-gradient(circle at 20% 80%, rgba(249, 115, 22, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(34, 197, 94, 0.08) 0%, transparent 50%);
      pointer-events: none;
    }

    .auth-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-xl);
      padding: 2.5rem;
      max-width: 600px;
      width: 100%;
    }

    .auth-logo {
      max-width: 150px;
      height: auto;
      margin-bottom: 1rem;
    }

    .auth-title {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--foreground);
      margin-bottom: 0.5rem;
    }

    .auth-subtitle {
      color: var(--text-secondary);
      font-size: 0.95rem;
      margin-bottom: 2rem;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      font-size: 1rem;
      background: var(--background);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      color: var(--foreground);
      transition: all var(--transition-fast);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
      background: var(--background-light);
    }

    .form-control::placeholder {
      color: var(--text-muted);
    }

    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='none' stroke='%2394A3B8' stroke-width='1.6'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 1rem center;
      background-size: 16px;
      padding-right: 2.5rem;
    }

    /* Profile Photo Upload */
    .avatar-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .avatar-input {
      display: none;
    }

    .avatar {
      position: relative;
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: var(--background);
      border: 2px dashed var(--border);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      transition: all var(--transition-fast);
    }

    .avatar:hover {
      border-color: var(--primary);
      box-shadow: 0 10px 30px rgba(249, 115, 22, 0.2);
      transform: translateY(-2px);
    }

    .avatar.has-img {
      border-style: solid;
      border-color: var(--primary);
    }

    .avatar-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
    }

    .avatar.has-img .avatar-img {
      display: block;
    }

    .avatar-placeholder {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      color: var(--text-muted);
      font-size: 12px;
      font-weight: 600;
    }

    .avatar-placeholder i {
      font-size: 2rem;
    }

    .avatar-edit {
      position: absolute;
      right: 6px;
      bottom: 6px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(249, 115, 22, 0.4);
      opacity: 0;
      transition: opacity var(--transition-fast);
    }

    .avatar:hover .avatar-edit,
    .avatar.has-img .avatar-edit {
      opacity: 1;
    }

    .avatar-hint {
      margin-top: 0.5rem;
      font-size: 12px;
      color: var(--text-muted);
    }

    .avatar-hint .required {
      color: #EF4444;
      font-weight: 700;
    }

    .avatar-error {
      color: #EF4444;
      font-size: 12px;
      margin-top: 0.35rem;
      display: none;
    }

    .avatar-remove {
      margin-top: 0.35rem;
      font-size: 12px;
      color: #EF4444;
      background: none;
      border: none;
      cursor: pointer;
      display: none;
      padding: 0;
    }

    .avatar.has-img ~ .avatar-remove {
      display: inline-block;
    }

    .avatar.is-invalid {
      border-style: solid;
      border-color: #EF4444;
    }

    /* Rules Section */
    .rules-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .rules-box {
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      overflow: hidden;
      background: var(--background);
      margin-bottom: 1rem;
    }

    .rules-box iframe {
      display: block;
      width: 100%;
      height: 180px;
      border: 0;
      background: white;
    }

    /* Checkbox */
    .form-check {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      padding-left: 0;
    }

    .form-check-input {
      width: 20px;
      height: 20px;
      margin: 0;
      accent-color: var(--primary);
      cursor: pointer;
    }

    .form-check-label {
      font-size: 0.9rem;
      color: var(--text-secondary);
      margin: 0;
      cursor: pointer;
    }

    .btn-primary {
      width: 100%;
      padding: 1rem;
      font-size: 1rem;
      font-weight: 700;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      border: none;
      border-radius: var(--radius-md);
      color: var(--foreground);
      cursor: pointer;
      transition: all var(--transition-fast);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(249, 115, 22, 0.4);
    }

    .auth-footer {
      text-align: center;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
    }

    .auth-footer p {
      font-size: 0.9rem;
      color: var(--text-secondary);
      margin: 0;
    }

    .auth-footer a {
      color: var(--primary);
      font-weight: 600;
    }

    .auth-copyright {
      position: absolute;
      bottom: 2rem;
      left: 0;
      right: 0;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.85rem;
    }

    .auth-copyright a {
      color: var(--primary);
      font-weight: 600;
    }

    .auth-copyright .heart {
      color: #EF4444;
    }

    .back-home {
      position: absolute;
      top: 2rem;
      left: 2rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
      text-decoration: none;
      font-weight: 500;
      transition: color var(--transition-fast);
      z-index: 10;
    }

    .back-home:hover {
      color: var(--primary);
    }

    /* Floating blobs */
    .reg-bg {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
    }

    .reg-blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(90px);
    }

    .reg-blob-1 {
      width: 380px;
      height: 380px;
      background: var(--primary);
      opacity: 0.08;
      top: -130px;
      left: -90px;
      animation: blobDrift 22s ease-in-out infinite;
    }

    .reg-blob-2 {
      width: 340px;
      height: 340px;
      background: #8B5CF6;
      opacity: 0.06;
      bottom: -120px;
      right: -90px;
      animation: blobDrift 26s ease-in-out infinite reverse;
    }

    .reg-blob-3 {
      width: 260px;
      height: 260px;
      background: var(--accent);
      opacity: 0.05;
      top: 40%;
      right: 8%;
      animation: blobDrift 30s ease-in-out infinite;
    }

    @keyframes blobDrift {
      0%, 100% { transform: translate(0, 0) scale(1); }
      50% { transform: translate(26px, -22px) scale(1.06); }
    }

    /* Card animation */
    .auth-card {
      animation: cardIn 0.6s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
    }

    @keyframes cardIn {
      from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
      }
      to {
        opacity: 1;
        transform: none;
      }
    }

    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }

    /* Responsive */
    @media (max-width: 576px) {
      .form-row {
        grid-template-columns: 1fr;
      }
      
      .form-row.three-col {
        grid-template-columns: 1fr;
      }
      
      .auth-card {
        padding: 1.5rem;
      }
    }
  </style>
</head>

<body>
  <a href="../" class="back-home">
    <i class="bi bi-arrow-left"></i>
    Back to Home
  </a>

  <div class="reg-bg">
    <span class="reg-blob reg-blob-1"></span>
    <span class="reg-blob reg-blob-2"></span>
    <span class="reg-blob reg-blob-3"></span>
  </div>

  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header" style="text-align: center; margin-bottom: 2rem;">
        <img class="auth-logo" src="../assets/img/brand/logo.png" title="<?php echo $business_name; ?>" alt="<?php echo $business_name; ?>">
        <h1 class="auth-title"><?php echo $translations["register"]; ?></h1>
        <p class="auth-subtitle">Join <?php echo $business_name; ?> today</p>
      </div>

      <?php if (!empty($login_error)) : ?>
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-circle"></i>
          <?php echo $login_error; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($alerts_html)) : ?>
        <?php echo $alerts_html; ?>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <!-- Profile Photo -->
        <div class="avatar-wrap">
          <label class="avatar" id="regAvatar" for="profile_photo">
            <img id="regAvatarImg" class="avatar-img" src="" alt="">
            <span class="avatar-placeholder">
              <i class="bi bi-person-fill"></i>
              <span>Add Photo</span>
            </span>
            <span class="avatar-edit">
              <i class="bi bi-pencil-fill"></i>
            </span>
          </label>
          <input type="file" class="avatar-input" id="profile_photo" name="profile_photo" accept="image/png,image/jpeg,image/webp,image/gif">
          <button type="button" class="avatar-remove" id="regAvatarRemove">Remove</button>
          <div class="avatar-hint">Profile photo (required, max 8 MB) <span class="required">*</span></div>
          <div class="avatar-error" id="regAvatarError">Profile photo is required!</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="firstname" class="form-label">
              <i class="bi bi-person"></i> <?php echo $translations["firstname"]; ?>
            </label>
            <input type="text" class="form-control" id="firstname" name="firstname" placeholder="First name" required>
          </div>
          <div class="form-group">
            <label for="lastname" class="form-label">
              <i class="bi bi-person"></i> <?php echo $translations["lastname"]; ?>
            </label>
            <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Last name" required>
          </div>
        </div>

        <div class="form-group">
          <label for="email" class="form-label">
            <i class="bi bi-envelope"></i> <?php echo $translations["email"]; ?>
          </label>
          <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="password" class="form-label">
              <i class="bi bi-lock"></i> <?php echo $translations["password"]; ?>
            </label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required>
          </div>
          <div class="form-group">
            <label for="confirm_password" class="form-label">
              <i class="bi bi-lock-fill"></i> <?php echo $translations["password-confirm"]; ?>
            </label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
          </div>
        </div>

        <div class="form-group">
          <label for="gender" class="form-label">
            <i class="bi bi-gender-ambiguous"></i> <?php echo $translations["gender"]; ?>
          </label>
          <select class="form-control" id="gender" name="gender" required>
            <option value="Male"><?php echo $translations["boy"]; ?></option>
            <option value="Female"><?php echo $translations["girl"]; ?></option>
          </select>
        </div>

        <div class="form-group">
          <label for="birthdate" class="form-label">
            <i class="bi bi-calendar"></i> <?php echo $translations["birthday"]; ?>
          </label>
          <input type="date" class="form-control" id="birthdate" name="birthdate" required>
        </div>

        <div class="form-group">
          <label for="city" class="form-label">
            <i class="bi bi-geo-alt"></i> <?php echo $translations["city"]; ?>
          </label>
          <input type="text" class="form-control" id="city" name="city" placeholder="City" required>
        </div>

        <span class="rules-label"><?php echo $translations["rulepage"] ?? 'Rules'; ?></span>
        <div class="rules-box">
          <iframe src="../admin/boss/rule/rule.html" frameborder="0"></iframe>
        </div>

        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" required>
          <label class="form-check-label" for="flexCheckDefault">
            <?php echo $translations["acceptrules"]; ?>
          </label>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="bi bi-person-plus"></i>
          <?php echo $translations["register"]; ?>
        </button>
      </form>

      <div class="auth-footer">
        <p>
          <?php echo $translations["doyouhaveaccount"]; ?> 
          <a href="../login/"><?php echo $translations["login"]; ?></a>
        </p>
      </div>
    </div>
  </div>

  <div class="auth-copyright">
    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($business_name); ?> &middot;
    <?php echo $translations["copyright"];?> <span class="heart">&hearts;</span>
    <a href="https://gymoneglobal.com/?lang=<?php echo $lang_code; ?>" target="_blank" rel="noopener noreferrer">GYM One</a>
  </div>

  <script>
    (function () {
      var input = document.getElementById('profile_photo');
      var avatar = document.getElementById('regAvatar');
      var img = document.getElementById('regAvatarImg');
      var removeBtn = document.getElementById('regAvatarRemove');
      var errorEl = document.getElementById('regAvatarError');
      var form = input ? input.closest('form') : null;
      var MAX = 8 * 1024 * 1024;
      var objectUrl = null;

      if (!input) return;

      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) { reset(); return; }

        if (!/^image\//.test(file.type)) { alert('Only image files are allowed.'); reset(); return; }
        if (file.size > MAX) { alert('Image is too large (max 8 MB).'); reset(); return; }

        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        img.src = objectUrl;
        avatar.classList.add('has-img');
        avatar.classList.remove('is-invalid');
        if (errorEl) errorEl.style.display = 'none';
      });

      if (removeBtn) {
        removeBtn.addEventListener('click', function () { reset(); });
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          if (!input.files || input.files.length === 0) {
            e.preventDefault();
            avatar.classList.add('is-invalid');
            if (errorEl) errorEl.style.display = 'block';
            avatar.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
      }

      function reset() {
        input.value = '';
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        img.src = '';
        avatar.classList.remove('has-img');
      }
    })();
  </script>
</body>

</html>
