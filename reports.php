<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$db    = new Database();
$conn  = $db->connect();
$admin = new Admin($conn);

// Filter params from GET
$filterDept     = isset($_GET['department'])  && $_GET['department']  !== '' ? (int)$_GET['department']  : null;
$filterCategory = isset($_GET['category'])    && $_GET['category']    !== '' ? (int)$_GET['category']    : null;
$filterDateFrom = isset($_GET['date_from'])   && $_GET['date_from']   !== '' ? $_GET['date_from']         : null;
$filterDateTo   = isset($_GET['date_to'])     && $_GET['date_to']     !== '' ? $_GET['date_to']           : null;

$stats          = $admin->getReportStats($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byDept         = $admin->getReportByDepartment($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byCategory     = $admin->getReportByCategory($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byPriority     = $admin->getReportByPriority($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byStaff        = $admin->getReportByStaff($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$monthlyTrend   = $admin->getReportMonthlyTrend($filterDateFrom, $filterDateTo);
$oldestPending  = $admin->getOldestPendingComplaints(10);
$departments    = $admin->getAllDepartments();
$categories     = $admin->getAllCategories();

$isFiltered = $filterDept || $filterCategory || $filterDateFrom || $filterDateTo;

function fmtHours($val): string
{
    if ($val === null || $val === '') return '<span class="text-muted">—</span>';
    return number_format((float)$val, 1) . ' hrs';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin</title>
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
                <li>
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
                <li class="active">
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

            <div class="p-4">

                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Reports &amp; Analytics</li>
                    </ol>
                </nav>

                <!-- ── Filter Card ──────────────────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-filter me-2"></i>Filter Reports</h4>
                    <form method="GET" action="reports.php">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Department</label>
                                <select class="form-select p-3 shadow-sm" name="department"
                                    style="border-radius:10px; border:1px solid #e0e6ed;">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department_id'] ?>"
                                            <?= $filterDept == $dept['department_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Category</label>
                                <select class="form-select p-3 shadow-sm" name="category"
                                    style="border-radius:10px; border:1px solid #e0e6ed;">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"
                                            <?= $filterCategory == $cat['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Date From</label>
                                <input type="date" name="date_from" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    value="<?= htmlspecialchars($filterDateFrom ?? '') ?>">
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Date To</label>
                                <input type="date" name="date_to" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px; border:1px solid #e0e6ed;"
                                    value="<?= htmlspecialchars($filterDateTo ?? '') ?>">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary p-3 fw-bold" style="border-radius:10px;">
                                <i class="fas fa-search me-1"></i> Generate Report
                            </button>
                            <a href="reports.php" class="btn btn-secondary p-3 fw-bold" style="border-radius:10px;">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                            <?php if ($isFiltered): ?>
                                <span class="badge bg-info text-dark align-self-center ms-1 p-2">
                                    <i class="fas fa-info-circle me-1"></i>Filtered results
                                </span>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- ── Summary Stats ───────────────────────────────── -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-folder-open fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"><?= $stats['total'] ?></h2>
                                <p class="mb-0 small fw-bold">Total Complaints</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-clock fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0"><?= $stats['pending'] ?></h2>
                                <p class="mb-0 small fw-bold">Pending</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-spinner fa-spin fa-2x" style="color:black;"></i>
                            <div class="text-end">
                                <h2 class="mb-0"><?= $stats['in_progress'] ?></h2>
                                <p class="mb-0 small fw-bold">In Progress</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                            <div class="text-end">
                                <h2 class="mb-0"><?= $stats['resolved'] ?></h2>
                                <p class="mb-0 small fw-bold">Resolved</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-times-circle fa-2x text-danger"></i>
                            <div class="text-end">
                                <h2 class="mb-0"><?= $stats['rejected'] ?></h2>
                                <p class="mb-0 small fw-bold">Rejected</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                            <i class="fas fa-hourglass-half fa-2x"></i>
                            <div class="text-end">
                                <h2 class="mb-0">
                                    <?= $stats['avg_resolution_hours'] !== null
                                        ? number_format((float)$stats['avg_resolution_hours'], 1) . ' hrs'
                                        : '—' ?>
                                </h2>
                                <p class="mb-0 small fw-bold">Avg Resolution Time</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Complaints by Department ─────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-building me-2"></i>Complaints by Department</h4>
                    <p class="text-muted small mb-3">Breakdown of complaints per department</p>

                    <div class="table-responsive">
                        <table id="tbl_department" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>DEPARTMENT</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">PENDING</th>
                                    <th class="text-center">IN PROGRESS</th>
                                    <th class="text-center">RESOLVED</th>
                                    <th class="text-center">REJECTED</th>
                                    <th class="text-center">AVG RESOLUTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byDept)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($byDept as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?= $row['total'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?= $row['pending'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-info text-white"><?= $row['in_progress'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-success"><?= $row['resolved'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= $row['rejected'] ?></span></td>
                                            <td class="text-center"><?= fmtHours($row['avg_resolution_hours']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Complaints by Category ──────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-tags me-2"></i>Complaints by Category</h4>
                    <p class="text-muted small mb-3">Breakdown of complaints per category</p>

                    <div class="table-responsive">
                        <table id="tbl_category" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>CATEGORY</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">PENDING</th>
                                    <th class="text-center">IN PROGRESS</th>
                                    <th class="text-center">RESOLVED</th>
                                    <th class="text-center">REJECTED</th>
                                    <th class="text-center">AVG RESOLUTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byCategory)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($byCategory as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['category_name']) ?></td>
                                            <td class="text-center"><span class="badge bg-secondary"><?= $row['total'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?= $row['pending'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-info text-white"><?= $row['in_progress'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-success"><?= $row['resolved'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= $row['rejected'] ?></span></td>
                                            <td class="text-center"><?= fmtHours($row['avg_resolution_hours']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Priority Breakdown ──────────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Complaints by Priority</h4>
                    <p class="text-muted small mb-3">Distribution across priority levels</p>

                    <?php if (empty($byPriority)): ?>
                        <p class="text-center text-muted py-3">No data for the selected filters.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php
                            $priorityConfig = [
                                'high'   => ['bg-danger',  'fa-arrow-up',    'High Priority'],
                                'medium' => ['bg-warning', 'fa-minus',       'Medium Priority'],
                                'low'    => ['bg-success', 'fa-arrow-down',  'Low Priority'],
                            ];
                            // index by priority for easy lookup
                            $priorityMap = [];
                            foreach ($byPriority as $pr) {
                                $priorityMap[$pr['priority']] = $pr;
                            }
                            foreach (['high', 'medium', 'low'] as $level):
                                if (!isset($priorityMap[$level])) continue;
                                $pr = $priorityMap[$level];
                                [$bg, $icon, $label] = $priorityConfig[$level];
                                $rate = $pr['total'] > 0
                                    ? round($pr['resolved'] / $pr['total'] * 100, 1)
                                    : 0;
                            ?>
                            <div class="col-12 col-md-4">
                                <div class="p-3 rounded border h-100" style="background:#fff;">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge <?= $bg ?> me-2 p-2">
                                            <i class="fas <?= $icon ?>"></i>
                                        </span>
                                        <span class="fw-bold"><?= $label ?></span>
                                        <span class="badge bg-secondary ms-auto"><?= $pr['total'] ?> total</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Resolved</span>
                                        <strong><?= $pr['resolved'] ?></strong>
                                    </div>
                                    <div class="progress mb-2" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                                    </div>
                                    <div class="row text-center small mt-2">
                                        <div class="col">
                                            <div class="fw-bold text-warning"><?= $pr['pending'] ?></div>
                                            <div class="text-muted">Pending</div>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold text-info"><?= $pr['in_progress'] ?></div>
                                            <div class="text-muted">In Progress</div>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold text-danger"><?= $pr['rejected'] ?></div>
                                            <div class="text-muted">Rejected</div>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold text-success"><?= $rate ?>%</div>
                                            <div class="text-muted">Resolve Rate</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ── Staff Performance ───────────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-user-tie me-2"></i>Staff Performance</h4>
                    <p class="text-muted small mb-3">Complaints handled per staff member</p>

                    <div class="table-responsive">
                        <table id="tbl_staff" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>STAFF</th>
                                    <th>ROLE</th>
                                    <th>DEPARTMENT</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">PENDING</th>
                                    <th class="text-center">IN PROGRESS</th>
                                    <th class="text-center">RESOLVED</th>
                                    <th class="text-center">REJECTED</th>
                                    <th class="text-center">AVG RESOLUTION</th>
                                    <th class="text-center">RESOLVE RATE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($byStaff)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No assigned complaints found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($byStaff as $row): ?>
                                        <?php $rate = (float)($row['resolution_rate'] ?? 0); ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['staff_name']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['role_name']) ?></span></td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td class="text-center"><span class="badge bg-dark"><?= $row['total'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?= $row['pending'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-info text-white"><?= $row['in_progress'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-success"><?= $row['resolved'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= $row['rejected'] ?></span></td>
                                            <td class="text-center"><?= fmtHours($row['avg_resolution_hours']) ?></td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center gap-1 justify-content-center">
                                                    <div class="progress flex-grow-1" style="height:8px; min-width:50px;">
                                                        <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                                                    </div>
                                                    <small><?= number_format($rate, 1) ?>%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Monthly Trend ───────────────────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-chart-line me-2"></i>Monthly Trend</h4>
                    <p class="text-muted small mb-3">
                        <?= ($filterDateFrom || $filterDateTo) ? 'Complaints within selected date range' : 'Complaints over the last 12 months' ?>
                    </p>

                    <div class="table-responsive">
                        <table id="tbl_monthly" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>MONTH</th>
                                    <th class="text-center">TOTAL</th>
                                    <th class="text-center">PENDING</th>
                                    <th class="text-center">IN PROGRESS</th>
                                    <th class="text-center">RESOLVED</th>
                                    <th class="text-center">REJECTED</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($monthlyTrend)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No data available.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($monthlyTrend as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['month_label']) ?></td>
                                            <td class="text-center"><span class="badge bg-dark"><?= $row['total'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-warning text-dark"><?= $row['pending'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-info text-white"><?= $row['in_progress'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-success"><?= $row['resolved'] ?></span></td>
                                            <td class="text-center"><span class="badge bg-danger"><?= $row['rejected'] ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ── Oldest Pending Complaints ───────────────────── -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-hourglass-end me-2 text-danger"></i>Oldest Pending Complaints</h4>
                    <p class="text-muted small mb-3">Top 10 complaints that have been waiting the longest — requires immediate attention</p>

                    <div class="table-responsive">
                        <table id="tbl_oldest" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th>CATEGORY</th>
                                    <th>DEPARTMENT</th>
                                    <th>STUDENT</th>
                                    <th class="text-center">PRIORITY</th>
                                    <th class="text-center">DAYS PENDING</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($oldestPending)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-check-circle text-success me-2"></i>No pending complaints — all caught up!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $priorityBadge = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-success'];
                                    foreach ($oldestPending as $i => $row):
                                        $days = (int)$row['days_pending'];
                                        $urgency = $days >= 14 ? 'text-danger fw-bold' : ($days >= 7 ? 'text-warning fw-semibold' : '');
                                    ?>
                                        <tr>
                                            <td class="text-muted small">#<?= $row['complaint_id'] ?></td>
                                            <td>
                                                <a href="complaint_details.php?id=<?= $row['complaint_id'] ?>"
                                                   class="text-decoration-none fw-semibold">
                                                    <?= htmlspecialchars($row['complaint_title']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($row['category_name']) ?></td>
                                            <td><?= htmlspecialchars($row['department_name']) ?></td>
                                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $priorityBadge[$row['priority']] ?? 'bg-secondary' ?>">
                                                    <?= ucfirst($row['priority']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center <?= $urgency ?>">
                                                <?= $days ?> day<?= $days !== 1 ? 's' : '' ?>
                                                <?php if ($days >= 14): ?>
                                                    <i class="fas fa-exclamation-circle ms-1 text-danger"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="manage_complaints.php?highlight=<?= $row['complaint_id'] ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        $(document).ready(function () {
            var dtOptions = {
                destroy: true,
                bFilter: true,
                sDom: 'fBtlpi',
                pagingType: 'numbers',
                ordering: true,
                language: {
                    search: ' ',
                    sLengthMenu: '_MENU_',
                    searchPlaceholder: 'Search...',
                    info: '_START_ - _END_ of _TOTAL_ items',
                    emptyTable: 'No data available'
                }
            };

            $('#tbl_department').DataTable($.extend({}, dtOptions, {
                language: { searchPlaceholder: 'Search department...' }
            }));

            $('#tbl_category').DataTable($.extend({}, dtOptions, {
                language: { searchPlaceholder: 'Search category...' }
            }));

            $('#tbl_staff').DataTable($.extend({}, dtOptions, {
                language: { searchPlaceholder: 'Search staff...' },
                order: [[3, 'desc']]
            }));

            $('#tbl_monthly').DataTable($.extend({}, dtOptions, {
                paging: false,
                bFilter: false,
                sDom: 'tip',
                ordering: false
            }));

            $('#tbl_oldest').DataTable($.extend({}, dtOptions, {
                language: { searchPlaceholder: 'Search complaints...' },
                paging: false,
                order: [[6, 'desc']]
            }));
        });
    </script>

</body>
</html>
