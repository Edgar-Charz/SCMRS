<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
