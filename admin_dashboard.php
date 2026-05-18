<?php
session_start();

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
$complaints = $admin->getComplaints();
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
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
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

        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <div class="logo-container me-2">
                    <img src="assets/img/logo.png" alt="UDSM Logo" class="img-fluid rounded circle"
                        Style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold"> UDSM</h6>
                    <small class="text-warning" style="font-size: 0.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user me-2"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold">ADMIN</p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="admin_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Overview</span>
                    </a>
                </li>
                <li>
                    <a href="manage_complaints.php" title="Manage Complaints">
                        <i class="fas fa-file-invoice me-2"></i>
                        <span class="link-text">Student Complaints</span>
                    </a>
                </li>
                <li>
                    <a href="user_management.php">
                        <i class="fas fa-user-shield me-2"></i>
                        <span class="link-text">User Management</span>
                    </a>
                </li>
                <!-- <li>
                    <a href="staff_approval.php">
                        <i class="fas fa-check-circle me-2"></i>
                        <span class="link-text">Staff Approval</span>
                    </a>
                </li> -->
                <li>
                    <a href="manage_departments.php" title="Departments">
                        <i class="fas fa-sitemap me-2"></i>
                        <span class="link-text">Departments</span>
                    </a>
                </li>
                <li>
                    <a href="manage_categories.php" title="Categories">
                        <i class="fas fa-tags me-2"></i>
                        <span class="link-text">Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" title="Reports">
                        <i class="fas fa-file-contract me-2"></i>
                        <span class="link-text">Reports</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" title="Sign Out">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span class="link-text">Sign Out</span>
                </a>
            </div>
        </nav>

        <div id="content" class="w-100">

            <!-- Topbar -->
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-user-shield" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Admin / Dashboard </li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= $_SESSION['username']; ?>!</h3>
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

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                            style="cursor:pointer; position:relative;" data-bs-toggle="modal"
                            data-bs-target="#userBreakdownModal">
                            <i class="fas fa-info-circle"
                                style="position:absolute; top:6px; right:8px; font-size:0.7rem; opacity:0.5;"></i>
                            <i class="fas fa-users fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_users; ?> </h2>
                                <p class="mb-0 fw-bold">Total Users</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-sitemap fa-2x" style="color: black;"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_departments; ?> </h2>
                                <p class="mb-0 fw-bold">Departments</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-tags fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_categories; ?> </h2>
                                <p class="mb-0 fw-bold">Categories</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-folder-open fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_complaints; ?> </h2>
                                <p class="mb-0 fw-bold small">Total Complaints</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-clock fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_pending; ?> </h2>
                                <p class="mb-0 fw-bold">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-spinner fa-spin fa-2x" style="color: black;"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_inprogress; ?> </h2>
                                <p class="mb-0 fw-bold">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_resolved; ?> </h2>
                                <p class="mb-0 fw-bold">Resolved</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-times-circle fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"> <?= $total_rejected; ?> </h2>
                                <p class="mb-0 fw-bold">Rejected</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container-card shadow-sm">
                    <h4 class="mb-4 fw-bold"><i class="fas fa-chart-line me-2"></i>Quick Actions</h4>
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">

                            <div class="action-card text-center shadow-sm">
                                <a href="manage_complaints.php">
                                    <i class="fas fa-envelope-open action-icon"></i>
                                    <h5>Review Complaints</h5>
                                </a>
                            </div>

                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="action-card text-center shadow-sm">
                                <a href="manage_departments.php">
                                    <i class="fas fa-sitemap action-icon"></i>
                                    <h5>Manage Departments</h5>
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="action-card text-center shadow-sm">
                                <a href="manage_categories.php">
                                    <i class="fas fa-tags action-icon"></i>
                                    <h5>Manage Categories</h5>
                                </a>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="action-card text-center shadow-sm">
                                <a href="reports.php">
                                    <i class="fas fa-chart-bar action-icon"></i>
                                    <h5>Reports</h5>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($complaints)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Complaints</h4>
                        <p class="text-muted small mb-3">Recent added complaints</p>

                        <div class="table-responsive">
                            <table id="complaintsTable" class="table table-stripped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>TITLE</th>
                                        <th>CATEGORY</th>
                                        <th>DATE</th>
                                        <th>STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $count = 1;
                                    foreach ($complaints as $complaint_row): ?>
                                        <tr>
                                            <td><?= $count++; ?></td>
                                            <td><?= $complaint_row['complaint_title']; ?></td>
                                            <td><?= $complaint_row['category_name']; ?></td>
                                            <td><?= date('M d, Y', strtotime($complaint_row['created_at'])); ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-<?= $complaint_row['complaint_status']; ?>"><?= ucfirst($complaint_row['complaint_status']); ?></span>
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

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
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
                        <div class="col-4">
                            <div class="rounded-3 p-3" style="background:#eef4ff;">
                                <i class="fas fa-user-graduate fa-2x mb-2" style="color:#0062cc;"></i>
                                <h3 class="fw-bold mb-0" style="color:#0062cc;"><?= $roleCounts['student'] ?></h3>
                                <p class="small mb-0 text-muted fw-semibold">Students</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="rounded-3 p-3" style="background:#fff8e1;">
                                <i class="fas fa-user-tie fa-2x mb-2" style="color:#f59e0b;"></i>
                                <h3 class="fw-bold mb-0" style="color:#f59e0b;"><?= $roleCounts['staff'] ?></h3>
                                <p class="small mb-0 text-muted fw-semibold">Staff</p>
                            </div>
                        </div>
                        <div class="col-4">
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
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>