<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$jadwal_id = intval($_GET['jadwal_id'] ?? 0);
$tanggal = sanitize($_GET['tanggal'] ?? '');

if ($jadwal_id <= 0 || empty($tanggal)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit();
}

try {
    // Get attendees for the given schedule and date
    $stmt = $conn->prepare("
        SELECT a.id, s.nama_lengkap, s.nisn, a.status, a.keterangan, DATE_FORMAT(a.created_at, '%H:%i') as waktu_absen 
        FROM absensi a 
        JOIN siswa s ON a.siswa_id = s.id 
        WHERE a.jadwal_id = ? AND a.tanggal = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("is", $jadwal_id, $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendees = [];
    while ($row = $result->fetch_assoc()) {
        $attendees[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $attendees
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
