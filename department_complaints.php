<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once 'config/Database.php';
require_once 'classes/Staff.php';
require_once 'includes/csrf.php';

$db   = new Database();
$conn = $db->connect();
$staff = new Staff($conn);

$userId       = (int) $_SESSION['user_id'];
$staffDetails = $staff->getStaffDetailsByUserId($userId);

if (!$staffDetails || (int) $staffDetails['staff_approval_status'] !== 1) {
    header('Location: staff_dashboard.php');
    exit;
}

$roleRank = (int) ($staffDetails['role_rank'] ?? 0);
if ($roleRank < 2) {
    $_SESSION['message_error'] = "Department View is only available to senior staff.";
    header('Location: staff_dashboard.php');
    exit;
}

$departmentId = (int) ($staffDetails['staff_department_id'] ?? 0);
if (!$departmentId) {
    $_SESSION['message_error'] = "You are not assigned to a department.";
    header('Location: staff_dashboard.php');
    exit;
}

$departmentComplaints = $staff->getDepartmentComplaints($departmentId);
$deptStats            = $staff->getDepartmentStats($departmentId);
$_staffSidebarRank    = $roleRank;

function deptStatusBadge($status)
{
    $map = [
        STATUS_PENDING => ['bg-warning text-dark',  'Pending'],
        STATUS_IN_PROGRESS => ['bg-info text-white',    'In Progress'],
        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
        STATUS_REJECTED => ['bg-danger text-white',  'Rejected'],
        STATUS_REOPENED => ['bg-warning text-dark',  'Reopened'],
        'on_hold'                   => ['bg-secondary text-white', 'On Hold'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary text-white', ucwords(str_replace('_', ' ', $status))];
    return "<span class=\"badge $class\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff | Department Complaints</title>
    <?php include 'includes/head_assets.php'; ?>
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
                            <a href="staff_dashboard.php"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="staff_dashboard.php" style="color:black;">Staff</a></li>
                        <li class="breadcrumb-item active">Department Complaints</li>
                    </ol>
                    <a href="staff_dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">
                        <i class="fas fa-building me-2"></i>
                        Department Complaints &mdash; <?= htmlspecialchars($staffDetails['department_name'] ?? 'N/A') ?>
                    </h3>
                    <p class="mb-0 opacity-75">Read-only overview of all complaints in your department.</p>
                </div>

                <!-- Department Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= (int)($deptStats['total'] ?? 0) ?></h2>
                                <p class="mb-0 fw-bold small">Total</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= (int)($deptStats['pending'] ?? 0) ?></h2>
                                <p class="mb-0 fw-bold small">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(2,132,199,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-spinner fa-spin fa-lg" style="color:#0284c7;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= (int)($deptStats['in_progress'] ?? 0) ?></h2>
                                <p class="mb-0 fw-bold small">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(108,117,125,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-pause-circle fa-lg" style="color:#6c757d;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#6c757d;"><?= (int)($deptStats['on_hold'] ?? 0) ?></h2>
                                <p class="mb-0 fw-bold small">On Hold</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= (int)($deptStats['resolved'] ?? 0) ?></h2>
                                <p class="mb-0 fw-bold small">Resolved</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="container-card shadow-sm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select id="filterStatus" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="awaiting_student_response">Awaiting Response</option>
                                <option value="resolved">Resolved</option>
                                <option value="rejected">Rejected</option>
                                <option value="reopened">Reopened</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <label class="form-label small fw-semibold mb-1">Category</label>
                            <select id="filterCategory" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php
                                $dcats = array_values(array_filter(array_unique(array_column($departmentComplaints, 'category_name'))));
                                sort($dcats);
                                foreach ($dcats as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date From</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date To</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="container-card shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-list me-2"></i>All Department Complaints
                        </h5>
                        <div class="search-input"></div>
                    </div>
                    <p class="text-muted small mb-3">
                        Showing all complaints routed to
                        <strong><?= htmlspecialchars($staffDetails['department_name'] ?? 'your department') ?></strong>.
                        This is a read-only view.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-striped" id="deptComplaintsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th class="text-center">STUDENT</th>
                                    <th class="text-center">CATEGORY</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">ASSIGNED TO</th>
                                    <th class="text-center">SUBMITTED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departmentComplaints)): ?>
                                    <?php foreach ($departmentComplaints as $c): ?>
                                        <tr data-status="<?= htmlspecialchars($c['complaint_status']) ?>"
                                            data-category="<?= htmlspecialchars($c['category_name'] ?? '') ?>"
                                            data-date="<?= date('Y-m-d', strtotime($c['created_at'])) ?>">
                                            <td>#<?= (int) $c['complaint_id'] ?></td>
                                            <td><?= htmlspecialchars($c['complaint_title']) ?></td>
                                            <td class="text-center">
                                                <?php if ($c['is_anonymous']): ?>
                                                    <em class="text-muted">Anonymous</em>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($c['student_name'] ?? 'N/A') ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= htmlspecialchars($c['category_name'] ?? 'N/A') ?>
                                            </td>
                                            <td class="text-center">
                                                <?= deptStatusBadge($c['complaint_status']) ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($c['assigned_staff_name'])): ?>
                                                    <?= htmlspecialchars($c['assigned_staff_name']) ?>
                                                    <?php if (!empty($c['staff_role'])): ?>
                                                        <br><small class="text-muted">(<?= htmlspecialchars($c['staff_role']) ?>)</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= date('d M Y', strtotime($c['created_at'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No complaints found for this department.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <?php $useDataTablesJs = true; include 'includes/foot_scripts.php'; ?>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'deptComplaintsTable') return true;
            var rowNode  = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) return true;
            var row     = $(rowNode);
            var rStatus = row.data('status')   || '';
            var rCat    = row.data('category') || '';
            var rDate   = row.data('date')     || '';
            var fStatus   = $('#filterStatus').val()   || '';
            var fCategory = $('#filterCategory').val() || '';
            var fFrom     = $('#filterDateFrom').val() || '';
            var fTo       = $('#filterDateTo').val()   || '';
            if (fStatus   && rStatus !== fStatus)   return false;
            if (fCategory && rCat    !== fCategory)  return false;
            if (fFrom     && rDate   < fFrom)        return false;
            if (fTo       && rDate   > fTo)          return false;
            return true;
        });

        var deptTable;
        $(document).ready(function () {
            if ($("#deptComplaintsTable").length > 0 && !$.fn.DataTable.isDataTable("#deptComplaintsTable")) {
                deptTable = $("#deptComplaintsTable").DataTable({
                    destroy: true,
                    bFilter: true,
                    sDom: "fBtlpi",
                    pagingType: "numbers",
                    ordering: true,
                    order: [[6, 'desc']],
                    language: {
                        search: " ",
                        sLengthMenu: "_MENU_",
                        searchPlaceholder: "Search complaints...",
                        info: "_START_ - _END_ of _TOTAL_ items"
                    },
                    initComplete: function() {
                        $(".dataTables_filter").appendTo(".search-input");
                    }
                });
                $('#filterStatus, #filterCategory').on('change', function() { deptTable.draw(); });
                $('#filterDateFrom, #filterDateTo').on('change', function() { deptTable.draw(); });
                $('#clearFilters').on('click', function() {
                    $('#filterStatus, #filterCategory').val('');
                    $('#filterDateFrom, #filterDateTo').val('');
                    deptTable.search('').draw();
                });
            }
        });
    </script>

</body>
</html>
