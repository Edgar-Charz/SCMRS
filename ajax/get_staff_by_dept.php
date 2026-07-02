<?php
require_once '../config/session.php';
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    echo json_encode(['success' => false, 'items' => []]);
    exit;
}

$deptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;
if ($deptId <= 0) {
    echo json_encode(['success' => false, 'items' => []]);
    exit;
}

$db   = new Database();
$conn = $db->connect();

$stmt = $conn->prepare(
    "SELECT s.staff_id, u.username, sr.role_name
     FROM staffs s
     JOIN users u ON u.user_id = s.staff_user_id
     LEFT JOIN staff_roles sr ON sr.role_id = s.staff_role_id
     WHERE s.staff_department_id = ?
       AND s.staff_approval_status = 1
       AND u.user_status = 'active'
     ORDER BY u.username"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'items' => []]);
    exit;
}

$stmt->bind_param('i', $deptId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'items' => $rows]);
