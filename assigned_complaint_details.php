<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

require_once 'config/Database.php';
require_once 'classes/Staff.php';
require_once 'classes/StudentLeader.php';
require_once 'includes/csrf.php';

$db   = new Database();
$conn = $db->connect();
$staff = new Staff($conn);

$userId       = (int) $_SESSION['user_id'];
$staffDetails = $staff->getStaffDetailsByUserId($userId);

if (!$staffDetails || (int) $staffDetails['staff_approval_status'] !== 1) {
    header('Location: staff_dashboard.php');
    exit;
}

$staffId   = $staffDetails['staff_id'];
$staffName = $staffDetails['username'] ?? 'Staff';

$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($complaintId <= 0) {
    header('Location: assigned_complaints.php');
    exit;
}

$complaint = $staff->getComplaintById($complaintId, $staffId);
if (!$complaint) {
    $_SESSION['message_error'] = "Complaint not found or not assigned to you.";
    header('Location: assigned_complaints.php');
    exit;
}

$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        if (empty($note)) {
            $error = "Note cannot be empty.";
        } else {
            try {
                $staff->addCollaborationNote($complaintId, $userId, $note);
                $_SESSION['message'] = "Note added.";
                header("Location: assigned_complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'request_info') {
        $question = trim($_POST['question'] ?? '');
        if (empty($question)) {
            $error = "Request message cannot be empty.";
        } else {
            try {
                $staff->requestInformation($complaintId, $userId, $question);
                $_SESSION['message'] = "Information request sent to student.";
                header("Location: assigned_complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'respond') {
        $response       = trim($_POST['response'] ?? '');
        $responseAction = $_POST['response_action'] ?? '';
        if (empty($response)) {
            $error = "Response text is required.";
        } elseif (!in_array($responseAction, ['resolve', 'reject', 'in_progress'])) {
            $error = "Invalid response action.";
        } else {
            try {
                $staff->respondToComplaint($complaintId, $userId, $response, $responseAction);
                $label = $responseAction === 'resolve' ? 'resolved' : ($responseAction === 'reject' ? 'rejected' : 'marked as in progress');
                $_SESSION['message'] = "Complaint #$complaintId has been $label.";
                header("Location: assigned_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'escalate') {
        $toStaffId = trim($_POST['to_staff_id'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');
        if (empty($toStaffId) || empty($reason)) {
            $error = "Select a staff member and provide a reason.";
        } else {
            try {
                $staff->forwardComplaint($complaintId, $staffId, $toStaffId, $userId, $reason);
                $_SESSION['message'] = "Complaint #$complaintId has been escalated.";
                header("Location: assigned_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'delegate') {
        $toStaffId = trim($_POST['to_staff_id'] ?? '');
        $reason    = trim($_POST['reason'] ?? '');
        if (empty($toStaffId) || empty($reason)) {
            $error = "Select a staff member and provide a reason.";
        } else {
            try {
                $staff->delegateComplaint($complaintId, $staffId, $toStaffId, $userId, $reason);
                $_SESSION['message'] = "Complaint #$complaintId has been delegated.";
                header("Location: assigned_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'on_hold') {
        $holdReason = trim($_POST['hold_reason'] ?? '');
        if (empty($holdReason)) {
            $error = "Hold reason cannot be empty.";
        } else {
            try {
                $staff->putComplaintOnHold($complaintId, $userId, $holdReason);
                $_SESSION['message'] = "Complaint #$complaintId has been put on hold.";
                header("Location: assigned_complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'resume') {
        try {
            $staff->resumeComplaint($complaintId, $userId);
            $_SESSION['message'] = "Complaint #$complaintId has been resumed.";
            header("Location: assigned_complaint_details.php?id=$complaintId");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

    } elseif ($action === 'progress_update') {
        $updateMessage = trim($_POST['update_message'] ?? '');
        if (empty($updateMessage)) {
            $error = "Progress update message cannot be empty.";
        } else {
            try {
                $staff->sendProgressUpdate($complaintId, $userId, $updateMessage);
                $_SESSION['message'] = "Progress update sent to student.";
                header("Location: assigned_complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'reject_assignment') {
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');
        if (empty($rejectionReason)) {
            $error = "Rejection reason cannot be empty.";
        } else {
            try {
                $staff->rejectAssignment($complaintId, $staffId, $userId, $rejectionReason);
                $_SESSION['message'] = "Assignment for complaint #$complaintId has been rejected. Admins have been notified.";
                header("Location: assigned_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'set_target_date') {
        $targetDate = trim($_POST['target_date'] ?? '');
        if (empty($targetDate)) {
            $error = "Please select a target date.";
        } else {
            try {
                $staff->setTargetDate($complaintId, $staffId, $targetDate);
                $_SESSION['message'] = "Target resolution date set to " . date('d M Y', strtotime($targetDate)) . ".";
                header("Location: assigned_complaint_details.php?id=$complaintId");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }

    $complaint = $staff->getComplaintById($complaintId, $staffId);
    if (!$complaint) {
        header('Location: assigned_complaints.php');
        exit;
    }
}

if (!empty($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (!empty($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

$attachments        = $staff->getComplaintAttachments($complaintId);
$collabNotes        = $staff->getCollaborationNotes($complaintId);
$leaderNotes        = array_filter(
    (new StudentLeader($conn))->getEndorsementsForComplaint($complaintId),
    fn($e) => !empty($e['note'])
);
$infoRequests       = $staff->getInformationRequests($complaintId);
$statusLogs         = $staff->getComplaintStatusLogs($complaintId);
$escalationHistory  = $staff->getEscalationHistoryForComplaint($complaintId);
$staffForEscalation = $staff->getStaffForEscalation($staffId);
$staffForDelegation = $staff->getStaffForDelegation($staffId);
$feedback           = $staff->getComplaintFeedback($complaintId);
$progressUpdates    = $staff->getProgressUpdates($complaintId);

$isClosed       = in_array($complaint['complaint_status'], [STATUS_RESOLVED, STATUS_REJECTED]);
$isReadOnly     = ($complaint['assignment_status'] ?? '') === 'forwarded';
$studentDisplay = $complaint['is_anonymous'] ? 'Anonymous Student' : htmlspecialchars($complaint['student_name'] ?? 'N/A');
$studentUserId  = (int) ($complaint['student_user_id'] ?? 0);

function statusLabel(string $status): string
{
    $map = [
        STATUS_PENDING           => 'Pending',
        STATUS_IN_PROGRESS       => 'In Progress',
        STATUS_AWAITING_RESPONSE => 'Awaiting Response',
        STATUS_RESOLVED          => 'Resolved',
        STATUS_REJECTED          => 'Rejected',
        STATUS_REOPENED          => 'Reopened',
        'on_hold'                => 'On Hold',
    ];
    return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function statusBadge($status)
{
    $map = [
        STATUS_PENDING => ['bg-warning text-dark',  'Pending'],
        STATUS_IN_PROGRESS => ['bg-info text-white',    'In Progress'],
        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
        STATUS_REJECTED => ['bg-danger text-white',  'Rejected'],
        STATUS_REOPENED => ['bg-warning text-dark',  'Reopened'],
        'on_hold'                   => ['bg-secondary text-white', 'On Hold'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary text-white', ucwords(str_replace('_', ' ', $status))];
    return "<span class=\"badge $class\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint #<?= $complaintId ?> | Staff</title>
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

    <?php $_staffSidebarRank = (int)($staffDetails['role_rank'] ?? 0); ?>
    <div class="d-flex">
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="staff_dashboard.php"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="staff_dashboard.php" style="color:black;">Staff</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="assigned_complaints.php" style="color:black;">Assigned Complaints</a>
                        </li>
                        <li class="breadcrumb-item active">Complaint #<?= $complaintId ?></li>
                    </ol>
                    <a href="assigned_complaints.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </nav>

                <!-- Flash messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Complaint Details -->
                <div class="container-card shadow-sm">
                    <div class="mb-3 pb-2" style="border-bottom: 2px solid #e9ecef;">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-file-invoice me-2"></i>Complaint #<?= $complaintId ?>
                            </h4>
                            <?= statusBadge($complaint['complaint_status']) ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Student:</div>
                        <div class="detail-value"><?= $studentDisplay ?></div>
                    </div>

                    <?php if (!$complaint['is_anonymous']): ?>
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
                    <?php endif; ?>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Title:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['complaint_title']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['category_name'] ?? 'N/A') ?></div>
                    </div>

                    <?php if (!empty($complaint['department_name'])): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Department:</div>
                            <div class="detail-value"><?= htmlspecialchars($complaint['department_name']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Priority:</div>
                        <div class="detail-value">
                            <?php
                            $pMap = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-success'];
                            $pClass = $pMap[$complaint['priority']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $pClass ?>"><?= ucfirst($complaint['priority'] ?? 'medium') ?></span>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Submitted:</div>
                        <div class="detail-value"><?= date('d M Y, g:i A', strtotime($complaint['created_at'])) ?></div>
                    </div>

                    <div class="mb-3 mt-2">
                        <div class="detail-label fw-bold mb-1">Description:</div>
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
                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:8px;">
                                <?php foreach ($attachments as $att): ?>
                                    <?php $isPdf = $att['file_type'] === 'application/pdf'; ?>
                                    <a href="download_attachment.php?id=<?= $att['attachment_id'] ?>&view=1"
                                        target="_blank"
                                        class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-dark"
                                        style="background:#fff; transition:box-shadow .2s;"
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
                    <?php else: ?>
                        <p class="text-muted small mb-0"><i class="fas fa-paperclip me-1"></i>No attachments.</p>
                    <?php endif; ?>

                    <!-- Existing response -->
                    <?php if (!empty($complaint['complaint_response'])): ?>
                        <div class="mt-3">
                            <div class="detail-label fw-bold mb-1">
                                <i class="fas fa-reply me-1"></i>Current Response:
                            </div>
                            <div class="p-3 rounded" style="background:#f0fdf4; border-left:4px solid #22c55e;">
                                <?= htmlspecialchars($complaint['complaint_response']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Respond / Resolve -->
                <div id="respond" class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Submit Response</h4>

                    <?php if ($isReadOnly): ?>
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-eye me-2"></i>
                            You delegated this complaint and can view it in read-only mode. Actions are only available to the currently assigned staff.
                        </div>
                    <?php elseif ($isClosed): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            This complaint is already
                            <strong><?= $complaint['complaint_status'] === STATUS_RESOLVED ? 'Resolved' : 'Rejected' ?></strong>.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="respond">
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    Response / Resolution <span class="text-danger">*</span>
                                </label>
                                <textarea name="response" class="form-control p-3" rows="5"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    placeholder="Write your resolution, response, or reason for rejection..."
                                    required><?= isset($_POST['response']) ? htmlspecialchars($_POST['response']) : '' ?></textarea>
                            </div>

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" name="response_action" value="resolve"
                                    class="btn btn-success p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="return confirmAction(this)">
                                    <i class="fas fa-check-circle me-1"></i>Resolve
                                </button>
                                <button type="submit" name="response_action" value="in_progress"
                                    class="btn btn-warning p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="return confirmAction(this)">
                                    <i class="fas fa-spinner me-1"></i>Mark In Progress
                                </button>
                                <button type="submit" name="response_action" value="reject"
                                    class="btn btn-danger p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="return confirmAction(this)">
                                    <i class="fas fa-times-circle me-1"></i>Reject
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Leader Endorsements (read-only; only shown when a leader has written a note) -->
                <?php if (!empty($leaderNotes)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-thumbs-up me-2"></i>Leader Endorsements (<?= count($leaderNotes) ?>)</h4>
                        <?php foreach ($leaderNotes as $ln): ?>
                            <div class="mb-2 p-3 rounded" style="background:#f8f7fd; border-left:4px solid #6f42c1;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($ln['leader_name']) ?></strong>
                                    <small class="text-muted">
                                        <?= date('d M Y, g:i A', strtotime($ln['created_at'])) ?>
                                    </small>
                                </div>
                                <div><?= nl2br(htmlspecialchars($ln['note'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Collaboration Notes -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-sticky-note me-2"></i>Collaboration Notes</h4>

                    <?php if (!empty($collabNotes)): ?>
                        <?php foreach ($collabNotes as $note): ?>
                            <div class="mb-2 p-3 rounded"
                                style="background:#f1f1f3; border-left:4px solid #6765ea;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($note['created_by_name'] ?? 'Staff') ?></strong>
                                    <small class="text-muted">
                                        <?= date('d M Y, g:i A', strtotime($note['created_at'])) ?>
                                    </small>
                                </div>
                                <div><?= nl2br(htmlspecialchars($note['note_text'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No collaboration notes yet.</p>
                    <?php endif; ?>

                    <?php if (!$isReadOnly): ?>
                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_note">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Add a Note</label>
                            <textarea name="note" class="form-control p-3" rows="3"
                                style="border-radius:10px; border:1px solid #e0e6ed;"
                                placeholder="Add an internal note visible to staff..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary fw-bold w-100 p-3"
                            style="border-radius:10px; background-color:var(--udsm-blue);">
                            <i class="fas fa-plus me-1"></i>Add Note
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Information Requests List -->
                <?php if (!empty($infoRequests)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-comments me-2"></i>Information Requests
                        </h4>
                        <?php foreach ($infoRequests as $req): ?>
                            <?php
                            $statusColor = match ($req['status']) {
                                'responded' => 'rgb(103, 101, 234)',
                                'closed'    => '#28a745',
                                default     => 'rgb(218, 203, 32)',
                            };
                            $statusBadgeCls = match ($req['status']) {
                                'responded' => 'bg-primary',
                                'closed'    => 'bg-success',
                                default     => 'bg-warning text-dark',
                            };
                            ?>
                            <div class="mb-3 p-3 rounded"
                                style="background:#f1f1f3; border-left:4px solid <?= $statusColor ?>;">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong><?= htmlspecialchars($req['requested_by_name'] ?? 'Staff') ?></strong>
                                    <span class="badge <?= $statusBadgeCls ?>">
                                        <?= ucfirst($req['status']) ?>
                                    </span>
                                </div>
                                <div class="mb-2"><?= nl2br(htmlspecialchars($req['request_message'])) ?></div>
                                <?php if (!empty($req['student_response'])): ?>
                                    <div class="p-2 rounded" style="background:white; border-radius:8px;">
                                        <strong>Student Response:</strong>
                                        <div class="mt-1"><?= nl2br(htmlspecialchars($req['student_response'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    Requested: <?= date('d M Y, g:i A', strtotime($req['created_at'])) ?>
                                    <?php if ($req['responded_at']): ?>
                                        &nbsp;·&nbsp; Responded: <?= date('d M Y', strtotime($req['responded_at'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Request Information from Student -->
                <?php if (!$isClosed && !$isReadOnly): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-question-circle me-2"></i>Request Information from Student
                        </h4>

                        <?php if ($complaint['complaint_status'] === STATUS_AWAITING_RESPONSE): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-hourglass-half me-2"></i>
                                Waiting for student response to a previous request.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="request_info">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Request Message</label>
                                <textarea name="question" class="form-control p-3" rows="3"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    placeholder="Describe what information you need from the student..."
                                    required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary fw-bold w-100 p-3"
                                style="border-radius:10px; background-color:var(--udsm-blue);">
                                <i class="fas fa-paper-plane me-1"></i>Send Request
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Escalate Complaint -->
                <?php if (!$isReadOnly && !$isClosed && empty($staffForEscalation)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-2 fw-bold text-muted"><i class="fas fa-level-up-alt me-2"></i>Escalate Complaint</h4>
                        <p class="text-muted mb-0 small"><i class="fas fa-info-circle me-1"></i>No higher-ranked staff available for escalation.</p>
                    </div>
                <?php elseif (!$isReadOnly && !$isClosed && !empty($staffForEscalation)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-level-up-alt me-2"></i>Escalate Complaint
                        </h4>
                        <p class="text-muted small mb-3">
                            Forward this complaint to a higher-ranked staff member if it requires further authority.
                        </p>

                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="escalate">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Escalate To <span class="text-danger">*</span></label>
                                <select name="to_staff_id" class="form-select" required>
                                    <option value="">-- Select Staff Member --</option>
                                    <?php foreach ($staffForEscalation as $s): ?>
                                        <option value="<?= $s['staff_id'] ?>">
                                            <?= htmlspecialchars($s['username']) ?>
                                            <?php if ($s['role_name']): ?>
                                                (<?= htmlspecialchars($s['role_name']) ?>)
                                            <?php endif; ?>
                                            <?php if ($s['department_name']): ?>
                                                - <?= htmlspecialchars($s['department_name']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reason <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control p-3" rows="3"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    placeholder="Explain why you are escalating this complaint..."
                                    required></textarea>
                            </div>
                            <button type="button" class="btn btn-warning fw-bold w-100 p-3"
                                style="border-radius:10px;"
                                onclick="confirmEscalate()">
                                <i class="fas fa-level-up-alt me-1"></i>Escalate
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Delegate Complaint -->
                <?php if (!$isReadOnly && !$isClosed && empty($staffForDelegation)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-2 fw-bold text-muted"><i class="fas fa-level-down-alt me-2"></i>Delegate Complaint</h4>
                        <p class="text-muted mb-0 small"><i class="fas fa-info-circle me-1"></i>No staff available for delegation.</p>
                    </div>
                <?php elseif (!$isReadOnly && !$isClosed && !empty($staffForDelegation)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-level-down-alt me-2"></i>Delegate Complaint
                        </h4>
                        <p class="text-muted small mb-3">
                            Assign this complaint to a lower-ranked staff member to handle on your behalf.
                            You will be notified when it is resolved.
                        </p>

                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delegate">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Delegate To <span class="text-danger">*</span></label>
                                <select name="to_staff_id" class="form-select" required>
                                    <option value="">-- Select Staff Member --</option>
                                    <?php foreach ($staffForDelegation as $s): ?>
                                        <option value="<?= $s['staff_id'] ?>">
                                            <?= htmlspecialchars($s['username']) ?>
                                            <?php if ($s['role_name']): ?>
                                                (<?= htmlspecialchars($s['role_name']) ?>)
                                            <?php endif; ?>
                                            <?php if ($s['department_name']): ?>
                                                - <?= htmlspecialchars($s['department_name']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Reason <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control p-3" rows="3"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    placeholder="Explain what you need the staff member to handle..."
                                    required></textarea>
                            </div>
                            <button type="button" class="btn btn-primary fw-bold w-100 p-3 text-white"
                                style="border-radius:10px; background-color:var(--udsm-blue);"
                                onclick="confirmDelegate()">
                                <i class="fas fa-level-down-alt me-1"></i>Delegate
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Target Resolution Date -->
                <?php if (!$isReadOnly && !$isClosed): ?>
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-calendar-alt me-2"></i>Target Resolution Date</h4>

                    <?php if (!empty($complaint['target_resolution_date'])): ?>
                        <p class="mb-3">
                            <i class="fas fa-flag me-1 text-info"></i>
                            Current target: <strong><?= date('d M Y', strtotime($complaint['target_resolution_date'])) ?></strong>
                        </p>
                    <?php else: ?>
                        <p class="text-muted small mb-3">No target date set yet.</p>
                    <?php endif; ?>

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="set_target_date">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Set Target Date</label>
                            <input type="date" name="target_date" class="form-control"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= htmlspecialchars($complaint['target_resolution_date'] ?? '') ?>"
                                style="border-radius:10px; border:1px solid #e0e6ed;" required>
                        </div>
                        <button type="submit" class="btn btn-info fw-bold w-100 p-3 text-white"
                            style="border-radius:10px;">
                            <i class="fas fa-calendar-check me-1"></i>Set Target Date
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Send Progress Update-->
                <?php if (!$isReadOnly && !$isClosed): ?>
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-bullhorn me-2"></i>Send Progress Update to Student</h4>

                    <?php if (!empty($progressUpdates)): ?>
                        <?php foreach ($progressUpdates as $pu): ?>
                            <div class="mb-2 p-3 rounded"
                                style="background:#f0fbff; border-left:4px solid #0dcaf0;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($pu['sent_by_name'] ?? 'Staff') ?></strong>
                                    <small class="text-muted">
                                        <?= date('d M Y, g:i A', strtotime($pu['created_at'])) ?>
                                    </small>
                                </div>
                                <div><?= nl2br(htmlspecialchars($pu['message'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No progress updates sent yet.</p>
                    <?php endif; ?>

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="progress_update">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Update Message</label>
                            <textarea name="update_message" class="form-control p-3" rows="3"
                                style="border-radius:10px; border:1px solid #e0e6ed;"
                                placeholder="Describe the current progress to the student..." required></textarea>
                        </div>
                        <button type="submit" class="btn fw-bold w-100 p-3 text-white"
                            style="border-radius:10px; background-color:#0dcaf0;">
                            <i class="fas fa-paper-plane me-1"></i>Send Update
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Put On Hold -->
                <?php if (!$isReadOnly && !$isClosed && $complaint['complaint_status'] !== 'on_hold'): ?>
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-pause-circle me-2 text-secondary"></i>Put Complaint On Hold</h4>
                    <p class="text-muted small mb-3">Use this when waiting on a third party outside the system.</p>

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="on_hold">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hold Reason <span class="text-danger">*</span></label>
                            <textarea name="hold_reason" class="form-control p-3" rows="3"
                                style="border-radius:10px; border:1px solid #e0e6ed;"
                                placeholder="Explain why this complaint is being put on hold..."
                                required></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary fw-bold w-100 p-3"
                            style="border-radius:10px;">
                            <i class="fas fa-pause me-1"></i>Put On Hold
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Resume from Hold -->
                <?php if (!$isReadOnly && $complaint['complaint_status'] === 'on_hold'): ?>
                <div class="container-card shadow-sm" style="border-left:4px solid #6c757d;">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-pause-circle me-2 text-secondary"></i>Complaint is On Hold</h4>

                    <?php if (!empty($complaint['hold_reason'])): ?>
                        <div class="p-3 rounded mb-3" style="background:#f8f9fa; border-left:4px solid #6c757d;">
                            <strong>Hold Reason:</strong>
                            <div class="mt-1"><?= nl2br(htmlspecialchars($complaint['hold_reason'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="resume">
                        <button type="submit" class="btn btn-success fw-bold w-100 p-3"
                            style="border-radius:10px;">
                            <i class="fas fa-play me-1"></i>Resume Complaint
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Reject Assignment -->
                <?php if (!$isReadOnly && !$isClosed): ?>
                <div class="container-card shadow-sm" style="border:1px solid #f8d7da;">
                    <h4 class="mb-3 fw-bold text-danger"><i class="fas fa-times-circle me-2"></i>Reject This Assignment</h4>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Use only if this complaint was incorrectly assigned to you. Admin will be notified to reassign.
                    </div>

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>" id="rejectAssignmentForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject_assignment">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control p-3" rows="3"
                                style="border-radius:10px; border:1px solid #e0e6ed;"
                                placeholder="Explain why you are rejecting this assignment..."
                                required></textarea>
                        </div>
                        <button type="button" class="btn btn-danger fw-bold w-100 p-3"
                            style="border-radius:10px;"
                            onclick="confirmRejectAssignment()">
                            <i class="fas fa-times me-1"></i>Reject Assignment
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Transfer History (Escalations & Delegations) -->
                <?php if (!empty($escalationHistory)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-exchange-alt me-2"></i>Transfer History
                        </h4>
                        <?php foreach ($escalationHistory as $esc):
                            $isDelegation = ($esc['type'] ?? 'escalation') === 'delegation';
                            $borderColor  = $isDelegation ? '#0dcaf0' : '#ffc107';
                            $bgColor      = $isDelegation ? '#e8f7fb'  : '#fff8e1';
                            $icon         = $isDelegation ? 'fa-level-down-alt text-info' : 'fa-level-up-alt text-warning';
                            $label        = $isDelegation ? 'Delegation' : 'Escalation';
                            $badgeCls     = $isDelegation ? 'bg-info'    : 'bg-warning text-dark';
                        ?>
                            <div class="mb-2 p-3 rounded" style="background:<?= $bgColor ?>; border-left:4px solid <?= $borderColor ?>;">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span>
                                        <i class="fas <?= $icon ?> me-1"></i>
                                        <strong><?= htmlspecialchars($esc['from_staff_name']) ?></strong>
                                        <?php if ($esc['from_role']): ?>
                                            <small class="text-muted">(<?= htmlspecialchars($esc['from_role']) ?>)</small>
                                        <?php endif; ?>
                                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                        <strong><?= htmlspecialchars($esc['to_staff_name']) ?></strong>
                                        <?php if ($esc['to_role']): ?>
                                            <small class="text-muted">(<?= htmlspecialchars($esc['to_role']) ?>)</small>
                                        <?php endif; ?>
                                    </span>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $badgeCls ?>" style="font-size:0.7rem;"><?= $label ?></span>
                                        <small class="text-muted">
                                            <?= date('d M Y', strtotime($esc['escalated_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="small text-muted">
                                    <strong>Reason:</strong> <?= htmlspecialchars($esc['reason']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

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
                                    Submitted: <?= date('d M Y, g:i A', strtotime($feedback['submitted_at'])) ?>
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

                <!-- Complaint Timeline -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold">
                        <i class="fas fa-history me-2"></i>Complaint Timeline
                    </h4>
                    <?php if (!empty($statusLogs)): ?>
                        <?php foreach ($statusLogs as $log): ?>
                            <?php
                            $logActor = ($complaint['is_anonymous'] && $studentUserId && (int)$log['performed_by'] === $studentUserId)
                                ? 'Anonymous Student'
                                : ($log['performed_by_name'] ?? 'System');
                            ?>
                            <div class="mb-2 p-3 rounded"
                                style="background:#f1f1f3; border-left:4px solid #dac820;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($logActor) ?></strong>
                                    <small class="text-muted">
                                        <?= date('d M Y, g:i A', strtotime($log['changed_at'])) ?>
                                    </small>
                                </div>
                                <div class="small">
                                    <strong>Action:</strong>
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))) ?>
                                    <?php if ($log['old_status'] && $log['new_status']): ?>
                                        &nbsp;·&nbsp;
                                        <span class="text-muted">
                                            <?= htmlspecialchars(statusLabel($log['old_status'])) ?>
                                        </span>
                                        <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                        <span>
                                            <?= htmlspecialchars(statusLabel($log['new_status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($log['remarks'])): ?>
                                    <div class="small text-muted mt-1">
                                        <strong>Notes:</strong> <?= htmlspecialchars($log['remarks']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small">No timeline entries yet.</p>
                    <?php endif; ?>
                </div>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/10.16.7/sweetalert2.all.min.js"></script> -->
    <script src="assets/js/script.js"></script>

    <script>
        function confirmAction(btn) {
            const labels = {
                resolve:     'Are you sure you want to resolve this complaint?',
                reject:      'Are you sure you want to reject this complaint?',
                in_progress: 'Are you sure you want to mark this complaint as in progress?',
            };
            const msg = labels[btn.value] || 'Are you sure?';
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirm Action',
                    text: msg,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: btn.value === 'resolve' ? '#198754' : btn.value === 'reject' ? '#dc3545' : '#0d6efd',
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

        function confirmEscalate() {
            Swal.fire({
                icon: 'warning',
                title: 'Escalate Complaint?',
                text: 'This will reassign the complaint to the selected higher-ranked staff member.',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, escalate',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.querySelector('form input[name="action"][value="escalate"]').closest('form').submit();
                }
            });
        }

        function confirmDelegate() {
            Swal.fire({
                icon: 'info',
                title: 'Delegate Complaint?',
                text: 'This will assign the complaint to the selected staff member. You will be notified when it is resolved.',
                showCancelButton: true,
                confirmButtonColor: '#0dcaf0',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delegate',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.querySelector('form input[name="action"][value="delegate"]').closest('form').submit();
                }
            });
        }

        function confirmRejectAssignment() {
            Swal.fire({
                icon: 'warning',
                title: 'Reject This Assignment?',
                text: 'This will return the complaint to pending status. All admins will be notified to reassign it.',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, reject assignment',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('rejectAssignmentForm').submit();
                }
            });
        }
    </script>

</body>
</html>
