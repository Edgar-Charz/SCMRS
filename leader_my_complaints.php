<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once 'config/Database.php';
require_once 'classes/User.php';
require_once 'classes/StudentLeader.php';
require_once 'classes/Student.php';

$db      = new Database();
$conn    = $db->connect();
$leader  = new StudentLeader($conn, $userId);
$student = new Student($conn);

$studentId = $leader->getStudentId();

// Handle DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_complaint') {
    $comp_id = (int)($_POST['complaint_id'] ?? 0);
    $reason  = trim($_POST['delete_reason'] ?? '');
    if ($comp_id > 0 && $studentId && $student->deleteComplaint($comp_id, $studentId, $reason)) {
        $_SESSION['message'] = "Complaint removed successfully.";
    } else {
        $_SESSION['message_error'] = "Failed to delete complaint. Only pending complaints can be deleted.";
    }
    header("Location: leader_my_complaints.php");
    exit;
}

$message = $error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

$filter     = $_GET['filter'] ?? 'all';
$counts     = $leader->getMyComplaintCounts();
$complaints = $leader->getMyComplaints(); 

$statusMap = [
    STATUS_PENDING           => ['bg-warning text-dark',  'Pending'],
    STATUS_IN_PROGRESS       => ['bg-info text-white',    'In Progress'],
    STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
    STATUS_RESOLVED          => ['bg-success text-white', 'Resolved'],
    STATUS_REJECTED          => ['bg-danger text-white',  'Rejected'],
    STATUS_REOPENED          => ['bg-orange text-white',  'Reopened'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Rep | My Complaints</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
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
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="leader_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="leader_dashboard.php" style="color:black;">Student Rep</a></li>
                        <li class="breadcrumb-item active">My Complaints</li>
                    </ol>
                </nav>

                <!-- Filter tabs -->
                <div class="card sticky-top mb-3" style="z-index:100;">
                    <div class="card-body pb-0">
                        <ul class="nav nav-tabs custom-tabs" role="tablist">
                            <?php
                            $tabs = [
                                'all'         => ['fa-list',          'All',         $counts['total']],
                                'pending'     => ['fa-clock',         'Pending',     $counts['pending']],
                                'in_progress' => ['fa-spinner',       'In Progress', $counts['in_progress']],
                                'resolved'    => ['fa-check-circle',  'Resolved',    $counts['resolved']],
                                'rejected'    => ['fa-times-circle',  'Rejected',    $counts['rejected']],
                            ];
                            foreach ($tabs as $key => [$icon, $label, $count]):
                            ?>
                            <li class="nav-item" role="presentation">
                                <a href="leader_my_complaints.php?filter=<?= $key ?>"
                                   class="nav-link <?= $filter === $key ? 'active' : '' ?>"
                                   data-filter="<?= $key ?>">
                                    <i class="fas <?= $icon ?>"></i> <?= $label ?>
                                    <span class="badge btn-primary me-2"><?= (int)$count ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Submit button -->
                <div class="d-flex justify-content-end mb-3">
                    <a href="leader_create_complaint.php" class="btn btn-primary fw-bold"
                       style="background-color:var(--udsm-blue);border-radius:8px;">
                        <i class="fas fa-plus-circle me-1"></i>Submit New Complaint
                    </a>
                </div>

                <?php if (!empty($complaints)): ?>
                    <!-- JS empty-filter state -->
                    <div id="empty-filter-state" class="empty-state" style="display:none;">
                        <i class="fas fa-filter"></i>
                        <h3>No Complaints Found</h3>
                        <p>You don't have any complaints in this category.</p>
                        <a href="#" class="btn btn-primary" onclick="applyFilter('all');return false;">
                            <i class="fas fa-list"></i> View All
                        </a>
                    </div>

                    <?php foreach ($complaints as $c): ?>
                        <?php
                        [$sc, $sl] = $statusMap[$c['complaint_status']] ?? ['bg-secondary text-white', ucfirst($c['complaint_status'])];
                        $statusSlug = strtolower(str_replace('_', '-', $c['complaint_status']));
                        ?>
                        <div class="complaint-card <?= $statusSlug ?>"
                             data-status="<?= htmlspecialchars($c['complaint_status']) ?>">
                            <div class="complaint-header">
                                <div class="complaint-title">
                                    <span class="complaint-id">Complaint #<?= $c['complaint_id'] ?></span>
                                    <h3><?= htmlspecialchars($c['complaint_title']) ?></h3>
                                </div>
                                <span class="badge <?= $statusSlug ?>">
                                    <i class="fas <?php
                                        echo match($c['complaint_status']) {
                                            STATUS_PENDING     => 'fa-clock',
                                            STATUS_IN_PROGRESS => 'fa-spinner',
                                            STATUS_AWAITING_RESPONSE => 'fa-user-check',
                                            STATUS_RESOLVED    => 'fa-check-circle',
                                            default            => 'fa-times-circle',
                                        };
                                    ?>"></i>
                                    <?= $sl ?>
                                </span>
                            </div>

                            <div class="complaint-description">
                                <?= nl2br(htmlspecialchars(substr($c['complaint_description'], 0, 200))) ?>
                                <?= strlen($c['complaint_description']) > 200 ? '...' : '' ?>
                            </div>

                            <div class="complaint-meta">
                                <?php if ($c['department_name']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span><?= htmlspecialchars($c['department_name']) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?= htmlspecialchars($c['category_name']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Submitted: <?= date('M d, Y', strtotime($c['created_at'])) ?></span>
                                </div>
                                <?php if (!empty($c['preferred_staff_name'])): ?>
                                <div class="meta-item">
                                    <i class="fas fa-user-tie"></i>
                                    <span>Suggested staff: <?= htmlspecialchars($c['preferred_staff_name']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="complaint-actions">
                                <?php if ($c['complaint_status'] === STATUS_AWAITING_RESPONSE): ?>
                                    <a href="student_complaint_details.php?id=<?= $c['complaint_id'] ?>&from=leader#info-requests"
                                       class="btn btn-primary" style="background:gold;border-color:var(--warning);">
                                        <i class="fas fa-reply"></i> Respond
                                    </a>
                                <?php endif; ?>
                                <a href="student_complaint_details.php?id=<?= $c['complaint_id'] ?>&from=leader"
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($c['complaint_status'] === STATUS_PENDING): ?>
                                    <a href="edit_student_complaint.php?id=<?= $c['complaint_id'] ?>"
                                       class="btn btn-warning text-dark fw-bold">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-danger"
                                            onclick="confirmDelete(<?= $c['complaint_id'] ?>)">
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
                        <p>You haven't submitted any complaints yet.</p>
                        <a href="leader_create_complaint.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Submit Your First Complaint
                        </a>
                    </div>
                <?php endif; ?>

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" action="leader_my_complaints.php" style="display:none;">
        <input type="hidden" name="action" value="delete_complaint">
        <input type="hidden" name="complaint_id" id="deleteComplaintId" value="">
        <input type="hidden" name="delete_reason" id="deleteReason" value="">
    </form>

    <!-- Delete Reason Modal -->
    <div class="modal fade" id="deleteReasonModal" tabindex="-1" aria-labelledby="deleteReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#dc3545,#c82333);">
                    <h5 class="modal-title fw-bold text-white" id="deleteReasonModalLabel">
                        <i class="fas fa-trash me-2"></i>Delete Complaint
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Please provide a brief reason for deleting this complaint.</p>
                    <div class="mb-0">
                        <label for="deleteReasonText" class="form-label fw-semibold">
                            Reason <span class="text-danger">*</span>
                        </label>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
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

        function applyFilter(filter) {
            var cards = document.querySelectorAll('.complaint-card[data-status]');
            var visible = 0;

            cards.forEach(function(card) {
                var show = (filter === 'all') || (card.dataset.status === filter);
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            var emptyState = document.getElementById('empty-filter-state');
            if (emptyState) emptyState.style.display = (visible === 0) ? '' : 'none';

            document.querySelectorAll('.nav-link[data-filter]').forEach(function(tab) {
                tab.classList.toggle('active', tab.dataset.filter === filter);
            });

            var url = new URL(window.location.href);
            if (filter === 'all') {
                url.searchParams.delete('filter');
            } else {
                url.searchParams.set('filter', filter);
            }
            history.pushState({ filter: filter }, '', url.toString());
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.nav-link[data-filter]').forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    applyFilter(this.dataset.filter);
                });
            });

            window.addEventListener('popstate', function(e) {
                applyFilter((e.state && e.state.filter) || 'all');
            });

            var urlFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
            applyFilter(urlFilter);
        });
    </script>
</body>
</html>
