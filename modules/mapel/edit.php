<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

$mapel_id = intval($_GET['id'] ?? 0);

try {
    $result = query("SELECT * FROM mata_pelajaran WHERE id = $mapel_id", $conn);
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    $mapel = $result->fetch_assoc();
} catch (Exception $e) {
    header("Location: index.php");
    exit();
}

$guru_list = getAllGuru($conn);
$kelas_list = getAllKelas($conn);
$hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$error = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token tidak valid';
    } else {
        $action = $_POST['action'] ?? 'update_mapel';
        
        if ($action === 'update_mapel') {
            $nama_mapel = sanitize($_POST['nama_mapel'] ?? '');
            $kode_mapel = sanitize($_POST['kode_mapel'] ?? '');
            $guru_id = intval($_POST['guru_id'] ?? 0);

            if (empty($nama_mapel) || $guru_id <= 0) {
                $error = 'Nama mapel dan guru harus dipilih';
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE mata_pelajaran SET nama_mapel = ?, kode_mapel = ?, guru_id = ? WHERE id = ?");
                    $stmt->bind_param("ssii", $nama_mapel, $kode_mapel, $guru_id, $mapel_id);
                    
                    if ($stmt->execute()) {
                        $success_msg = 'Mata pelajaran berhasil diperbarui';
                        // Refresh mapel info
                        $result = query("SELECT * FROM mata_pelajaran WHERE id = $mapel_id", $conn);
                        $mapel = $result->fetch_assoc();
                    } else {
                        $error = 'Error saat update';
                    }
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'add_jadwal') {
            $kelas_id = intval($_POST['kelas_id'] ?? 0);
            $hari = sanitize($_POST['hari'] ?? '');
            $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
            $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');
            $guru_id = intval($mapel['guru_id']);
            
            if ($kelas_id <= 0 || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
                $error = 'Semua field jadwal harus diisi';
            } else {
                try {
                    $stmt = $conn->prepare("INSERT INTO jadwal_pelajaran (kelas_id, mata_pelajaran_id, guru_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiisss", $kelas_id, $mapel_id, $guru_id, $hari, $jam_mulai, $jam_selesai);
                    if ($stmt->execute()) {
                        $success_msg = 'Jadwal pelajaran baru berhasil ditambahkan';
                    } else {
                        $error = 'Gagal menambahkan jadwal';
                    }
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_jadwal') {
            $jadwal_id = intval($_POST['jadwal_id'] ?? 0);
            if ($jadwal_id > 0) {
                try {
                    $stmt = $conn->prepare("DELETE FROM jadwal_pelajaran WHERE id = ? AND mata_pelajaran_id = ?");
                    $stmt->bind_param("ii", $jadwal_id, $mapel_id);
                    if ($stmt->execute()) {
                        $success_msg = 'Jadwal pelajaran berhasil dihapus';
                    } else {
                        $error = 'Gagal menghapus jadwal';
                    }
                } catch (Exception $e) {
                    $error = 'Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch active schedules for this mapel
$schedules = [];
try {
    $sched_res = query("SELECT jp.*, k.nama_kelas FROM jadwal_pelajaran jp JOIN kelas k ON jp.kelas_id = k.id WHERE jp.mata_pelajaran_id = $mapel_id ORDER BY FIELD(jp.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jp.jam_mulai", $conn);
    while ($row = $sched_res->fetch_assoc()) {
        $schedules[] = $row;
    }
} catch (Exception $e) {
    // handle
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mapel - Sistem Absensi SMA</title>
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
            <h4>✏️ Edit Mata Pelajaran</h4>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Left Column: Edit Mapel Form -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h5 class="card-title fw-bold text-dark mb-0"><i class="fas fa-edit text-primary me-2"></i>Informasi Mata Pelajaran</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_mapel">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label text-secondary fw-semibold">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                                <input type="text" class="form-control border-2" name="nama_mapel" value="<?php echo htmlspecialchars($mapel['nama_mapel']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label text-secondary fw-semibold">Kode Mapel</label>
                                <input type="text" class="form-control border-2" name="kode_mapel" value="<?php echo htmlspecialchars($mapel['kode_mapel'] ?? ''); ?>">
                            </div>

                            <div class="mb-4">
                                <label class="form-label text-secondary fw-semibold">Guru Pengampu <span class="text-danger">*</span></label>
                                <select class="form-select border-2" name="guru_id" required>
                                    <?php foreach ($guru_list as $guru): ?>
                                        <option value="<?php echo $guru['id']; ?>" <?php echo $mapel['guru_id'] === $guru['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($guru['nama_lengkap']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end border-top pt-3">
                                <a href="index.php" class="btn btn-secondary px-4">Batal</a>
                                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Schedule Management -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title fw-bold text-dark mb-0"><i class="fas fa-calendar-alt text-primary me-2"></i>Jadwal Pelajaran</h5>
                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddJadwal" aria-expanded="false" aria-controls="collapseAddJadwal">
                            <i class="fas fa-plus me-1"></i> Tambah Jadwal
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <!-- Add Schedule Collapse Form -->
                        <div class="collapse mb-4" id="collapseAddJadwal">
                            <div class="card card-body bg-light border-0 shadow-sm p-3">
                                <h6 class="fw-bold text-dark mb-3"><i class="fas fa-calendar-plus text-primary me-2"></i>Tambah Jadwal Baru</h6>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_jadwal">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Pilih Kelas <span class="text-danger">*</span></label>
                                            <select class="form-select border-2" name="kelas_id" required>
                                                <option value="">- Pilih Kelas -</option>
                                                <?php foreach ($kelas_list as $kelas): ?>
                                                    <option value="<?php echo $kelas['id']; ?>"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Hari <span class="text-danger">*</span></label>
                                            <select class="form-select border-2" name="hari" required>
                                                <option value="">- Pilih Hari -</option>
                                                <?php foreach ($hari_list as $h): ?>
                                                    <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Jam Mulai <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control border-2" name="jam_mulai" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold text-secondary">Jam Selesai <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control border-2" name="jam_selesai" required>
                                        </div>
                                        <div class="col-12 text-end border-top pt-2">
                                            <button type="button" class="btn btn-secondary btn-sm px-3 me-1" data-bs-toggle="collapse" data-bs-target="#collapseAddJadwal">Batal</button>
                                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-save me-1"></i> Simpan Jadwal</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Schedule List Table -->
                        <?php if (empty($schedules)): ?>
                            <div class="text-center py-5 text-secondary bg-light rounded-3">
                                <i class="fas fa-calendar-times fa-3x mb-3 text-muted opacity-50"></i>
                                <p class="mb-0 fw-medium">Belum ada jadwal terdaftar untuk pelajaran ini.</p>
                                <span class="small text-muted">Klik tombol "Tambah Jadwal" di atas untuk membuat jadwal baru.</span>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border-0">
                                    <thead class="table-light border-0">
                                        <tr>
                                            <th class="border-0 rounded-start">Kelas</th>
                                            <th class="border-0">Hari</th>
                                            <th class="border-0">Waktu</th>
                                            <th class="border-0 rounded-end text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedules as $sched): ?>
                                            <tr>
                                                <td class="fw-semibold text-dark"><?php echo htmlspecialchars($sched['nama_kelas']); ?></td>
                                                <td><span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-2.5 py-1.5 rounded-pill"><?php echo $sched['hari']; ?></span></td>
                                                <td class="text-secondary small fw-medium"><?php echo substr($sched['jam_mulai'], 0, 5) . ' - ' . substr($sched['jam_selesai'], 0, 5); ?></td>
                                                <td class="text-end">
                                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');" style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_jadwal">
                                                        <input type="hidden" name="jadwal_id" value="<?php echo $sched['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 border-0" title="Hapus Jadwal">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
