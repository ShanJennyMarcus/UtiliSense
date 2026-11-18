<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user']['id'])) {
    log_activity($pdo, $_SESSION['user']['id'], 'LOGOUT', "Signed out: " . ($_SESSION['user']['name'] ?? 'User'));
}

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>