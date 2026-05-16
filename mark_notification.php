<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

require_once 'config/Database.php';
require_once 'classes/Notification.php';

$db = new Database();
$conn = $db->connect();
$notif = new Notification($conn);

$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'mark_all') {
    $notif->markAllRead($userId);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_read') {
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id > 0) {
        $notif->markRead($id, $userId);
        $link = $notif->getLink($id, $userId);
        echo json_encode(['success' => true, 'link' => $link]);
        exit;
    }
}

echo json_encode(['success' => false]);
