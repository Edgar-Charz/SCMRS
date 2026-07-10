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
//
// fastcgi_finish_request() only exists under PHP-FPM. Under Apache + mod_php
// (this project's default XAMPP setup) it doesn't exist, so there is no way
// to detach the client before sending mail here — doing so would block every
// request that queues a notification for as long as the real SMTP handshake
// takes. Only auto-flush inline where we can actually do it for free; on
// mod_php, leave queued mail for email_queue.php (admin-triggered) or a
// scheduled task to send out-of-band.
if (!defined('EMAIL_QUEUE_REGISTERED')) {
    define('EMAIL_QUEUE_REGISTERED', true);
    if (function_exists('fastcgi_finish_request')) {
        register_shutdown_function(function () {
            fastcgi_finish_request();
            require_once __DIR__ . '/../classes/EmailQueue.php';
            EmailQueue::processPending();
        });
    }
}
