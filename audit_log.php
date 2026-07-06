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

$perPage     = 100;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$filterAction = $_GET['filter_action'] ?? null;
$filterFrom   = $_GET['filter_from'] ?? null;
$filterTo     = $_GET['filter_to'] ?? null;

$logs  = $admin->getActivityLogs($currentPage, $perPage, $filterAction ?: null, $filterFrom ?: null, $filterTo ?: null);
$total = $admin->getActivityLogsCount($filterAction ?: null, $filterFrom ?: null, $filterTo ?: null);
$totalPages = max(1, (int)ceil($total / $perPage));

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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log | Admin</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css"> -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
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
                            <a href="admin_dashboard.php"><i class="fas fa-clipboard-list" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Audit Log</li>
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
                    <span id="recordsBadge" class="badge bg-primary fs-6"><?= number_format($total) ?>
                        record<?= $total !== 1 ? 's' : '' ?></span>
                </div>

                <!-- Filters -->
                <div class="container-card border-0 shadow-sm p-4 mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold small">Action Type</label>
                            <select id="filterAction" class="form-select">
                                <option value="">All Actions</option>
                                <?php foreach ($actionMeta as $key => $meta): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold small">Date From</label>
                            <input type="date" id="filterDateFrom" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold small">Date To</label>
                            <input type="date" id="filterDateTo" class="form-control">
                        </div>
                        <div class="col-12 col-md-2 d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary" title="Clear filters">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Log table -->
                <div class="container-card border-0 shadow-sm p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold text-muted">Log Entries</h6>
                        <div class="search-input"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="auditTable" class="table table-hover align-middle mb-0">
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
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-clipboard-list fa-3x mb-3 d-block" style="opacity:.3;"></i>
                                            No activity records found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr data-action="<?= htmlspecialchars($log['action']) ?>"
                                            data-date="<?= date('Y-m-d', strtotime($log['created_at'])) ?>">
                                            <td class="text-nowrap" style="font-size:.82rem; color:#555;">
                                                <?= date('d M Y', strtotime($log['created_at'])) ?>
                                                <br>
                                                <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                            </td>
                                            <td><?= actionBadge($log['action'], $actionMeta) ?></td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($log['admin_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="fw-semibold"><?= htmlspecialchars($log['target_name'] ?? '-') ?></span>
                                                <?php if ($log['target_id']): ?>
                                                    <small class="text-muted d-block">#<?= $log['target_id'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:.85rem; color:#555; max-width:280px;">
                                                <?= htmlspecialchars($log['details'] ?? '-') ?>
                                            </td>
                                            <td style="font-size:.8rem; color:#777; font-family:monospace;">
                                                <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-3">
                        <ul class="pagination pagination-sm justify-content-center flex-wrap">
                            <?php
                            $qBase = http_build_query(array_filter([
                                'filter_action' => $filterAction,
                                'filter_from'   => $filterFrom,
                                'filter_to'     => $filterTo,
                            ]));
                            $qBase = $qBase ? '&' . $qBase : '';
                            ?>
                            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $currentPage - 1 . $qBase ?>">‹ Prev</a>
                            </li>
                            <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                                <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p . $qBase ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $currentPage + 1 . $qBase ?>">Next ›</a>
                            </li>
                        </ul>
                        <p class="text-center text-muted small">
                            Showing page <?= $currentPage ?> of <?= $totalPages ?> (<?= $total ?> total entries)
                        </p>
                    </nav>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'auditTable') return true;
            var rowNode = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) return true;
            var row    = $(rowNode);
            var rAction = row.data('action') || '';
            var rDate   = row.data('date')   || '';
            var fAction = $('#filterAction').val()   || '';
            var fFrom   = $('#filterDateFrom').val() || '';
            var fTo     = $('#filterDateTo').val()   || '';
            if (fAction && rAction !== fAction) return false;
            if (fFrom   && rDate   < fFrom)    return false;
            if (fTo     && rDate   > fTo)      return false;
            return true;
        });

        var auditTable;
        $(function() {
            auditTable = $('#auditTable').DataTable({
                order:      [[0, 'desc']],
                pageLength: 25,
                bFilter:    true,
                sDom:       "fBtlpi",
                pagingType: "numbers",
                language: {
                    search:            " ",
                    sLengthMenu:       "_MENU_",
                    searchPlaceholder: "Search logs...",
                    info:              "_START_ - _END_ of _TOTAL_ entries"
                },
                initComplete: function() {
                    $(".dataTables_filter").appendTo(".search-input");
                }
            });

            auditTable.on('draw.dt', function() {
                var info  = auditTable.page.info();
                var count = info.recordsDisplay;
                $('#recordsBadge').text(count.toLocaleString() + ' record' + (count !== 1 ? 's' : ''));
            });

            $('#filterAction').on('change', function() { auditTable.draw(); });
            $('#filterDateFrom, #filterDateTo').on('change', function() { auditTable.draw(); });
            $('#clearFilters').on('click', function() {
                $('#filterAction').val('');
                $('#filterDateFrom, #filterDateTo').val('');
                auditTable.search('').draw();
            });
        });
    </script>
</body>

</html>