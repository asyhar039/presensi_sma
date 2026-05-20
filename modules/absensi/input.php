<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();
$kelas_id = intval($_GET['kelas_id'] ?? 0);

if ($kelas_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get kelas info
try {
    $result = query("SELECT * FROM kelas WHERE id = $kelas_id", $conn);
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    $kelas = $result->fetch_assoc();
} catch (Exception $e) {
    header("Location: index.php");
    exit();
}

// Get siswa
$siswa_list = getSiswaByKelas($kelas_id, $conn);

// Get jadwal (for guru)
if ($user['role'] !== 'admin') {
    $user_id = $user['id'];
    $result = query("SELECT g.id FROM guru g JOIN users u ON g.user_id = u.id WHERE u.id = $user_id", $conn);
    if ($result->num_rows > 0) {
        $guru = $result->fetch_assoc();
        $jadwal_result = query("SELECT * FROM jadwal_pelajaran WHERE kelas_id = $kelas_id AND guru_id = {$guru['id']}", $conn);
        $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $jadwal_list = [];
    }
} else {
    // Admin can see all jadwal
    $jadwal_result = query("SELECT * FROM jadwal_pelajaran WHERE kelas_id = $kelas_id", $conn);
    $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
}

$message = getFlashMessage();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token tidak valid';
    } else {
        $tanggal = sanitize($_POST['tanggal'] ?? '');
        $jadwal_id = intval($_POST['jadwal_id'] ?? 0);

        if (empty($tanggal) || $jadwal_id <= 0) {
            $error = 'Tanggal dan jadwal harus dipilih';
        } else {
            try {
                // Process absensi
                foreach ($siswa_list as $siswa) {
                    $siswa_id = $siswa['id'];
                    $status = sanitize($_POST["status_$siswa_id"] ?? 'Hadir');
                    $keterangan = sanitize($_POST["keterangan_$siswa_id"] ?? '');
                    $user_id = $user['id'];

                    // Check if already exists
                    $check = query("SELECT id FROM absensi WHERE siswa_id = $siswa_id AND jadwal_id = $jadwal_id AND tanggal = '$tanggal'", $conn);
                    
                    if ($check->num_rows > 0) {
                        // Update
                        $stmt = $conn->prepare("UPDATE absensi SET status = ?, keterangan = ?, dicatat_oleh = ? WHERE siswa_id = ? AND jadwal_id = ? AND tanggal = ?");
                        $stmt->bind_param("sssisi", $status, $keterangan, $user_id, $siswa_id, $jadwal_id, $tanggal);
                    } else {
                        // Insert
                        $stmt = $conn->prepare("INSERT INTO absensi (siswa_id, jadwal_id, tanggal, status, keterangan, dicatat_oleh) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iisssi", $siswa_id, $jadwal_id, $tanggal, $status, $keterangan, $user_id);
                    }
                    $stmt->execute();
                }

                // Update rekap
                $bulan = date('m');
                $tahun = date('Y');
                foreach ($siswa_list as $siswa) {
                    updateRekapAbsensi($siswa['id'], $bulan, $tahun, $conn);
                }

                header("Location: index.php?message=success");
                exit();
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Absensi - Sistem Absensi SMA</title>
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
        .table-responsive { margin-top: 20px; }
        .table { background: white; margin-bottom: 0; }
        .table thead { background: #f8f9fa; }
        .status-badge { display: inline-block; padding: 8px 12px; border-radius: 5px; font-size: 12px; font-weight: 600; }
        .badge-hadir { background: #d4edda; color: #155724; }
        .badge-izin { background: #d1ecf1; color: #0c5460; }
        .badge-sakit { background: #fff3cd; color: #856404; }
        .badge-alfa { background: #f8d7da; color: #721c24; }
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
            <?php endif; ?>
            <a href="../absensi/" class="nav-link active"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>📝 Input Absensi - <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h4>
            <div>
                <a href="scan.php?kelas_id=<?php echo $kelas['id']; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-camera"></i> Scan Barcode</a>
                <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>

        <?php if ($message) echo $message; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jadwal Pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="jadwal_id" required>
                                <option value="">- Pilih Jadwal -</option>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <option value="<?php echo $jadwal['id']; ?>">
                                        <?php
                                        $mapel = query("SELECT nama_mapel FROM mata_pelajaran WHERE id = {$jadwal['mata_pelajaran_id']}", $conn)->fetch_assoc();
                                        echo htmlspecialchars($mapel['nama_mapel']) . " (" . $jadwal['hari'] . " " . substr($jadwal['jam_mulai'], 0, 5) . "-" . substr($jadwal['jam_selesai'], 0, 5) . ")";
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($siswa_list)): ?>
                        <div class="alert alert-info">Tidak ada siswa di kelas ini</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>NISN</th>
                                        <th>Nama Siswa</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siswa_list as $idx => $siswa): ?>
                                        <tr>
                                            <td><?php echo ($idx + 1); ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nisn']); ?></td>
                                            <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                            <td>
                                                <select class="form-select form-select-sm" name="status_<?php echo $siswa['id']; ?>">
                                                    <option value="Hadir">Hadir</option>
                                                    <option value="Izin">Izin</option>
                                                    <option value="Sakit">Sakit</option>
                                                    <option value="Alfa">Alfa</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" name="keterangan_<?php echo $siswa['id']; ?>" placeholder="Alasan (opsional)">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Absensi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
