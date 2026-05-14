<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['user_id'];

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";
 
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

    if ($action === 'add_note') {
        $noteText = trim($_POST['note_text'] ?? '');
        if (empty($noteText)) {
            $error = "Note cannot be empty.";
        } elseif ($admin->addCollaborationNote($complaintId, $adminId, $noteText)) {
            $_SESSION['message'] = "Note added.";
            header("Location: complaint_details.php?id=$complaintId");
            exit;
        } else {
            $error = "Failed to add note.";
        }
    } elseif ($action === 'request_info') {
        $requestMsg = trim($_POST['request_message'] ?? '');
        if (empty($requestMsg)) {
            $error = "Request message cannot be empty.";
        } else {
            try {
                $admin->requestInformation($complaintId, $adminId, $requestMsg);
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
$infoRequests = $admin->getInformationRequests($complaintId);
$statusLogs   = $admin->getComplaintStatusLogs($complaintId);

$studentName = $complaint['is_anonymous']
    ? 'Anonymous Student'
    : htmlspecialchars($complaint['student_name']);

function statusBadge($status)
{
    $map = [
        'pending'                   => ['bg-warning text-dark', 'Pending'],
        'in_progress'               => ['bg-info text-white',   'In Progress'],
        'awaiting_student_response' => ['bg-primary text-white', 'Awaiting Response'],
        'resolved'                  => ['bg-success text-white', 'Resolved'],
        'rejected'                  => ['bg-danger text-white',  'Rejected'],
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
    'pending'   => ['bg-warning text-dark', 'Pending'],
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
                    <p class="mb-0 small fw-bold">ADMIN</p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="admin_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i><span class="link-text">Overview</span>
                    </a>
                </li>
                <li class="active">
                    <a href="manage_complaints.php" title="Manage Complaints">
                        <i class="fas fa-file-invoice me-2"></i><span class="link-text">Student Complaints</span>
                    </a>
                </li>
                <li>
                    <a href="user_management.php">
                        <i class="fas fa-user-shield me-2"></i><span class="link-text">User Management</span>
                    </a>
                </li>
                <li>
                    <a href="manage_departments.php" title="Departments">
                        <i class="fas fa-sitemap me-2"></i><span class="link-text">Departments</span>
                    </a>
                </li>
                <li>
                    <a href="manage_categories.php" title="Categories">
                        <i class="fas fa-tags me-2"></i><span class="link-text">Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" title="Reports">
                        <i class="fas fa-file-contract me-2"></i><span class="link-text">Reports</span>
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

            <!-- Toast Notifications -->
            <div aria-live="polite" aria-atomic="true"
                class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1100;">
                <?php if (!empty($message) || !empty($error)):
                    $type = !empty($message) ? 'success' : 'danger';
                    $text = !empty($message) ? $message : $error;
                    $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                ?>
                    <div class="toast show align-items-center text-white bg-<?= $type ?> border-0"
                        role="alert" aria-live="assertive" aria-atomic="true">
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

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-home" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="manage_complaints.php" style="color: black;">Manage Complaints</a>
                        </li>
                        <li class="breadcrumb-item active">Complaint #<?= $complaintId ?></li>
                    </ol>
                    <a href="respond_complaint.php?id=<?= $complaintId ?>" class="btn btn-add">
                        <i class="fas fa-reply me-1"></i> Respond
                    </a>
                </nav>

                <!-- ── Complaint Details ───────────────────────────────── -->
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
                        <div class="detail-value"><?= $studentName ?></div>
                    </div>

                    <?php if (!$complaint['is_anonymous']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Reg. Number:</div>
                            <div class="detail-value">
                                <?= htmlspecialchars($complaint['student_registration_number']) ?>
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

                    <div class="mb-3">
                        <div class="detail-label fw-bold mb-1">Description:</div>
                        <div class="p-3 bg-light rounded border"x>
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

                <!-- ── Collaboration Notes ─────────────────────────────── -->
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

                <!-- ── Request Information ────────────────────────────── -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold">
                        <i class="fas fa-question-circle me-2"></i>Request Information from Student
                    </h4>
                    <form method="POST" action="complaint_details.php?id=<?= $complaintId ?>">
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

                <!-- ── Information Requests Log ───────────────────────── -->
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

                <!-- ── Status Timeline ────────────────────────────────── -->
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

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>

</body>
</html>
