<?php
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

require_once '../config/Database.php';

$target = $_GET['target'] ?? '';
$period = $_GET['period'] ?? 'all';
$scope = $_GET['scope'] ?? 'sent';

$allowedPeriods = ['30', '60', '90', '180', '365', 'all'];
if (!in_array($period, $allowedPeriods, true)) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}
$days = $period === 'all' ? null : (int) $period;

$db = new Database();
$conn = $db->connect();

if ($target === 'audit') {
    require_once '../classes/Admin.php';
    $admin = new Admin($conn);
    $count = $admin->countPurgableActivityLogs($days);
} elseif ($target === 'email') {
    require_once '../classes/EmailQueue.php';
    $scope = in_array($scope, ['sent', 'sent_failed'], true) ? $scope : 'sent';
    $count = EmailQueue::countPurgable($conn, $scope, $days);
} else {
    echo json_encode(['success' => false, 'count' => 0]);
    exit;
}

echo json_encode(['success' => true, 'count' => $count]);
