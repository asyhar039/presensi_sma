<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$error = '';

// Fetch current schedule data
try {
    $result = query("SELECT * FROM jadwal_pelajaran WHERE id = $id", $conn);
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    $jadwal = $result->fetch_assoc();
} catch (Exception $e) {
    header("Location: index.php");
    exit();
}

// Get all kelas
$kelas_list = getAllKelas($conn);

// Get all subjects with guru info
try {
    $result_mapel = query("SELECT m.id, m.nama_mapel, u.nama_lengkap as guru_nama, m.guru_id 
                          FROM mata_pelajaran m 
                          JOIN guru g ON m.guru_id = g.id 
                          JOIN users u ON g.user_id = u.id 
                          ORDER BY m.nama_mapel", $conn);
    $mapel_list = $result_mapel->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $mapel_list = [];
}

$hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token tidak valid';
    } else {
        $kelas_id = intval($_POST['kelas_id'] ?? 0);
        $mapel_guru = sanitize($_POST['mapel_guru'] ?? '');
        $hari = sanitize($_POST['hari'] ?? '');
        $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
        $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');

        if ($kelas_id <= 0 || empty($mapel_guru) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
            $error = 'Semua field wajib diisi';
        } else {
            try {
                $parts = explode('|', $mapel_guru);
                if (count($parts) === 2) {
                    $mata_pelajaran_id = intval($parts[0]);
                    $guru_id = intval($parts[1]);

                    $stmt = $conn->prepare("UPDATE jadwal_pelajaran SET kelas_id = ?, mata_pelajaran_id = ?, guru_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ?");
                    $stmt->bind_param("iiisssi", $kelas_id, $mata_pelajaran_id, $guru_id, $hari, $jam_mulai, $jam_selesai, $id);
                    
                    if ($stmt->execute()) {
                        header("Location: index.php?message=success");
                        exit();
                    } else {
                        $error = 'Gagal memperbarui jadwal';
                    }
                } else {
                    $error = 'Data mata pelajaran tidak valid';
                }
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
    <title>Edit Jadwal Pelajaran - Sistem Absensi SMA</title>
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
            <a href="../../public/dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="../siswa/" class="nav-link"><i class="fas fa-users"></i> Data Siswa</a>
            <a href="../guru/" class="nav-link"><i class="fas fa-chalkboard-user"></i> Data Guru</a>
            <a href="../kelas/" class="nav-link"><i class="fas fa-school"></i> Data Kelas</a>
            <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <a href="../jadwal/" class="nav-link active"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>✏️ Edit Jadwal Pelajaran</h4>
        </div>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Pilih Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" name="kelas_id" required>
                            <option value="">- Pilih Kelas -</option>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>" <?php echo $jadwal['kelas_id'] == $kelas['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran & Pengampu <span class="text-danger">*</span></label>
                        <select class="form-select" name="mapel_guru" required>
                            <option value="">- Pilih Mata Pelajaran -</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id'] . '|' . $mapel['guru_id']; ?>" <?php echo ($jadwal['mata_pelajaran_id'] == $mapel['id'] && $jadwal['guru_id'] == $mapel['guru_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mapel['nama_mapel'] . ' (' . $mapel['guru_nama'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Hari <span class="text-danger">*</span></label>
                        <select class="form-select" name="hari" required>
                            <option value="">- Pilih Hari -</option>
                            <?php foreach ($hari_list as $hari): ?>
                                <option value="<?php echo $hari; ?>" <?php echo $jadwal['hari'] === $hari ? 'selected' : ''; ?>><?php echo $hari; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_mulai" value="<?php echo substr($jadwal['jam_mulai'], 0, 5); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="jam_selesai" value="<?php echo substr($jadwal['jam_selesai'], 0, 5); ?>" required>
                        </div>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
