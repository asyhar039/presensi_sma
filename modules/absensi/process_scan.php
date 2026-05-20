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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

// Get and sanitize POST data
$nisn = sanitize($_POST['nisn'] ?? '');
$kelas_id = intval($_POST['kelas_id'] ?? 0);
$jadwal_id = intval($_POST['jadwal_id'] ?? 0);
$tanggal = sanitize($_POST['tanggal'] ?? '');
$user_id = $user['id'];

if (empty($nisn) || $kelas_id <= 0 || $jadwal_id <= 0 || empty($tanggal)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit();
}

try {
    // 1. Cek apakah siswa ada di kelas tersebut berdasarkan NISN
    $stmt = $conn->prepare("SELECT id, nama_lengkap FROM siswa WHERE nisn = ? AND kelas_id = ? AND is_active = 1");
    $stmt->bind_param("si", $nisn, $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Siswa dengan NISN ini tidak ditemukan di kelas ini']);
        exit();
    }

    $siswa = $result->fetch_assoc();
    $siswa_id = $siswa['id'];
    $nama_siswa = $siswa['nama_lengkap'];

    // 2. Cek apakah sudah ada absensi
    $stmt_check = $conn->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?");
    $stmt_check->bind_param("iis", $siswa_id, $jadwal_id, $tanggal);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    $status_hadir = 'Hadir';
    $keterangan = 'Via Barcode';

    if ($result_check->num_rows > 0) {
        // Update
        $stmt_update = $conn->prepare("UPDATE absensi SET status = ?, keterangan = ?, dicatat_oleh = ? WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?");
        $stmt_update->bind_param("sssisi", $status_hadir, $keterangan, $user_id, $siswa_id, $jadwal_id, $tanggal);
        $stmt_update->execute();
    } else {
        // Insert
        $stmt_insert = $conn->prepare("INSERT INTO absensi (siswa_id, jadwal_id, tanggal, status, keterangan, dicatat_oleh) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("iisssi", $siswa_id, $jadwal_id, $tanggal, $status_hadir, $keterangan, $user_id);
        $stmt_insert->execute();
    }

    // 3. Update Rekap
    $bulan = date('m', strtotime($tanggal));
    $tahun = date('Y', strtotime($tanggal));
    updateRekapAbsensi($siswa_id, $bulan, $tahun, $conn);

    echo json_encode([
        'status' => 'success', 
        'message' => 'Berhasil diabsen', 
        'nama_siswa' => $nama_siswa
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
