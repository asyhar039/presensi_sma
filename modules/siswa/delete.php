<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

requireAdmin();

$siswa_id = intval($_GET['id'] ?? 0);

if ($siswa_id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE siswa SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $siswa_id);
    $stmt->execute();
    
    header("Location: index.php?deleted=success");
} catch (Exception $e) {
    header("Location: index.php?deleted=error");
}
exit();
?>
