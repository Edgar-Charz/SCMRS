<?php
require_once 'config/session.php';

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

// Filter params
$filterDept     = isset($_GET['department'])  && $_GET['department']  !== '' ? (int)$_GET['department']  : null;
$filterCategory = isset($_GET['category'])    && $_GET['category']    !== '' ? (int)$_GET['category']    : null;
$filterDateFrom = isset($_GET['date_from'])   && $_GET['date_from']   !== '' ? $_GET['date_from']         : null;
$filterDateTo   = isset($_GET['date_to'])     && $_GET['date_to']     !== '' ? $_GET['date_to']           : null;
$activeTab      = in_array($_GET['tab'] ?? '', ['reports', 'analytics']) ? $_GET['tab'] : 'reports';

$stats         = $admin->getReportStats($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byDept        = $admin->getReportByDepartment($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byCategory    = $admin->getReportByCategory($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byPriority    = $admin->getReportByPriority($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byStaff       = $admin->getReportByStaff($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$monthlyTrend  = $admin->getReportMonthlyTrend($filterDateFrom, $filterDateTo);
$oldestPending = $admin->getOldestPendingComplaints(10);
$departments   = $admin->getAllDepartments();
$categories    = $admin->getAllCategories();

$isFiltered = $filterDept || $filterCategory || $filterDateFrom || $filterDateTo;

// Chart data (JSON) 
$total = (int)$stats['total'];

// Status doughnut
$chartStatus = [
    'labels' => ['Pending', 'In Progress', 'Resolved', 'Rejected'],
    'data'   => [
        (int)$stats['pending'],
        (int)$stats['in_progress'],
        (int)$stats['resolved'],
        (int)$stats['rejected'],
    ],
];

// Monthly trend line
$monthlyLabels   = array_map(fn($r) => $r['month_label'], $monthlyTrend);
$monthlyTotal    = array_map(fn($r) => (int)$r['total'],    $monthlyTrend);
$monthlyResolved = array_map(fn($r) => (int)$r['resolved'], $monthlyTrend);
$monthlyPending  = array_map(fn($r) => (int)$r['pending'],  $monthlyTrend);

// Department bar (top 10)
$deptSlice    = array_slice($byDept, 0, 10);
$deptLabels   = array_map(fn($r) => $r['department_name'], $deptSlice);
$deptTotals   = array_map(fn($r) => (int)$r['total'],      $deptSlice);
$deptResolved = array_map(fn($r) => (int)$r['resolved'],   $deptSlice);

// Category bar (top 10)
$catSlice    = array_slice($byCategory, 0, 10);
$catLabels   = array_map(fn($r) => $r['category_name'], $catSlice);
$catTotals   = array_map(fn($r) => (int)$r['total'],    $catSlice);

// Staff bar (top 10 by total)
$staffSlice    = array_slice($byStaff, 0, 10);
$staffLabels   = array_map(fn($r) => $r['staff_name'], $staffSlice);
$staffResolved = array_map(fn($r) => (int)$r['resolved'], $staffSlice);
$staffTotal    = array_map(fn($r) => (int)$r['total'],    $staffSlice);

// Priority doughnut
$prMap = [];
foreach ($byPriority as $p) $prMap[$p['priority']] = (int)$p['total'];
$chartPriority = [
    'labels' => ['High', 'Medium', 'Low'],
    'data'   => [$prMap['high'] ?? 0, $prMap['medium'] ?? 0, $prMap['low'] ?? 0],
];

// Resolution rate
$resolutionRate = $total > 0 ? round(($stats['resolved'] / $total) * 100, 1) : 0;

function fmtHours($val): string
{
    if ($val === null || $val === '') return '<span class="text-muted">-</span>';
    return number_format((float)$val, 1) . ' hrs';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Reports &amp; Analytics</title>
    <?php include 'includes/head_assets.php'; ?>
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

                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Reports &amp; Analytics</li>
                    </ol>
                </nav>

                <!-- Filter Card -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-filter me-2"></i>Filter</h4>
                    <form method="GET" action="reports.php" id="filterForm">
                        <input type="hidden" name="tab" id="activeTabInput" value="<?= $activeTab ?>">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Department</label>
                                <select class="form-select p-3 shadow-sm" name="department" id="filterDepartment" style="border-radius:10px;border:1px solid #e0e6ed;">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['department_id'] ?>" <?= $filterDept == $dept['department_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Category</label>
                                <select class="form-select p-3 shadow-sm" name="category" id="filterCategory" style="border-radius:10px;border:1px solid #e0e6ed;">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>" <?= $filterCategory == $cat['category_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Date From</label>
                                <input type="date" name="date_from" id="filterDateFrom" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;"
                                    value="<?= htmlspecialchars($filterDateFrom ?? '') ?>">
                            </div>
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label fw-bold small">Date To</label>
                                <input type="date" name="date_to" id="filterDateTo" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;"
                                    value="<?= htmlspecialchars($filterDateTo ?? '') ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
                            <button type="submit" id="applyFilterBtn" class="btn btn-primary p-3 fw-bold" style="border-radius:10px;">
                                <i class="fas fa-search me-1"></i> <span class="btn-label">Apply Filter</span>
                            </button>
                            <a href="reports.php?tab=<?= $activeTab ?>" id="resetFilterBtn" class="btn btn-secondary p-3 fw-bold" style="border-radius:10px;">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                            <span id="filteredBadge" class="badge bg-info text-dark align-self-center ms-1 p-2 <?= $isFiltered ? '' : 'd-none' ?>">
                                <i class="fas fa-info-circle me-1"></i>Filtered results
                            </span>
                            <div class="ms-auto">
                                <div class="dropdown">
                                    <button class="btn btn-success fw-bold dropdown-toggle p-3" type="button"
                                        data-bs-toggle="dropdown" aria-expanded="false" style="border-radius:10px;">
                                        <i class="fas fa-download me-1"></i> Export
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1"
                                        style="border-radius:12px; min-width:230px;">
                                        <li>
                                            <h6 class="dropdown-header small text-uppercase">
                                                <i class="fas fa-file-pdf me-1 text-danger"></i>PDF
                                            </h6>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button" onclick="exportFullReportPDF()">
                                                <i class="fas fa-file-alt me-2 text-danger"></i>Full Report (all tables)
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button" onclick="exportAnalyticsPDF()">
                                                <i class="fas fa-chart-bar me-2 text-danger"></i>Analytics Charts PDF
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <h6 class="dropdown-header small text-uppercase">
                                                <i class="fas fa-file-csv me-1 text-success"></i>CSV
                                            </h6>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button"
                                                onclick="exportCSV('tbl_department','report-by-department.csv')">
                                                <i class="fas fa-building me-2 text-muted"></i>By Department
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button"
                                                onclick="exportCSV('tbl_category','report-by-category.csv')">
                                                <i class="fas fa-tags me-2 text-muted"></i>By Category
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button"
                                                onclick="exportCSV('tbl_staff','report-by-staff.csv')">
                                                <i class="fas fa-user-tie me-2 text-muted"></i>By Staff
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button"
                                                onclick="exportCSV('tbl_monthly','report-monthly-trend.csv')">
                                                <i class="fas fa-chart-line me-2 text-muted"></i>Monthly Trend
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item small" type="button"
                                                onclick="exportCSV('tbl_oldest','report-oldest-pending.csv')">
                                                <i class="fas fa-hourglass-end me-2 text-muted"></i>Oldest Pending
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Stats (always visible) -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold" id="statTotal"><?= $total ?></h3>
                            <p class="mb-0 small fw-bold">Total</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold text-warning" id="statPending"><?= $stats['pending'] ?></h3>
                            <p class="mb-0 small fw-bold">Pending</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold text-info" id="statInProgress"><?= $stats['in_progress'] ?></h3>
                            <p class="mb-0 small fw-bold">In Progress</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold text-success" id="statResolved"><?= $stats['resolved'] ?></h3>
                            <p class="mb-0 small fw-bold">Resolved</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold text-danger" id="statRejected"><?= $stats['rejected'] ?></h3>
                            <p class="mb-0 small fw-bold">Rejected</p>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="stat-card bg-stat p-3 text-center shadow-sm">
                            <h3 class="mb-0 fw-bold" style="color:var(--udsm-blue);" id="statResolveRate"><?= $resolutionRate ?>%</h3>
                            <p class="mb-0 small fw-bold">Resolve Rate</p>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-4 fw-bold" id="reportTabs">
                    <li class="nav-item">
                        <button class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" onclick="switchReportTab('reports')">
                            <i class="fas fa-table me-2"></i>Reports
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link <?= $activeTab === 'analytics' ? 'active' : '' ?>" onclick="switchReportTab('analytics')">
                            <i class="fas fa-chart-bar me-2"></i>Analytics
                        </button>
                    </li>
                </ul>

                <!-- TAB: REPORTS -->
                <div id="tab-reports" <?= $activeTab !== 'reports' ? 'style="display:none;"' : '' ?>>

                    <!-- Complaints by Department -->
                    <div class="container-card shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-building me-2"></i>Complaints by Department</h5>
                            <div class="search-input" id="si-department"></div>
                        </div>
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
                                <tbody id="tbody_department">
                                    <?php if (empty($byDept)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td></tr>
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

                    <!-- Complaints by Category -->
                    <div class="container-card shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-tags me-2"></i>Complaints by Category</h5>
                            <div class="search-input" id="si-category"></div>
                        </div>
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
                                <tbody id="tbody_category">
                                    <?php if (empty($byCategory)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td></tr>
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

                    <!-- Priority Breakdown -->
                    <div class="container-card shadow-sm mb-4">
                        <h5 class="mb-1 fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Complaints by Priority</h5>
                        <p class="text-muted small mb-3">Distribution across priority levels</p>
                        <div id="priorityBreakdown">
                        <?php if (empty($byPriority)): ?>
                            <p class="text-center text-muted py-3">No data for the selected filters.</p>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php
                                $priorityConfig = [
                                    'high'   => ['bg-danger',  'fa-arrow-up',   'High Priority'],
                                    'medium' => ['bg-warning', 'fa-minus',      'Medium Priority'],
                                    'low'    => ['bg-success', 'fa-arrow-down', 'Low Priority'],
                                ];
                                $priorityMap = [];
                                foreach ($byPriority as $pr) $priorityMap[$pr['priority']] = $pr;
                                foreach (['high', 'medium', 'low'] as $level):
                                    if (!isset($priorityMap[$level])) continue;
                                    $pr = $priorityMap[$level];
                                    [$bg, $icon, $label] = $priorityConfig[$level];
                                    $rate = $pr['total'] > 0 ? round($pr['resolved'] / $pr['total'] * 100, 1) : 0;
                                ?>
                                <div class="col-12 col-md-4">
                                    <div class="p-3 rounded border h-100" style="background:#fff;">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge <?= $bg ?> me-2 p-2"><i class="fas <?= $icon ?>"></i></span>
                                            <span class="fw-bold"><?= $label ?></span>
                                            <span class="badge bg-secondary ms-auto"><?= $pr['total'] ?> total</span>
                                        </div>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span>Resolved</span><strong><?= $pr['resolved'] ?></strong>
                                        </div>
                                        <div class="progress mb-2" style="height:6px;">
                                            <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                                        </div>
                                        <div class="row text-center small mt-2">
                                            <div class="col"><div class="fw-bold text-warning"><?= $pr['pending'] ?></div><div class="text-muted">Pending</div></div>
                                            <div class="col"><div class="fw-bold text-info"><?= $pr['in_progress'] ?></div><div class="text-muted">In Progress</div></div>
                                            <div class="col"><div class="fw-bold text-danger"><?= $pr['rejected'] ?></div><div class="text-muted">Rejected</div></div>
                                            <div class="col"><div class="fw-bold text-success"><?= $rate ?>%</div><div class="text-muted">Resolve Rate</div></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Staff Performance -->
                    <div class="container-card shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-user-tie me-2"></i>Staff Performance</h5>
                            <div class="search-input" id="si-staff"></div>
                        </div>
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
                                <tbody id="tbody_staff">
                                    <?php if (empty($byStaff)): ?>
                                        <tr><td colspan="10" class="text-center text-muted py-4">No assigned complaints found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($byStaff as $row):
                                            $rate = (float)($row['resolution_rate'] ?? 0); ?>
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
                                                        <div class="progress flex-grow-1" style="height:8px;min-width:50px;">
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

                    <!-- Monthly Trend -->
                    <div class="container-card shadow-sm mb-4">
                        <h5 class="mb-1 fw-bold"><i class="fas fa-chart-line me-2"></i>Monthly Trend</h5>
                        <p class="text-muted small mb-3" id="monthlyTrendNote">
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
                                <tbody id="tbody_monthly">
                                    <?php if (empty($monthlyTrend)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No data available.</td></tr>
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

                    <!-- Oldest Pending -->
                    <div class="container-card shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-hourglass-end me-2 text-danger"></i>Oldest Pending Complaints</h5>
                            <div class="search-input" id="si-oldest"></div>
                        </div>
                        <p class="text-muted small mb-3">Top 10 complaints waiting the longest - requires immediate attention</p>
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
                                                <i class="fas fa-check-circle text-success me-2"></i>No pending complaints - all caught up!
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        $priorityBadge = ['high' => 'bg-danger', 'medium' => 'bg-warning text-dark', 'low' => 'bg-success'];
                                        foreach ($oldestPending as $i => $row):
                                            $days    = (int)$row['days_pending'];
                                            $urgency = $days >= 14 ? 'text-danger fw-bold' : ($days >= 7 ? 'text-warning fw-semibold' : '');
                                        ?>
                                            <tr>
                                                <td class="text-muted small">#<?= $row['complaint_id'] ?></td>
                                                <td>
                                                    <a href="complaint_details.php?id=<?= $row['complaint_id'] ?>" class="text-decoration-none fw-semibold">
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
                                                    <?php if ($days >= 14): ?><i class="fas fa-exclamation-circle ms-1 text-danger"></i><?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="manage_complaints.php?highlight=<?= $row['complaint_id'] ?>" class="btn btn-sm btn-outline-primary">
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

                </div>
                <!-- /tab-reports -->

                <!-- TAB: ANALYTICS -->
                <div id="tab-analytics" <?= $activeTab !== 'analytics' ? 'style="display:none;"' : '' ?>>

                    <div class="d-flex justify-content-end mb-3">
                        <button class="btn btn-danger fw-bold" onclick="exportAnalyticsPDF()" style="border-radius:10px;">
                            <i class="fas fa-file-pdf me-1"></i> Export Charts PDF
                        </button>
                    </div>

                    <!-- Row 1: Status doughnut + Avg resolution + Priority pie -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-5">
                            <div class="container-card shadow-sm h-100">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-chart-pie me-2"></i>
                                    Complaints by Status
                                </h6>
                                <div style="max-height:260px; display:flex; justify-content:center;">
                                    <canvas id="chartStatus"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-2 d-flex flex-column gap-3">
                            <div class="container-card shadow-sm text-center flex-fill d-flex flex-column justify-content-center">
                                <p class="text-muted small mb-1 fw-semibold">AVG RESOLUTION</p>
                                <h2 class="fw-bold mb-0" style="color:var(--udsm-blue);" id="statAvgResolution">
                                    <?= $stats['avg_resolution_hours'] !== null ? number_format((float)$stats['avg_resolution_hours'], 1) : '-' ?>
                                </h2>
                                <p class="text-muted small mb-0">hours</p>
                            </div>
                            <div class="container-card shadow-sm text-center flex-fill d-flex flex-column justify-content-center">
                                <p class="text-muted small mb-1 fw-semibold">RESOLVE RATE</p>
                                <h2 class="fw-bold mb-0 text-success" id="statResolveRateAnalytics"><?= $resolutionRate ?>%</h2>
                                <div class="progress mt-2" style="height:6px;">
                                    <div class="progress-bar bg-success" id="resolveRateBar" style="width:<?= $resolutionRate ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-5">
                            <div class="container-card shadow-sm h-100">
                                <h6 class="fw-bold mb-3"><i class="fas fa-flag me-2"></i>Priority Distribution</h6>
                                <div style="max-height:260px; display:flex; justify-content:center;">
                                    <canvas id="chartPriority"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Monthly trend line -->
                    <div class="container-card shadow-sm mb-4">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-chart-line me-2"></i>Monthly Trend
                            <small class="text-muted fw-normal ms-2" id="monthlyTrendNoteAnalytics">
                                <?= ($filterDateFrom || $filterDateTo) ? '(filtered range)' : '(last 12 months)' ?>
                            </small>
                        </h6>
                        <p class="text-center text-muted py-4" id="chartMonthlyEmpty" <?= empty($monthlyTrend) ? '' : 'style="display:none;"' ?>>No data for the selected period.</p>
                        <canvas id="chartMonthly" style="max-height:280px; <?= empty($monthlyTrend) ? 'display:none;' : '' ?>"></canvas>
                    </div>

                    <!-- Row 3: Department bar + Category bar -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-md-6">
                            <div class="container-card shadow-sm h-100">
                                <h6 class="fw-bold mb-3"><i class="fas fa-building me-2"></i>Top Departments</h6>
                                <p class="text-center text-muted py-4" id="chartDeptEmpty" <?= empty($byDept) ? '' : 'style="display:none;"' ?>>No data.</p>
                                <canvas id="chartDept" style="max-height:320px; <?= empty($byDept) ? 'display:none;' : '' ?>"></canvas>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="container-card shadow-sm h-100">
                                <h6 class="fw-bold mb-3"><i class="fas fa-tags me-2"></i>Top Categories</h6>
                                <p class="text-center text-muted py-4" id="chartCatEmpty" <?= empty($byCategory) ? '' : 'style="display:none;"' ?>>No data.</p>
                                <canvas id="chartCat" style="max-height:320px; <?= empty($byCategory) ? 'display:none;' : '' ?>"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Staff performance bar -->
                    <div class="container-card shadow-sm mb-4" id="staffChartWrap" <?= empty($byStaff) ? 'style="display:none;"' : '' ?>>
                        <h6 class="fw-bold mb-3"><i class="fas fa-user-tie me-2"></i>Staff Performance - Resolved vs Total (top 10)</h6>
                        <canvas id="chartStaff" style="max-height:300px;"></canvas>
                    </div>

                </div><!-- /tab-analytics -->

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div><!-- /d-flex -->

    <?php $useDataTablesJs = true; include 'includes/foot_scripts.php'; ?>
    <script src="assets/js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
    // ---- Live data model (initialized from PHP, refreshed via AJAX on filter apply) ----
    var reportData = {
        stats:          <?= json_encode($stats) ?>,
        resolutionRate: <?= json_encode($resolutionRate) ?>,
        byDept:         <?= json_encode($byDept) ?>,
        byCategory:     <?= json_encode($byCategory) ?>,
        byPriority:     <?= json_encode($byPriority) ?>,
        byStaff:        <?= json_encode($byStaff) ?>,
        monthlyTrend:   <?= json_encode($monthlyTrend) ?>,
        isFiltered:     <?= json_encode($isFiltered) ?>,
        filters: {
            department: <?= json_encode($filterDept) ?>,
            category:   <?= json_encode($filterCategory) ?>,
            date_from:  <?= json_encode($filterDateFrom) ?>,
            date_to:    <?= json_encode($filterDateTo) ?>
        }
    };

    var priorityConfig = {
        high:   { bg: 'bg-danger',  icon: 'fa-arrow-up',   label: 'High Priority' },
        medium: { bg: 'bg-warning', icon: 'fa-minus',      label: 'Medium Priority' },
        low:    { bg: 'bg-success', icon: 'fa-arrow-down', label: 'Low Priority' }
    };

    function escapeHtml(str) {
        var d = document.createElement('div');
        d.textContent = str === null || str === undefined ? '' : String(str);
        return d.innerHTML;
    }

    function fmtHours(val) {
        if (val === null || val === undefined || val === '') return '<span class="text-muted">-</span>';
        return Number(val).toFixed(1) + ' hrs';
    }

    // Tab switching
    function switchReportTab(tab) {
        document.getElementById('tab-reports').style.display   = tab === 'reports'   ? '' : 'none';
        document.getElementById('tab-analytics').style.display = tab === 'analytics' ? '' : 'none';
        document.querySelectorAll('#reportTabs .nav-link').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('onclick').includes(tab));
        });
        document.getElementById('activeTabInput').value = tab;

        // Keep the active tab in the URL so reload lands on the same tab
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url.toString());

        if (tab === 'analytics') renderCharts();
    }

    // ---- DataTables ----
    function dtInitComplete(settings) {
        $(settings.nTableWrapper).find('.dataTables_filter')
            .appendTo($(settings.nTable).closest('.container-card').find('.search-input'));
    }
    var dtOpts = {
        destroy: true, bFilter: true, sDom: 'fBtlpi', pagingType: 'numbers', ordering: true,
        language: { search: ' ', sLengthMenu: '_MENU_', searchPlaceholder: 'Search...', info: '_START_ - _END_ of _TOTAL_ items', emptyTable: 'No data available' },
        initComplete: dtInitComplete
    };
    function initFilterableTables() {
        $('#tbl_department').DataTable($.extend({}, dtOpts, { language: { search: ' ', sLengthMenu: '_MENU_', info: '_START_ - _END_ of _TOTAL_ items', searchPlaceholder: 'Search department...' } }));
        $('#tbl_category').DataTable($.extend({}, dtOpts, { language: { search: ' ', sLengthMenu: '_MENU_', info: '_START_ - _END_ of _TOTAL_ items', searchPlaceholder: 'Search category...' } }));
        $('#tbl_staff').DataTable($.extend({}, dtOpts, { language: { search: ' ', sLengthMenu: '_MENU_', info: '_START_ - _END_ of _TOTAL_ items', searchPlaceholder: 'Search staff...' }, order: [[3, 'desc']] }));
        $('#tbl_monthly').DataTable($.extend({}, dtOpts, { paging: false, bFilter: false, sDom: 'tip', ordering: false, initComplete: null }));
    }
    function initOldestTable() {
        $('#tbl_oldest').DataTable($.extend({}, dtOpts, { language: { search: ' ', sLengthMenu: '_MENU_', info: '_START_ - _END_ of _TOTAL_ items', searchPlaceholder: 'Search complaints...' }, paging: false, order: [[6, 'desc']] }));
    }
    $(document).ready(function () {
        initFilterableTables();
        initOldestTable();
    });

    // ---- Table renderers (rebuild tbody HTML from reportData, then re-init DataTables) ----
    function renderDeptTable() {
        var rows = reportData.byDept, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td></tr>';
        } else {
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td class="fw-semibold">' + escapeHtml(r.department_name) + '</td>' +
                    '<td class="text-center"><span class="badge bg-secondary">' + r.total + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-warning text-dark">' + r.pending + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-info text-white">' + r.in_progress + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-success">' + r.resolved + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-danger">' + r.rejected + '</span></td>' +
                    '<td class="text-center">' + fmtHours(r.avg_resolution_hours) + '</td>' +
                    '</tr>';
            });
        }
        document.getElementById('tbody_department').innerHTML = html;
    }

    function renderCategoryTable() {
        var rows = reportData.byCategory, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="7" class="text-center text-muted py-4">No data for the selected filters.</td></tr>';
        } else {
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td class="fw-semibold">' + escapeHtml(r.category_name) + '</td>' +
                    '<td class="text-center"><span class="badge bg-secondary">' + r.total + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-warning text-dark">' + r.pending + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-info text-white">' + r.in_progress + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-success">' + r.resolved + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-danger">' + r.rejected + '</span></td>' +
                    '<td class="text-center">' + fmtHours(r.avg_resolution_hours) + '</td>' +
                    '</tr>';
            });
        }
        document.getElementById('tbody_category').innerHTML = html;
    }

    function renderStaffTable() {
        var rows = reportData.byStaff, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="10" class="text-center text-muted py-4">No assigned complaints found.</td></tr>';
        } else {
            rows.forEach(function (r) {
                var rate = parseFloat(r.resolution_rate || 0);
                html += '<tr>' +
                    '<td class="fw-semibold">' + escapeHtml(r.staff_name) + '</td>' +
                    '<td><span class="badge bg-secondary">' + escapeHtml(r.role_name) + '</span></td>' +
                    '<td>' + escapeHtml(r.department_name) + '</td>' +
                    '<td class="text-center"><span class="badge bg-dark">' + r.total + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-warning text-dark">' + r.pending + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-info text-white">' + r.in_progress + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-success">' + r.resolved + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-danger">' + r.rejected + '</span></td>' +
                    '<td class="text-center">' + fmtHours(r.avg_resolution_hours) + '</td>' +
                    '<td class="text-center"><div class="d-flex align-items-center gap-1 justify-content-center">' +
                        '<div class="progress flex-grow-1" style="height:8px;min-width:50px;"><div class="progress-bar bg-success" style="width:' + rate + '%"></div></div>' +
                        '<small>' + rate.toFixed(1) + '%</small>' +
                        '</div></td>' +
                    '</tr>';
            });
        }
        document.getElementById('tbody_staff').innerHTML = html;
    }

    function renderMonthlyTable() {
        var rows = reportData.monthlyTrend, html = '';
        if (!rows.length) {
            html = '<tr><td colspan="6" class="text-center text-muted py-4">No data available.</td></tr>';
        } else {
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td class="fw-semibold">' + escapeHtml(r.month_label) + '</td>' +
                    '<td class="text-center"><span class="badge bg-dark">' + r.total + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-warning text-dark">' + r.pending + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-info text-white">' + r.in_progress + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-success">' + r.resolved + '</span></td>' +
                    '<td class="text-center"><span class="badge bg-danger">' + r.rejected + '</span></td>' +
                    '</tr>';
            });
        }
        document.getElementById('tbody_monthly').innerHTML = html;
    }

    function rebuildTables() {
        renderDeptTable();
        renderCategoryTable();
        renderStaffTable();
        renderMonthlyTable();
        initFilterableTables();
    }

    // ---- Stat cards / badges / notes ----
    function renderStats() {
        var s = reportData.stats;
        document.getElementById('statTotal').textContent       = s.total;
        document.getElementById('statPending').textContent     = s.pending;
        document.getElementById('statInProgress').textContent  = s.in_progress;
        document.getElementById('statResolved').textContent    = s.resolved;
        document.getElementById('statRejected').textContent    = s.rejected;
        document.getElementById('statResolveRate').textContent = reportData.resolutionRate + '%';
        document.getElementById('statAvgResolution').textContent =
            (s.avg_resolution_hours !== null && s.avg_resolution_hours !== undefined) ? Number(s.avg_resolution_hours).toFixed(1) : '-';
        document.getElementById('statResolveRateAnalytics').textContent = reportData.resolutionRate + '%';
        document.getElementById('resolveRateBar').style.width = reportData.resolutionRate + '%';
        document.getElementById('filteredBadge').classList.toggle('d-none', !reportData.isFiltered);

        var hasDateRange = !!(reportData.filters.date_from || reportData.filters.date_to);
        document.getElementById('monthlyTrendNote').textContent = hasDateRange ? 'Complaints within selected date range' : 'Complaints over the last 12 months';
        document.getElementById('monthlyTrendNoteAnalytics').textContent = hasDateRange ? '(filtered range)' : '(last 12 months)';
    }

    // ---- Priority breakdown cards ----
    function renderPriorityCards() {
        var map = {};
        reportData.byPriority.forEach(function (p) { map[p.priority] = p; });
        var container = document.getElementById('priorityBreakdown');
        var present = ['high', 'medium', 'low'].filter(function (l) { return map[l]; });

        if (!present.length) {
            container.innerHTML = '<p class="text-center text-muted py-3">No data for the selected filters.</p>';
            return;
        }

        var html = '<div class="row g-3">';
        present.forEach(function (level) {
            var pr  = map[level];
            var cfg = priorityConfig[level];
            var rate = pr.total > 0 ? Math.round((pr.resolved / pr.total) * 1000) / 10 : 0;
            html += '<div class="col-12 col-md-4">' +
                '<div class="p-3 rounded border h-100" style="background:#fff;">' +
                    '<div class="d-flex align-items-center mb-2">' +
                        '<span class="badge ' + cfg.bg + ' me-2 p-2"><i class="fas ' + cfg.icon + '"></i></span>' +
                        '<span class="fw-bold">' + cfg.label + '</span>' +
                        '<span class="badge bg-secondary ms-auto">' + pr.total + ' total</span>' +
                    '</div>' +
                    '<div class="d-flex justify-content-between small mb-1"><span>Resolved</span><strong>' + pr.resolved + '</strong></div>' +
                    '<div class="progress mb-2" style="height:6px;"><div class="progress-bar bg-success" style="width:' + rate + '%"></div></div>' +
                    '<div class="row text-center small mt-2">' +
                        '<div class="col"><div class="fw-bold text-warning">' + pr.pending + '</div><div class="text-muted">Pending</div></div>' +
                        '<div class="col"><div class="fw-bold text-info">' + pr.in_progress + '</div><div class="text-muted">In Progress</div></div>' +
                        '<div class="col"><div class="fw-bold text-danger">' + pr.rejected + '</div><div class="text-muted">Rejected</div></div>' +
                        '<div class="col"><div class="fw-bold text-success">' + rate + '%</div><div class="text-muted">Resolve Rate</div></div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // ---- Chart.js ----
    var chartInstances = {};
    var BLUE   = '#0062cc';
    var GREEN  = '#16a34a';
    var YELLOW = '#f59e0b';
    var RED    = '#dc2626';
    var GREY   = '#94a3b8';

    function destroyChart(id) {
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }

    function toggleChartEmpty(emptyId, canvasId, isEmpty) {
        var emptyEl  = document.getElementById(emptyId);
        var canvasEl = document.getElementById(canvasId);
        if (emptyEl)  emptyEl.style.display  = isEmpty ? '' : 'none';
        if (canvasEl) canvasEl.style.display = isEmpty ? 'none' : '';
    }

    function renderCharts() {
        // Only render when the analytics tab is actually visible; canvases with
        // zero size otherwise produce blank/broken Chart.js instances.
        if (document.getElementById('tab-analytics').style.display === 'none') return;

        var s = reportData.stats;

        destroyChart('chartStatus');
        chartInstances.chartStatus = new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Resolved', 'Rejected'],
                datasets: [{ data: [s.pending, s.in_progress, s.resolved, s.rejected], backgroundColor: [YELLOW, BLUE, GREEN, RED], borderWidth: 2 }]
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
        });

        var prMap = {};
        reportData.byPriority.forEach(function (p) { prMap[p.priority] = p.total; });
        destroyChart('chartPriority');
        chartInstances.chartPriority = new Chart(document.getElementById('chartPriority'), {
            type: 'pie',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{ data: [prMap.high || 0, prMap.medium || 0, prMap.low || 0], backgroundColor: [RED, YELLOW, GREEN], borderWidth: 2 }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        destroyChart('chartMonthly');
        var mt = reportData.monthlyTrend;
        toggleChartEmpty('chartMonthlyEmpty', 'chartMonthly', !mt.length);
        if (mt.length) {
            chartInstances.chartMonthly = new Chart(document.getElementById('chartMonthly'), {
                type: 'line',
                data: {
                    labels: mt.map(function (r) { return r.month_label; }),
                    datasets: [
                        { label: 'Submitted', data: mt.map(function (r) { return +r.total; }),    borderColor: BLUE,   backgroundColor: 'rgba(0,98,204,0.08)',  fill: true, tension: 0.35, pointRadius: 4 },
                        { label: 'Resolved',  data: mt.map(function (r) { return +r.resolved; }), borderColor: GREEN,  backgroundColor: 'rgba(22,163,74,0.08)', fill: true, tension: 0.35, pointRadius: 4 },
                        { label: 'Pending',   data: mt.map(function (r) { return +r.pending; }),  borderColor: YELLOW, backgroundColor: 'rgba(245,158,11,0.08)', fill: true, tension: 0.35, pointRadius: 4 }
                    ]
                },
                options: { plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, responsive: true }
            });
        }

        destroyChart('chartDept');
        var deptSlice = reportData.byDept.slice(0, 10);
        toggleChartEmpty('chartDeptEmpty', 'chartDept', !deptSlice.length);
        if (deptSlice.length) {
            chartInstances.chartDept = new Chart(document.getElementById('chartDept'), {
                type: 'bar',
                data: {
                    labels: deptSlice.map(function (r) { return r.department_name; }),
                    datasets: [
                        { label: 'Total',    data: deptSlice.map(function (r) { return +r.total; }),    backgroundColor: 'rgba(0,98,204,0.7)' },
                        { label: 'Resolved', data: deptSlice.map(function (r) { return +r.resolved; }), backgroundColor: 'rgba(22,163,74,0.7)' }
                    ]
                },
                options: { indexAxis: 'y', plugins: { legend: { position: 'top' } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }, responsive: true }
            });
        }

        destroyChart('chartCat');
        var catSlice = reportData.byCategory.slice(0, 10);
        toggleChartEmpty('chartCatEmpty', 'chartCat', !catSlice.length);
        if (catSlice.length) {
            chartInstances.chartCat = new Chart(document.getElementById('chartCat'), {
                type: 'bar',
                data: {
                    labels: catSlice.map(function (r) { return r.category_name; }),
                    datasets: [{ label: 'Complaints', data: catSlice.map(function (r) { return +r.total; }), backgroundColor: 'rgba(245,158,11,0.75)', borderRadius: 4 }]
                },
                options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }, responsive: true }
            });
        }

        destroyChart('chartStaff');
        var staffSlice = reportData.byStaff.slice(0, 10);
        document.getElementById('staffChartWrap').style.display = staffSlice.length ? '' : 'none';
        if (staffSlice.length) {
            chartInstances.chartStaff = new Chart(document.getElementById('chartStaff'), {
                type: 'bar',
                data: {
                    labels: staffSlice.map(function (r) { return r.staff_name; }),
                    datasets: [
                        { label: 'Total',    data: staffSlice.map(function (r) { return +r.total; }),    backgroundColor: 'rgba(0,98,204,0.6)', borderRadius: 4 },
                        { label: 'Resolved', data: staffSlice.map(function (r) { return +r.resolved; }), backgroundColor: 'rgba(22,163,74,0.7)', borderRadius: 4 }
                    ]
                },
                options: { plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, responsive: true }
            });
        }
    }

    // Render charts immediately if analytics tab is active on load
    document.addEventListener('DOMContentLoaded', renderCharts);

    // ---- AJAX filtering (no page reload) ----
    function buildFilterParams() {
        var params = new URLSearchParams();
        var dept = document.getElementById('filterDepartment').value;
        var cat  = document.getElementById('filterCategory').value;
        var from = document.getElementById('filterDateFrom').value;
        var to   = document.getElementById('filterDateTo').value;
        if (dept) params.set('department', dept);
        if (cat)  params.set('category', cat);
        if (from) params.set('date_from', from);
        if (to)   params.set('date_to', to);
        return params;
    }

    function applyFilters() {
        var params   = buildFilterParams();
        var btn      = document.getElementById('applyFilterBtn');
        var btnLabel = btn.querySelector('.btn-label');
        btn.disabled = true;
        if (btnLabel) btnLabel.textContent = 'Applying...';

        fetch('ajax/get_report_data.php?' + params.toString())
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error('Request failed');

                reportData.stats          = data.stats;
                reportData.resolutionRate = data.resolutionRate;
                reportData.byDept         = data.byDept;
                reportData.byCategory     = data.byCategory;
                reportData.byPriority     = data.byPriority;
                reportData.byStaff        = data.byStaff;
                reportData.monthlyTrend   = data.monthlyTrend;
                reportData.isFiltered     = data.isFiltered;
                reportData.filters        = data.filters;

                renderStats();
                renderPriorityCards();
                rebuildTables();
                renderCharts();

                var url = new URL(window.location.href);
                ['department', 'category', 'date_from', 'date_to'].forEach(function (k) { url.searchParams.delete(k); });
                params.forEach(function (v, k) { url.searchParams.set(k, v); });
                history.replaceState(null, '', url.toString());
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Failed to apply filters', text: 'Please try again.' });
            })
            .finally(function () {
                btn.disabled = false;
                if (btnLabel) btnLabel.textContent = 'Apply Filter';
            });
    }

    document.getElementById('filterForm').addEventListener('submit', function (e) {
        e.preventDefault();
        applyFilters();
    });

    document.getElementById('resetFilterBtn').addEventListener('click', function (e) {
        e.preventDefault();
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterCategory').value   = '';
        document.getElementById('filterDateFrom').value   = '';
        document.getElementById('filterDateTo').value     = '';
        applyFilters();
    });

    // Auto-apply as soon as a filter changes (debounced so a quick run of
    // changes - e.g. typing a date - only fires one request).
    var _autoApplyTimer = null;
    function scheduleAutoApply() {
        clearTimeout(_autoApplyTimer);
        _autoApplyTimer = setTimeout(applyFilters, 300);
    }
    ['filterDepartment', 'filterCategory', 'filterDateFrom', 'filterDateTo'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', scheduleAutoApply);
    });

    // Export helpers

    var _REPORT_TITLE   = 'SCMRS - Complaints Report';
    var _HEAD_COLOR     = [30, 58, 95];
    var _ALT_ROW        = [245, 248, 252];

    function _filterLabel() {
        var f = reportData.filters;
        var parts = [];
        if (f.department) parts.push('Dept #' + f.department);
        if (f.category)   parts.push('Cat #' + f.category);
        if (f.date_from)  parts.push('From: ' + f.date_from);
        if (f.date_to)    parts.push('To: ' + f.date_to);
        return parts.length ? parts.join(' | ') : 'No filters applied';
    }

    function _stripCell(html) {
        var d = document.createElement('div');
        d.innerHTML = html;
        return (d.textContent || d.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function _tableHeaders(tableId) {
        return Array.from(document.querySelectorAll('#' + tableId + ' thead th'))
            .map(function(th) { return th.textContent.trim(); });
    }

    function _tableRows(tableId) {
        var rows = [];
        var dt = $('#' + tableId).DataTable();
        dt.rows({ search: 'applied' }).every(function() {
            rows.push(Array.from(this.node().querySelectorAll('td'))
                .map(function(td) { return _stripCell(td.innerHTML); }));
        });
        return rows;
    }

    function _pdfHeader(doc, subtitle) {
        doc.setFontSize(16);
        doc.setTextColor(30, 58, 95);
        doc.text(_REPORT_TITLE, 14, 14);
        if (subtitle) {
            doc.setFontSize(11);
            doc.text(subtitle, 14, 21);
        }
        doc.setFontSize(8);
        doc.setTextColor(130);
        doc.text('Generated: ' + new Date().toLocaleString() + '   |   Filters: ' + _filterLabel(),
            14, subtitle ? 27 : 21);
        doc.setTextColor(0);
        return subtitle ? 33 : 27;
    }

    function _summaryTable(doc, startY) {
        var s = reportData.stats;
        doc.autoTable({
            head: [['Total', 'Pending', 'In Progress', 'Resolved', 'Rejected', 'Resolution Rate']],
            body: [[
                String(s.total),
                String(s.pending),
                String(s.in_progress),
                String(s.resolved),
                String(s.rejected),
                reportData.resolutionRate + '%'
            ]],
            startY: startY,
            styles: { fontSize: 9, halign: 'center', fontStyle: 'bold' },
            headStyles: { fillColor: _HEAD_COLOR, textColor: 255, halign: 'center' }
        });
        return doc.lastAutoTable.finalY;
    }

    // CSV export 
    function exportCSV(tableId, filename) {
        var headers = _tableHeaders(tableId);
        var rows    = _tableRows(tableId);
        var lines   = [headers.map(function(h) { return '"' + h.replace(/"/g, '""') + '"'; }).join(',')];
        rows.forEach(function(row) {
            lines.push(row.map(function(c) { return '"' + c.replace(/"/g, '""') + '"'; }).join(','));
        });
        var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var a    = document.createElement('a');
        a.href   = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    // Full report PDF (all tables) 
    function exportFullReportPDF() {
        var doc = new window.jspdf.jsPDF({ orientation: 'landscape' });
        var y   = _pdfHeader(doc, 'Full Complaints Report');
        y = _summaryTable(doc, y + 4);

        var sections = [
            { id: 'tbl_department', title: 'Complaints by Department' },
            { id: 'tbl_category',   title: 'Complaints by Category' },
            { id: 'tbl_staff',      title: 'Staff Performance' },
            { id: 'tbl_monthly',    title: 'Monthly Trend' },
            { id: 'tbl_oldest',     title: 'Oldest Pending Complaints' }
        ];

        var pageH = doc.internal.pageSize.getHeight();

        sections.forEach(function(s) {
            var rows = _tableRows(s.id);
            if (!rows.length) return;

            if (y + 30 > pageH - 10) { doc.addPage(); y = 15; }

            doc.setFontSize(11);
            doc.setTextColor(30, 58, 95);
            doc.text(s.title, 14, y + 8);

            doc.autoTable({
                head: [_tableHeaders(s.id)],
                body: rows,
                startY: y + 12,
                styles: { fontSize: 8, cellPadding: 2.5 },
                headStyles: { fillColor: _HEAD_COLOR, textColor: 255 },
                alternateRowStyles: { fillColor: _ALT_ROW },
                didDrawPage: function() { y = 10; }
            });
            y = doc.lastAutoTable.finalY + 6;
        });

        doc.save('scmrs-report-' + new Date().toISOString().slice(0, 10) + '.pdf');
    }

    // Analytics charts PDF 
    function exportAnalyticsPDF() {
        renderCharts();

        var doc   = new window.jspdf.jsPDF({ orientation: 'landscape' });
        var pageW = doc.internal.pageSize.getWidth();
        var pageH = doc.internal.pageSize.getHeight();
        var y     = _pdfHeader(doc, 'Analytics Report');
        y = _summaryTable(doc, y + 4) + 10;

        var charts = [
            { id: 'chartStatus',   title: 'Complaints by Status' },
            { id: 'chartPriority', title: 'Priority Distribution' },
            { id: 'chartMonthly',  title: 'Monthly Trend' },
            { id: 'chartDept',     title: 'Top Departments' },
            { id: 'chartCat',      title: 'Top Categories' },
            { id: 'chartStaff',    title: 'Staff Performance' }
        ];

        // Two charts per row (side by side)
        var col = 0;
        var rowH = 0;

        charts.forEach(function(c) {
            var canvas = document.getElementById(c.id);
            if (!canvas) return;

            var imgData = canvas.toDataURL('image/png');
            var colW    = (pageW - 28) / 2;
            var imgH    = Math.min(colW * (canvas.height / canvas.width), 90);
            var x       = col === 0 ? 14 : 14 + colW + 7;

            if (col === 0 && y + imgH + 12 > pageH - 10) {
                doc.addPage();
                y = 15;
            }

            doc.setFontSize(9);
            doc.setTextColor(30, 58, 95);
            doc.text(c.title, x, y);
            doc.addImage(imgData, 'PNG', x, y + 3, colW, imgH);

            if (col === 0) {
                rowH = imgH;
                col  = 1;
            } else {
                y   += Math.max(rowH, imgH) + 14;
                col  = 0;
                rowH = 0;
            }
        });

        // If last row only had one chart, advance y
        if (col === 1) y += rowH + 14;

        doc.save('scmrs-analytics-' + new Date().toISOString().slice(0, 10) + '.pdf');
    }
    </script>

</body>
</html>
