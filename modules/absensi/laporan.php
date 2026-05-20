<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();

$bulan_indo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get parameters
$siswa_id = intval($_GET['siswa_id'] ?? 0);
$bulan = intval($_GET['bulan'] ?? date('m'));
$tahun = intval($_GET['tahun'] ?? date('Y'));

// Get all kelas for filter/validation based on role
if ($user['role'] === 'admin') {
    $kelas_list = getAllKelas($conn);
} else {
    try {
        $g_res = query("SELECT id FROM guru WHERE user_id = {$user['id']}", $conn);
        if ($g_res && $g_res->num_rows > 0) {
            $guru_id = $g_res->fetch_assoc()['id'];
            $res_opt = query("SELECT DISTINCT k.* FROM kelas k 
                            LEFT JOIN jadwal_pelajaran jp ON k.id = jp.kelas_id 
                            WHERE jp.guru_id = $guru_id OR k.guru_id = $guru_id
                            ORDER BY k.tingkat, k.nama_kelas", $conn);
            $kelas_list = $res_opt->fetch_all(MYSQLI_ASSOC);
        } else {
            $kelas_list = [];
        }
    } catch (Exception $e) {
        $kelas_list = [];
    }
}

// Get siswa info & validate access
if ($siswa_id > 0) {
    $result = query("SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE s.id = $siswa_id", $conn);
    if ($result->num_rows > 0) {
        $siswa = $result->fetch_assoc();
        
        // If not admin, check if guru has access to this student's class
        if ($user['role'] !== 'admin') {
            $has_access = false;
            foreach ($kelas_list as $k) {
                if ($k['id'] === intval($siswa['kelas_id'])) {
                    $has_access = true;
                    break;
                }
            }
            if (!$has_access) {
                header("Location: index.php");
                exit();
            }
        }
    } else {
        header("Location: index.php");
        exit();
    }
} else {
    $siswa = null;
}

// Get rekap data
$rekap = null;
if ($siswa_id > 0) {
    $result = query("SELECT * FROM rekap_absensi WHERE siswa_id = $siswa_id AND bulan = $bulan AND tahun = $tahun", $conn);
    if ($result->num_rows > 0) {
        $rekap = $result->fetch_assoc();
    }
}

// Get siswa list by kelas & validate access
$kelas_id = intval($_GET['kelas_id'] ?? 0);
if ($kelas_id > 0) {
    if ($user['role'] !== 'admin') {
        $has_access = false;
        foreach ($kelas_list as $k) {
            if ($k['id'] === $kelas_id) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            header("Location: index.php");
            exit();
        }
    }
    try {
        $result = query("SELECT s.*, 
                               r.total_hadir, r.total_izin, r.total_sakit, r.total_alfa 
                        FROM siswa s 
                        LEFT JOIN rekap_absensi r ON s.id = r.siswa_id AND r.bulan = $bulan AND r.tahun = $tahun 
                        WHERE s.kelas_id = $kelas_id AND s.is_active = 1 
                        ORDER BY s.nama_lengkap", $conn);
        $siswa_list = $result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        $siswa_list = [];
    }
} else {
    $siswa_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Absensi - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-card { background: white; padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 15px; border-left: 5px solid; }
        .stat-card.hadir { border-left-color: #28a745; }
        .stat-card.izin { border-left-color: #17a2b8; }
        .stat-card.sakit { border-left-color: #ffc107; }
        .stat-card.alfa { border-left-color: #dc3545; }
        .stat-number { font-size: 32px; font-weight: 700; }
        .stat-label { color: #777; font-size: 14px; margin-top: 10px; }
        .table { background: white; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h5>📚 ABSENSI SMA</h5></div>
        <nav>
            <a href="../../public/dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="../siswa/" class="nav-link"><i class="fas fa-users"></i> Data Siswa</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../guru/" class="nav-link"><i class="fas fa-chalkboard-user"></i> Data Guru</a>
            <?php endif; ?>
            <a href="../kelas/" class="nav-link"><i class="fas fa-school"></i> Data Kelas</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
                <a href="../jadwal/" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <?php endif; ?>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link active"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>📊 Laporan Rekap Absensi</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <div class="card mb-4">
            <div class="card-body p-4">
                <h5 class="mb-4">Filter Data</h5>
                <form method="GET" class="row">
                    <div class="col-md-3">
                        <label class="form-label">Pilih Kelas</label>
                        <select class="form-select" name="kelas_id" id="kelasSelect">
                            <option value="">- Pilih Kelas -</option>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_id === $kelas['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bulan</label>
                        <select class="form-select" name="bulan">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m === $bulan ? 'selected' : ''; ?>>
                                    <?php echo $bulan_indo[$m]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <select class="form-select" name="tahun">
                            <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $tahun ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($kelas_id > 0 && !empty($siswa_list)): ?>
            <div class="card">
                <div class="card-body p-4">
                    <?php
                    $kelas_nama_display = '';
                    foreach ($kelas_list as $k) {
                        if ($k['id'] === $kelas_id) {
                            $kelas_nama_display = $k['nama_kelas'];
                            break;
                        }
                    }
                    ?>
                    <h5 class="mb-4">Rekap Absensi Siswa - <?php echo htmlspecialchars($kelas_nama_display); ?> (<?php echo $bulan_indo[$bulan] . " " . $tahun; ?>)</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th style="text-align: center;">Hadir</th>
                                    <th style="text-align: center;">Izin</th>
                                    <th style="text-align: center;">Sakit</th>
                                    <th style="text-align: center;">Alfa</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_list as $idx => $s): ?>
                                    <tr>
                                        <td><?php echo ($idx + 1); ?></td>
                                        <td><?php echo htmlspecialchars($s['nisn']); ?></td>
                                        <td><?php echo htmlspecialchars($s['nama_lengkap']); ?></td>
                                        <td style="text-align: center;"><span class="badge bg-success"><?php echo isset($s['total_hadir']) ? $s['total_hadir'] : 0; ?></span></td>
                                        <td style="text-align: center;"><span class="badge bg-info"><?php echo isset($s['total_izin']) ? $s['total_izin'] : 0; ?></span></td>
                                        <td style="text-align: center;"><span class="badge bg-warning"><?php echo isset($s['total_sakit']) ? $s['total_sakit'] : 0; ?></span></td>
                                        <td style="text-align: center;"><span class="badge bg-danger"><?php echo isset($s['total_alfa']) ? $s['total_alfa'] : 0; ?></span></td>
                                        <td>
                                            <a href="laporan.php?siswa_id=<?php echo $s['id']; ?>&bulan=<?php echo $bulan; ?>&tahun=<?php echo $tahun; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($siswa_id > 0 && $rekap): ?>
            <div class="card">
                <div class="card-body p-4">
                    <h5 class="mb-4">Detail Absensi: <?php echo htmlspecialchars($siswa['nama_lengkap']); ?></h5>
                    <p class="text-muted">NISN: <?php echo htmlspecialchars($siswa['nisn']); ?> | Kelas: <?php echo htmlspecialchars($siswa['nama_kelas']); ?></p>
                    
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card hadir">
                                <div class="stat-number"><?php echo $rekap['total_hadir']; ?></div>
                                <div class="stat-label">Hadir</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card izin">
                                <div class="stat-number"><?php echo $rekap['total_izin']; ?></div>
                                <div class="stat-label">Izin</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card sakit">
                                <div class="stat-number"><?php echo $rekap['total_sakit']; ?></div>
                                <div class="stat-label">Sakit</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card alfa">
                                <div class="stat-number"><?php echo $rekap['total_alfa']; ?></div>
                                <div class="stat-label">Alfa</div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Get detail absensi
                    $start_date = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
                    $end_date = date('Y-m-t', strtotime($start_date));
                    $detail_result = query("SELECT a.*, m.nama_mapel FROM absensi a 
                                           JOIN jadwal_pelajaran jp ON a.jadwal_id = jp.id 
                                           JOIN mata_pelajaran m ON jp.mata_pelajaran_id = m.id 
                                           WHERE a.siswa_id = $siswa_id 
                                           AND a.tanggal BETWEEN '$start_date' AND '$end_date' 
                                           ORDER BY a.tanggal DESC", $conn);
                    $details = $detail_result->fetch_all(MYSQLI_ASSOC);
                    ?>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($details)): ?>
                                    <tr><td colspan="4" class="text-center py-3">Tidak ada data absensi</td></tr>
                                <?php else: ?>
                                    <?php foreach ($details as $detail): ?>
                                        <tr>
                                            <td><?php echo formatDateIndonesia($detail['tanggal']); ?></td>
                                            <td><?php echo htmlspecialchars($detail['nama_mapel']); ?></td>
                                            <td><?php echo getStatusBadge($detail['status']); ?></td>
                                            <td><?php echo htmlspecialchars($detail['keterangan'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Silakan pilih kelas untuk melihat data rekap absensi
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
