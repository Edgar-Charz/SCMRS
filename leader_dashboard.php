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

$stats  = $leader->getStats();
$depts  = $leader->getDepartments();
$recent = $leader->getComplaints(5);

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
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">
            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-user-friends" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Student Rep / Dashboard</li>
                    </ol>
                </nav>

                <div class="welcome-banner mb-4 shadow-sm">
                    <h3 class="mb-1">WELCOME, <?= htmlspecialchars($_SESSION['username']) ?>!</h3>
                    <p class="mb-0 opacity-75">
                        Representing:
                        <?php if (empty($depts)): ?>
                            <em>No departments assigned yet — contact an administrator.</em>
                        <?php else: ?>
                            <strong><?= htmlspecialchars(implode(', ', array_column($depts, 'department_name'))) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Stat cards -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
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
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
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
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
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
                        <div class="stat-card bg-stat p-4 d-flex align-items-center justify-content-between shadow-sm">
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

                <!-- Recent complaints -->
                <?php if (!empty($recent)): ?>
                <div class="container-card shadow-sm">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-file-invoice me-2"></i>Recent Department Complaints</h4>
                    <p class="text-muted small mb-3">Latest complaints submitted in your department(s)</p>
                    <div class="table-responsive">
                        <table class="table table-stripped">
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

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
