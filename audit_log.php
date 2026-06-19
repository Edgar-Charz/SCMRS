<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

// Filters
$filterAction = isset($_GET['action']) && $_GET['action'] !== '' ? $_GET['action'] : null;
$filterDateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$filterDateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$total = $admin->getActivityLogsCount($filterAction, $filterDateFrom, $filterDateTo);
$pages = (int) ceil($total / $limit);
$logs = $admin->getActivityLogs($page, $limit, $filterAction, $filterDateFrom, $filterDateTo);

// Human-readable labels and badge colours for each action type
$actionMeta = [
    'user_created' => ['label' => 'User Created', 'class' => 'bg-success'],
    'user_deleted' => ['label' => 'User Deleted', 'class' => 'bg-danger'],
    'user_suspended' => ['label' => 'Account Suspended', 'class' => 'bg-warning text-dark'],
    'user_activated' => ['label' => 'Account Activated', 'class' => 'bg-info text-dark'],
    'password_reset' => ['label' => 'Password Reset', 'class' => 'bg-secondary'],
    'staff_approved' => ['label' => 'Staff Approved', 'class' => 'bg-success'],
    'staff_rejected' => ['label' => 'Staff Rejected', 'class' => 'bg-danger'],
    'staff_demoted'        => ['label' => 'Staff Approval Revoked', 'class' => 'bg-warning text-dark'],
    'complaint_assigned'   => ['label' => 'Complaint Assigned',     'class' => 'bg-info text-dark'],
    'complaint_deleted'    => ['label' => 'Complaint Deleted',      'class' => 'bg-danger'],
    'department_added'     => ['label' => 'Department Added',       'class' => 'bg-success'],
    'department_updated'   => ['label' => 'Department Updated',     'class' => 'bg-info text-dark'],
    'department_deleted'   => ['label' => 'Department Deleted',     'class' => 'bg-danger'],
    'category_added'       => ['label' => 'Category Added',         'class' => 'bg-success'],
    'category_updated'     => ['label' => 'Category Updated',       'class' => 'bg-info text-dark'],
    'category_deleted'     => ['label' => 'Category Deleted',       'class' => 'bg-danger'],
    'subcategory_added'    => ['label' => 'Subcategory Added',      'class' => 'bg-success'],
    'subcategory_updated'  => ['label' => 'Subcategory Updated',    'class' => 'bg-info text-dark'],
    'subcategory_deleted'  => ['label' => 'Subcategory Deleted',    'class' => 'bg-danger'],
];

function actionBadge(string $action, array $meta): string
{
    $m = $meta[$action] ?? ['label' => ucwords(str_replace('_', ' ', $action)), 'class' => 'bg-secondary'];
    return '<span class="badge ' . $m['class'] . '">' . htmlspecialchars($m['label']) . '</span>';
}

// Build pagination query string
function pageUrl(int $p, ?string $action, ?string $from, ?string $to): string
{
    $q = ['page' => $p];
    if ($action)
        $q['action'] = $action;
    if ($from)
        $q['date_from'] = $from;
    if ($to)
        $q['date_to'] = $to;
    return 'audit_log.php?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Admin</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
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

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center p-2 mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Admin / Audit Log</li>
                    </ol>
                </nav>

                <!-- Page heading -->
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="mb-0 fw-bold" style="color:var(--udsm-blue);">
                            <i class="fas fa-clipboard-list me-2"></i>Audit Log
                        </h4>
                        <small class="text-muted">All administrator actions are recorded here</small>
                    </div>
                    <span class="badge bg-primary fs-6"><?= number_format($total) ?>
                        record<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <!-- Filters -->
                <div class="container-card border-0 shadow-sm p-4 mb-4">
                    <form method="GET" action="audit_log.php" id="auditFilterForm" class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold small">Action Type</label>
                            <select name="action" class="form-select" onchange="autoFilter()">
                                <option value="">All Actions</option>
                                <?php foreach ($actionMeta as $key => $meta): ?>
                                    <option value="<?= $key ?>" <?= $filterAction === $key ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold small">Date From</label>
                            <input type="date" name="date_from" class="form-control"
                                value="<?= htmlspecialchars($filterDateFrom ?? '') ?>" onchange="autoFilter()">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold small">Date To</label>
                            <input type="date" name="date_to" class="form-control"
                                value="<?= htmlspecialchars($filterDateTo ?? '') ?>" onchange="autoFilter()">
                        </div>
                        <div class="col-12 col-md-2 d-flex gap-2 align-items-end">
                            <!-- Loading indicator shown while form is submitting -->
                            <span id="filterSpinner" class="d-none me-1">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                            </span>
                            <a href="audit_log.php" class="btn btn-outline-secondary" title="Clear filters">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Log table -->
                <div class="container-card border-0 shadow-sm p-4">
                    <?php if (empty($logs)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-clipboard-list fa-3x mb-3 d-block" style="opacity:.3;"></i>
                            <p class="mb-0">No activity records
                                found<?= ($filterAction || $filterDateFrom || $filterDateTo) ? ' for the selected filters' : '' ?>.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:160px;">DATE &amp; TIME</th>
                                        <th style="width:140px;">ACTION</th>
                                        <th>PERFORMED BY</th>
                                        <th>TARGET</th>
                                        <th>DETAILS</th>
                                        <th style="width:110px;">IP ADDRESS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="text-nowrap" style="font-size:.82rem; color:#555;">
                                                <?= date('d M Y', strtotime($log['created_at'])) ?>
                                                <br>
                                                <span
                                                    class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                            </td>
                                            <td><?= actionBadge($log['action'], $actionMeta) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($log['admin_name']) ?></span>
                                            </td>
                                            <td>
                                                <span
                                                    class="fw-semibold"><?= htmlspecialchars($log['target_name'] ?? '—') ?></span>
                                                <?php if ($log['target_id']): ?>
                                                    <small class="text-muted d-block">#<?= $log['target_id'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:.85rem; color:#555; max-width:280px;">
                                                <?= htmlspecialchars($log['details'] ?? '—') ?>
                                            </td>
                                            <td style="font-size:.8rem; color:#777; font-family:monospace;">
                                                <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pages > 1): ?>
                            <nav class="mt-4 d-flex align-items-center justify-content-between">
                                <small class="text-muted">
                                    Showing <?= (($page - 1) * $limit) + 1 ?>–<?= min($page * $limit, $total) ?>
                                    of <?= number_format($total) ?> records
                                </small>
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                            href="<?= pageUrl($page - 1, $filterAction, $filterDateFrom, $filterDateTo) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($pages, $page + 2);
                                    if ($start > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="<?= pageUrl(1, $filterAction, $filterDateFrom, $filterDateTo) ?>">1</a>
                                        </li>
                                        <?php if ($start > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($i = $start; $i <= $end; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="<?= pageUrl($i, $filterAction, $filterDateFrom, $filterDateTo) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($end < $pages): ?>
                                        <?php if ($end < $pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="<?= pageUrl($pages, $filterAction, $filterDateFrom, $filterDateTo) ?>"><?= $pages ?></a>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                        <a class="page-link"
                                            href="<?= pageUrl($page + 1, $filterAction, $filterDateFrom, $filterDateTo) ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function autoFilter() {
            document.getElementById('filterSpinner').classList.remove('d-none');
            document.getElementById('auditFilterForm').submit();
        }
    </script>
</body>

</html>