<?php
require_once __DIR__ . '/../config/database.php';

try {
    $result = query("SELECT s.id, s.nisn, s.nama_lengkap, k.nama_kelas, s.is_active FROM siswa s JOIN kelas k ON s.kelas_id = k.id ORDER BY k.nama_kelas, s.nama_lengkap", $conn);
    
    echo "=== DATA SISWA TERDAFTAR DI DATABASE ===\n\n";
    if ($result->num_rows === 0) {
        echo "Belum ada data siswa di database.\n";
    } else {
        while ($row = $result->fetch_assoc()) {
            echo "Nama: " . $row['nama_lengkap'] . "\n";
            echo "NISN (Password): " . $row['nisn'] . "\n";
            echo "Kelas: " . $row['nama_kelas'] . "\n";
            echo "Status: " . ($row['is_active'] ? 'Aktif' : 'Tidak Aktif') . "\n";
            echo "----------------------------------------\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
