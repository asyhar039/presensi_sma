<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if student is logged in
if (!isset($_SESSION['student']['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Anda harus login terlebih dahulu.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit();
}

$siswa_id = intval($_SESSION['student']['id']);
$nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
$nisn = sanitize($_POST['nisn'] ?? '');
$no_telp = sanitize($_POST['no_telp'] ?? '');
$alamat = sanitize($_POST['alamat'] ?? '');

if (empty($nama_lengkap) || empty($nisn)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama Lengkap dan NISN tidak boleh kosong.']);
    exit();
}

// Validate NISN format (only numbers, min 10 chars for standard NISN)
if (!preg_match('/^[0-9]+$/', $nisn)) {
    echo json_encode(['status' => 'error', 'message' => 'NISN hanya boleh berisi angka.']);
    exit();
}

try {
    // Check if NISN is already taken by another student
    $stmt_check = $conn->prepare("SELECT id FROM siswa WHERE nisn = ? AND id != ?");
    $stmt_check->bind_param("si", $nisn, $siswa_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'NISN tersebut sudah digunakan oleh siswa lain.']);
        exit();
    }

    // Update siswa profile
    $stmt_update = $conn->prepare("
        UPDATE siswa 
        SET nama_lengkap = ?, nisn = ?, no_telp = ?, alamat = ? 
        WHERE id = ?
    ");
    $stmt_update->bind_param("ssssi", $nama_lengkap, $nisn, $no_telp, $alamat, $siswa_id);
    
    if ($stmt_update->execute()) {
        // Update session
        $_SESSION['student']['nama_lengkap'] = $nama_lengkap;
        $_SESSION['student']['nisn'] = $nisn;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Profil dan NISN (Password) berhasil diperbarui!',
            'data' => [
                'nama_lengkap' => $nama_lengkap,
                'nisn' => $nisn
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui profil di database.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan server: ' . $e->getMessage()]);
}
?>
