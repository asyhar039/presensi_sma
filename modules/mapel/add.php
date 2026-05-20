<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

$guru_list = getAllGuru($conn);
$kelas_list = getAllKelas($conn);
$hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token tidak valid';
    } else {
        $nama_mapel = sanitize($_POST['nama_mapel'] ?? '');
        $kode_mapel = sanitize($_POST['kode_mapel'] ?? '');
        $guru_id = intval($_POST['guru_id'] ?? 0);

        if (empty($nama_mapel) || $guru_id <= 0) {
            $error = 'Nama mapel dan guru harus dipilih';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO mata_pelajaran (nama_mapel, kode_mapel, guru_id) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $nama_mapel, $kode_mapel, $guru_id);
                
                if ($stmt->execute()) {
                    $mapel_id = $conn->insert_id;
                    
                    $buat_jadwal = isset($_POST['buat_jadwal']) && $_POST['buat_jadwal'] === '1';
                    if ($buat_jadwal) {
                        $kelas_id = intval($_POST['kelas_id'] ?? 0);
                        $hari = sanitize($_POST['hari'] ?? '');
                        $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
                        $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');
                        
                        if ($kelas_id <= 0 || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
                            throw new Exception("Kolom jadwal tidak boleh kosong ketika opsi jadwal langsung diaktifkan");
                        }
                        
                        $stmt_jadwal = $conn->prepare("INSERT INTO jadwal_pelajaran (kelas_id, mata_pelajaran_id, guru_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_jadwal->bind_param("iiisss", $kelas_id, $mapel_id, $guru_id, $hari, $jam_mulai, $jam_selesai);
                        $stmt_jadwal->execute();
                    }
                    
                    header("Location: index.php?message=success");
                    exit();
                } else {
                    $error = 'Error saat menyimpan';
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
    <title>Tambah Mapel - Sistem Absensi SMA</title>
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
            <a href="index.php" class="nav-link active"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <a href="../jadwal/" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>➕ Tambah Mata Pelajaran</h4>
        </div>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kode Mapel</label>
                        <input type="text" class="form-control" name="kode_mapel" placeholder="Contoh: MTK001">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Guru Pengampu <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" required>
                            <option value="">- Pilih Guru -</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="card bg-light border-0 mb-4 p-3 shadow-sm rounded-3 mt-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="buat_jadwal" name="buat_jadwal" value="1" onchange="toggleScheduleForm()">
                            <label class="form-check-label fw-bold text-dark" for="buat_jadwal">
                                <i class="fas fa-calendar-plus text-primary me-2"></i>Buat Jadwal Pelajaran Langsung
                            </label>
                        </div>
                        
                        <div id="schedule_form" style="display: none;">
                            <hr class="my-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold">Pilih Kelas <span class="text-danger">*</span></label>
                                    <select class="form-select border-2" id="kelas_id" name="kelas_id">
                                        <option value="">- Pilih Kelas -</option>
                                        <?php foreach ($kelas_list as $kelas): ?>
                                            <option value="<?php echo $kelas['id']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold">Hari <span class="text-danger">*</span></label>
                                    <select class="form-select border-2" id="hari" name="hari">
                                        <option value="">- Pilih Hari -</option>
                                        <?php foreach ($hari_list as $h): ?>
                                            <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control border-2" id="jam_mulai" name="jam_mulai">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-secondary fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control border-2" id="jam_selesai" name="jam_selesai">
                                </div>
                            </div>
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
    <script>
        function toggleScheduleForm() {
            const checkbox = document.getElementById('buat_jadwal');
            const form = document.getElementById('schedule_form');
            const fields = ['kelas_id', 'hari', 'jam_mulai', 'jam_selesai'];
            
            if (checkbox.checked) {
                form.style.display = 'block';
                fields.forEach(id => {
                    document.getElementById(id).setAttribute('required', 'required');
                });
            } else {
                form.style.display = 'none';
                fields.forEach(id => {
                    document.getElementById(id).removeAttribute('required');
                    document.getElementById(id).value = '';
                });
            }
        }
    </script>
</body>
</html>
