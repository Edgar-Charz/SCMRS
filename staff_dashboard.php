<?php
session_start();

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
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'rejected' => 0,
];
$recentComplaints = $isApproved ? $staff->getRecentAssignedComplaints($staffDetails['staff_id']) : [];

function formatStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'pending':
            return 'bg-danger';
        case 'in_progress':
        case 'in progress':
            return 'bg-warning';
        case 'resolved':
            return 'bg-success';
        case 'rejected':
            return 'bg-secondary';
        case 'awaiting_student_response':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
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
    <title>Staff Dashboard</title>
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
                    <p class="mb-0 small fw-bold"><?= htmlspecialchars($staffName) ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li class="active">
                    <a href="staff_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="assigned_complaints.php" title="Assigned Complaints">
                        <i class="fas fa-comment-dots me-2"></i>
                        <span class="link-text">Assigned Complaints</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div id="content" class="w-100">

            <!-- Topbar -->
            <nav class="navbar navbar-expand-lg navbar-dark custom-nav">
                <button id="sidebarCollapse" class="btn btn-dark ms-2">
                    <i class="fas fa-list"></i>
                </button>

                <div class="container-fluid">
                    <div class="dropdown ms-auto">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                            id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user"></i>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2"
                            aria-labelledby="profileDropdown">
                            <li>
                                <h6 class="dropdown-header">User Settings</h6>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="profile.php">
                                    <i class="fas fa-user me-2"></i> View Profile
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-user-tie" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Staff / Dashboard</li>
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

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-folder-open fa-2x"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?= $complaintCounts['total'] ?></h2>
                                    <p class="mb-0 fw-bold small">Total Complaints</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-clock fa-2x"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?= $complaintCounts['pending'] ?></h2>
                                    <p class="mb-0 fw-bold">Pending</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-spinner fa-spin fa-2x" style="color: black;"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?= $complaintCounts['in_progress'] ?></h2>
                                    <p class="mb-0 fw-bold">In Progress</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-check-circle fa-2x"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?= $complaintCounts['resolved'] ?></h2>
                                    <p class="mb-0 fw-bold">Resolved</p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="action-card text-center shadow-sm">
                                <a href="assigned_complaints.php">
                                    <i class="fas fa-folder-open action-icon text-center"></i>
                                    <h5>View Assigned Complaint</h5>
                                    <p class="text-muted small mb-0">View all complaints assigned to you</p>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="container-card shadow-sm">
                        <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Assigned Complaints</h4>
                        <p class="text-muted small mb-3">Your latest complaint assignments</p>

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
                                    <?php if (!empty($recentComplaints)): ?>
                                        <?php foreach ($recentComplaints as $complaint): ?>
                                            <tr>
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
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No recent assigned complaints found.</td>
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

    <?php if (!empty($_SESSION['login_success'])): unset($_SESSION['login_success']); ?>
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