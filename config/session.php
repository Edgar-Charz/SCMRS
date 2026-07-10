<?php
require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    // Sessions expire after N minutes of inactivity (admin-configurable, default 30)
    $timeoutMinutes = 30;
    try {
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/../classes/Settings.php';
        $_sessionConn = (new Database())->connect();
        $timeoutMinutes = (new Settings($_sessionConn))->getSessionTimeoutMinutes();
    } catch (Throwable $e) {
        // Fall back to the default above if settings can't be read
    }
    ini_set('session.gc_maxlifetime', $timeoutMinutes * 60);

    session_set_cookie_params([
        'lifetime' => 0,        // Cookie expires when browser closes
        'httponly' => true,     // JS cannot read the session cookie
        'samesite' => 'Strict', // Cookie not sent on cross-site requests
    ]);

    session_start();
}

// Process pending emails after the response is sent (fire-after-response).
// register_shutdown_function runs once per request, so multiple includes are safe.
if (!defined('EMAIL_QUEUE_REGISTERED')) {
    define('EMAIL_QUEUE_REGISTERED', true);
    register_shutdown_function(function () {
        // Flush output to browser first so the user doesn't wait
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        require_once __DIR__ . '/../classes/EmailQueue.php';
        EmailQueue::processPending();
    });
}
