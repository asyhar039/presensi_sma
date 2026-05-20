<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

$guru_list = getAllGuru($conn);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF Token tidak valid';
    } else {
        $nama_kelas = sanitize($_POST['nama_kelas'] ?? '');
        $tingkat = sanitize($_POST['tingkat'] ?? '');
        $jurusan = sanitize($_POST['jurusan'] ?? '');
        $guru_id = intval($_POST['guru_id'] ?? 0);

        if ($tingkat === '10') {
            $jurusan = ''; // Kelas 10 tidak perlu jurusan
        }

        if (empty($nama_kelas) || empty($tingkat)) {
            $error = 'Nama kelas dan tingkat harus diisi';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, jurusan, guru_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $nama_kelas, $tingkat, $jurusan, $guru_id);
                
                if ($stmt->execute()) {
                    header("Location: index.php?message=success");
                    exit();
                } else {
                    $error = 'Error saat menyimpan data';
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
    <title>Tambah Kelas - Sistem Absensi SMA</title>
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
            <a href="index.php" class="nav-link active"><i class="fas fa-school"></i> Data Kelas</a>
            <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>➕ Tambah Kelas Baru</h4>
        </div>

        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" id="nama_kelas" placeholder="Contoh: X FASE E 1" required>
                        <div class="form-text text-muted" id="nama_kelas_help">Format kelas X: X FASE E [1-9] (Contoh: X FASE E 1)</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tingkat <span class="text-danger">*</span></label>
                            <select class="form-select" name="tingkat" id="tingkat" required>
                                <option value="">- Pilih -</option>
                                <option value="10">X (10)</option>
                                <option value="11">XI (11)</option>
                                <option value="12">XII (12)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="jurusan-container">
                            <label class="form-label">Jurusan</label>
                            <input type="text" class="form-control" name="jurusan" id="jurusan" placeholder="Contoh: IPA, IPS">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Wali Kelas</label>
                        <select class="form-select" name="guru_id">
                            <option value="0">- Pilih Guru -</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>"><?php echo htmlspecialchars($guru['nama_lengkap']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
        const tingkatSelect = document.getElementById('tingkat');
        const jurusanContainer = document.getElementById('jurusan-container');
        const jurusanInput = document.getElementById('jurusan');
        const namaKelasInput = document.getElementById('nama_kelas');
        const namaKelasHelp = document.getElementById('nama_kelas_help');

        function handleTingkatChange() {
            const val = tingkatSelect.value;
            if (val === '10') {
                // Kelas 10 tidak perlu jurusan
                jurusanContainer.style.display = 'none';
                jurusanInput.value = '';
                jurusanInput.removeAttribute('required');
                
                // Ubah placeholder dan petunjuk nama kelas
                namaKelasInput.placeholder = 'Contoh: X FASE E 1';
                namaKelasHelp.textContent = 'Format kelas X: X FASE E [1-9] (Contoh: X FASE E 1)';
            } else {
                // Kelas 11 & 12 butuh jurusan
                jurusanContainer.style.display = 'block';
                
                // Ubah placeholder dan petunjuk nama kelas
                if (val === '11') {
                    namaKelasInput.placeholder = 'Contoh: XI-IPA-1';
                    namaKelasHelp.textContent = 'Format kelas XI: XI-[JURUSAN]-[NOMOR] (Contoh: XI-IPA-1)';
                } else if (val === '12') {
                    namaKelasInput.placeholder = 'Contoh: XII-IPA-1';
                    namaKelasHelp.textContent = 'Format kelas XII: XII-[JURUSAN]-[NOMOR] (Contoh: XII-IPA-1)';
                } else {
                    namaKelasInput.placeholder = 'Contoh: X-IPA-1';
                    namaKelasHelp.textContent = 'Pilih tingkat untuk melihat petunjuk format kelas.';
                }
            }
        }

        tingkatSelect.addEventListener('change', handleTingkatChange);
        // Jalankan saat load pertama kali
        handleTingkatChange();
    </script>
</body>
</html>
