<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($complaintId <= 0) {
    header("Location: leader_complaints.php");
    exit;
}

require_once 'config/Database.php';
require_once 'classes/StudentLeader.php';

$db     = new Database();
$conn   = $db->connect();
$leader = new StudentLeader($conn, $userId);

$complaint = $leader->getComplaintById($complaintId);
if (!$complaint) {
    header("Location: leader_complaints.php");
    exit;
}

$message = $error = '';

// Handle endorse / remove / update note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'endorse') {
        $note = trim($_POST['note'] ?? '');
        if ($leader->endorse($complaintId, $note)) {
            $_SESSION['message'] = "Complaint endorsed successfully.";
        } else {
            $_SESSION['message_error'] = "You have already endorsed this complaint.";
        }
        header("Location: leader_complaint_details.php?id=$complaintId");
        exit;
    }

    if ($action === 'remove_endorsement') {
        $leader->removeEndorsement($complaintId);
        $_SESSION['message'] = "Endorsement removed.";
        header("Location: leader_complaint_details.php?id=$complaintId");
        exit;
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

// Re-fetch after possible POST changes
$complaint    = $leader->getComplaintById($complaintId);
$endorsements = $leader->getEndorsementsForComplaint($complaintId);
$iEndorsed    = (bool)($complaint['i_endorsed'] ?? false);

$statusMap = [
    STATUS_PENDING           => ['bg-warning text-dark',  'Pending'],
    STATUS_IN_PROGRESS       => ['bg-info text-white',    'In Progress'],
    STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
    STATUS_RESOLVED          => ['bg-success text-white', 'Resolved'],
    STATUS_REJECTED          => ['bg-danger text-white',  'Rejected'],
    STATUS_REOPENED          => ['bg-orange text-white',  'Reopened'],
];
[$sc, $sl] = $statusMap[$complaint['complaint_status']] ?? ['bg-secondary text-white', ucfirst($complaint['complaint_status'])];
$priorityMap = ['low' => 'bg-success', 'medium' => 'bg-warning text-dark', 'high' => 'bg-danger'];
$priClass = $priorityMap[$complaint['priority']] ?? 'bg-secondary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details | Student Rep</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="leader_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="leader_complaints.php">Department Complaints</a>
                        </li>
                        <li class="breadcrumb-item active">Complaint #<?= $complaintId ?></li>
                    </ol>
                </nav>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Complaint details -->
                    <div class="col-lg-8">
                        <div class="container-card shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h4 class="fw-bold mb-0" style="color:var(--udsm-blue);">
                                    <?= htmlspecialchars($complaint['complaint_title']) ?>
                                </h4>
                                <span class="badge <?= $sc ?>"><?= $sl ?></span>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-sm-6">
                                    <p class="mb-1 text-muted small fw-bold">STUDENT</p>
                                    <p class="mb-0">
                                        <?= $complaint['is_anonymous']
                                            ? '<em class="text-muted">Anonymous</em>'
                                            : htmlspecialchars($complaint['student_name']) ?>
                                    </p>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 text-muted small fw-bold">DEPARTMENT</p>
                                    <p class="mb-0"><?= htmlspecialchars($complaint['department_name']) ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 text-muted small fw-bold">CATEGORY</p>
                                    <p class="mb-0"><?= htmlspecialchars($complaint['category_name']) ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 text-muted small fw-bold">PRIORITY</p>
                                    <span class="badge <?= $priClass ?>"><?= ucfirst($complaint['priority']) ?></span>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 text-muted small fw-bold">SUBMITTED ON</p>
                                    <p class="mb-0"><?= date('d M Y, H:i', strtotime($complaint['created_at'])) ?></p>
                                </div>
                            </div>

                            <p class="mb-1 text-muted small fw-bold">DESCRIPTION</p>
                            <div class="p-3 rounded" style="background:#f8f9fa;white-space:pre-wrap;line-height:1.7;">
                                <?= htmlspecialchars($complaint['complaint_description']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Endorsement panel -->
                    <div class="col-lg-4">
                        <div class="container-card shadow-sm">
                            <h5 class="fw-bold mb-3" style="color:#6f42c1;">
                                <i class="fas fa-thumbs-up me-2"></i>Endorsement
                            </h5>

                            <?php if ($iEndorsed): ?>
                                <div class="alert alert-success py-2 mb-3">
                                    <i class="fas fa-check-circle me-1"></i>
                                    You have endorsed this complaint.
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="remove_endorsement">
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="fas fa-times me-1"></i>Remove My Endorsement
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted small mb-3">
                                    Endorsing a complaint signals to administrators that this issue is important
                                    and needs attention from your department.
                                </p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="endorse">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Note <span class="text-muted">(optional)</span></label>
                                        <textarea name="note" class="form-control" rows="3"
                                                  placeholder="Why are you endorsing this complaint?"></textarea>
                                    </div>
                                    <button type="submit" class="btn w-100 text-white"
                                            style="background-color:#6f42c1;">
                                        <i class="fas fa-thumbs-up me-1"></i>Endorse This Complaint
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($endorsements)): ?>
                                <hr>
                                <h6 class="fw-bold mb-3">All Endorsements (<?= count($endorsements) ?>)</h6>
                                <?php foreach ($endorsements as $e): ?>
                                    <div class="border rounded p-2 mb-2 bg-light">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold small"><?= htmlspecialchars($e['leader_name']) ?></span>
                                            <span class="text-muted" style="font-size:0.75rem;">
                                                <?= date('d M Y', strtotime($e['created_at'])) ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($e['note'])): ?>
                                            <p class="mb-0 small mt-1 text-muted"><?= htmlspecialchars($e['note']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <a href="leader_complaints.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left me-1"></i>Back to Complaints
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
