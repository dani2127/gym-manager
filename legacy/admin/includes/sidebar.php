<?php
// admin/includes/sidebar.php
// Shared sidebar for all admin pages

$current_page = basename($_SERVER['SCRIPT_FILENAME'], '.php');
$current_dir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
?>

<aside class="admin-sidebar">
    <div class="sidebar-logo">
        <img src="<?php echo $_base ?? '/GYM-One'; ?>/assets/img/logo.png" alt="Logo">
        <div class="logo-text">
            <h1><?php echo $business_name; ?></h1>
            <span>v<?php echo $version; ?></span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/dashboard/" class="nav-link <?php echo ($current_dir === 'dashboard' && $current_page === 'index') ? 'active' : ''; ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <?php echo $translations["mainpage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/users/" class="nav-link <?php echo ($current_dir === 'users') ? 'active' : ''; ?>">
                    <i class="bi bi-people-fill"></i>
                    <?php echo $translations["users"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/statistics/" class="nav-link <?php echo ($current_dir === 'statistics') ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart-fill"></i>
                    <?php echo $translations["statspage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/sell/" class="nav-link <?php echo ($current_dir === 'sell') ? 'active' : ''; ?>">
                    <i class="bi bi-shop"></i>
                    <?php echo $translations["sellpage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/invoices/" class="nav-link <?php echo ($current_dir === 'invoices') ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i>
                    <?php echo $translations["invoicepage"]; ?>
                </a>
            </div>
        </div>

        <?php if ($is_boss === 1): ?>
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/mainsettings/" class="nav-link <?php echo ($current_dir === 'mainsettings') ? 'active' : ''; ?>">
                    <i class="bi bi-gear-fill"></i>
                    <?php echo $translations["businesspage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/workers/" class="nav-link <?php echo ($current_dir === 'workers') ? 'active' : ''; ?>">
                    <i class="bi bi-person-gear"></i>
                    <?php echo $translations["workers"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/packages/" class="nav-link <?php echo ($current_dir === 'packages') ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam-fill"></i>
                    <?php echo $translations["packagepage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/hours/" class="nav-link <?php echo ($current_dir === 'hours') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-fill"></i>
                    <?php echo $translations["openhourspage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/smtp/" class="nav-link <?php echo ($current_dir === 'smtp') ? 'active' : ''; ?>">
                    <i class="bi bi-envelope-at-fill"></i>
                    <?php echo $translations["mailpage"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/boss/rule/" class="nav-link <?php echo ($current_dir === 'rule') ? 'active' : ''; ?>">
                    <i class="bi bi-file-ruled"></i>
                    <?php echo $translations["rulepage"]; ?>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-section">
            <div class="nav-section-title">Shop</div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/shop/tickets/" class="nav-link <?php echo ($current_dir === 'tickets') ? 'active' : ''; ?>">
                    <i class="bi bi-ticket-fill"></i>
                    <?php echo $translations["ticketspage"]; ?>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">Trainers</div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/trainers/timetable/" class="nav-link <?php echo ($current_dir === 'timetable') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event-fill"></i>
                    <?php echo $translations["timetable"]; ?>
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/trainers/personal/" class="nav-link <?php echo ($current_dir === 'personal') ? 'active' : ''; ?>">
                    <i class="bi bi-award-fill"></i>
                    <?php echo $translations["trainers"]; ?>
                </a>
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <?php if ($is_boss === 1): ?>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/updater/" class="nav-link <?php echo ($current_dir === 'updater') ? 'active' : ''; ?>">
                    <i class="bi bi-cloud-download-fill"></i>
                    <?php echo $translations["updatepage"]; ?>
                    <?php if ($is_new_version_available): ?>
                        <span class="nav-badge">!</span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a href="<?php echo $_base ?? '/GYM-One'; ?>/admin/log/" class="nav-link <?php echo ($current_dir === 'log') ? 'active' : ''; ?>">
                    <i class="bi bi-clock-history"></i>
                    <?php echo $translations["logpage"]; ?>
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($username ?? 'A', 0, 2)); ?>
            </div>
            <div class="sidebar-user-info">
                <h4><?php echo htmlspecialchars($username ?? 'Admin'); ?></h4>
                <span><?php echo $is_boss === 1 ? 'Admin' : 'Staff'; ?></span>
            </div>
        </div>
    </div>
</aside>
