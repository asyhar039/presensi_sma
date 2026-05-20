<?php
/**
 * Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Format date to Indonesian
 */
function formatDateIndonesia($date) {
    $hari = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
    $bulan = array(
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($date);
    $day = $hari[date('w', $timestamp)];
    $day_num = date('d', $timestamp);
    $month = $bulan[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return "$day, $day_num $month $year";
}

/**
 * Get status badge color
 */
function getStatusBadge($status) {
    $badges = [
        'Hadir' => '<span class="badge bg-success">Hadir</span>',
        'Izin' => '<span class="badge bg-info">Izin</span>',
        'Sakit' => '<span class="badge bg-warning">Sakit</span>',
        'Alfa' => '<span class="badge bg-danger">Alfa</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">-</span>';
}

/**
 * Get all guru
 */
function getAllGuru($conn) {
    try {
        $result = query("SELECT g.id, u.nama_lengkap, g.nip, u.email 
                        FROM guru g 
                        JOIN users u ON g.user_id = u.id 
                        WHERE u.is_active = 1 
                        ORDER BY u.nama_lengkap", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all siswa by kelas
 */
function getSiswaByKelas($kelas_id, $conn) {
    try {
        $result = query("SELECT * FROM siswa WHERE kelas_id = $kelas_id AND is_active = 1 ORDER BY nama_lengkap", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all kelas
 */
function getAllKelas($conn) {
    try {
        $result = query("SELECT k.*, u.nama_lengkap as guru_nama 
                        FROM kelas k 
                        LEFT JOIN guru g ON k.guru_id = g.id 
                        LEFT JOIN users u ON g.user_id = u.id 
                        ORDER BY k.tingkat, k.nama_kelas", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all mata pelajaran
 */
function getAllMataPelajaran($conn) {
    try {
        $result = query("SELECT m.*, u.nama_lengkap as guru_nama 
                        FROM mata_pelajaran m 
                        JOIN guru g ON m.guru_id = g.id 
                        JOIN users u ON g.user_id = u.id 
                        ORDER BY m.nama_mapel", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get mata pelajaran by guru
 */
function getMataPelajaranByGuru($guru_id, $conn) {
    try {
        $result = query("SELECT * FROM mata_pelajaran WHERE guru_id = $guru_id ORDER BY nama_mapel", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get jadwal by kelas
 */
function getJadwalByKelas($kelas_id, $conn) {
    try {
        $result = query("SELECT j.*, m.nama_mapel, u.nama_lengkap as guru_nama 
                        FROM jadwal_pelajaran j 
                        JOIN mata_pelajaran m ON j.mata_pelajaran_id = m.id 
                        JOIN guru g ON j.guru_id = g.id 
                        JOIN users u ON g.user_id = u.id 
                        WHERE j.kelas_id = $kelas_id 
                        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'), j.jam_mulai", $conn);
        return $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get rekap absensi siswa
 */
function getRekapAbsensiSiswa($siswa_id, $bulan, $tahun, $conn) {
    try {
        $result = query("SELECT * FROM rekap_absensi WHERE siswa_id = $siswa_id AND bulan = $bulan AND tahun = $tahun", $conn);
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Update rekap absensi
 */
function updateRekapAbsensi($siswa_id, $bulan, $tahun, $conn) {
    try {
        $start_date = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
        // Count each status
        $hadir = query("SELECT COUNT(*) as count FROM absensi 
                       WHERE siswa_id = $siswa_id AND status = 'Hadir' 
                       AND tanggal BETWEEN '$start_date' AND '$end_date'", $conn)->fetch_assoc()['count'];
        $izin = query("SELECT COUNT(*) as count FROM absensi 
                      WHERE siswa_id = $siswa_id AND status = 'Izin' 
                      AND tanggal BETWEEN '$start_date' AND '$end_date'", $conn)->fetch_assoc()['count'];
        $sakit = query("SELECT COUNT(*) as count FROM absensi 
                       WHERE siswa_id = $siswa_id AND status = 'Sakit' 
                       AND tanggal BETWEEN '$start_date' AND '$end_date'", $conn)->fetch_assoc()['count'];
        $alfa = query("SELECT COUNT(*) as count FROM absensi 
                      WHERE siswa_id = $siswa_id AND status = 'Alfa' 
                      AND tanggal BETWEEN '$start_date' AND '$end_date'", $conn)->fetch_assoc()['count'];
        
        // Check if rekap exists
        $check = query("SELECT id FROM rekap_absensi WHERE siswa_id = $siswa_id AND bulan = $bulan AND tahun = $tahun", $conn);
        
        if ($check->num_rows > 0) {
            // Update
            query("UPDATE rekap_absensi SET total_hadir = $hadir, total_izin = $izin, total_sakit = $sakit, total_alfa = $alfa 
                   WHERE siswa_id = $siswa_id AND bulan = $bulan AND tahun = $tahun", $conn);
        } else {
            // Insert
            query("INSERT INTO rekap_absensi (siswa_id, bulan, tahun, total_hadir, total_izin, total_sakit, total_alfa) 
                   VALUES ($siswa_id, $bulan, $tahun, $hadir, $izin, $sakit, $alfa)", $conn);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get guru info
 */
function getGuruInfo($guru_id, $conn) {
    try {
        $result = query("SELECT g.*, u.nama_lengkap, u.email, u.username 
                        FROM guru g 
                        JOIN users u ON g.user_id = u.id 
                        WHERE g.id = $guru_id", $conn);
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Generate random color
 */
function getRandomColor() {
    $colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
    return $colors[array_rand($colors)];
}
?>
