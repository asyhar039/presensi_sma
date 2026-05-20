<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/helpers.php';

requireLogin();

$user = getCurrentUser();
$message = getFlashMessage();

// Fetch guru data if user is a guru
$guru_data = null;
if ($user['role'] === 'guru') {
    try {
        $g = query("SELECT g.id FROM guru g WHERE g.user_id = {$user['id']}", $conn);
        if ($g && $g->num_rows > 0) {
            $guru_data = $g->fetch_assoc();
        }
    } catch (Exception $e) {
        // ignore
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="logo"><h5>📚 ABSENSI SMA</h5></div>
    <nav>
        <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="../modules/siswa/" class="nav-link"><i class="fas fa-users"></i> Data Siswa</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="../modules/guru/" class="nav-link"><i class="fas fa-chalkboard-user"></i> Data Guru</a>
        <?php endif; ?>
        <a href="../modules/kelas/" class="nav-link"><i class="fas fa-school"></i> Data Kelas</a>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="../modules/mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
        <?php endif; ?>
        <a href="../modules/absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
        <a href="../modules/absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
        <hr style="border-color: rgba(255,255,255,0.2);">
        <a href="../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>
<div class="main-content">
    <div class="topbar">
        <h4>👋 Selamat datang, <?php echo htmlspecialchars($user['nama_lengkap'] ?? $user['username']); ?></h4>
    </div>
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <div class="row g-3">
        <!-- Statistik Umum -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-label">Absensi Hari Ini</div>
                    <div class="stat-number">
                        <?php
                        try {
                            $today = date('Y-m-d');
                            // If guru, limit to their classes
                            if ($guru_data) {
                                $sql = "SELECT COUNT(*) as cnt FROM absensi a JOIN jadwal_pelajaran jp ON a.jadwal_id = jp.id WHERE jp.guru_id = {$guru_data['id']} AND a.tanggal = '$today'";
                            } else {
                                $sql = "SELECT COUNT(*) as cnt FROM absensi WHERE tanggal = '$today'";
                            }
                            $res = query($sql, $conn);
                            echo $res->fetch_assoc()['cnt'];
                        } catch (Exception $e) { echo '0'; }
                        ?>
                    </div>
                </div>
                <i class="fas fa-calendar" style="font-size: 40px; opacity: 0.2; position: absolute; top: 10px; right: 10px;"></i>
            </div>
        </div>
        <?php if ($guru_data): ?>
        <!-- Guru specific cards -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-label">Mata Pelajaran Saya</div>
                    <div class="stat-number">
                        <?php
                        try {
                            $sql = "SELECT COUNT(*) as cnt FROM mata_pelajaran WHERE guru_id = {$guru_data['id']}";
                            $res = query($sql, $conn);
                            echo $res->fetch_assoc()['cnt'];
                        } catch (Exception $e) { echo '0'; }
                        ?>
                    </div>
                </div>
                <i class="fas fa-book" style="font-size: 40px; opacity: 0.2; position: absolute; top: 10px; right: 10px;"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-label">Kelas Saya (Wali Kelas)</div>
                    <div class="stat-number">
                        <?php
                        try {
                            $sql = "SELECT COUNT(*) as cnt FROM kelas WHERE guru_id = {$guru_data['id']}";
                            $res = query($sql, $conn);
                            echo $res->fetch_assoc()['cnt'];
                        } catch (Exception $e) { echo '-'; }
                        ?>
                    </div>
                </div>
                <i class="fas fa-school" style="font-size: 40px; opacity: 0.2; position: absolute; top: 10px; right: 10px;"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-label">Total Siswa Diajar</div>
                    <div class="stat-number">
                        <?php
                        try {
                            $sql = "SELECT COUNT(DISTINCT s.id) as cnt FROM siswa s JOIN kelas k ON s.kelas_id = k.id JOIN jadwal_pelajaran jp ON jp.kelas_id = k.id WHERE jp.guru_id = {$guru_data['id']} AND s.is_active = 1";
                            $res = query($sql, $conn);
                            echo $res->fetch_assoc()['cnt'];
                        } catch (Exception $e) { echo '0'; }
                        ?>
                    </div>
                </div>
                <i class="fas fa-users" style="font-size: 40px; opacity: 0.2; position: absolute; top: 10px; right: 10px;"></i>
            </div>
        </div>
<?php endif; ?>
    </div>
    <?php if ($guru_data): ?>
    <!-- Tabel Kelas yang diajar Guru -->
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fas fa-school me-2 text-primary"></i>Kelas yang Saya Ajar</h5>
<?php
$kelas_list = query("SELECT DISTINCT k.nama_kelas, k.tingkat, k.jurusan, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.is_active = 1) as jumlah_siswa, mp.nama_mapel, CASE WHEN k.guru_id = {$guru_data['id']} THEN 'Wali Kelas' ELSE 'Pengajar' END as status_kelas FROM kelas k JOIN jadwal_pelajaran jp ON jp.kelas_id = k.id JOIN mata_pelajaran mp ON jp.mata_pelajaran_id = mp.id WHERE jp.guru_id = {$guru_data['id']} ORDER BY k.tingkat, k.nama_kelas", $conn);
if ($kelas_list && $kelas_list->num_rows > 0):
            ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kelas</th>
                            <th>Tingkat</th>
                            <th>Jurusan</th>
                            <th>Mata Pelajaran</th>
                            <th>Jumlah Siswa</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $kelas_list->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['nama_kelas']); ?></strong></td>
                            <td>Kelas <?php echo htmlspecialchars($row['tingkat']); ?></td>
                            <td><?php echo htmlspecialchars($row['jurusan'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['nama_mapel']); ?></td>
                            <td><span class="badge bg-primary"><?php echo $row['jumlah_siswa']; ?> siswa</span></td>
                            <td>
                                <?php if($row['status_kelas'] === 'Wali Kelas'): ?>
                                    <span class="badge bg-success">Wali Kelas</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pengajar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>Belum ada jadwal pelajaran yang ditetapkan untuk Anda. Hubungi Admin untuk menambahkan jadwal.</div>
            <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <!-- Scan Barcode Presensi untuk Guru -->
    <?php if ($guru_data): ?>
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fas fa-camera me-2 text-info"></i>📷 Scan Barcode Presensi</h5>
            <p class="text-muted mb-3">Pilih kelas untuk memulai scanning barcode presensi siswa</p>
            
            <?php
            $kelas_scan_list = query("SELECT DISTINCT k.id, k.nama_kelas, k.tingkat, k.jurusan, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.is_active = 1) as jumlah_siswa FROM kelas k JOIN jadwal_pelajaran jp ON jp.kelas_id = k.id WHERE jp.guru_id = {$guru_data['id']} ORDER BY k.tingkat, k.nama_kelas", $conn);
            if ($kelas_scan_list && $kelas_scan_list->num_rows > 0):
            ?>
            <div class="row g-3">
                <?php while($kelas = $kelas_scan_list->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h6>
                            <p class="card-text text-muted small mb-2">
                                <i class="fas fa-graduation-cap"></i> Kelas <?php echo $kelas['tingkat']; ?> 
                                <?php if ($kelas['jurusan']): echo htmlspecialchars($kelas['jurusan']); endif; ?><br>
                                <i class="fas fa-users"></i> <?php echo $kelas['jumlah_siswa']; ?> siswa
                            </p>
                            <div class="mt-auto">
                                <a href="../modules/absensi/scan.php?kelas_id=<?php echo $kelas['id']; ?>" class="btn btn-info btn-sm w-100">
                                    <i class="fas fa-barcode"></i> Scan Barcode
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>Tidak ada kelas tersedia untuk scanning. Hubungi Admin untuk menambahkan jadwal pelajaran.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="card mt-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Aksi Cepat</h5>
            <div class="row">
                <?php if ($user['role'] === 'admin'): ?>
                <div class="col-md-3"><a href="../modules/siswa/" class="btn btn-primary w-100"><i class="fas fa-plus"></i> Tambah Siswa</a></div>
                <div class="col-md-3"><a href="../modules/guru/" class="btn btn-success w-100"><i class="fas fa-plus"></i> Tambah Guru</a></div>
                <div class="col-md-3"><a href="../modules/kelas/" class="btn btn-info w-100"><i class="fas fa-plus"></i> Tambah Kelas</a></div>
                <div class="col-md-3"><a href="../modules/mapel/" class="btn btn-warning w-100"><i class="fas fa-plus"></i> Tambah Mapel</a></div>
                <?php endif; ?>
                <div class="col-md-3" <?php if ($user['role'] !== 'admin') echo 'style="margin-top:0;"'; ?>><a href="../modules/absensi/" class="btn btn-danger w-100"><i class="fas fa-clipboard-list"></i> Input Absensi</a></div>
                <?php if ($guru_data): ?>
                <div class="col-md-3"><a href="../modules/absensi/laporan.php" class="btn btn-secondary w-100"><i class="fas fa-chart-bar"></i> Laporan Absensi</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
