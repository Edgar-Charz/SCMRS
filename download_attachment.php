<?php
require_once 'config/Database.php';
require_once 'classes/User.php';
require_once 'config/session.php';

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

// Confirm the resolved path is inside uploads/ before serving
$upload_base = realpath(__DIR__ . '/uploads/complaints');
$real_file   = realpath($file_path);

if ($real_file === false || $upload_base === false || strpos($real_file, $upload_base . DIRECTORY_SEPARATOR) !== 0) {
    die("Invalid file path.");
}

if (file_exists($real_file)) {
    // Strip characters that could inject extra headers via Content-Disposition
    $safe_name = preg_replace('/[^\w.\-]/', '_', $file_name);

    Header('Content-Description: File Transfer');
    Header('Content-Type: ' . $file['file_type']);

    $disposition = isset($_GET['view']) ? 'inline' : 'attachment';
    Header('Content-Disposition: ' . $disposition . '; filename="' . $safe_name . '"');

    Header('Expires: 0');
    Header('Cache-Control: must-revalidate');
    Header('Pragma: public');
    Header('Content-Length: ' . filesize($real_file));

    readfile($real_file);
    exit;
} else {
    die("The file does not exist on the server.");
}
