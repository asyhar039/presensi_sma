<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();
$message = getFlashMessage();

// Get kelas list
if ($user['role'] === 'admin') {
    $kelas_list = getAllKelas($conn);
} else {
    // Get guru's classes from jadwal
    $user_id = $user['id'];
    $result = query("SELECT g.id FROM guru g JOIN users u ON g.user_id = u.id WHERE u.id = $user_id", $conn);
    if ($result->num_rows > 0) {
        $guru = $result->fetch_assoc();
        $result = query("SELECT DISTINCT k.*, COUNT(DISTINCT jp.id) as jadwal_count 
                        FROM kelas k 
                        JOIN jadwal_pelajaran jp ON k.id = jp.kelas_id 
                        WHERE jp.guru_id = {$guru['id']} 
                        GROUP BY k.id 
                        ORDER BY k.tingkat, k.nama_kelas", $conn);
        $kelas_list = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $kelas_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .kelas-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #667eea;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        .kelas-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }
        .kelas-card h5 { color: #333; margin-bottom: 10px; }
        .kelas-info { color: #777; font-size: 14px; }
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
            <h4>📋 Input Absensi</h4>
        </div>

        <?php if ($message) echo $message; ?>

        <div class="card">
            <div class="card-body p-4">
                <h5 class="mb-4">Pilih Kelas untuk Input Absensi</h5>
                
                <?php if (empty($kelas_list)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Tidak ada kelas yang tersedia. Silakan hubungi administrator.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($kelas_list as $kelas): ?>
                            <div class="col-md-6">
                                <a href="input.php?kelas_id=<?php echo $kelas['id']; ?>" class="kelas-card">
                                    <h5><?php echo htmlspecialchars($kelas['nama_kelas']); ?></h5>
                                    <div class="kelas-info">
                                        <i class="fas fa-graduation-cap"></i> Kelas <?php echo $kelas['tingkat']; ?> <?php echo htmlspecialchars($kelas['jurusan'] ?? ''); ?><br>
                                        <?php if (isset($kelas['jadwal_count'])): ?>
                                            <i class="fas fa-calendar"></i> <?php echo $kelas['jadwal_count']; ?> Jadwal Tersedia
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body p-4">
                <h5 class="mb-4">📊 Laporan Absensi</h5>
                <a href="laporan.php" class="btn btn-primary"><i class="fas fa-chart-bar"></i> Lihat Laporan</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
