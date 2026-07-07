<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once 'config/Database.php';
require_once 'classes/User.php';
require_once 'classes/Staff.php';

$db = new Database();
$conn = $db->connect();
$staff = new Staff($conn);

$userId = (int) $_SESSION['user_id'];
$staffDetails = $staff->getStaffDetailsByUserId($userId);

if (!$staffDetails) {
    header('Location: login.php');
    exit;
}

$isApproved = (int) $staffDetails['staff_approval_status'] === 1;
$departmentName = $staffDetails['department_name'] ?? 'Unassigned';
$staffName = $staffDetails['username'] ?? 'Staff';
$staffEmail = $staffDetails['user_email'] ?? '';

$complaintCounts = $isApproved ? $staff->getStaffComplaintCounts($staffDetails['staff_id']) : [
    'total' => 0,
    STATUS_PENDING => 0,
    STATUS_IN_PROGRESS => 0,
    STATUS_AWAITING_RESPONSE => 0,
    STATUS_RESOLVED => 0,
    STATUS_REJECTED => 0,
];
$recentComplaints = $isApproved ? $staff->getRecentAssignedComplaints($staffDetails['staff_id']) : [];
$studentRespondedCount = $isApproved ? $staff->getStudentRespondedCount($staffDetails['staff_id']) : 0;
$performanceStats = $isApproved ? $staff->getPerformanceStats($staffDetails['staff_id']) : [];
$overdueCount = $isApproved ? $staff->getOverdueCount($staffDetails['staff_id']) : 0;

function formatStatusBadgeClass($status)
{
    return match (strtolower($status)) {
        STATUS_PENDING => 'bg-danger',
        'in_progress', 'in progress' => 'bg-warning',
        STATUS_RESOLVED => 'bg-success',
        STATUS_REJECTED => 'bg-secondary',
        STATUS_AWAITING_RESPONSE => 'bg-info',
        STATUS_REOPENED => 'bg-orange',
        default => 'bg-secondary',
    };
}

function formatStatusLabel($status)
{
    return ucwords(str_replace(['_', '-'], ' ', $status));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff | Dashboard</title>
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

    <?php $_staffSidebarRank = (int) ($staffDetails['role_rank'] ?? 0); ?>
    <div class="d-flex">
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <!-- Topbar -->
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="staff_dashboard.php"><i class="fas fa-user-tie" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="staff_dashboard.php" style="color:black;">Staff</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= htmlspecialchars($staffName) ?>!</h3>
                    <p class="mb-0 opacity-75">Manage your assigned complaints.</p>
                </div>

                <?php if (!$isApproved): ?>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="container-card shadow-sm p-4 text-center">
                                <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                                <h4 class="mb-2">Waiting for Admin Approval</h4>
                                <p class="mb-3 text-muted">
                                    Your staff account is currently pending approval by an administrator.
                                    You will be able to access assigned complaints and the full dashboard once approved.
                                </p>
                                <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($departmentName) ?></p>
                                <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($staffEmail) ?></p>
                                <a href="logout.php" class="btn btn-danger btn-sm mt-3">
                                    <i class="fas fa-sign-out-alt me-2"></i>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>

                    <?php if ($studentRespondedCount > 0): ?>
                        <div id="respondedAlert"
                            class="alert alert-success alert-dismissible d-flex align-items-center mb-4 shadow-sm" role="alert"
                            style="border-left: 5px solid #16a34a; border-radius: 10px;">
                            <i class="fas fa-reply fa-lg me-3 text-success flex-shrink-0"></i>
                            <a href="assigned_complaints.php"
                                class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center"
                                style="cursor:pointer;">
                                <div class="flex-grow-1">
                                    <strong><?= $studentRespondedCount ?>
                                        complaint<?= $studentRespondedCount > 1 ? 's have' : ' has' ?> a student response
                                        waiting.</strong>
                                    <span class="ms-2 text-muted small">A student has replied to your information request
                                        &mdash; click to review &rarr;</span>
                                </div>
                                <span class="badge bg-success fs-6 me-2"><?= $studentRespondedCount ?></span>
                            </a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($overdueCount > 0): ?>
                        <div id="overdueAlert"
                            class="alert alert-warning alert-dismissible d-flex align-items-center mb-4 shadow-sm" role="alert"
                            style="border-left: 5px solid #dc2626; border-radius: 10px;">
                            <i class="fas fa-exclamation-triangle fa-lg me-3 text-danger flex-shrink-0"></i>
                            <a href="assigned_complaints.php"
                                class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center"
                                style="cursor:pointer;">
                                <div class="flex-grow-1">
                                    <strong><?= $overdueCount ?> overdue complaint<?= $overdueCount > 1 ? 's' : '' ?>.</strong>
                                    <span class="ms-2 text-muted small">Past deadline or open &gt;7 days without one &mdash; click to review &rarr;</span>
                                </div>
                                <span class="badge bg-danger fs-6 me-2"><?= $overdueCount ?></span>
                            </a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Active status counts -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= $complaintCounts['total'] ?></h2>
                                    <p class="mb-0 fw-bold small">Total Complaints</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $complaintCounts['pending'] ?></h2>
                                    <p class="mb-0 fw-bold small">Pending</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(2,132,199,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-spinner fa-spin fa-lg" style="color:#0284c7;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= $complaintCounts['in_progress'] ?>
                                    </h2>
                                    <p class="mb-0 fw-bold small">In Progress</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(14,165,233,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-user-clock fa-lg" style="color:#0ea5e9;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#0ea5e9;">
                                        <?= $complaintCounts[STATUS_AWAITING_RESPONSE] ?></h2>
                                    <p class="mb-0 fw-bold small">Awaiting Response</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outcome + alert counts -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $complaintCounts['resolved'] ?></h2>
                                    <p class="mb-0 fw-bold small">Resolved</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(107,114,128,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-times-circle fa-lg" style="color:#6b7280;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#6b7280;"><?= $complaintCounts['rejected'] ?></h2>
                                    <p class="mb-0 fw-bold small">Rejected</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <a href="assigned_complaints.php" class="text-decoration-none">
                                <div class="stat-card p-3 d-flex align-items-center justify-content-between shadow-sm"
                                    style="<?= $overdueCount > 0 ? 'background:linear-gradient(135deg,#fef2f2,#fee2e2);border:2px solid #dc2626;' : '' ?>">
                                    <div
                                        style="width:48px;height:48px;border-radius:12px;background:rgba(220,38,38,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="fas fa-exclamation-circle fa-lg" style="color:#dc2626;"></i>
                                    </div>
                                    <div class="text-end">
                                        <h2 class="mb-0 fw-bold" style="color:#dc2626;"><?= $overdueCount ?></h2>
                                        <p class="mb-0 fw-bold small">Overdue (past deadline)</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-calendar-check fa-lg" style="color:#16a34a;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#16a34a;">
                                        <?= $performanceStats['resolved_this_month'] ?? 0 ?>
                                    </h2>
                                    <p class="mb-0 fw-bold small">Resolved This Month</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Performance Stats -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(13,202,240,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-clock fa-lg" style="color:#0dcaf0;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#0dcaf0;">
                                        <?= $performanceStats['avg_resolution_days'] ?? '--' ?>
                                    </h2>
                                    <p class="mb-0 fw-bold small">Avg Resolution (days)</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-percent fa-lg" style="color:#6366f1;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#6366f1;">
                                        <?= $performanceStats['resolution_rate'] ?? '--' ?>%
                                    </h2>
                                    <p class="mb-0 fw-bold small">Resolution Rate</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-star fa-lg text-warning"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#f59e0b;">
                                        <?= $performanceStats['avg_rating'] ?? '--' ?>
                                    </h2>
                                    <p class="mb-0 fw-bold small">Avg Rating / 5</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 <?= (int) ($staffDetails['role_rank'] ?? 0) >= 2 ? 'col-md-6' : 'col-md-12' ?>">
                            <a href="assigned_complaints.php" class="action-card action-card--blue">
                                <i class="fas fa-folder-open action-icon"></i>
                                <h5>View Assigned Complaints</h5>
                                <small>View all complaints assigned to you</small>
                            </a>
                        </div>
                        <?php if ((int) ($staffDetails['role_rank'] ?? 0) >= 2): ?>
                            <div class="col-12 col-md-6">
                                <a href="department_complaints.php" class="action-card action-card--teal">
                                    <i class="fas fa-building action-icon"></i>
                                    <h5>Department View</h5>
                                    <small>View all complaints in your department</small>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="container-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h4 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Assigned Complaints</h4>
                            <div class="search-input"></div>
                        </div>
                        <p class="text-muted small mb-3">Your latest complaint assignments</p>

                        <div class="table-responsive">
                            <table id="complaintsTable" class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>TITLE</th>
                                        <th>CATEGORY</th>
                                        <th>DATE</th>
                                        <th>STATUS</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recentComplaints)): ?>
                                        <?php foreach ($recentComplaints as $complaint): ?>
                                            <tr data-href="assigned_complaint_details.php?id=<?= (int) $complaint['complaint_id'] ?>">
                                                <td>#<?= htmlspecialchars($complaint['complaint_id']) ?></td>
                                                <td><?= htmlspecialchars($complaint['complaint_title']) ?></td>
                                                <td><?= htmlspecialchars($complaint['category_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($complaint['created_at']))) ?></td>
                                                <td>
                                                    <span
                                                        class="badge <?= formatStatusBadgeClass($complaint['complaint_status']) ?>">
                                                        <?= htmlspecialchars(formatStatusLabel($complaint['complaint_status'])) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="assigned_complaint_details.php?id=<?= (int) $complaint['complaint_id'] ?>"
                                                        class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No recent assigned complaints found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var userId = <?= (int) $_SESSION['user_id'] ?>;

            function bindDismissAlert(alertId, storageKey, count) {
                var alertEl = document.getElementById(alertId);
                if (!alertEl || count <= 0) return;
                if (localStorage.getItem(storageKey)) {
                    alertEl.style.display = 'none';
                    return;
                }
                alertEl.addEventListener('closed.bs.alert', function () {
                    localStorage.setItem(storageKey, '1');
                });
                var link = alertEl.querySelector('a');
                if (link) {
                    link.addEventListener('click', function () {
                        localStorage.setItem(storageKey, '1');
                    });
                }
            }

            bindDismissAlert(
                'respondedAlert',
                'scmrs_dismissed_responded_' + userId + '_' + <?= (int) $studentRespondedCount ?>,
                <?= (int) $studentRespondedCount ?>
            );
            bindDismissAlert(
                'overdueAlert',
                'scmrs_dismissed_overdue_' + userId + '_' + <?= (int) $overdueCount ?>,
                <?= (int) $overdueCount ?>
            );
        });
    </script>

    <?php $useDataTablesJs = true; include 'includes/foot_scripts.php'; ?>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $(document).ready(function () {
            if ($("#complaintsTable").length > 0) {
                if (!$.fn.DataTable.isDataTable("#complaintsTable")) {
                    $("#complaintsTable").DataTable({
                        destroy: true,
                        bFilter: true,
                        sDom: "fBtlpi",
                        pagingType: "numbers",
                        ordering: true,
                        language: {
                            search: " ",
                            sLengthMenu: "_MENU_",
                            searchPlaceholder: "Search Complaints...",
                            info: "_START_ - _END_ of _TOTAL_ items"
                        },
                        initComplete: function (settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                    $('#complaintsTable tbody').on('click', 'tr[data-href]', function(e) {
                        if ($(e.target).closest('a, button, input, label').length) return;
                        window.location.href = $(this).data('href');
                    });
                }
            }
        });
    </script>

    <?php if (!empty($_SESSION['login_success'])):
        unset($_SESSION['login_success']); ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php endif; ?>

    <!-- <script>
        $(document).ready(function () {
            $('#complaintsTable').DataTable({
                responsive: true,
                order: [[3, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search complaints..."
                }
            });

            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script> -->
</body>

</html>
