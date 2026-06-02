<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'staff') {
    header('Location: login.php');
    exit;
}

require_once 'config/Database.php';
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
$staffName = $staffDetails['username'] ?? 'Staff';
$departmentName = $staffDetails['department_name'] ?? 'Unassigned';
$staffEmail = $staffDetails['user_email'] ?? '';
$complaintCounts = $isApproved ? $staff->getStaffComplaintCounts($staffDetails['staff_id']) : [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'rejected' => 0,
];
$assignedComplaints = $isApproved ? $staff->getAssignedComplaints($staffDetails['staff_id']) : [];

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
    <title>Assigned Complaints</title>
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

    <?php $_staffSidebarRank = (int)($staffDetails['role_rank'] ?? 0); ?>
    <div class="d-flex">
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-user-tie" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Staff / Dashboard</li>
                    </ol>
                </nav>

                <?php if (!$isApproved): ?>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <div class="container-card shadow-sm p-4 text-center">
                            <i class="fas fa-hourglass-half fa-3x text-warning mb-3"></i>
                            <h4 class="mb-2">Waiting for Admin Approval</h4>
                            <p class="mb-3 text-muted">
                                Your staff account is currently pending approval by an administrator.
                                Please wait for approval before viewing assigned complaints.
                            </p>
                            <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($departmentName) ?></p>
                            <p class="mb-0"><strong>Email:</strong> <?= htmlspecialchars($staffEmail) ?></p>
                            <a href="logout.php" class="btn btn-danger btn-sm mt-3">Logout</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= $complaintCounts['total'] ?></h2>
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
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $complaintCounts['pending'] ?></h2>
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
                                <h2 class="mb-0 fw-bold" style="color:#0284c7;"><?= $complaintCounts['in_progress'] ?></h2>
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
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $complaintCounts['resolved'] ?></h2>
                                <p class="mb-0 fw-bold small">Resolved</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container-card shadow-sm">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Assigned Complaints</h4>
                    <p class="text-muted small mb-3">Complaints currently assigned to you.</p>

                    <div class="table-responsive">
                        <table class="table table-stripped" id="complaintsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-center">TITLE</th>
                                    <th class="text-center">STUDENT</th>
                                    <th class="text-center">CATEGORY</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">DATE</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($assignedComplaints)): ?>
                                    <?php foreach ($assignedComplaints as $index => $complaint): ?>
                                        <tr>
                                            <td>#<?= htmlspecialchars($complaint['complaint_id']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($complaint['complaint_title']) ?></td>
                                            <td class="text-center"><?= $complaint['is_anonymous'] ? '<em class="text-muted">Anonymous</em>' : htmlspecialchars($complaint['student_name'] ?? 'N/A') ?></td>
                                            <td class="text-center"><?= htmlspecialchars($complaint['category_name'] ?? 'N/A') ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= formatStatusBadgeClass($complaint['complaint_status']) ?>">
                                                    <?= htmlspecialchars(formatStatusLabel($complaint['complaint_status'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= htmlspecialchars(date('Y-m-d', strtotime($complaint['created_at']))) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="assigned_complaint_details.php?id=<?= urlencode($complaint['complaint_id']) ?>" class="btn btn-status btn-outline-secondary" title="View Details">
                                                        <i class="fas fa-eye text-dark"></i>
                                                    </a>
                                                    <?php if (!in_array($complaint['complaint_status'], ['resolved', 'rejected'], true)): ?>
                                                        <a href="respond_assigned_complaint.php?id=<?= urlencode($complaint['complaint_id']) ?>" class="btn btn-status btn-outline-primary" title="Respond">
                                                            <i class="fas fa-reply"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No assigned complaints found.</td>
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
        $(document).ready(function() {
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
                        initComplete: function(settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                }
            }
        });
    </script>
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