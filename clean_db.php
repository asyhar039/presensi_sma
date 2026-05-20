<?php
/**
 * Script Helper untuk Membersihkan dan Mengimpor Database
 */

$dir = 'C:/xampp/mysql/data/absensi_sma';
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $dir . '/' . $file;
            if (is_file($filePath)) {
                if (unlink($filePath)) {
                    echo "[1/3] Berhasil menghapus file: $file\n";
                } else {
                    echo "[1/3] Gagal menghapus file: $file\n";
                }
            }
        }
    }
} else {
    echo "[1/3] Direktori database tidak ditemukan atau sudah bersih\n";
}

$conn = new mysqli('localhost', 'root', '');
if ($conn->connect_error) {
    die("Koneksi MySQL Gagal: " . $conn->connect_error . "\n");
}

// Hapus dan buat ulang database agar bersih dari error InnoDB dictionary
if ($conn->query("DROP DATABASE IF EXISTS absensi_sma")) {
    echo "[2/3] Database absensi_sma berhasil di-drop/dibersihkan\n";
} else {
    echo "[2/3] Gagal men-drop database: " . $conn->error . "\n";
}

if ($conn->query("CREATE DATABASE absensi_sma")) {
    echo "[2/3] Database absensi_sma berhasil dibuat ulang\n";
} else {
    die("[2/3] Gagal membuat database: " . $conn->error . "\n");
}

// Hubungkan ke database baru
$conn->select_db('absensi_sma');

// Matikan foreign key checks saat import
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$sql = file_get_contents(__DIR__ . '/database.sql');

// Bersihkan statement CREATE DATABASE dan USE dari script SQL agar tidak konflik
$sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
$sql = preg_replace('/USE [^;]+;/i', '', $sql);

echo "[3/3] Mengimpor tabel dari database.sql...\n";
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    if ($conn->error) {
        echo "Error saat import: " . $conn->error . "\n";
    } else {
        echo "=== IMPORT BERHASIL SELESAI ===\n";
    }
} else {
    echo "Gagal menjalankan import: " . $conn->error . "\n";
}

// Hidupkan kembali foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
$conn->close();
