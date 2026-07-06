<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once 'config/Database.php';
require_once 'classes/StudentLeader.php';

$db     = new Database();
$conn   = $db->connect();
$leader = new StudentLeader($conn, $userId);

$stats              = $leader->getStats();
$depts              = $leader->getDepartments();
$recent             = $leader->getComplaints(5);
$myComplaintCounts  = $leader->getMyComplaintCounts();
$unendorsedPending  = $leader->getUnendorsedPendingCount();

$message = $error = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leader Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
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

            <div class="p-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="leader_dashboard.php"><i class="fas fa-user-friends" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="leader_dashboard.php" style="color:black;">Student Rep</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= htmlspecialchars($_SESSION['username']) ?>!</h3>
                    <p class="mb-0 opacity-75">
                        Representing:
                        <?php if (empty($depts)): ?>
                            <strong><i class="fas fa-crown me-1"></i>All Departments (Senior Leader)</strong>
                        <?php else: ?>
                            <strong><?= htmlspecialchars(implode(', ', array_column($depts, 'department_name'))) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Attention alert for unendorsed pending complaints -->
                <?php if ($unendorsedPending > 0): ?>
                <div class="alert alert-warning alert-dismissible d-flex align-items-center mb-4 shadow-sm"
                     role="alert" style="border-left:5px solid #f59e0b;border-radius:10px;">
                    <i class="fas fa-exclamation-triangle fa-lg me-3 flex-shrink-0" style="color:#f59e0b;"></i>
                    <a href="leader_complaints.php" class="text-decoration-none text-reset flex-grow-1 d-flex align-items-center" style="cursor:pointer;">
                        <div class="flex-grow-1">
                            <strong><?= $unendorsedPending ?> pending complaint<?= $unendorsedPending > 1 ? 's need' : ' needs' ?> your endorsement.</strong>
                            <span class="ms-2 text-muted small">Review and endorse to escalate priority &rarr;</span>
                        </div>
                        <span class="badge bg-warning text-dark fs-6 me-2"><?= $unendorsedPending ?></span>
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
                </div>
                <?php endif; ?>

                <!-- Stat cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-folder-open fa-lg" style="color:#4f46e5;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#4f46e5;"><?= $stats['total'] ?></h2>
                                <p class="mb-0 fw-bold small">Total Complaints</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $stats['pending'] ?></h2>
                                <p class="mb-0 fw-bold small">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-check-circle fa-lg" style="color:#16a34a;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $stats['resolved'] ?></h2>
                                <p class="mb-0 fw-bold small">Resolved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(111,66,193,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-thumbs-up fa-lg" style="color:#6f42c1;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#6f42c1;"><?= $stats['endorsed'] ?></h2>
                                <p class="mb-0 fw-bold small">Endorsed by You</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Complaints stats + Needs Endorsement -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(14,165,233,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-paper-plane fa-lg" style="color:#0ea5e9;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#0ea5e9;"><?= $myComplaintCounts['total'] ?></h2>
                                <p class="mb-0 fw-bold small">My Submitted</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(22,163,74,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-check-double fa-lg" style="color:#16a34a;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#16a34a;"><?= $myComplaintCounts['resolved'] ?></h2>
                                <p class="mb-0 fw-bold small">My Resolved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(245,158,11,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-clock fa-lg" style="color:#f59e0b;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#f59e0b;"><?= $myComplaintCounts['pending'] ?></h2>
                                <p class="mb-0 fw-bold small">My Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <a href="leader_complaints.php" style="text-decoration:none;">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm"
                             style="<?= $unendorsedPending > 0 ? 'border:2px solid #f59e0b;' : '' ?>">
                            <div style="width:48px;height:48px;border-radius:12px;background:rgba(239,68,68,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-bell fa-lg" style="color:#ef4444;"></i>
                            </div>
                            <div class="text-end">
                                <h2 class="mb-0 fw-bold" style="color:#ef4444;"><?= $unendorsedPending ?></h2>
                                <p class="mb-0 fw-bold small">Needs Endorsement</p>
                            </div>
                        </div>
                        </a>
                    </div>
                </div>

                <!-- Action cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <a href="leader_create_complaint.php" class="action-card action-card--blue">
                            <i class="fas fa-plus-circle action-icon"></i>
                            <h5>Submit a Complaint</h5>
                            <small>File a new complaint and optionally suggest a staff member</small>
                        </a>
                    </div>
                    <div class="col-12 col-md-6">
                        <a href="leader_my_complaints.php" class="action-card action-card--blue">
                            <i class="fas fa-search action-icon"></i>
                            <h5>My Complaints</h5>
                            <small>Track the status of complaints you've submitted</small>
                        </a>
                    </div>
                </div>

                <!-- Recent complaints -->
                <?php if (!empty($recent)): ?>
                <div class="container-card shadow-sm">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Department Complaints</h4>
                    <p class="text-muted small mb-3">Latest complaints submitted in your department(s)</p>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th>STUDENT</th>
                                    <th>CATEGORY</th>
                                    <th>DATE</th>
                                    <th>STATUS</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $n = 1; foreach ($recent as $c): ?>
                                <?php
                                $statusMap = [
                                    STATUS_PENDING           => ['bg-warning text-dark',  'Pending'],
                                    STATUS_IN_PROGRESS       => ['bg-info text-white',    'In Progress'],
                                    STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
                                    STATUS_RESOLVED          => ['bg-success text-white', 'Resolved'],
                                    STATUS_REJECTED          => ['bg-danger text-white',  'Rejected'],
                                    STATUS_REOPENED          => ['bg-orange text-white',  'Reopened'],
                                ];
                                [$sc, $sl] = $statusMap[$c['complaint_status']] ?? ['bg-secondary text-white', ucfirst($c['complaint_status'])];
                                ?>
                                <tr>
                                    <td><?= $n++ ?></td>
                                    <td><?= htmlspecialchars($c['complaint_title']) ?></td>
                                    <td><?= $c['is_anonymous'] ? '<em class="text-muted">Anonymous</em>' : htmlspecialchars($c['student_name']) ?></td>
                                    <td><?= htmlspecialchars($c['category_name']) ?></td>
                                    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                    <td>
                                        <span class="badge <?= $sc ?>"><?= $sl ?></span>
                                        <?php if ($c['i_endorsed']): ?>
                                            <br><span class="badge mt-1" style="background-color:#6f42c1;">
                                                <i class="fas fa-thumbs-up me-1"></i>Endorsed
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="leader_complaint_details.php?id=<?= $c['complaint_id'] ?>"
                                           class="btn btn-status btn-outline-secondary" title="View">
                                            <i class="fas fa-eye text-dark"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="leader_complaints.php" class="btn btn-sm btn-outline-primary">
                            View All Complaints <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="container-card shadow-sm text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-2x mb-3"></i>
                    <p class="mb-0">No complaints in your department(s) yet.</p>
                </div>
                <?php endif; ?>

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div>

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
