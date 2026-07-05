<?php
// admin/includes/topbar.php
// Shared topbar for all admin pages
?>

<header class="admin-topbar">
    <div class="topbar-left">
        <h2><?php echo $page_title ?? 'Dashboard'; ?></h2>
    </div>
    <div class="topbar-right">
        <div class="topbar-time" id="clock"></div>
        <a href="https://gymoneglobal.com/docs" class="topbar-btn topbar-btn-outline" target="_blank">
            <i class="bi bi-journals"></i>
            Docs
        </a>
        <button type="button" class="topbar-btn topbar-btn-primary" data-toggle="modal" data-target="#logoutModal">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </button>
    </div>
</header>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" style="margin-top: 100px;">
        <div class="modal-content">
            <div class="modal-body text-center" style="padding: 40px;">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-box-arrow-right" style="color: var(--accent-red); font-size: 36px;"></i>
                </div>
                <h4 style="font-weight: 700; margin-bottom: 12px; color: var(--text-primary);">
                    <?php echo $translations["exit-modal"]; ?>
                </h4>
                <p style="color: var(--text-muted); margin-bottom: 24px;">Are you sure you want to logout?</p>
                <div>
                    <button type="button" class="btn btn-ghost" data-dismiss="modal" style="margin-right: 12px;">
                        <?php echo $translations["not-yet"]; ?>
                    </button>
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="bi bi-check-circle"></i>
                        <?php echo $translations["confirm"]; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
