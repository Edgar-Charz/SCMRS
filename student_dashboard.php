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
        <div class="loader-content">
            <img src="assets/img/logo.png" alt="UDSM" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <div class="d-flex">
        <?php require_once 'includes/sidebar.php'; ?>

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


                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= $total_complaints; ?></h2>
                                <p class="mb-0 fw-bold small">Total Complaints</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $total_pending; ?></h2>
                                <p class="mb-0 fw-bold small">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(2,132,199,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-spinner fa-spin fa-lg" style="color:#0284c7;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= $total_inprogress; ?></h2>
                                <p class="mb-0 fw-bold small">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $total_resolved; ?></h2>
                                <p class="mb-0 fw-bold small">Resolved</p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <a href="create_complaint.php" class="action-card action-card--blue">
                            <i class="fas fa-file-signature action-icon"></i>
                            <h5>Submit New Complaint</h5>
                            <small>File a new complaint with the system</small>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a href="track_complaints.php" class="action-card action-card--teal">
                            <i class="fas fa-search-location action-icon"></i>
                            <h5>Track Complaints</h5>
                            <small>Monitor the status of your submissions</small>
                        </a>
                    </div>
                    <div class="col-12 col-md-4">
                        <a href="track_complaints.php?filter=pending" class="action-card action-card--amber">
                            <i class="fas fa-history action-icon"></i>
                            <h5>View Pending Issues</h5>
                            <small>Complaints awaiting review</small>
                        </a>
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
                                                <?php
                                                $statusMap = [
                                                    'pending'                   => ['bg-warning text-dark', 'Pending'],
                                                    'in_progress'               => ['bg-info text-white',    'In Progress'],
                                                    'awaiting_student_response' => ['bg-primary text-white', 'Awaiting Response'],
                                                    'resolved'                  => ['bg-success text-white', 'Resolved'],
                                                    'rejected'                  => ['bg-danger text-white',  'Rejected'],
                                                    'reopened'                  => ['bg-orange text-white',  'Reopened'],
                                                ];
                                                [$sCls, $sLabel] = $statusMap[$complaint_row['complaint_status']] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $complaint_row['complaint_status']))];
                                                ?>
                                                <span class="badge <?= $sCls ?>"><?= $sLabel ?></span>
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
                            <a href="create_complaint.php" class="action-card action-card--blue">
                                <i class="fas fa-plus-circle action-icon"></i>
                                <h5>Submit Your First Complaint</h5>
                                <small>File your first complaint with the system</small>
                            </a>
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
                    timer: 3000,
                    timerProgressBar: true,
                });
            });
        </script>
    <?php endif; ?>

</body>

</html>