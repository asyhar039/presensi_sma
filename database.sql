CREATE DATABASE IF NOT EXISTS absensi_sma;
USE absensi_sma;

-- Clean existing tables to avoid conflicts
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS rekap_absensi;
DROP TABLE IF EXISTS absensi;
DROP TABLE IF EXISTS jadwal_pelajaran;
DROP TABLE IF EXISTS mata_pelajaran;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS siswa;
DROP TABLE IF EXISTS guru;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'guru') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. GURU
CREATE TABLE guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nip VARCHAR(20) NOT NULL UNIQUE,
    no_telp VARCHAR(20) NULL,
    alamat TEXT NULL,
    jenis_kelamin ENUM('L','P') NULL,
    tanggal_lahir DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. KELAS
CREATE TABLE kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(20) NOT NULL,
    tingkat VARCHAR(10) NOT NULL,
    jurusan VARCHAR(50) NULL,
    guru_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE SET NULL
);

-- 4. SISWA
CREATE TABLE siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(20) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L','P') NOT NULL,
    tanggal_lahir DATE NOT NULL,
    no_telp VARCHAR(20) NULL,
    alamat TEXT NULL,
    kelas_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE
);

-- 5. MATA PELAJARAN
CREATE TABLE mata_pelajaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_mapel VARCHAR(50) NOT NULL,
    kode_mapel VARCHAR(20) NOT NULL UNIQUE,
    guru_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
);

-- 6. JADWAL PELAJARAN
CREATE TABLE jadwal_pelajaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_id INT NOT NULL,
    mata_pelajaran_id INT NOT NULL,
    guru_id INT NOT NULL,
    hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mata_pelajaran_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
);

-- 7. ABSENSI
CREATE TABLE absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    jadwal_id INT NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('Hadir','Izin','Sakit','Alfa') NOT NULL,
    keterangan TEXT NULL,
    dicatat_oleh INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (dicatat_oleh) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. REKAP ABSENSI
CREATE TABLE rekap_absensi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    bulan INT NOT NULL,
    tahun INT NOT NULL,
    total_hadir INT DEFAULT 0,
    total_izin INT DEFAULT 0,
    total_sakit INT DEFAULT 0,
    total_alfa INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- 9. ACTIVITY LOG
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- SEED DEFAULT ADMIN USER (Password: admin123)
INSERT INTO users (id, username, password, email, nama_lengkap, role, is_active) 
VALUES (1, 'admin', '$2y$10$iJ4qj6a.K325wcDyBd9kLOUCSTUQks2k2BK2M.nU.LWroZW/zJk12', 'admin@example.com', 'Administrator', 'admin', 1);

-- SEED DEFAULT GURU USER (Password: guru123)
INSERT INTO users (id, username, password, email, nama_lengkap, role, is_active) 
VALUES (2, 'guru', '$2y$10$wR9KPcPCXCgMzoq/Brc71u5mPhbvKXE8flGQpT88dl/qqnZ4qRAmG', 'guru@example.com', 'Guru Demo, S.Pd', 'guru', 1);

INSERT INTO guru (user_id, nip, no_telp, alamat) 
VALUES (2, '198701012010121001', '081234567890', 'Jl. Pendidikan No. 123');
