<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

requireAdmin();

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $stmt = $conn->prepare("DELETE FROM jadwal_pelajaran WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        header("Location: index.php?message=deleted");
        exit();
    } catch (Exception $e) {
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
