<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/helpers.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set header to return JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit();
}

// Get and sanitize POST data
$nisn = sanitize($_POST['nisn'] ?? '');
if (empty($nisn) && isset($_SESSION['student']['nisn'])) {
    $nisn = $_SESSION['student']['nisn'];
}
$jadwal_id = intval($_POST['jadwal_id'] ?? 0);
$tanggal = sanitize($_POST['tanggal'] ?? '');

if (empty($nisn) || $jadwal_id <= 0 || empty($tanggal)) {
    echo json_encode(['status' => 'error', 'message' => 'Data input tidak lengkap.']);
    exit();
}

try {
    // 1. Dapatkan detail jadwal (kelas_id dan user_id milik guru)
    $stmt_jadwal = $conn->prepare("
        SELECT j.kelas_id, g.user_id 
        FROM jadwal_pelajaran j 
        JOIN guru g ON j.guru_id = g.id 
        WHERE j.id = ?
    ");
    $stmt_jadwal->bind_param("i", $jadwal_id);
    $stmt_jadwal->execute();
    $res_jadwal = $stmt_jadwal->get_result();

    if ($res_jadwal->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Sesi presensi pelajaran tidak valid atau tidak ditemukan.']);
        exit();
    }

    $jadwal_data = $res_jadwal->fetch_assoc();
    $kelas_id = $jadwal_data['kelas_id'];
    $guru_user_id = $jadwal_data['user_id']; // ID guru dari tabel users untuk 'dicatat_oleh'

    // 2. Validasi apakah NISN terdaftar aktif pada kelas ini
    $stmt_siswa = $conn->prepare("
        SELECT id, nama_lengkap 
        FROM siswa 
        WHERE nisn = ? AND kelas_id = ? AND is_active = 1
    ");
    $stmt_siswa->bind_param("si", $nisn, $kelas_id);
    $stmt_siswa->execute();
    $res_siswa = $stmt_siswa->get_result();

    if ($res_siswa->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'NISN tidak terdaftar atau tidak aktif di kelas untuk pelajaran ini.']);
        exit();
    }

    $siswa_data = $res_siswa->fetch_assoc();
    $siswa_id = $siswa_data['id'];
    $nama_siswa = $siswa_data['nama_lengkap'];

    // 3. Cek apakah sudah terabsen untuk sesi ini hari ini
    $stmt_check = $conn->prepare("
        SELECT id 
        FROM absensi 
        WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?
    ");
    $stmt_check->bind_param("iis", $siswa_id, $jadwal_id, $tanggal);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    $status_hadir = 'Hadir';
    $keterangan = 'Hadir Mandiri via QR';
    $waktu_sekarang = date('H:i');

    if ($res_check->num_rows > 0) {
        // Jika sudah ada, update statusnya menjadi Hadir Mandiri
        $stmt_update = $conn->prepare("
            UPDATE absensi 
            SET status = ?, keterangan = ?, dicatat_oleh = ? 
            WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?
        ");
        $stmt_update->bind_param("sssisi", $status_hadir, $keterangan, $guru_user_id, $siswa_id, $jadwal_id, $tanggal);
        $stmt_update->execute();
    } else {
        // Jika belum ada, buat record presensi baru
        $stmt_insert = $conn->prepare("
            INSERT INTO absensi (siswa_id, jadwal_id, tanggal, status, keterangan, dicatat_oleh) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("iisssi", $siswa_id, $jadwal_id, $tanggal, $status_hadir, $keterangan, $guru_user_id);
        $stmt_insert->execute();
    }

    // 4. Update Rekap bulanan untuk siswa bersangkutan
    $bulan = date('m', strtotime($tanggal));
    $tahun = date('Y', strtotime($tanggal));
    updateRekapAbsensi($siswa_id, $bulan, $tahun, $conn);

    // 5. Kembalikan response sukses
    echo json_encode([
        'status' => 'success',
        'message' => 'Presensi berhasil dicatat secara real-time.',
        'data' => [
            'nama_siswa' => $nama_siswa,
            'waktu_absen' => $waktu_sekarang
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan server: ' . $e->getMessage()]);
}
?>
