<?php
// Collect all flash messages from session and local vars
$_toasts = [];

if (!empty($_SESSION['message'])) {
    $_toasts[] = ['type' => 'success', 'text' => $_SESSION['message']];
    unset($_SESSION['message']);
}
if (!empty($_SESSION['message_error'])) {
    $_toasts[] = ['type' => 'danger', 'text' => $_SESSION['message_error']];
    unset($_SESSION['message_error']);
}
if (!empty($_SESSION['message_warning'])) {
    $_toasts[] = ['type' => 'warning', 'text' => $_SESSION['message_warning']];
    unset($_SESSION['message_warning']);
}
if (!empty($_SESSION['message_info'])) {
    $_toasts[] = ['type' => 'info text-dark', 'text' => $_SESSION['message_info']];
    unset($_SESSION['message_info']);
}

// Pick up inline $message / $error set in the calling page
if (!empty($message ?? '')) {
    $already = array_filter($_toasts, fn($t) => $t['text'] === $message);
    if (empty($already)) $_toasts[] = ['type' => 'success', 'text' => $message];
    $message = '';
}
if (!empty($error ?? '')) {
    $already = array_filter($_toasts, fn($t) => $t['text'] === $error);
    if (empty($already)) $_toasts[] = ['type' => 'danger', 'text' => $error];
    $error = '';
}

if (empty($_toasts)) return;

$_iconMap = [
    'success'       => 'fa-check-circle',
    'danger'        => 'fa-exclamation-circle',
    'warning'       => 'fa-exclamation-triangle',
    'info text-dark'=> 'fa-info-circle',
];
?>
<div class="position-fixed top-0 start-50 translate-middle-x p-3"
    style="z-index:11000; min-width:400px; max-width:600px;" id="flashToastStack">
    <?php foreach ($_toasts as $_t):
        $bgType = explode(' ', $_t['type'])[0];
        $icon   = $_iconMap[$_t['type']] ?? 'fa-info-circle';
    ?>
    <div class="toast show align-items-center text-white bg-<?= $_t['type'] ?> border-0 mb-2 shadow-sm"
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                <i class="fas <?= $icon ?> me-2"></i><?= htmlspecialchars($_t['text']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    setTimeout(function () {
        document.querySelectorAll('#flashToastStack .toast.show').forEach(function (t) {
            if (window.bootstrap && bootstrap.Toast) {
                bootstrap.Toast.getOrCreateInstance(t).hide();
            } else {
                t.style.transition = 'opacity .4s';
                t.style.opacity = '0';
                setTimeout(function () { t.style.display = 'none'; }, 420);
            }
        });
    }, 5000);
})();
</script>