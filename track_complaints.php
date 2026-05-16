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

$db = new Database();
$conn = $db->connect();
$student = new Student($conn);

$message = $error = "";

$studentId = $student->getStudentId($userId);

// Handle Delete (POST only — safer than GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_complaint') {
    $comp_id = (int) ($_POST['complaint_id'] ?? 0);
    $reason = trim($_POST['delete_reason'] ?? '');
    if ($comp_id > 0 && $student->deleteComplaint($comp_id, $studentId, $reason)) {
        $_SESSION['message'] = "Complaint removed successfully.";
    } else {
        $_SESSION['message_error'] = "Failed to delete complaint. Only pending complaints can be deleted.";
    }
    header("Location: track_complaints.php");
    exit;
}

// Get flash messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get filtered complaints
$student_id = $studentId;
$complaints = $student->getFilteredComplaints($student_id, $filter);

// Get counts for filter tabs
$counts = $student->getComplaintCounts($student_id);

// Pending info requests count (for "action needed" highlight)
$action_needed_count = $student->getPendingInfoRequestsCount($student_id);

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

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-search-location" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Student / Track Complaints</li>
                    </ol>
                </nav>

                <!-- Alert -->
                <div aria-live="polite" aria-atomic="true"
                    class="position-fixed top-0 start-50 translate-middle-x p-3 w-100"
                    style="z-index: 1100; max-width: 800px;">
                    <?php if (!empty($message) || !empty($error)):
                        $type = !empty($message) ? 'success' : 'danger';
                        $text = !empty($message) ? $message : $error;
                        $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                        ?>
                        <div id="livetoast"
                            class="toast show align-items-center text-white bg-<?php echo $type ?> border-0 w-100"
                            role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                    <?php echo htmlspecialchars($text); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                    aria-label="Close">

                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- / Alert -->


                <!-- <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 6px solid var(--warning); padding: var(--spacing-lg); border-radius: var(--radius-lg); margin-bottom: var(--spacing-xl);">
                        <strong style="color: #92400e;"><i class="fas fa-exclamation-circle"></i> Action required</strong>
                        <p style="margin: var(--spacing-xs) 0 0 0; color: #78350f;">You have complaint(s) with a request for more information from staff. Open them and submit your response.</p>
                    </div> -->

                <div class="card sticky-top" style="z-index: 1020;">
                    <div class="card-body pb-0">
                        <ul class="nav nav-tabs custom-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=all"
                                    class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                    <i class="fas fa-list"></i> All
                                    <span class="badge btn-primary me-2"><?php echo $counts['all']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=awaiting_student_response"
                                    class="nav-link <?php echo $filter === 'awaiting_student_response' ? 'active' : ''; ?>"
                                    style="<?php echo $action_needed_count > 0 ? 'border-bottom: 2px solid var(--warning);' : ''; ?>">
                                    <i class="fas fa-user-check"></i> Awaiting your response
                                    <span
                                        class="badge btn-primary me-2"><?php echo $counts['awaiting_student_response']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=pending"
                                    class="nav-link <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                                    <i class="fas fa-clock"></i> Pending
                                    <span class="badge btn-primary me-2"><?php echo $counts['pending']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=in_progress"
                                    class="nav-link <?php echo $filter === 'in_progress' ? 'active' : ''; ?>">
                                    <i class="fas fa-spinner"></i> In Progress
                                    <span class="badge btn-primary me-2"><?php echo $counts['in_progress']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=resolved"
                                    class="nav-link <?php echo $filter === 'resolved' ? 'active' : ''; ?>">
                                    <i class="fas fa-check-circle"></i> Resolved
                                    <span class="badge btn-primary me-2"><?php echo $counts['resolved']; ?></span>
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="track_complaints.php?filter=rejected"
                                    class="nav-link <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                                    <i class="fas fa-times-circle"></i> Rejected
                                    <span class="badge btn-primary me-2"><?php echo $counts['rejected']; ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($complaints)): ?>
                    <?php foreach ($complaints as $complaint_row): ?>
                        <div
                            class="complaint-card <?php echo strtolower(str_replace('_', '-', $complaint_row['complaint_status'])); ?>">
                            <div class="complaint-header">
                                <div class="complaint-title">
                                    <span class="complaint-id">Complaint #<?php echo $complaint_row['complaint_id']; ?></span>
                                    <h3><?php echo htmlspecialchars($complaint_row['complaint_title'] ?? 'No title'); ?></h3>
                                </div>
                                <span
                                    class="badge <?php echo strtolower(str_replace('_', '-', $complaint_row['complaint_status'])); ?>">
                                    <i class="fas <?php
                                    if ($complaint_row['complaint_status'] === 'pending')
                                        echo 'fa-clock';
                                    elseif ($complaint_row['complaint_status'] === 'in_progress')
                                        echo 'fa-spinner';
                                    elseif ($complaint_row['complaint_status'] === 'awaiting_student_response')
                                        echo 'fa-user-check';
                                    elseif ($complaint_row['complaint_status'] === 'resolved')
                                        echo 'fa-check-circle';
                                    else
                                        echo 'fa-times-circle';
                                    ?>"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $complaint_row['complaint_status'])); ?>
                                </span>
                                <?php if (($complaint_row['pending_requests'] ?? 0) > 0): ?>
                                    <span
                                        style="margin-left: var(--spacing-sm); padding: 2px 8px; background: var(--warning); color: #78350f; border-radius: var(--radius); font-size: 0.75rem; font-weight: 600;">
                                        <i class="fas fa-exclamation-circle"></i> Response needed
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="complaint-description">
                                <?php echo nl2br(htmlspecialchars(substr($complaint_row['complaint_description'], 0, 200))); ?>
                                <?php if (strlen($complaint_row['complaint_description']) > 200): ?>...<?php endif; ?>
                            </div>

                            <div class="complaint-meta">
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($complaint_row['department_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($complaint_row['category_name'] ?? 'Uncategorized'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Submitted:
                                        <?php echo date('M d, Y', strtotime($complaint_row['created_at'])); ?></span>
                                </div>
                                <?php if ($complaint_row['updated_at'] && $complaint_row['updated_at'] != $complaint_row['created_at']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Updated: <?php echo date('M d, Y', strtotime($complaint_row['updated_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="complaint-actions">
                                <?php if (($complaint_row['pending_requests'] ?? 0) > 0): ?>
                                    <a href="student_complaint_details.php?id=<?php echo $complaint_row['complaint_id']; ?>#info-requests"
                                        class="btn btn-primary" style="background: gold; border-color: var(--warning);">
                                        <i class="fas fa-reply"></i> Respond
                                    </a>
                                <?php endif; ?>
                                <a href="student_complaint_details.php?id=<?php echo $complaint_row['complaint_id']; ?>"
                                    class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($complaint_row['complaint_status'] === 'pending' && empty($complaint_row['pending_requests'])): ?>
                                    <button type="button" class="btn btn-danger"
                                        onclick="confirmDelete(<?php echo $complaint_row['complaint_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Complaints Found</h3>
                        <p>
                            <?php if ($filter === 'all'): ?>
                                You haven't submitted any complaints yet. Start by creating your first complaint!
                            <?php else: ?>
                                You don't have any
                                <?php echo $filter === 'awaiting_student_response' ? 'complaints awaiting your response' : ucfirst(str_replace('_', ' ', $filter)); ?>
                                complaints at the moment.
                            <?php endif; ?>
                        </p>
                        <?php if ($filter === 'all'): ?>
                            <a href="create_complaint.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Submit Your First Complaint
                            </a>
                        <?php else: ?>
                            <a href="track_complaints.php" class="btn btn-primary">
                                <i class="fas fa-list"></i> View All Complaints
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Hidden form for POST-based delete -->
    <form id="deleteForm" method="POST" action="track_complaints.php" style="display:none;">
        <input type="hidden" name="action" value="delete_complaint">
        <input type="hidden" name="complaint_id" id="deleteComplaintId" value="">
        <input type="hidden" name="delete_reason" id="deleteReason" value="">
    </form>

    <!-- Delete Reason Modal -->
    <div class="modal fade" id="deleteReasonModal" tabindex="-1" aria-labelledby="deleteReasonModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <h5 class="modal-title fw-bold" id="deleteReasonModalLabel">
                        <i class="fas fa-trash me-2"></i>
                        Delete Complaint
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Please provide a brief reason for deleting this complaint.</p>
                    <div class="mb-0">
                        <label for="deleteReasonText" class="form-label fw-semibold">Reason <span
                                class="text-danger">*</span></label>
                        <textarea class="form-control" id="deleteReasonText" rows="3"
                            placeholder="Enter reason for deletion..." maxlength="500"></textarea>
                        <div class="invalid-feedback">Please provide a reason before deleting.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i>Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id) {
            document.getElementById('deleteComplaintId').value = id;
            document.getElementById('deleteReasonText').value = '';
            document.getElementById('deleteReasonText').classList.remove('is-invalid');
            new bootstrap.Modal(document.getElementById('deleteReasonModal')).show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            var reason = document.getElementById('deleteReasonText').value.trim();
            if (!reason) {
                document.getElementById('deleteReasonText').classList.add('is-invalid');
                return;
            }
            document.getElementById('deleteReason').value = reason;
            document.getElementById('deleteForm').submit();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hash = window.location.hash;
            const storedTab = localStorage.getItem('activeTab');
            const target = hash || storedTab;

            if (target) {
                const tabTrigger = document.querySelector(`[data-bs-target="${target}"]`);
                if (tabTrigger) {
                    const tab = new bootstrap.Tab(tabTrigger);
                    tab.show();
                }
            }

            // Store tab and update hash without scrolling
            document.querySelectorAll('.nav-link[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    const targetTab = e.target.getAttribute('data-bs-target');
                    localStorage.setItem('activeTab', targetTab);
                    history.replaceState(null, null, targetTab);
                });
            });
        });
    </script>
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

</body>

</html>