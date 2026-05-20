<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);
    exit();
}

$username = sanitize($_POST['username'] ?? ''); // Nama Lengkap
$password = sanitize($_POST['password'] ?? ''); // NISN

if (empty($username) || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama Lengkap dan NISN wajib diisi.']);
    exit();
}

try {
    // Trim and query case-insensitively
    $stmt = $conn->prepare("
        SELECT id, nama_lengkap, nisn, kelas_id, is_active 
        FROM siswa 
        WHERE LOWER(TRIM(nama_lengkap)) = LOWER(TRIM(?)) 
          AND TRIM(nisn) = TRIM(?) 
          AND is_active = 1
    ");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $siswa = $result->fetch_assoc();
        
        // Save to student session
        $_SESSION['student'] = [
            'id' => $siswa['id'],
            'nama_lengkap' => $siswa['nama_lengkap'],
            'nisn' => $siswa['nisn'],
            'kelas_id' => $siswa['kelas_id']
        ];

        echo json_encode([
            'status' => 'success',
            'message' => 'Login berhasil!',
            'data' => [
                'nama_siswa' => $siswa['nama_lengkap']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Kombinasi Nama Lengkap (Username) atau NISN (Password) salah atau tidak aktif.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Kesalahan server: ' . $e->getMessage()]);
}
?>
