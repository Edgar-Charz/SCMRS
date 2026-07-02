<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
} else {
    $adminId = $_SESSION['user_id'];
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$message = $error = "";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

$total_users = $admin->getTotalUsers();
$total_departments = $admin->getTotalDepartments();
$total_categories = $admin->getTotalCategories();
$total_complaints = $admin->getTotalComplaints();
$total_pending = $admin->getTotalPending();
$total_inprogress = $admin->getTotalInprogress();
$total_resolved = $admin->getTotalResolved();
$total_rejected = $admin->getTotalRejected();
$total_awaiting = $admin->getTotalAwaiting();
$total_unassigned = $admin->getUnassignedCount();
$complaints = $admin->getComplaints(10);
$roleCounts = $admin->getUserCountsByRole();

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

$pendingStaffCount = $admin->getPendingStaffCount();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <!-- <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css"> -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
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
        <!-- Sidebar -->
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <!-- Topbar -->
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-user-shield" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= htmlspecialchars($_SESSION['username']) ?>!</h3>
                    <p class="mb-0 opacity-75">Monitor and manage complaint monitoring system.</p>
                </div>

                <?php if ($pendingStaffCount > 0): ?>
                    <a href="user_management.php#approval" class="text-decoration-none">
                        <div class="alert alert-warning d-flex align-items-center mb-4 shadow-sm" role="alert"
                            style="border-left: 5px solid #f59e0b; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-user-clock fa-lg me-3 text-warning"></i>
                            <div class="flex-grow-1">
                                <strong><?= $pendingStaffCount ?> staff member<?= $pendingStaffCount > 1 ? 's' : '' ?>
                                    awaiting approval.</strong>
                                <span class="ms-2 text-muted small">Click to review &rarr;</span>
                            </div>
                            <span class="badge bg-warning text-dark fs-6"><?= $pendingStaffCount ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($total_unassigned > 0): ?>
                    <a href="manage_complaints.php" class="text-decoration-none">
                        <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert"
                            style="border-left: 5px solid #dc2626; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-exclamation-triangle fa-lg me-3 text-danger"></i>
                            <div class="flex-grow-1">
                                <strong><?= $total_unassigned ?> pending
                                    complaint<?= $total_unassigned > 1 ? 's have' : ' has' ?> no staff assigned.</strong>
                                <span class="ms-2 text-muted small">Assign staff to these complaints &rarr;</span>
                            </div>
                            <span class="badge bg-danger fs-6"><?= $total_unassigned ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- System overview -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                            style="cursor:pointer; position:relative;" data-bs-toggle="modal"
                            data-bs-target="#userBreakdownModal">
                            <i class="fas fa-circle-info"
                                style="position:absolute; top:6px; right:8px; font-size:0.7rem; opacity:0.5;"></i>
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(0,98,204,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-users fa-lg" style="color:#0062cc;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0062cc;"><?= $total_users ?></h2>
                                <p class="mb-0 fw-bold small">Total Users</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(124,58,237,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-sitemap fa-lg" style="color:#7c3aed;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#7c3aed;"><?= $total_departments ?></h2>
                                <p class="mb-0 fw-bold small">Departments</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(8,145,178,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-tags fa-lg" style="color:#0891b2;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0891b2;"><?= $total_categories ?></h2>
                                <p class="mb-0 fw-bold small">Categories</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= $total_complaints ?></h2>
                                <p class="mb-0 fw-bold small">Total Complaints</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Complaint status counts -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg">
                        <a href="manage_complaints.php" class="text-decoration-none">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                                style="cursor:pointer;">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $total_pending ?></h2>
                                    <p class="mb-0 fw-bold small">Pending</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <a href="manage_complaints.php" class="text-decoration-none">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                                style="cursor:pointer;">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(2,132,199,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-spinner fa-spin fa-lg" style="color:#0284c7;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= $total_inprogress ?></h2>
                                    <p class="mb-0 fw-bold small">In Progress</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <a href="manage_complaints.php" class="text-decoration-none">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                                style="cursor:pointer;">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(14,165,233,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-user-clock fa-lg" style="color:#0ea5e9;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#0ea5e9;"><?= $total_awaiting ?></h2>
                                    <p class="mb-0 fw-bold small">Awaiting Response</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <a href="manage_complaints.php" class="text-decoration-none">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                                style="cursor:pointer;">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $total_resolved ?></h2>
                                    <p class="mb-0 fw-bold small">Resolved</p>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="col-12 col-md-6 col-lg">
                        <a href="manage_complaints.php" class="text-decoration-none">
                            <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                                style="cursor:pointer;">
                                <div
                                    style="width:48px;height:48px;border-radius:12px;background:rgba(220,38,38,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-times-circle fa-lg" style="color:#dc2626;"></i>
                                </div>
                                <div class="text-end">
                                    <h2 class="mb-0 fw-bold" style="color:#dc2626;"><?= $total_rejected ?></h2>
                                    <p class="mb-0 fw-bold small">Rejected</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="container-card shadow-sm">
                    <h4 class="mb-4 fw-bold"><i class="fas fa-bolt me-2"></i>Quick Actions</h4>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg">
                            <a href="manage_complaints.php" class="action-card action-card--blue">
                                <i class="fas fa-envelope-open action-icon"></i>
                                <h5>Review Complaints</h5>
                                <small>View &amp; manage student complaints</small>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg">
                            <a href="user_management.php" class="action-card action-card--teal">
                                <i class="fas fa-user-shield action-icon"></i>
                                <h5>User Management</h5>
                                <small>Manage users &amp; approve staff</small>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg">
                            <a href="manage_departments.php" class="action-card action-card--amber">
                                <i class="fas fa-sitemap action-icon"></i>
                                <h5>Manage Departments</h5>
                                <small>Add or update departments</small>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg">
                            <a href="manage_categories.php" class="action-card action-card--purple">
                                <i class="fas fa-tags action-icon"></i>
                                <h5>Manage Categories</h5>
                                <small>Organise complaint categories</small>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg">
                            <a href="reports.php" class="action-card action-card--blue">
                                <i class="fas fa-chart-bar action-icon"></i>
                                <h5>Reports &amp; Analytics</h5>
                                <small>View statistics &amp; reports</small>
                            </a>
                        </div>
                        <div class="col-12 col-md-6 col-lg">
                            <a href="audit_log.php" class="action-card action-card--amber">
                                <i class="fas fa-clipboard-list action-icon"></i>
                                <h5>Audit Log</h5>
                                <small>Track all system activity</small>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($complaints)): ?>
                    <div class="container-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h4 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Complaints</h4>
                            <div class="d-flex align-items-center gap-3">
                                <div class="search-input"></div>
                                <a href="manage_complaints.php" class="btn btn-sm btn-outline-primary">View All &rarr;</a>
                            </div>
                        </div>
                        <p class="text-muted small mb-3">Recently added complaints</p>

                        <div class="table-responsive">
                            <table id="complaintsTable" class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>TITLE</th>
                                        <th>CATEGORY</th>
                                        <th>DATE</th>
                                        <th>STATUS</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $statusMap = [
                                        STATUS_PENDING => ['bg-warning text-dark', 'Pending'],
                                        STATUS_IN_PROGRESS => ['bg-info text-white', 'In Progress'],
                                        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
                                        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
                                        STATUS_REJECTED => ['bg-danger text-white', 'Rejected'],
                                        STATUS_REOPENED => ['bg-warning text-dark', 'Reopened'],
                                    ];
                                    foreach ($complaints as $complaint_row):
                                        [$sCls, $sLabel] = $statusMap[$complaint_row['complaint_status']] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $complaint_row['complaint_status']))];
                                        ?>
                                        <tr data-href="complaint_details.php?id=<?= (int) $complaint_row['complaint_id'] ?>">
                                            <td>#<?= (int) $complaint_row['complaint_id'] ?></td>
                                            <td><?= htmlspecialchars($complaint_row['complaint_title']) ?></td>
                                            <td><?= htmlspecialchars($complaint_row['category_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($complaint_row['created_at'])) ?></td>
                                            <td><span class="badge <?= $sCls ?>"><?= $sLabel ?></span></td>
                                            <td class="text-center">
                                                <a href="complaint_details.php?id=<?= (int) $complaint_row['complaint_id'] ?>"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="assets/js/jquery.dataTables.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <!-- <script src="assets/js/dataTables.bootstrap4.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/10.16.7/sweetalert2.all.min.js"></script> -->
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

    <!-- User Role Breakdown Modal -->
    <div class="modal fade" id="userBreakdownModal" tabindex="-1" aria-labelledby="userBreakdownModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="userBreakdownModalLabel">
                        <i class="fas fa-users me-2" style="color:var(--udsm-blue);"></i>User Breakdown
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3 pb-4">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="rounded-3 p-3" style="background:#eef4ff;">
                                <i class="fas fa-user-graduate fa-2x mb-2" style="color:#0062cc;"></i>
                                <h3 class="fw-bold mb-0" style="color:#0062cc;"><?= $roleCounts['student'] ?></h3>
                                <p class="small mb-0 text-muted fw-semibold">Students</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded-3 p-3" style="background:#faf5ff;">
                                <i class="fas fa-user-tag fa-2x mb-2" style="color:#7c3aed;"></i>
                                <h3 class="fw-bold mb-0" style="color:#7c3aed;"><?= $roleCounts['student_leader'] ?>
                                </h3>
                                <p class="small mb-0 text-muted fw-semibold">Student Reps</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded-3 p-3" style="background:#fff8e1;">
                                <i class="fas fa-user-tie fa-2x mb-2" style="color:#f59e0b;"></i>
                                <h3 class="fw-bold mb-0" style="color:#f59e0b;"><?= $roleCounts['staff'] ?></h3>
                                <p class="small mb-0 text-muted fw-semibold">Staff</p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="rounded-3 p-3" style="background:#f0fdf4;">
                                <i class="fas fa-user-shield fa-2x mb-2" style="color:#16a34a;"></i>
                                <h3 class="fw-bold mb-0" style="color:#16a34a;"><?= $roleCounts['admin'] ?></h3>
                                <p class="small mb-0 text-muted fw-semibold">Admins</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-center text-muted small mt-3 mb-0">
                        Total: <strong><?= $total_users ?></strong> registered users
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <a href="user_management.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Manage Users
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['login_success'])):
        unset($_SESSION['login_success']); ?>
        <!-- <style>.swal2-toast-shown .swal2-container.swal2-top-end { top: 100px; }</style> -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: <?= json_encode('Welcome back, ' . ($_SESSION['username'] ?? '') . '!') ?>,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>