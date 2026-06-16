<?php
require_once '../config/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once '../config/Database.php';
require_once '../classes/Notification.php';

$db   = new Database();
$conn = $db->connect();
$notif = new Notification($conn);

echo json_encode(['count' => $notif->countUnread((int) $_SESSION['user_id'])]);
