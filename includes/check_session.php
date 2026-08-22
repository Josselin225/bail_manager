<?php
$timeout_duration = 600;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function _redirect(string $url): void {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    }
    exit;
}

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) >= $timeout_duration) {
        session_unset();
        session_destroy();
        _redirect('../pages/login.php?error=timeout');
    }
    $_SESSION['last_activity'] = time();
} else {
    _redirect('../pages/login.php');
}
?>