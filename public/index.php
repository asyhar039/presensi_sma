<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
?>
