<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

requireAdmin();

$guru_id = intval($_GET['id'] ?? 0);

try {
    $result = query("SELECT user_id FROM guru WHERE id = $guru_id", $conn);
    if ($result->num_rows > 0) {
        $guru = $result->fetch_assoc();
        query("UPDATE users SET is_active = 0 WHERE id = {$guru['user_id']}", $conn);
    }
    header("Location: index.php?deleted=success");
} catch (Exception $e) {
    header("Location: index.php?deleted=error");
}
exit();
?>
