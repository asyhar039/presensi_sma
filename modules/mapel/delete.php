<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

requireAdmin();

$mapel_id = intval($_GET['id'] ?? 0);

try {
    query("DELETE FROM mata_pelajaran WHERE id = $mapel_id", $conn);
    header("Location: index.php?deleted=success");
} catch (Exception $e) {
    header("Location: index.php?deleted=error");
}
exit();
?>
