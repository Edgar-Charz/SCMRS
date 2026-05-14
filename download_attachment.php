<?php
require_once 'config/Database.php';
require_once 'classes/User.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    die("Unauthorized access or missing file ID.");
}

$db = new Database();
$conn = $db->connect();
$user = new User($conn);

$attachment_id = intval($_GET['id']);
$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

$file = $user->getAttachmentById($attachment_id, $user_id, $user_role);
if (!$file) {
    die("You do not have permission to view this file or the file does not exist.");
}

$file_path = $file['file_path'];
$file_name = $file['file_name'];

if (file_exists($file_path)) {
    // Set Headers
    Header('Content-Description: File Transfer');
    Header('Content-Type: ' . $file['file_type']);

    // Use 'inline' to view in browser, 'attachment' to force download
    $disposition = isset($_GET['view']) ? 'inline' : 'attachment';
    Header('Content-Disposition: ' . $disposition . '; filename="' . $file_name . '"');

    Header('Expires: 0');
    Header('Cache-Control: must-revalidate');
    Header('Pragma: public');
    Header('Content-Length: ' . filesize($file_path));

    // Output the file
    Readfile($file_path);
    exit;
} else {
    die("The file does not exist on the server.");
}
