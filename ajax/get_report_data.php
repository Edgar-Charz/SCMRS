<?php
require_once '../config/session.php';
require_once '../config/Database.php';
require_once '../classes/User.php';
require_once '../classes/Admin.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db    = new Database();
$conn  = $db->connect();
$admin = new Admin($conn);

$filterDept     = isset($_GET['department']) && $_GET['department'] !== '' ? (int)$_GET['department'] : null;
$filterCategory = isset($_GET['category'])   && $_GET['category']   !== '' ? (int)$_GET['category']   : null;
$filterDateFrom = isset($_GET['date_from'])  && $_GET['date_from']  !== '' ? $_GET['date_from']        : null;
$filterDateTo   = isset($_GET['date_to'])    && $_GET['date_to']    !== '' ? $_GET['date_to']          : null;

$stats        = $admin->getReportStats($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byDept       = $admin->getReportByDepartment($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byCategory   = $admin->getReportByCategory($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byPriority   = $admin->getReportByPriority($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$byStaff      = $admin->getReportByStaff($filterDept, $filterCategory, $filterDateFrom, $filterDateTo);
$monthlyTrend = $admin->getReportMonthlyTrend($filterDateFrom, $filterDateTo);

$total          = (int)$stats['total'];
$resolutionRate = $total > 0 ? round(($stats['resolved'] / $total) * 100, 1) : 0;

echo json_encode([
    'success'        => true,
    'stats'          => $stats,
    'resolutionRate' => $resolutionRate,
    'byDept'         => $byDept,
    'byCategory'     => $byCategory,
    'byPriority'     => $byPriority,
    'byStaff'        => $byStaff,
    'monthlyTrend'   => $monthlyTrend,
    'isFiltered'     => (bool)($filterDept || $filterCategory || $filterDateFrom || $filterDateTo),
    'filters'        => [
        'department' => $filterDept,
        'category'   => $filterCategory,
        'date_from'  => $filterDateFrom,
        'date_to'    => $filterDateTo,
    ],
]);
