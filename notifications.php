<?php
require_once 'config/session.php';

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
    'admin'          => 'admin_dashboard.php',
    'staff'          => 'staff_dashboard.php',
    'student_leader' => 'leader_dashboard.php',
    default          => 'student_dashboard.php',
};
$roleLabel = match ($role) {
    'admin'          => 'Admin',
    'staff'          => 'Staff',
    'student_leader' => 'Student Rep',
    default          => 'Student',
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- Local FontAwesome plugins (replaced with CDN for shared hosting compatibility) -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css"> -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php require_once 'includes/flash_toast.php'; ?>

    <div id="loader">
        <div class="loader-content">
            <img src="assets/img/logo.png" alt="UDSM" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <div class="d-flex">
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="<?= $dashboardLink ?>"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="<?= $dashboardLink ?>" style="color:black;"><?= $roleLabel ?></a></li>
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

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
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