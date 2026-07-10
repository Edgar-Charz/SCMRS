<?php
require_once '../config/session.php';
require_once '../config/Database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    echo json_encode(['success' => false, 'items' => []]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['success' => false, 'items' => []]);
    exit;
}

$db = new Database();
$conn = $db->connect();

$like = '%' . $q . '%';
$stmt = $conn->prepare(
    "SELECT s.student_id, u.username, s.student_registration_number
     FROM students s
     JOIN users u ON u.user_id = s.student_user_id
     WHERE u.user_status = 'active'
       AND (u.username LIKE ? OR s.student_registration_number LIKE ?)
     ORDER BY u.username
     LIMIT 10"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'items' => $rows]);
