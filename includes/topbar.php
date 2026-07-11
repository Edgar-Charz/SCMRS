<?php
require_once __DIR__ . '/../includes/csrf.php';

$_topbarRole = $_SESSION['user_role'] ?? 'student';
$_topbarName = htmlspecialchars($_SESSION['username'] ?? 'User');
$_topbarAvatar = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));

$_roleConfig = [
    'admin'          => ['label' => 'Administrator', 'icon' => 'fa-user-shield'],
    'staff'          => ['label' => 'Staff Member',  'icon' => 'fa-user-tie'],
    'student'        => ['label' => 'Student',        'icon' => 'fa-user-graduate'],
    'student_leader' => ['label' => 'Student Rep',    'icon' => 'fa-user-friends'],
];
$_rc = $_roleConfig[$_topbarRole] ?? $_roleConfig['student'];

// Notifications
$_notifItems = [];
$_notifUnread = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/Database.php';
    require_once __DIR__ . '/../classes/Notification.php';
    $_notifDb = new Database();
    $_notifConn = $_notifDb->connect();
    $_notifObj = new Notification($_notifConn);
    $_notifItems = $_notifObj->getRecent((int) $_SESSION['user_id'], 10);
    $_notifUnread = $_notifObj->countUnread((int) $_SESSION['user_id']);
}
?>

<style>
    /* Notification list: allow vertical scroll but hide the scrollbar track */
    #notifList {
        overflow-x: hidden;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    #notifList::-webkit-scrollbar {
        width: 0;
        height: 0;
    }
</style>

<!-- Topbar -->
<nav class="navbar navbar-dark custom-nav">
    <div class="d-flex align-items-center w-100 px-2 gap-2">

        <button id="sidebarCollapse" class="btn btn-dark">
            <i class="fas fa-list"></i>
        </button>

        <div class="ms-auto d-flex align-items-center gap-2 me-1">

            <!-- Quick sign-out -->
            <a href="logout.php" title="Sign Out"
                class="btn p-0 border-0"
                style="background:rgba(255,107,107,0.18); border-radius:50%; width:38px; height:38px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-sign-out-alt" style="font-size:.95rem; color:#ff9090;"></i>
            </a>

            <!-- Notification Bell -->
            <div class="dropdown">
                <button class="btn position-relative p-2 text-white border-0" id="notifDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    style="background: rgba(255,255,255,0.12); border-radius: 50%; width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-bell" style="font-size:1rem;"></i>
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger<?= $_notifUnread > 0 ? '' : ' d-none' ?>"
                        style="font-size:0.6rem; min-width:18px; padding:3px 5px;">
                        <?= $_notifUnread > 99 ? '99+' : max(0, $_notifUnread) ?>
                    </span>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0" aria-labelledby="notifDropdown"
                    style="min-width:420px; max-width:460px; border-radius:12px; overflow:hidden;">

                    <!-- Header -->
                    <div class="d-flex align-items-center justify-content-between px-3 py-2"
                        style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f); color:#fff;">

                        <span class="fw-bold" style="font-size:.9rem;">
                            <i class="fas fa-bell me-1"></i>
                            Notifications
                            <?php if ($_notifUnread > 0): ?>
                                <span class="badge bg-danger ms-1" style="font-size:.65rem;"><?= $_notifUnread ?></span>
                            <?php endif; ?>
                        </span>

                        <?php if ($_notifUnread > 0): ?>
                            <button class="btn btn-sm text-white p-0 border-0"
                                style="font-size:.75rem; opacity:.85; background:none;" onclick="markAllRead()">
                                <i class="fas fa-check-double me-1"></i>
                                Mark all read
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
                                    data-id="<?= $n['notification_id'] ?>" data-link="<?= htmlspecialchars($n['link'] ?? '') ?>"
                                    style="cursor:pointer; background:<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>; transition:background .15s;"
                                    onmouseover="this.style.background='#e8f0fe'"
                                    onmouseout="this.style.background='<?= $n['is_read'] ? '#fff' : '#f0f7ff' ?>'">

                                    <div class="flex-shrink-0 mt-1">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                                            style="width:32px; height:32px; background:#e9ecef;">
                                            <i class="fas <?= Notification::typeIcon($n['type']) ?>"
                                                style="font-size:.8rem;"></i>
                                        </div>
                                    </div>

                                    <?php $_isLongMsg = mb_strlen($n['message']) > 75; ?>
                                    <div class="flex-grow-1" style="min-width:0;">
                                        <div class="small <?= $n['is_read'] ? 'text-muted' : 'fw-semibold' ?>"
                                            style="line-height:1.3;">
                                            <div class="notif-msg-short"
                                                style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                <?= htmlspecialchars($n['message']) ?>
                                            </div>
                                            <?php if ($_isLongMsg): ?>
                                                <div class="notif-msg-full d-none" style="white-space:normal;">
                                                    <?= htmlspecialchars($n['message']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($_isLongMsg): ?>
                                            <a href="javascript:void(0)" class="fw-semibold notif-readmore"
                                                style="font-size:.72rem; color:var(--udsm-blue); text-decoration: none;"
                                                onclick="event.stopPropagation(); toggleNotifMsg(this);">
                                                Read more
                                            </a>
                                        <?php endif; ?>
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
                        <a href="notifications.php" class="small text-decoration-none fw-semibold"
                            style="color:var(--udsm-blue);">
                            View all notifications
                            <i class="fas fa-arrow-right ms-1" style="font-size:.7rem;"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Profile dropdown -->
            <div class="dropdown">
                <button class="d-flex align-items-center gap-2 text-white border-0" id="profileDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false" style="background:rgba(255,255,255,0.1); border-radius:50px;
                           padding:4px 14px 4px 4px; transition:background 0.2s; cursor:pointer;">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                        style="width:32px; height:32px; background:var(--udsm-yellow); color:#1e3a5f; font-size:.85rem;">
                        <?= $_topbarAvatar ?>
                    </div>
                    <span class="d-none d-sm-inline small fw-medium"
                        style="max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?= $_topbarName ?>
                    </span>
                    <i class="fas fa-chevron-down d-none d-sm-inline" style="font-size:.6rem; opacity:.7;"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 py-0" aria-labelledby="profileDropdown"
                    style="min-width:245px; border-radius:12px; overflow:hidden;">

                    <!-- User identity header -->
                    <li style="background:#f8f9fa; border-bottom:1px solid #e9ecef;">
                        <div class="d-flex align-items-center gap-3 px-3 py-3">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                style="width:46px; height:46px; background:var(--udsm-yellow); color:#1e3a5f; font-size:1.2rem;">
                                <?= $_topbarAvatar ?>
                            </div>
                            <div class="overflow-hidden flex-grow-1">
                                <div class="fw-semibold text-truncate"
                                    style="font-size:.9rem; line-height:1.3; color:#1e3a5f;">
                                    <?= $_topbarName ?>
                                </div>
                                <span class="badge mt-1"
                                    style="background:rgba(0,98,204,0.1); color:#0062cc; font-size:.68rem; letter-spacing:.4px; padding:3px 8px;">
                                    <i class="fas <?= $_rc['icon'] ?> me-1"></i><?= $_rc['label'] ?>
                                </span>
                            </div>
                        </div>
                    </li>

                    <!-- Menu items -->
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 py-2 px-3" href="profile.php">
                            <span class="d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width:32px; height:32px; border-radius:8px; background:rgba(0,98,204,0.08);">
                                <i class="fas fa-user" style="color:#0062cc; font-size:.85rem;"></i>
                            </span>
                            <div>
                                <div class="small fw-semibold">My Profile</div>
                                <div class="text-muted" style="font-size:.71rem;">View and edit your account</div>
                            </div>
                        </a>
                    </li>

                    <!-- <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 py-2 px-3" href="notifications.php">
                            <span class="d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width:32px; height:32px; border-radius:8px; background:rgba(245,158,11,0.08);">
                                <i class="fas fa-bell" style="color:#d97706; font-size:.85rem;"></i>
                            </span>
                            <div>
                                <div class="small fw-semibold">Notifications
                                    <?php if ($_notifUnread > 0): ?>
                                        <span class="badge bg-danger ms-1" style="font-size:.6rem;"><?= $_notifUnread ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted" style="font-size:.71rem;">View all notifications</div>
                            </div>
                        </a>
                    </li> -->

                    <li>
                        <hr class="dropdown-divider my-1">
                    </li>

                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-3 py-2 px-3" href="logout.php">
                            <span class="d-flex align-items-center justify-content-center flex-shrink-0"
                                style="width:32px; height:32px; border-radius:8px; background:rgba(220,53,69,0.08);">
                                <i class="fas fa-sign-out-alt text-danger" style="font-size:.85rem;"></i>
                            </span>
                            <div>
                                <div class="small fw-semibold text-danger">
                                    Sign Out
                                </div>
                                <div class="text-muted" style="font-size:.71rem;">
                                    End your current session
                                </div>
                            </div>
                        </a>
                    </li>
                    <li style="height:6px;"></li>
                </ul>
            </div>

        </div>

    </div>
</nav>

<script>
    var _csrfToken = <?= json_encode(csrf_token()) ?>;

    function toggleNotifMsg(link) {
        var wrap = link.previousElementSibling;
        var shortEl = wrap.querySelector('.notif-msg-short');
        var fullEl = wrap.querySelector('.notif-msg-full');
        var expanded = !fullEl.classList.contains('d-none');
        if (expanded) {
            fullEl.classList.add('d-none');
            shortEl.classList.remove('d-none');
            link.textContent = 'Read more';
        } else {
            fullEl.classList.remove('d-none');
            shortEl.classList.add('d-none');
            link.textContent = 'Show less';
        }
    }

    function markAllRead() {
        fetch('mark_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=mark_all&csrf_token=' + encodeURIComponent(_csrfToken)
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
                var id = this.dataset.id;
                var link = this.dataset.link;

                fetch('mark_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=mark_read&id=' + id + '&csrf_token=' + encodeURIComponent(_csrfToken)
                }).then(function () {
                    if (link) window.location.href = link;
                });
            });
        });
    });

    // Poll for unread count every 60 s and update the bell badge without a page reload
    (function () {
        function updateBadge(count) {
            var badge = document.getElementById('notifBadge');
            if (!badge) return;
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
        setInterval(function () {
            fetch('ajax/notification_count.php')
                .then(function (r) { return r.json(); })
                .then(function (d) { updateBadge(d.count || 0); })
                .catch(function () {});
        }, 60000);
    })();
</script>