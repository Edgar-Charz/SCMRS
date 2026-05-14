<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Complaint.php';

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($categoryId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category_id', 'items' => []]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->connect();

    $complaint = new Complaint($conn);
    $items = $complaint->getSubcategoriesByCategoryId($categoryId);

    echo json_encode(['success' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'items' => []]);
}

