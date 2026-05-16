<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/Database.php';
require_once 'classes/Notification.php';

$db = new Database();
$conn = $db->connect();
$notif = new Notification($conn);

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'student';

// Mark all read if requested
if (isset($_GET['mark_all'])) {
    $notif->markAllRead($userId);
    header('Location: notifications.php');
    exit;
}

$notifications = $notif->getAll($userId);

$dashboardLink = match ($role) {
    'admin' => 'admin_dashboard.php',
    'staff' => 'staff_dashboard.php',
    default => 'student_dashboard.php',
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php require_once 'includes/flash_toast.php'; ?>

    <div id="loader">
        <div class="spinner"></div>
    </div>

    <div class="d-flex">

        <!-- Sidebar (role-specific) -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <div class="logo-container me-2">
                    <img src="assets/img/logo.png" alt="UDSM Logo" class="img-fluid rounded circle"
                        style="width:45px; height:45px; object-fit:cover; border:2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold">UDSM</h6>
                    <small class="text-warning" style="font-size:.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0"><i class="fas fa-user me-2"></i></div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold"><?= strtoupper($role) ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <?php if ($role === 'admin'): ?>
                    <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie me-2"></i><span
                                class="link-text">Overview</span></a></li>
                    <li><a href="manage_complaints.php"><i class="fas fa-file-invoice me-2"></i><span
                                class="link-text">Student Complaints</span></a></li>
                    <li><a href="user_management.php"><i class="fas fa-user-shield me-2"></i><span class="link-text">User
                                Management</span></a></li>
                    <li><a href="manage_departments.php"><i class="fas fa-sitemap me-2"></i><span
                                class="link-text">Departments</span></a></li>
                    <li><a href="manage_categories.php"><i class="fas fa-tags me-2"></i><span
                                class="link-text">Categories</span></a></li>
                    <li><a href="reports.php"><i class="fas fa-file-contract me-2"></i><span
                                class="link-text">Reports</span></a></li>
                <?php elseif ($role === 'staff'): ?>
                    <li><a href="staff_dashboard.php"><i class="fas fa-chart-pie me-2"></i><span
                                class="link-text">Dashboard</span></a></li>
                    <li><a href="assigned_complaints.php"><i class="fas fa-comment-dots me-2"></i><span
                                class="link-text">Assigned Complaints</span></a></li>
                <?php else: ?>
                    <li><a href="student_dashboard.php"><i class="fas fa-chart-pie me-2"></i><span
                                class="link-text">Dashboard</span></a></li>
                    <li><a href="create_complaint.php"><i class="fas fa-plus-circle me-2"></i><span class="link-text">Submit
                                Complaint</span></a></li>
                    <li><a href="track_complaints.php"><i class="fas fa-search me-2"></i><span class="link-text">Track
                                Complaints</span></a></li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i><span class="link-text">Sign Out</span></a>
            </div>
        </nav>

        <div id="content" class="w-100">
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="<?= $dashboardLink ?>"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Notifications</li>
                    </ol>
                    <?php if (!empty($notifications)): ?>
                        <a href="?mark_all=1" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-check-double me-1"></i>Mark all as read
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-bell me-2"></i>All Notifications</h4>

                    <?php if (empty($notifications)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
                            <p class="mb-0">You have no notifications.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $n): ?>
                                <a href="<?= htmlspecialchars($n['link'] ?? '#') ?>"
                                    class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3 px-2 notif-row"
                                    data-id="<?= $n['notification_id'] ?>"
                                    style="background:<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>; border-left:3px solid <?= $n['is_read'] ? 'transparent' : 'var(--udsm-blue)' ?>; text-decoration:none; color:inherit;">

                                    <div class="flex-shrink-0 mt-1">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                            style="width:38px; height:38px; background:#e9ecef;">
                                            <i class="fas <?= Notification::typeIcon($n['type']) ?>"></i>
                                        </div>
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <div class="<?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </div>
                                        <small class="text-muted">
                                            <?= date('d M Y, g:i A', strtotime($n['created_at'])) ?>
                                            &middot; <?= Notification::timeAgo($n['created_at']) ?>
                                        </small>
                                    </div>

                                    <?php if (!$n['is_read']): ?>
                                        <div class="flex-shrink-0 mt-2">
                                            <div class="rounded-circle bg-primary" style="width:9px; height:9px;" title="Unread">
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        document.querySelectorAll('.notif-row').forEach(function (el) {
            el.addEventListener('click', function (e) {
                var id = this.dataset.id;
                var href = this.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                fetch('mark_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_read&id=' + id
                }).then(function () {
                    window.location.href = href;
                });
            });
        });
    </script>
</body>

</html>