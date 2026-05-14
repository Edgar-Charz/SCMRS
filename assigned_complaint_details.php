<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header("Location: login.php");
    exit;
}

require_once 'config/Database.php';
require_once 'classes/Staff.php';

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
$infoRequests       = $staff->getInformationRequests($complaintId);
$statusLogs         = $staff->getComplaintStatusLogs($complaintId);
$escalationHistory  = $staff->getEscalationHistoryForComplaint($complaintId);
$staffForEscalation = $staff->getStaffForEscalation($staffId);

$isClosed       = in_array($complaint['complaint_status'], ['resolved', 'rejected']);
$studentDisplay = $complaint['is_anonymous'] ? 'Anonymous Student' : htmlspecialchars($complaint['student_name'] ?? 'N/A');

function statusBadge($status)
{
    $map = [
        'pending'                   => ['bg-warning text-dark', 'Pending'],
        'in_progress'               => ['bg-info text-white',   'In Progress'],
        'awaiting_student_response' => ['bg-primary text-white', 'Awaiting Response'],
        'resolved'                  => ['bg-success text-white', 'Resolved'],
        'rejected'                  => ['bg-danger text-white',  'Rejected'],
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
                        style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold">UDSM</h6>
                    <small class="text-warning" style="font-size: 0.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0"><i class="fas fa-user me-2"></i></div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold"><?= htmlspecialchars($staffName) ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="staff_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li class="active">
                    <a href="assigned_complaints.php" title="Assigned Complaints">
                        <i class="fas fa-comment-dots me-2"></i>
                        <span class="link-text">Assigned Complaints</span>
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

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="staff_dashboard.php"><i class="fas fa-home" style="color:black;"></i></a>
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

                <!-- ── Complaint Details ──────────────────────────────── -->
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
                        <div class="p-3 bg-light rounded border">
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

                <!-- ── Respond / Resolve ──────────────────────────────── -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Submit Response</h4>

                    <?php if ($isClosed): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            This complaint is already
                            <strong><?= $complaint['complaint_status'] === 'resolved' ? 'Resolved' : 'Rejected' ?></strong>.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
                            <input type="hidden" name="action" value="respond">
                            <input type="hidden" name="response_action" id="responseAction" value="">

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
                                <button type="button" class="btn btn-success p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="submitResponse('resolve')">
                                    <i class="fas fa-check-circle me-1"></i>Resolve
                                </button>
                                <button type="button" class="btn btn-warning p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="submitResponse('in_progress')">
                                    <i class="fas fa-spinner me-1"></i>Mark In Progress
                                </button>
                                <button type="button" class="btn btn-danger p-3 fw-bold flex-fill"
                                    style="border-radius:10px;"
                                    onclick="submitResponse('reject')">
                                    <i class="fas fa-times-circle me-1"></i>Reject
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- ── Collaboration Notes ───────────────────────────── -->
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

                    <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>" class="mt-3">
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
                </div>

                <!-- ── Request Information from Student ──────────────── -->
                <?php if (!$isClosed): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-question-circle me-2"></i>Request Information from Student
                        </h4>

                        <?php if ($complaint['complaint_status'] === 'awaiting_student_response'): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="fas fa-hourglass-half me-2"></i>
                                Waiting for student response to a previous request.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
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

                <!-- ── Information Requests List ─────────────────────── -->
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

                <!-- ── Escalate Complaint ────────────────────────────── -->
                <?php if (!$isClosed && !empty($staffForEscalation)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-level-up-alt me-2"></i>Escalate Complaint
                        </h4>
                        <p class="text-muted small mb-3">
                            Forward this complaint to a higher-ranked staff member if it requires further authority.
                        </p>

                        <form method="POST" action="assigned_complaint_details.php?id=<?= $complaintId ?>">
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
                                                — <?= htmlspecialchars($s['department_name']) ?>
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

                <!-- ── Escalation History ─────────────────────────────── -->
                <?php if (!empty($escalationHistory)): ?>
                    <div class="container-card shadow-sm">
                        <h4 class="mb-3 fw-bold">
                            <i class="fas fa-exchange-alt me-2"></i>Escalation History
                        </h4>
                        <?php foreach ($escalationHistory as $esc): ?>
                            <div class="mb-2 p-3 rounded" style="background:#fff8e1; border-left:4px solid #ffc107;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
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
                                    <small class="text-muted">
                                        <?= date('d M Y', strtotime($esc['escalated_at'])) ?>
                                    </small>
                                </div>
                                <div class="small text-muted">
                                    <strong>Reason:</strong> <?= htmlspecialchars($esc['reason']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- ── Complaint Timeline ─────────────────────────────── -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold">
                        <i class="fas fa-history me-2"></i>Complaint Timeline
                    </h4>
                    <?php if (!empty($statusLogs)): ?>
                        <?php foreach ($statusLogs as $log): ?>
                            <div class="mb-2 p-3 rounded"
                                style="background:#f1f1f3; border-left:4px solid #dac820;">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= htmlspecialchars($log['performed_by_name'] ?? 'System') ?></strong>
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
                                            <?= ucwords(str_replace('_', ' ', $log['old_status'])) ?>
                                        </span>
                                        <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                        <span>
                                            <?= ucwords(str_replace('_', ' ', $log['new_status'])) ?>
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

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        function submitResponse(action) {
            const labels = {
                resolve:     { title: 'Resolve Complaint?',      text: 'Are you sure you want to resolve this complaint?',          icon: 'question', color: '#198754' },
                reject:      { title: 'Reject Complaint?',       text: 'Are you sure you want to reject this complaint?',           icon: 'warning',  color: '#dc3545' },
                in_progress: { title: 'Mark as In Progress?',   text: 'Are you sure you want to mark this complaint as in progress?', icon: 'info',   color: '#0d6efd' },
            };
            const cfg = labels[action];
            Swal.fire({
                icon: cfg.icon,
                title: cfg.title,
                text: cfg.text,
                showCancelButton: true,
                confirmButtonColor: cfg.color,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('responseAction').value = action;
                    document.querySelector('form input[name="response_action"]').closest('form').submit();
                }
            });
        }

        function confirmEscalate() {
            Swal.fire({
                icon: 'warning',
                title: 'Escalate Complaint?',
                text: 'This will reassign the complaint to the selected staff member.',
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
    </script>

</body>
</html>
