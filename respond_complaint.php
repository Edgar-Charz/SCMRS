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

// Handle response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responseAction = $_POST['response_action'] ?? '';
    $responseText   = trim($_POST['response'] ?? '');

    if (empty($responseText)) {
        $error = "A response is required before submitting.";
    } elseif (!in_array($responseAction, ['resolve', 'reject'])) {
        $error = "Invalid action. Use the Resolve or Deny button.";
    } else {
        $newStatus = ($responseAction === 'resolve') ? 'resolved' : 'rejected';
        try {
            $admin->respondComplaint($complaintId, $responseText, $newStatus);
            $label = ($newStatus === 'resolved') ? 'resolved' : 'rejected';
            $_SESSION['message'] = "Complaint #$complaintId has been $label.";
            header("Location: manage_complaints.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$attachments = $admin->getComplaintAttachments($complaintId);
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respond — Complaint #<?= $complaintId ?> | Admin</title>
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
                <?php if (!empty($error)): ?>
                    <div class="toast show align-items-center text-white bg-danger border-0"
                        role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?= htmlspecialchars($error) ?>
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
                        <li class="breadcrumb-item active">Respond — #<?= $complaintId ?></li>
                    </ol>
                    <a href="complaint_details.php?id=<?= $complaintId ?>" class="btn btn-add">
                        <i class="fas fa-eye me-1"></i> View Full Details
                    </a>
                </nav>

                <!-- ── Complaint Summary ───────────────────────────────── -->
                <div class="container-card shadow-sm">
                    <div class="mb-3 pb-2" style="border-bottom: 2px solid #e9ecef;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="fw-bold mb-0">
                                <i class="fas fa-file-invoice me-2"></i>Complaint #<?= $complaintId ?>
                            </h4>
                            <?= statusBadge($complaint['complaint_status']) ?>
                        </div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Student:</div>
                        <div class="detail-value"><?= $studentName ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Title:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['complaint_title']) ?></div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Category:</div>
                        <div class="detail-value"><?= htmlspecialchars($complaint['category_name']) ?></div>
                    </div>

                    <?php if ($complaint['department_name']): ?>
                        <div class="detail-row">
                            <div class="detail-label fw-bold">Department:</div>
                            <div class="detail-value"><?= htmlspecialchars($complaint['department_name']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <div class="detail-label fw-bold mb-1">Description:</div>
                        <div class="p-3 bg-light rounded border" style="white-space: pre-wrap;">
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
                                    <?php $isPdf = $att['file_type'] === 'application/pdf'; ?>
                                    <a href="download_attachment.php?id=<?= $att['attachment_id'] ?>&view=1"
                                        target="_blank"
                                        class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-dark"
                                        style="background:#fff; transition: box-shadow .2s;"
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

                    <!-- Existing response (if already responded) -->
                    <?php if (!empty($complaint['complaint_response'])): ?>
                        <div class="mt-3">
                            <div class="detail-label fw-bold mb-1">
                                <i class="fas fa-reply me-1"></i>Current Response:
                            </div>
                            <div class="p-3 rounded" style="background:#f0fdf4; border-left: 4px solid #22c55e;">
                                <?= htmlspecialchars($complaint['complaint_response']) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ── Response Form ───────────────────────────────────── -->
                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Submit Response</h4>

                    <?php
                    $alreadyClosed = in_array($complaint['complaint_status'], ['resolved', 'rejected']);
                    if ($alreadyClosed): ?>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            This complaint is already
                            <strong><?= $complaint['complaint_status'] === 'resolved' ? 'resolved' : 'rejected' ?></strong>.
                            You can update the response below if needed.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="respond_complaint.php?id=<?= $complaintId ?>">
                        <input type="hidden" name="response_action" id="responseAction" value="">

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                Response / Reason <span class="text-danger">*</span>
                            </label>
                            <textarea name="response" class="form-control p-3" rows="5"
                                style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                placeholder="Write your response, resolution, or reason for denial..."
                                required><?= isset($_POST['response']) ? htmlspecialchars($_POST['response']) : '' ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary p-3 fw-bold flex-fill"
                                style="border-radius: 10px;"
                                onclick="submitResponse('resolve')">
                                <i class="fas fa-check-circle me-1"></i>Resolve
                            </button>
                            <button type="button" class="btn btn-danger p-3 fw-bold flex-fill"
                                style="border-radius: 10px;"
                                onclick="submitResponse('reject')">
                                <i class="fas fa-times-circle me-1"></i>Deny / Reject
                            </button>
                        </div>
                    </form>
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
            var label = action === 'resolve' ? 'resolve' : 'deny/reject';
            Swal.fire({
                icon: action === 'resolve' ? 'question' : 'warning',
                title: action === 'resolve' ? 'Resolve Complaint?' : 'Deny / Reject Complaint?',
                text: 'Are you sure you want to ' + label + ' this complaint?',
                showCancelButton: true,
                confirmButtonColor: action === 'resolve' ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, ' + label + '!',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('responseAction').value = action;
                    document.querySelector('form[method="POST"]').submit();
                }
            });
        }
    </script>

</body>
</html>
