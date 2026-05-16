<?php
$_topbarRole = $_SESSION['user_role'] ?? 'student';
$_topbarName = htmlspecialchars($_SESSION['username'] ?? 'User');
$_topbarAvatar = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));

$_roleConfig = [
    'admin'   => ['label' => 'Administrator',  'icon' => 'fa-user-shield'],
    'staff'   => ['label' => 'Staff Member',   'icon' => 'fa-user-tie'],
    'student' => ['label' => 'Student',        'icon' => 'fa-user-graduate'],
];
$_rc = $_roleConfig[$_topbarRole] ?? $_roleConfig['student'];

// Notifications
$_notifItems   = [];
$_notifUnread  = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/Database.php';
    require_once __DIR__ . '/../classes/Notification.php';
    $_notifDb    = new Database();
    $_notifConn  = $_notifDb->connect();
    $_notifObj   = new Notification($_notifConn);
    $_notifItems = $_notifObj->getRecent((int) $_SESSION['user_id'], 10);
    $_notifUnread = $_notifObj->countUnread((int) $_SESSION['user_id']);
}
?>

<!-- Topbar -->
<nav class="navbar navbar-expand-lg navbar-dark custom-nav">
    <button id="sidebarCollapse" class="btn btn-dark ms-2">
        <i class="fas fa-list"></i>
    </button>

    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2 ms-auto">

            <!-- Notification Bell -->
            <div class="dropdown">
                <button class="btn position-relative p-2 text-white border-0"
                    id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                    style="background: rgba(255,255,255,0.12); border-radius: 50%; width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-bell" style="font-size:1rem;"></i>
                    <?php if ($_notifUnread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                            style="font-size:0.6rem; min-width:18px; padding:3px 5px;">
                            <?= $_notifUnread > 99 ? '99+' : $_notifUnread ?>
                        </span>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0"
                    aria-labelledby="notifDropdown"
                    style="min-width:340px; max-width:360px; border-radius:12px; overflow:hidden;">

                    <!-- Header -->
                    <div class="d-flex align-items-center justify-content-between px-3 py-2"
                        style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f); color:#fff;">
                        <span class="fw-bold" style="font-size:.9rem;">
                            <i class="fas fa-bell me-1"></i>Notifications
                            <?php if ($_notifUnread > 0): ?>
                                <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?= $_notifUnread ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($_notifUnread > 0): ?>
                            <button class="btn btn-sm text-white p-0 border-0"
                                style="font-size:.75rem; opacity:.85; background:none;"
                                onclick="markAllRead()">
                                <i class="fas fa-check-double me-1"></i>Mark all read
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- List -->
                    <div style="max-height:360px; overflow-y:auto;" id="notifList">
                        <?php if (empty($_notifItems)): ?>
                            <div class="text-center text-muted py-4 px-3">
                                <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                                <small>No notifications yet</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($_notifItems as $n): ?>
                                <div class="notif-item d-flex align-items-start gap-2 px-3 py-2 border-bottom"
                                    data-id="<?= $n['notification_id'] ?>"
                                    data-link="<?= htmlspecialchars($n['link'] ?? '') ?>"
                                    style="cursor:pointer; background:<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>; transition:background .15s;"
                                    onmouseover="this.style.background='#e8f0fe'"
                                    onmouseout="this.style.background='<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>'">

                                    <div class="flex-shrink-0 mt-1">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                            style="width:32px; height:32px; background:#e9ecef;">
                                            <i class="fas <?= Notification::typeIcon($n['type']) ?>" style="font-size:.8rem;"></i>
                                        </div>
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <div class="small <?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>" style="line-height:1.3;">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.7rem; margin-top:2px;">
                                            <?= Notification::timeAgo($n['created_at']) ?>
                                        </div>
                                    </div>

                                    <?php if (!$n['is_read']): ?>
                                        <div class="flex-shrink-0 mt-2">
                                            <div class="rounded-circle bg-primary" style="width:8px; height:8px;"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Footer -->
                    <div class="text-center py-2" style="border-top:1px solid #e9ecef;">
                        <a href="notifications.php" class="small text-decoration-none fw-semibold" style="color:var(--udsm-blue);">
                            View all notifications <i class="fas fa-arrow-right ms-1" style="font-size:.7rem;"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile dropdown -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-white text-decoration-none dropdown-toggle"
                    id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false"
                    style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25);
                           border-radius:50px; padding:5px 12px 5px 6px; transition:background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.22)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                    <span class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                        style="width:30px; height:30px; background:rgba(255,255,255,0.3); font-size:0.8rem;">
                        <?= $_topbarAvatar ?>
                    </span>
                    <span class="d-none d-sm-inline" style="font-size:0.875rem; max-width:120px; overflow:hidden;
                          text-overflow:ellipsis; white-space:nowrap;">
                        <?= $_topbarName ?>
                    </span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 py-0"
                    aria-labelledby="profileDropdown"
                    style="min-width:230px; border-radius:10px; overflow:hidden;">

                    <li class="px-3 py-3" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold flex-shrink-0"
                                style="width:42px; height:42px; background:rgba(255,255,255,0.2); font-size:1.1rem;">
                                <?= $_topbarAvatar ?>
                            </div>
                            <div class="overflow-hidden">
                                <div class="text-white fw-semibold text-truncate" style="font-size:.9rem;">
                                    <?= $_topbarName ?>
                                </div>
                                <span class="badge mt-1"
                                    style="background:rgba(255,255,255,0.25); font-size:.7rem; letter-spacing:.5px;">
                                    <i class="fas <?= $_rc['icon'] ?> me-1"></i><?= $_rc['label'] ?>
                                </span>
                            </div>
                        </div>
                    </li>

                    <li><hr class="dropdown-divider my-0"></li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center py-2 px-3" href="profile.php">
                            <span class="me-2 text-center" style="width:20px;">
                                <i class="fas fa-user-circle text-primary"></i>
                            </span>
                            My Profile
                        </a>
                    </li>

                    <li><hr class="dropdown-divider my-0"></li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center py-2 px-3 text-danger" href="logout.php">
                            <span class="me-2 text-center" style="width:20px;">
                                <i class="fas fa-sign-out-alt"></i>
                            </span>
                            Sign Out
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</nav>

<script>
function markAllRead() {
    fetch('mark_notification.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all'
    }).then(function () {
        document.querySelectorAll('.notif-item').forEach(function (el) {
            el.style.background = '#fff';
            el.onmouseout = function () { this.style.background = '#fff'; };
            var dot = el.querySelector('.bg-primary.rounded-circle');
            if (dot) dot.remove();
            var msg = el.querySelector('.fw-semibold');
            if (msg) { msg.classList.remove('fw-semibold'); msg.classList.add('text-muted'); }
        });
        var badge = document.querySelector('#notifDropdown .badge');
        if (badge) badge.remove();
        var headerBadge = document.querySelector('#notifList').previousElementSibling.querySelector('.badge.bg-danger');
        if (headerBadge) headerBadge.remove();
        var markAllBtn = document.querySelector('[onclick="markAllRead()"]');
        if (markAllBtn) markAllBtn.remove();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.notif-item').forEach(function (el) {
        el.addEventListener('click', function () {
            var id   = this.dataset.id;
            var link = this.dataset.link;

            fetch('mark_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_read&id=' + id
            }).then(function () {
                if (link) window.location.href = link;
            });
        });
    });
});
</script>
