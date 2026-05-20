<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear student session
if (isset($_SESSION['student'])) {
    unset($_SESSION['student']);
}

$jadwal_id = intval($_GET['jadwal_id'] ?? 0);
$tanggal = urlencode($_GET['tanggal'] ?? '');

// Redirect back to check-in screen
header("Location: absen.php?jadwal_id=$jadwal_id&tanggal=$tanggal");
exit();
?>
