<?php
require_once 'config/session.php';

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
$counts = $student->getComplaintCounts($studentId);
$total_complaints = $counts['all'];
$total_pending = $counts['pending'];
$total_inprogress = $counts['in_progress'];
$total_resolved = $counts['resolved'];
$total_rejected = $counts['rejected'];
$total_awaiting = $counts['awaiting_student_response'];
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <!-- Topbar -->
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="student_dashboard.php"><i class="fas fa-user-graduate" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="student_dashboard.php" style="color:black;">Student</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= htmlspecialchars($_SESSION['username']) ?>!</h3>
                    <p class="mb-0 opacity-75">Here's an overview of your complaints and quick actions.</p>
                </div>

                <?php if ($pendingInfoCount > 0): ?>
                    <div id="infoRequestAlert"
                        class="alert alert-warning alert-dismissible d-flex align-items-center mb-4 shadow-sm" role="alert"
                        style="border-left: 5px solid #d97706; border-radius: 10px;">
                        <i class="fas fa-exclamation-circle fa-lg me-3 text-warning flex-shrink-0"></i>
                        <a href="track_complaints.php?filter=awaiting_student_response"
                            class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center"
                            style="cursor:pointer;">
                            <div class="flex-grow-1">
                                <strong>Staff is waiting for your response on <?= $pendingInfoCount ?>
                                    complaint<?= $pendingInfoCount > 1 ? 's' : '' ?>.</strong>
                                <span class="ms-2 text-muted small">A staff member has requested more information &mdash;
                                    click to respond &rarr;</span>
                            </div>
                            <span class="badge bg-warning text-dark fs-6 me-2"><?= $pendingInfoCount ?></span>
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                    </div>
                <?php endif; ?>

                <!-- Active status counts -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-4">
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

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $total_pending ?></h2>
                                <p class="mb-0 fw-bold small">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(2,132,199,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-spinner fa-spin fa-lg" style="color:#0284c7;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= $total_inprogress ?></h2>
                                <p class="mb-0 fw-bold small">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                            style="<?= $total_awaiting > 0 ? 'background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #d97706;' : '' ?>">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(14,165,233,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user-clock fa-lg" style="color:#0ea5e9;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0ea5e9;"><?= $total_awaiting ?></h2>
                                <p class="mb-0 fw-bold small">Awaiting Response</p>
                            </div>
                        </div>
                    </div>

                    <!-- Outcome counts -->
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $total_resolved ?></h2>
                                <p class="mb-0 fw-bold small">Resolved</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div
                                style="width:48px;height:48px;border-radius:12px;background:rgba(107,114,128,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-times-circle fa-lg" style="color:#6b7280;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#6b7280;"><?= $total_rejected ?></h2>
                                <p class="mb-0 fw-bold small">Rejected</p>
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
                        <?php if ($pendingInfoCount > 0): ?>
                            <a href="track_complaints.php?filter=awaiting_student_response"
                                class="action-card action-card--amber">
                                <i class="fas fa-reply action-icon"></i>
                                <h5>Respond to Request</h5>
                                <small><?= $pendingInfoCount ?> complaint<?= $pendingInfoCount > 1 ? 's need' : ' needs' ?>
                                    your response</small>
                            </a>
                        <?php else: ?>
                            <a href="track_complaints.php?filter=pending" class="action-card action-card--amber">
                                <i class="fas fa-history action-icon"></i>
                                <h5>View Pending Issues</h5>
                                <small>Complaints awaiting review</small>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($complaints)): ?>
                    <div class="container-card shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h4 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Complaints</h4>
                            <div class="search-input"></div>
                        </div>
                        <p class="text-muted small mb-3">Your latest submissions and their current status</p>

                        <div class="table-responsive">
                            <table id="complaintsTable" class="table table-stripped">
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
                                    <?php
                                    $statusMap = [
                                        STATUS_PENDING => ['bg-warning text-dark', 'Pending'],
                                        STATUS_IN_PROGRESS => ['bg-info text-white', 'In Progress'],
                                        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
                                        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
                                        STATUS_REJECTED => ['bg-danger text-white', 'Rejected'],
                                        STATUS_REOPENED => ['bg-orange text-white', 'Reopened'],
                                    ];
                                    foreach ($complaints as $complaint_row):
                                        [$sCls, $sLabel] = $statusMap[$complaint_row['complaint_status']] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $complaint_row['complaint_status']))];
                                        ?>
                                        <tr data-href="student_complaint_details.php?id=<?= (int) $complaint_row['complaint_id'] ?>">
                                            <td>#<?= (int) $complaint_row['complaint_id'] ?></td>
                                            <td><?= htmlspecialchars($complaint_row['complaint_title']) ?></td>
                                            <td><?= htmlspecialchars($complaint_row['category_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($complaint_row['created_at'])) ?></td>
                                            <td><span class="badge <?= $sCls ?>"><?= $sLabel ?></span></td>
                                            <td>
                                                <a href="student_complaint_details.php?id=<?= (int) $complaint_row['complaint_id'] ?>"
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

                <?php else: ?>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <a href="create_complaint.php" class="action-card action-card--blue">
                                <i class="fas fa-plus-circle action-icon"></i>
                                <h5>Submit Your First Complaint</h5>
                                <small>File your first complaint with the system</small>
                            </a>
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
        document.addEventListener('DOMContentLoaded', function () {
            var userId = <?= (int) $_SESSION['user_id'] ?>;
            var count = <?= (int) $pendingInfoCount ?>;
            var key = 'scmrs_dismissed_info_req_' + userId + '_' + count;
            var alertEl = document.getElementById('infoRequestAlert');
            if (alertEl && count > 0) {
                if (localStorage.getItem(key)) {
                    alertEl.style.display = 'none';
                } else {
                    alertEl.addEventListener('closed.bs.alert', function () {
                        localStorage.setItem(key, '1');
                    });
                    var link = alertEl.querySelector('a');
                    if (link) link.addEventListener('click', function () {
                        localStorage.setItem(key, '1');
                    });
                }
            }
        });
    </script>
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

</body>

</html>