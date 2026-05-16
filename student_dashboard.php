<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
} else {
    $userId = $_SESSION['user_id'];
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Student.php";

$message = $error = "";

$db = new Database();
$conn = $db->connect();
$student = new Student($conn);

$studentId = $student->getStudentId($userId);
$total_complaints = $student->getTotalComplaints($studentId);
$total_pending = $student->getTotalPending($studentId);
$total_inprogress = $student->getTotalInprogress($studentId);
$total_resolved = $student->getTotalResolved($studentId);
$pendingInfoCount = $student->getPendingInfoRequestsCount($studentId);
$complaints = $student->getStudentComplaints($studentId);

// Get message from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
                    <p class="mb-0 small fw-bold">
                        <?= strtoupper($_SESSION['user_role']); ?>
                    </p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="student_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_complaint.php" title="Submit Complaint">
                        <i class="fas fa-paper-plane me-2"></i>
                        <span class="link-text">Submit Complaint</span>
                    </a>
                </li>
                <li>
                    <a href="track_complaints.php" title="Track Complaints">
                        <i class="fas fa-search-location me-2"></i>
                        <span class="link-text">Track Complaints</span>
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
                            <a href="#"><i class="fas fa-user-graduate" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Student / Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= $_SESSION['username']; ?>!</h3>
                    <p class="mb-0 opacity-75">Here's an overview of your complaints and quick actions.</p>
                </div>

                <?php if ($pendingInfoCount > 0): ?>
                    <a href="track_complaints.php?filter=awaiting_student_response" class="text-decoration-none">
                        <div class="alert alert-warning d-flex align-items-center mb-3 shadow-sm" role="alert"
                            style="border-left: 5px solid #f59e0b; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-exclamation-circle fa-lg me-3 text-warning"></i>
                            <div class="flex-grow-1">
                                <strong><?= $pendingInfoCount ?>
                                    complaint<?= $pendingInfoCount > 1 ? 's require' : ' requires' ?> your
                                    response.</strong>
                                <span class="ms-2 text-muted small">A staff member has requested more information &mdash;
                                    click to respond &rarr;</span>
                            </div>
                            <span class="badge bg-warning text-dark fs-6"><?= $pendingInfoCount ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <?php if ($total_resolved > 0): ?>
                    <a href="track_complaints.php?filter=resolved" class="text-decoration-none">
                        <div class="alert alert-success d-flex align-items-center mb-4 shadow-sm" role="alert"
                            style="border-left: 5px solid #10b981; border-radius: 10px; cursor: pointer;">
                            <i class="fas fa-check-circle fa-lg me-3 text-success"></i>
                            <div class="flex-grow-1">
                                <strong><?= $total_resolved ?> complaint<?= $total_resolved > 1 ? 's have' : ' has' ?> been
                                    resolved.</strong>
                                <span class="ms-2 text-muted small">Click to view resolutions &rarr;</span>
                            </div>
                            <span class="badge bg-success fs-6"><?= $total_resolved ?></span>
                        </div>
                    </a>
                <?php endif; ?>

                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-folder-open fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0">
                                    <?= $total_complaints; ?>
                                </h2>
                                <p class="mb-0 fw-bold small">Total Complaints</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-clock fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0">
                                    <?= $total_pending; ?>
                                </h2>
                                <p class="mb-0 fw-bold">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-spinner fa-spin fa-2x" style="color: black;"></i>
                            <div class="text-end">
                                <h2 class="mb-0">
                                    <?= $total_inprogress; ?>
                                </h2>
                                <p class="mb-0 fw-bold">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-check-circle fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0">
                                    <?= $total_resolved; ?>
                                </h2>
                                <p class="mb-0 fw-bold">Resolved</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="action-card text-center shadow-sm">
                            <a href="create_complaint.php">
                                <i class="fas fa-file-signature action-icon"></i>
                                <h5>Submit New Complaint</h5>
                                <p class="text-muted small mb-0">File new complaint with the system</p>
                            </a>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="action-card text-center shadow-sm">
                            <a href="track_complaints.php">
                                <i class="fas fa-search-location action-icon"></i>
                                <h5>Track Complaints</h5>
                                <p class="text-muted small mb-0">Monitor status of your submission</p>
                            </a>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="action-card text-center shadow-sm">
                            <a href="track_complaints.php?filter=pending">
                                <i class="fas fa-history action-icon"></i>
                                <h5>View Pending Issues</h5>
                                <p class="text-muted small mb-0">Complaints awaiting review</p>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($complaints)): ?>
                    <!-- Recent Complaints -->
                    <div class="container-card shadow-sm">
                        <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Complaints</h4>
                        <p class="text-muted small mb-3">Your latest submissions and their current status</p>

                        <div class="table-responsive">
                            <table id="complaintsTable" class="table table-stripped">
                                <thead class="table-light">
                                    <tr>
                                        <th>S/N</th>
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
                    <!-- / Recent complaints -->

                <?php else: ?>
                    <!-- Submit first complaint -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="action-card text-center shadow-sm">
                                <a href="create_complaint.php">
                                    <i class="fas fa-plus-circle action-icon"></i>
                                    <h5>Submit Your First Complaint</h5>
                                    <p class="text-muted small mb-0">File your first complaint with the system</p>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- / Submit first complaint -->
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
                    timer: 6000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>