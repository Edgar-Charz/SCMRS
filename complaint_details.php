<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['user_id'];

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";
require_once "classes/StudentLeader.php";
require_once "includes/csrf.php";

$db    = new Database();
$conn  = $db->connect();
$admin = new Admin($conn);

$message = $error = "";

$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($complaintId <= 0) {
    header("Location: manage_complaints.php");
    exit;
}

$complaint = $admin->getComplaintById($complaintId);
if (!$complaint) {
    header("Location: manage_complaints.php");
    exit;
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'respond') {
        csrf_verify();
        $responseAction = $_POST['response_action'] ?? '';
        $responseText   = trim($_POST['response'] ?? '');
        if (empty($responseText)) {
            $error = "A response is required before submitting.";
        } elseif (!in_array($responseAction, ['resolve', 'reject'], true)) {
            $error = "Invalid action.";
        } else {
            $newStatus = ($responseAction === 'resolve') ? 'resolved' : 'rejected';
            try {
                $admin->respondComplaint($complaintId, $responseText, $newStatus);
                $label = ($responseAction === 'resolve') ? 'resolved' : 'rejected';
                $admin->logActivity($adminId, 'complaint_' . $label, 'complaint', $complaintId, "Complaint #$complaintId", "Admin $label the complaint.");
                $_SESSION['message'] = "Complaint #$complaintId has been $label.";
                header("Location: complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'add_note') {
        csrf_verify();
        $noteText = trim($_POST['note_text'] ?? '');
        if (empty($noteText)) {
            $error = "Note cannot be empty.";
        } elseif ($admin->addCollaborationNote($complaintId, $adminId, $noteText)) {
            $admin->logActivity($adminId, 'note_added', 'complaint', $complaintId, "Complaint #$complaintId", 'Admin added an internal note');
            $_SESSION['message'] = "Note added.";
            header("Location: complaint_details.php?id=$complaintId");
            exit;
        } else {
            $error = "Failed to add note.";
        }
    } elseif ($action === 'request_info') {
        csrf_verify();
        $requestMsg = trim($_POST['request_message'] ?? '');
        if (empty($requestMsg)) {
            $error = "Request message cannot be empty.";
        } else {
            try {
                $admin->requestInformation($complaintId, $adminId, $requestMsg);
                $admin->logActivity($adminId, 'info_requested', 'complaint', $complaintId, "Complaint #$complaintId", 'Admin requested additional information from student');
                $_SESSION['message'] = "Information requested from student.";
                header("Location: complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$attachments  = $admin->getComplaintAttachments($complaintId);
$notes        = $admin->getCollaborationNotes($complaintId);
$leaderNotes  = array_filter(
    (new StudentLeader($conn))->getEndorsementsForComplaint($complaintId),
    fn($e) => !empty($e['note'])
);
$infoRequests = $admin->getInformationRequests($complaintId);
$statusLogs   = $admin->getComplaintStatusLogs($complaintId);
$feedback     = $admin->getComplaintFeedback($complaintId);

$isClosed    = in_array($complaint['complaint_status'], [STATUS_RESOLVED, STATUS_REJECTED], true);
$isAssigned  = !empty($complaint['assigned_staff_name']);
$studentName = htmlspecialchars($complaint['student_name']);
$anonymousBadge = $complaint['is_anonymous']
    ? ' <span class="badge ms-1" style="background:#111;color:#fff;font-size:.7rem;vertical-align:middle;">Anonymous</span>'
    : '';

function statusBadge($status)
{
    $map = [
        STATUS_PENDING => ['bg-warning text-dark',  'Pending'],
        STATUS_IN_PROGRESS => ['bg-info text-white',    'In Progress'],
        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
        STATUS_REJECTED => ['bg-danger text-white',  'Rejected'],
        STATUS_REOPENED => ['bg-warning text-white',  'Reopened'],
        'on_hold'       => ['bg-secondary text-white', 'On Hold'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $status))];
    return "<span class=\"badge $class\">$label</span>";
}

function priorityBadge($priority)
{
    $map = ['low' => 'bg-success', 'medium' => 'bg-warning text-dark', 'high' => 'bg-danger'];
    $class = $map[$priority] ?? 'bg-secondary';
    return "<span class=\"badge $class\">" . ucfirst($priority) . "</span>";
}

$irStatusMap = [
    STATUS_PENDING => ['bg-warning text-dark', 'Pending'],
    'responded' => ['bg-success text-white', 'Responded'],
    'closed'    => ['bg-secondary text-white', 'Closed'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint #<?= $complaintId ?> | Admin</title>
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

            <?php require_once 'includes/topbar.php'; ?>

            <!-- Toast Notifications -->
            <div aria-live="polite" aria-atomic="true"
                class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1100; pointer-events: none;">
                <?php if (!empty($message) || !empty($error)):
                    $type = !empty($message) ? 'success' : 'danger';
                    $text = !empty($message) ? $message : $error;
                    $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                ?>
                    <div class="toast show align-items-center text-white bg-<?= $type ?> border-0"
                        role="alert" aria-live="assertive" aria-atomic="true" style="pointer-events: auto;">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas <?= $icon ?> me-2"></i>
                                <?= htmlspecialchars($text) ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4">

                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-file-invoice" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" style="color:black;">Admin</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="manage_complaints.php" style="color: black;">Manage Complaints</a>
                        </li>
                        <li class="breadcrumb-item active">Complaint #<?= $complaintId ?></li>
                    </ol>
                </nav>

                <!-- Complaint Details -->
                <div class="container-card shadow-sm">
                    <div class="mb-3" style="border-bottom: 2px solid #e9ecef;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-file-invoice me-2"></i>Complaint #<?= $complaintId ?>
                            </h4>
                            <div class="d-flex gap-2">
                                <?= statusBadge($complaint['complaint_status']) ?>
                                <?= priorityBadge($complaint['priority']) ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Student:</div>
                        <div class="detail-value"><?= $studentName . $anonymousBadge ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Reg. Number:</div>
                        <div class="detail-value">
                            <?= htmlspecialchars($complaint['student_registration_number']) ?>
                        </div>
                    </div>

                    <?php if (!empty($complaint['student_email'])): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Email:</div>
                            <div class="detail-value">
                                <a href="mailto:<?= htmlspecialchars($complaint['student_email']) ?>">
                                    <?= htmlspecialchars($complaint['student_email']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($complaint['student_phone'])): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Phone:</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($complaint['student_phone']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Title:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['complaint_title']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['category_name']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Sub-Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['subcategory_name']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Department:</div>
                        <div class="detail-value">
                            <?= $complaint['department_name'] ? htmlspecialchars($complaint['department_name']) : '<em class="text-muted">Not assigned</em>' ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Assigned Staff:</div>
                        <div class="detail-value">
                            <?= $complaint['assigned_staff_name'] ? htmlspecialchars($complaint['assigned_staff_name']) : '<em class="text-muted">Unassigned</em>' ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Submitted:</div>
                        <div class="detail-value">
                            <?= date('d M Y, H:i', strtotime($complaint['created_at'])) ?>
                        </div>
                    </div>

                    <?php if ($complaint['resolved_at']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Resolved:</div>
                            <div class="detail-value">
                                <?= date('d M Y, H:i', strtotime($complaint['resolved_at'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3 pb-2" style="border-bottom: 1px solid #dee2e6;">
                        <div class="fw-bold mb-2">Description:</div>
                        <div class="p-4 bg-light rounded border">
                            <?= htmlspecialchars($complaint['complaint_description']) ?>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <?php if (!empty($attachments)): ?>
                        <div class="mb-2">
                            <div class="detail-label fw-bold mb-2">
                                <i class="fas fa-paperclip me-1"></i>Attachments (<?= count($attachments) ?>):
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px,1fr)); gap: 8px;">
                                <?php foreach ($attachments as $att): ?>
                                    <?php
                                    $isPdf = $att['file_type'] === 'application/pdf';
                                    $iconClass = $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-success';
                                    ?>
                                    <a href="download_attachment.php?id=<?= $att['attachment_id'] ?>&view=1"
                                        target="_blank"
                                        class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-dark"
                                        style="background:#fff; transition: box-shadow .2s;"
                                        onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.12)'"
                                        onmouseout="this.style.boxShadow='none'">
                                        <i class="fas <?= $iconClass ?> fa-lg flex-shrink-0"></i>
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

                    <!-- Existing Response -->
                    <?php if (!empty($complaint['complaint_response'])): ?>
                        <div class="mt-3">
                            <div class="detail-label fw-bold mb-1">
                                <i class="fas fa-reply me-1"></i>Resolution:
                            </div>
                            <div class="p-3 rounded" style="background:#f0fdf4; border-left: 4px solid #22c55e;">
                                <?= htmlspecialchars($complaint['complaint_response']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submit Response -->
                <?php if (!$isClosed && !$isAssigned): ?>
                <div id="respond" class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Submit Response</h4>
                    <form method="POST" action="complaint_details.php?id=<?= $complaintId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="respond">
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                Response / Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="response" class="form-control p-3" rows="5"
                                style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                placeholder="Write your response, resolution, or reason for denial..."
                                required><?= isset($_POST['response']) ? htmlspecialchars($_POST['response']) : '' ?></textarea>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" name="response_action" value="resolve"
                                class="btn btn-success p-3 fw-bold flex-fill"
                                style="border-radius: 10px;"
                                onclick="return confirmAction(this)">
                                <i class="fas fa-check-circle me-1"></i>Resolve
                            </button>
                            <button type="submit" name="response_action" value="reject"
                                class="btn btn-danger p-3 fw-bold flex-fill"
                                style="border-radius: 10px;"
                                onclick="return confirmAction(this)">
                                <i class="fas fa-times-circle me-1"></i>Deny / Reject
                            </button>
                        </div>
                    </form>
                </div>
                <?php elseif (!$isClosed && $isAssigned): ?>
                <div id="respond" class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Submit Response</h4>
                    <div class="alert alert-info mb-0 d-flex align-items-start gap-3" style="border-radius: 10px;">
                        <i class="fas fa-info-circle fa-lg mt-1 flex-shrink-0"></i>
                        <div>
                            This complaint is assigned to <strong><?= htmlspecialchars($complaint['assigned_staff_name']) ?></strong>.
                            The assigned staff member is responsible for responding to it.
                            <br>To respond directly, first <a href="manage_complaints.php" class="alert-link">reassign or unassign it</a> from the Manage Complaints page.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Leader Endorsements (read-only; only shown when a leader has written a note) -->
                <?php if (!empty($leaderNotes)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-thumbs-up me-2"></i>Leader Endorsements (<?= count($leaderNotes) ?>)
                        </h4>
                        <?php foreach ($leaderNotes as $ln): ?>
                            <div class="mb-2 p-2 rounded" style="background:#f8f7fd; border-left: 4px solid #6f42c1;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($ln['leader_name']) ?></strong>
                                    <span class="text-muted" style="font-size:.85rem;">
                                        <?= date('d M Y, H:i', strtotime($ln['created_at'])) ?>
                                    </span>
                                </div>
                                <div><?= nl2br(htmlspecialchars($ln['note'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Collaboration Notes -->
                <div class="container-card shadow-sm">
                    <div class="mb-3">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-file-invoice me-2"></i>Collaboration Notes
                        </h4>
                    </div>

                    <?php if (empty($notes)): ?>
                        <p class="text-muted small">No notes yet.</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="mb-2 p-2 rounded"
                                style="background:#f1f1f3; border-left: 4px solid #6765ea;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($note['username']) ?></strong>
                                    <span class="text-muted" style="font-size:.85rem;">
                                        <?= date('d M Y, H:i', strtotime($note['created_at'])) ?>
                                    </span>
                                </div>
                                <div><?= htmlspecialchars($note['note_text']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <form method="POST" action="complaint_details.php?id=<?= $complaintId ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_note">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Add a Note</label>
                            <textarea name="note_text" class="form-control" rows="3"
                                placeholder="Add an internal note for other staff..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 p-3 fw-bold" 
                        style="border-radius: 10px; background-color: var(--udsm-blue); width: 100%;">
                            <i class="fas fa-plus me-1"></i>Add Note
                        </button>
                    </form>
                </div>

                <!-- Information Requests Log -->
                <?php if (!empty($infoRequests)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-inbox me-2"></i>Information Requests
                        </h4>
                        <?php foreach ($infoRequests as $ir):
                            [$irClass, $irLabel] = $irStatusMap[$ir['status']] ?? ['bg-secondary', ucfirst($ir['status'])];
                        ?>
                            <div class="mb-3 p-3 rounded"
                                style="background:#f9f9f9; border-left: 4px solid #eab308;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($ir['requested_by_name']) ?></strong>
                                    <span class="badge <?= $irClass ?>"><?= $irLabel ?></span>
                                </div>
                                <div class="mb-2"><?= htmlspecialchars($ir['request_message']) ?></div>
                                <?php if (!empty($ir['student_response'])): ?>
                                    <div class="p-2 rounded" style="background:#fff; border: 1px solid #e5e7eb;">
                                        <strong>Student Response:</strong>
                                        <div class="mt-1"><?= htmlspecialchars($ir['student_response']) ?></div>
                                        <?php if ($ir['responded_at']): ?>
                                            <small class="text-muted">
                                                Responded: <?= date('d M Y, H:i', strtotime($ir['responded_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted d-block mt-1">
                                    Requested: <?= date('d M Y, H:i', strtotime($ir['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Request Information -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold">
                        <i class="fas fa-question-circle me-2"></i>Request Information from Student
                    </h4>
                    <form method="POST" action="complaint_details.php?id=<?= $complaintId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_info">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Request</label>
                            <textarea name="request_message" class="form-control" rows="3"
                                placeholder="Describe what additional information you need from the student..."
                                required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 p-3 fw-bold" 
                        style="border-radius: 10px; background-color: var(--udsm-blue); width: 100%;">
                            <i class="fas fa-paper-plane me-1"></i>Send Request
                        </button>
                    </form>
                </div>

                <!-- Status Timeline -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold">
                        <i class="fas fa-history me-2"></i>Complaint Timeline
                    </h4>
                    <?php if (empty($statusLogs)): ?>
                        <p class="text-muted small">No activity recorded.</p>
                    <?php else: ?>
                        <?php foreach ($statusLogs as $log): ?>
                            <div class="mb-2 p-2 rounded"
                                style="background:#f9f9f9; border-left: 4px solid #eab308;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong>
                                        &mdash;
                                        <span class="text-capitalize">
                                            <?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?>
                                        </span>
                                    </span>
                                    <span class="text-muted" style="font-size:.85rem;">
                                        <?= date('d M Y, H:i', strtotime($log['changed_at'])) ?>
                                    </span>
                                </div>
                                <?php if ($log['old_status'] || $log['new_status']): ?>
                                    <div class="small text-muted">
                                        <?php if ($log['old_status']): ?>
                                            <span class="text-capitalize">
                                                <?= str_replace('_', ' ', $log['old_status']) ?>
                                            </span>
                                            &rarr;
                                        <?php endif; ?>
                                        <span class="text-capitalize fw-semibold">
                                            <?= str_replace('_', ' ', $log['new_status']) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($log['remarks'])): ?>
                                    <div class="small mt-1 text-muted fst-italic">
                                        <?= htmlspecialchars($log['remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Student Feedback -->
                <?php if ($complaint['complaint_status'] === STATUS_RESOLVED): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-star me-2 text-warning"></i>Student Feedback
                        </h4>

                        <?php if ($feedback): ?>
                            <div class="p-3 rounded" style="background:#fffbeb; border-left:4px solid #f59e0b;">
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <div>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color:<?= $i <= $feedback['rating'] ? '#f59e0b' : '#d1d5db' ?>; font-size:1.3rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="fw-bold fs-5"><?= $feedback['rating'] ?>/5</span>
                                    <?php if (!$complaint['is_anonymous']): ?>
                                        <span class="text-muted small">by <?= htmlspecialchars($feedback['student_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($feedback['feedback_text'])): ?>
                                    <div class="p-2 rounded bg-white border">
                                        <i class="fas fa-quote-left text-muted me-1"></i>
                                        <?= htmlspecialchars($feedback['feedback_text']) ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted d-block mt-2">
                                    Submitted: <?= date('d M Y, H:i', strtotime($feedback['submitted_at'])) ?>
                                </small>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-hourglass-half me-1"></i>
                                No feedback submitted yet. The student can rate the resolution from their complaint page.
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function confirmAction(btn) {
            var msg = btn.value === 'resolve'
                ? 'Are you sure you want to resolve this complaint?'
                : 'Are you sure you want to deny/reject this complaint?';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirm Action',
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: btn.value === 'resolve' ? '#198754' : '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = btn.name;
                        hidden.value = btn.value;
                        btn.form.appendChild(hidden);
                        btn.form.submit();
                    }
                });
                return false;
            }
            return confirm(msg);
        }
    </script>

</body>
</html>
