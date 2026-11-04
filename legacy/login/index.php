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

function get_db_connection()
{
    global $env_data;

    $db_host = $env_data['DB_SERVER'] ?? '';
    $db_username = $env_data['DB_USERNAME'] ?? '';
    $db_password = $env_data['DB_PASSWORD'] ?? '';
    $db_name = $env_data['DB_NAME'] ?? '';

    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

$env_data = read_env_file('../.env');

$business_name = $env_data['BUSINESS_NAME'] ?? 'PowerFit Gym';
$lang_code = $env_data['LANG_CODE'] ?? 'EN';
$version = $env_data["APP_VERSION"] ?? '';

$lang = $lang_code;

$langDir = __DIR__ . "/../assets/lang/";

$langFile = $langDir . "$lang.json";

if (!file_exists($langFile)) {
    die("Language file not found: $langFile");
}

$translations = json_decode(file_get_contents($langFile), true);


$login_error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $conn = get_db_connection();

    $stmt = $conn->prepare("SELECT userid, password, confirmed FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($userid, $hashed_password, $confirmed);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            if ($confirmed == 'Yes') {
                $current_datetime = date('Y-m-d H:i:s');
                $user_ip = $_SERVER['REMOTE_ADDR'];
                $update_stmt = $conn->prepare("UPDATE users SET lastlogin = ?, lastip = ? WHERE userid = ?");
                $update_stmt->bind_param("ssi", $current_datetime, $user_ip, $userid);
                $update_stmt->execute();
                $update_stmt->close();
                session_start();
                $_SESSION['userid'] = $userid;
                header("Location: ../dashboard");
                exit();
            } else {
                $login_error = $translations["acceptemailplease"];
            }
        } else {
            $login_error = $translations["notcorrectlogin"];
        }
    } else {
        $login_error = $translations["notcorrectlogin"];
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $translations["login"]; ?> - <?php echo $business_name; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/unified-theme.css">
    <link rel="shortcut icon" href="../assets/img/brand/favicon.png" type="image/x-icon">
    <style>
        .auth-page {
            background: var(--background);
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
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
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
            color: #94A3B8;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #F8FAFC;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            background: #0F172A;
            border: 1.5px solid #475569;
            border-radius: 12px;
            color: #F8FAFC;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #F97316;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
            background: #1E293B;
        }

        .form-control::placeholder {
            color: var(--text-muted);
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

        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #334155;
        }

        .auth-links p {
            font-size: 0.9rem;
            color: #94A3B8;
            margin: 0;
        }

        .auth-links a {
            color: #F97316;
            font-weight: 600;
        }

        .auth-links a:hover {
            text-decoration: underline;
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
        .lg-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .lg-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(90px);
        }

        .lg-blob-1 {
            width: 380px;
            height: 380px;
            background: var(--primary);
            opacity: 0.08;
            top: -130px;
            left: -90px;
            animation: lgDrift 22s ease-in-out infinite;
        }

        .lg-blob-2 {
            width: 340px;
            height: 340px;
            background: #8B5CF6;
            opacity: 0.06;
            bottom: -120px;
            right: -90px;
            animation: lgDrift 26s ease-in-out infinite reverse;
        }

        .lg-blob-3 {
            width: 260px;
            height: 260px;
            background: var(--accent);
            opacity: 0.05;
            top: 40%;
            right: 8%;
            animation: lgDrift 30s ease-in-out infinite;
        }

        @keyframes lgDrift {
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

        /* Staggered animation */
        .auth-card .form-group:nth-child(1) { animation: fadeInUp 0.5s ease 0.1s both; }
        .auth-card .form-group:nth-child(2) { animation: fadeInUp 0.5s ease 0.2s both; }
        .auth-card .btn-primary { animation: fadeInUp 0.5s ease 0.3s both; }
        .auth-links { animation: fadeInUp 0.5s ease 0.4s both; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
    </style>
</head>

<body>
    <a href="../" class="back-home">
        <i class="bi bi-arrow-left"></i>
        Back to Home
    </a>

    <div class="lg-bg">
        <span class="lg-blob lg-blob-1"></span>
        <span class="lg-blob lg-blob-2"></span>
        <span class="lg-blob lg-blob-3"></span>
    </div>

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <img class="auth-logo" src="../assets/img/brand/logo.png" title="<?php echo $business_name; ?>" alt="<?php echo $business_name; ?>">
                <h1 class="auth-title"><?php echo $translations["login"]; ?></h1>
                <p class="auth-subtitle">Welcome back to <?php echo $business_name; ?></p>
            </div>

            <?php if (!empty($login_error)) : ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo $login_error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope"></i> <?php echo $translations["email"]; ?>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock"></i> <?php echo $translations["password"]; ?>
                    </label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <?php echo $translations["next"]; ?>
                </button>
            </form>

            <div class="auth-links">
                <p>
                    <?php echo $translations["youdonthaveaccount"]; ?> 
                    <a href="../register/"><?php echo $translations["registerbtn"]; ?></a>
                </p>
                <p style="margin-top: 0.5rem;">
                    <a href="../admin/"><?php echo $translations["adminaccountlogin"]; ?></a>
                </p>
            </div>
        </div>
    </div>

    <div class="auth-copyright">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($business_name); ?> &middot;
        <?php echo $translations["copyright"];?> <span class="heart">&hearts;</span>
        <a href="https://gymoneglobal.com/?lang=<?php echo $lang_code; ?>" target="_blank" rel="noopener noreferrer">GYM One</a>
    </div>
</body>

</html>
