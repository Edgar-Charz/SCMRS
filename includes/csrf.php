<?php
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (!$stored || !hash_equals($stored, $submitted)) {
        http_response_code(403);
        die("Security check failed. Please go back and try again.");
    }
}
