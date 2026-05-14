<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($complaintId <= 0) {
    header("Location: track_complaints.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Student.php";

$db      = new Database();
$conn    = $db->connect();
$student = new Student($conn);

$studentId = $student->getStudentId($userId);

// Ownership check — load complaint and verify it belongs to this student
$complaint_details = $student->readStudentComplaint($complaintId);
if (!$complaint_details || (int)$complaint_details['student_id'] !== (int)$studentId) {
    header("Location: track_complaints.php");
    exit;
}

$message = $error = "";

// ── Handle: respond to an information request ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_info_request') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $response  = trim($_POST['response'] ?? '');

    if ($requestId <= 0 || empty($response)) {
        $error = "Response text is required.";
    } else {
        try {
            $student->respondToInfoRequest($requestId, $complaintId, $studentId, $response);
            $_SESSION['message'] = "Your response has been submitted.";
            header("Location: student_complaint_details.php?id=$complaintId#info-requests");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// ── Handle: submit feedback ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $rating       = (int)($_POST['rating'] ?? 0);
    $feedbackText = trim($_POST['feedback_text'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5.";
    } elseif (empty($feedbackText)) {
        $error = "Feedback text is required.";
    } else {
        try {
            $student->submitFeedback($complaintId, $studentId, $rating, $feedbackText);
            $_SESSION['message'] = "Thank you for your feedback!";
            header("Location: student_complaint_details.php?id=$complaintId");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get session flash
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Reload fresh data after any POST
$complaint_details    = $student->readStudentComplaint($complaintId);
$complaint_history    = $student->readStudentComplaintHistory($complaintId);
$complaint_attachment = $student->readStudentComplaintAttachments($complaintId);
$complaint_info_req   = $student->readStudentComplaintInfoRequests($complaintId);
$existing_feedback    = $student->getComplaintFeedback($complaintId, $studentId);

$has_pending_request = false;
foreach ($complaint_info_req as $req) {
    if ($req['status'] === 'pending') {
        $has_pending_request = true;
        break;
    }
}

function statusBadge($status): string
{
    $map = [
        'pending'                   => ['bg-warning text-dark', 'Pending'],
        'in_progress'               => ['bg-info text-white',   'In Progress'],
        'awaiting_student_response' => ['bg-primary text-white','Awaiting Your Response'],
        'resolved'                  => ['bg-success text-white','Resolved'],
        'rejected'                  => ['bg-danger text-white', 'Rejected'],
    ];
    [$cls, $label] = $map[$status] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $status))];
    return "<span class=\"badge $cls\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint #<?= $complaintId ?> | Student</title>
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
                        style="width:45px;height:45px;object-fit:cover;border:2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold">UDSM</h6>
                    <small class="text-warning" style="font-size:.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0"><i class="fas fa-user me-2"></i></div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold"><?= strtoupper($_SESSION['user_role']) ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="student_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i><span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_complaint.php" title="Submit Complaint">
                        <i class="fas fa-paper-plane me-2"></i><span class="link-text">Submit Complaint</span>
                    </a>
                </li>
                <li class="active">
                    <a href="track_complaints.php" title="Track Complaints">
                        <i class="fas fa-search-location me-2"></i><span class="link-text">Track Complaints</span>
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

            <!-- Toast -->
            <div aria-live="polite" aria-atomic="true"
                class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:1100;">
                <?php if (!empty($message)): ?>
                    <div class="toast show align-items-center text-white bg-success border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="toast show align-items-center text-white bg-danger border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4">

                <nav aria-label="breadcrumb"
                    class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="student_dashboard.php">
                                <i class="fas fa-home" style="color:black;"></i>
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="track_complaints.php" style="color:black;">Track Complaints</a>
                        </li>
                        <li class="breadcrumb-item active">Complaint #<?= $complaintId ?></li>
                    </ol>
                    <?php if ($has_pending_request): ?>
                        <a href="#info-requests" class="btn btn-warning btn-sm fw-bold">
                            <i class="fas fa-exclamation-circle me-1"></i> Response Required
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- ── Complaint Details ──────────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <div class="mb-3 pb-2" style="border-bottom:2px solid #e9ecef;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-file-invoice me-2"></i>Complaint #<?= $complaintId ?>
                            </h4>
                            <?= statusBadge($complaint_details['complaint_status']) ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Title:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint_details['complaint_title']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint_details['category_name']) ?></div>
                    </div>

                    <?php if ($complaint_details['department_name']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Department:</div>
                            <div class="detail-value"><?= htmlspecialchars($complaint_details['department_name']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Priority:</div>
                        <div class="detail-value">
                            <?php
                            $pBadge = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-success'];
                            $pClass = $pBadge[$complaint_details['priority']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $pClass ?>"><?= ucfirst($complaint_details['priority']) ?></span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Submitted:</div>
                        <div class="detail-value"><?= date('F d, Y \a\t g:i A', strtotime($complaint_details['created_at'])) ?></div>
                    </div>

                    <?php if ($complaint_details['routed_at']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Assigned:</div>
                            <div class="detail-value"><?= date('F d, Y \a\t g:i A', strtotime($complaint_details['routed_at'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($complaint_details['updated_at'] && $complaint_details['updated_at'] !== $complaint_details['created_at']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Last Updated:</div>
                            <div class="detail-value"><?= date('F d, Y \a\t g:i A', strtotime($complaint_details['updated_at'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <div class="detail-label fw-bold mb-1">Description:</div>
                        <div class="p-3 bg-light rounded border">
                            <?= htmlspecialchars($complaint_details['complaint_description']) ?>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <?php if (!empty($complaint_attachment)): ?>
                        <div class="mb-2">
                            <div class="detail-label fw-bold mb-2">
                                <i class="fas fa-paperclip me-1"></i>Attachments (<?= count($complaint_attachment) ?>):
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;">
                                <?php foreach ($complaint_attachment as $att): ?>
                                    <?php $isPdf = $att['file_type'] === 'application/pdf'; ?>
                                    <a href="download_attachment.php?id=<?= $att['attachment_id'] ?>&view=1"
                                        target="_blank"
                                        class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-dark"
                                        style="background:#fff;transition:box-shadow .2s;"
                                        onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.12)'"
                                        onmouseout="this.style.boxShadow='none'">
                                        <i class="fas <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-success' ?> fa-lg flex-shrink-0"></i>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold text-truncate small"
                                                title="<?= htmlspecialchars($att['file_name']) ?>">
                                                <?= htmlspecialchars($att['file_name']) ?>
                                            </div>
                                            <div class="text-muted" style="font-size:.75rem;">
                                                <?= date('d M Y', strtotime($att['uploaded_at'])) ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-download text-secondary flex-shrink-0"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Official response -->
                    <?php if (!empty($complaint_details['complaint_response'])): ?>
                        <div class="mt-3">
                            <div class="detail-label fw-bold mb-1">
                                <i class="fas fa-reply me-1"></i>Official Response:
                            </div>
                            <div class="p-3 rounded" style="background:#f0fdf4;border-left:4px solid #22c55e;">
                                <?= htmlspecialchars($complaint_details['complaint_response']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ── Information Requests ──────────────────────── -->
                <?php if (!empty($complaint_info_req)): ?>
                    <div class="container-card shadow-sm mb-4" id="info-requests">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-question-circle me-2"></i>Information Requests
                        </h5>

                        <?php foreach ($complaint_info_req as $req): ?>
                            <div class="mb-3 p-3 rounded"
                                style="background:#fafafa;border-left:4px solid <?= $req['status'] === 'pending' ? '#f59e0b' : '#22c55e' ?>;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong><?= htmlspecialchars($req['username']) ?> asked:</strong>
                                    <span class="badge <?= $req['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-success' ?>">
                                        <?= ucfirst($req['status']) ?>
                                    </span>
                                </div>
                                <p class="mb-2"><?= nl2br(htmlspecialchars($req['request_message'])) ?></p>

                                <?php if (!empty($req['student_response'])): ?>
                                    <div class="mt-2 p-2 bg-white rounded border">
                                        <strong>Your response:</strong>
                                        <div class="mt-1 text-muted"><?= nl2br(htmlspecialchars($req['student_response'])) ?></div>
                                    </div>
                                <?php elseif ($req['status'] === 'pending'): ?>
                                    <form method="POST"
                                        action="student_complaint_details.php?id=<?= $complaintId ?>#info-requests">
                                        <input type="hidden" name="action" value="respond_info_request">
                                        <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                        <div class="mb-2 mt-2">
                                            <label class="form-label fw-bold small">
                                                Your Response <span class="text-danger">*</span>
                                            </label>
                                            <textarea name="response" class="form-control" rows="3"
                                                placeholder="Type your response here..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold">
                                            <i class="fas fa-paper-plane me-1"></i> Submit Response
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <small class="text-muted d-block mt-2">
                                    Requested: <?= date('M d, Y g:i A', strtotime($req['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- ── Feedback ──────────────────────────────────── -->
                <?php if ($complaint_details['complaint_status'] === 'resolved'): ?>
                    <div class="container-card shadow-sm mb-4">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-star me-2"></i>Rate This Resolution
                        </h5>

                        <?php if ($existing_feedback): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                You rated this complaint <strong><?= $existing_feedback['rating'] ?>/5</strong>.
                                <?php if ($existing_feedback['feedback_text']): ?>
                                    <br><em>"<?= htmlspecialchars($existing_feedback['feedback_text']) ?>"</em>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="student_complaint_details.php?id=<?= $complaintId ?>">
                                <input type="hidden" name="action" value="submit_feedback">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        Rating <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex gap-2" id="starRating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label class="star-label" style="cursor:pointer;font-size:2rem;color:#d1d5db;">
                                                <input type="radio" name="rating" value="<?= $i ?>"
                                                    style="display:none;" required>
                                                <i class="fas fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        Comment <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="feedback_text" class="form-control p-3" rows="3"
                                        style="border-radius:10px;border:1px solid #e0e6ed;"
                                        placeholder="Share your experience with the resolution..."
                                        required></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary p-3 fw-bold w-100"
                                    style="border-radius:10px;">
                                    <i class="fas fa-paper-plane me-1"></i> Submit Feedback
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- ── Complaint Timeline ────────────────────────── -->
                <?php if (!empty($complaint_history)): ?>
                    <div class="container-card shadow-sm mb-4">
                        <h5 class="fw-bold mb-3">
                            <i class="fas fa-history me-2"></i>Complaint Timeline
                        </h5>
                        <?php foreach ($complaint_history as $entry): ?>
                            <div class="history-item">
                                <div class="history-header">
                                    <span class="history-action">
                                        <?= ucfirst(str_replace('_', ' ', $entry['action'])) ?>
                                    </span>
                                    <span class="history-date">
                                        <?= date('M d, Y g:i A', strtotime($entry['changed_at'])) ?>
                                    </span>
                                </div>
                                <div style="color:#666;font-size:.9rem;">
                                    <strong>By:</strong> <?= htmlspecialchars($entry['username'] ?? 'System') ?><br>
                                    <?php if ($entry['old_status'] && $entry['new_status']): ?>
                                        <strong>Status:</strong>
                                        <?= ucfirst(str_replace('_', ' ', $entry['old_status'])) ?>
                                        → <?= ucfirst(str_replace('_', ' ', $entry['new_status'])) ?><br>
                                    <?php endif; ?>
                                    <?php if ($entry['remarks']): ?>
                                        <strong>Notes:</strong> <?= htmlspecialchars($entry['remarks']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        // Star rating highlight
        document.querySelectorAll('#starRating .star-label').forEach(function (label, idx, all) {
            var input = label.querySelector('input');
            label.addEventListener('mouseover', function () {
                all.forEach(function (l, i) {
                    l.style.color = i <= idx ? '#fbbf24' : '#d1d5db';
                });
            });
            label.addEventListener('mouseout', function () {
                var checked = document.querySelector('#starRating input:checked');
                var checkedVal = checked ? parseInt(checked.value) - 1 : -1;
                all.forEach(function (l, i) {
                    l.style.color = i <= checkedVal ? '#fbbf24' : '#d1d5db';
                });
            });
            input.addEventListener('change', function () {
                all.forEach(function (l, i) {
                    l.style.color = i <= idx ? '#fbbf24' : '#d1d5db';
                });
            });
        });
    </script>

</body>
</html>
