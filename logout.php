<?php
session_start();

if (isset($_SESSION['user']['id'])) {
    require_once 'config/database.php';
    try {
        log_activity($pdo, (int)$_SESSION['user']['id'], 'LOGOUT', "Signed out: " . ($_SESSION['user']['name'] ?? 'User'));
    } catch (Exception $e) {}
}

$_SESSION = [];
session_unset();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $params['path'],
        'domain'   => $params['domain'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => !empty($params['samesite']) ? $params['samesite'] : 'Lax'
    ]);
}

session_destroy();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Location: login.php");
exit();