<?php
// admin/includes/head.php
// Shared head section for all admin pages
// Compute base path: admin/includes -> admin -> project root
$_project_root = realpath(__DIR__ . '/../..');
$_doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
$_base = '/' . trim(substr($_project_root, strlen($_doc_root)), '\\/');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang_code; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo $business_name; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="<?php echo $_base; ?>/assets/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo $_base; ?>/assets/css/admin-dashboard.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</head>
<body>
