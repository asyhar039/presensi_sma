<?php
/**
 * Database Configuration & Helpers
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absensi_sma');

// Create connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4 (lebih mendukung karakter modern dibanding utf8 biasa)
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

/**
 * Helper function untuk query langsung (Sangat praktis untuk SELECT data umum)
 * Kamu cukup panggil: $hasil = query("SELECT * FROM kelas");
 */
function query($sql) {
    global $conn; // Mengambil variabel koneksi dari luar fungsi
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query Error: " . $conn->error);
    }
    return $result;
}

/**
 * Helper function untuk prepare statement (Wajib dipakai untuk Insert/Update/Delete demi mencegah SQL Injection)
 * Kamu cukup panggil: $stmt = prepare("INSERT INTO kelas (nama_kelas) VALUES (?)");
 */
function prepare($sql) {
    global $conn; // Mengambil variabel koneksi dari luar fungsi
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare Error: " . $conn->error);
    }
    return $stmt;
}
?>