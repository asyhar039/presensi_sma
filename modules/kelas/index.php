<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();

try {
    if ($user['role'] === 'guru') {
        $g_res = query("SELECT id FROM guru WHERE user_id = {$user['id']}", $conn);
        if ($g_res && $g_res->num_rows > 0) {
            $guru = $g_res->fetch_assoc();
            $result = query("SELECT DISTINCT k.*, u.nama_lengkap as guru_nama FROM kelas k 
                            LEFT JOIN guru g ON k.guru_id = g.id 
                            LEFT JOIN users u ON g.user_id = u.id 
                            LEFT JOIN jadwal_pelajaran jp ON jp.kelas_id = k.id 
                            WHERE jp.guru_id = {$guru['id']} OR k.guru_id = {$guru['id']}
                            ORDER BY k.tingkat, k.nama_kelas", $conn);
            $kelas_list = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $kelas_list = [];
        }
    } else {
        $result = query("SELECT k.*, u.nama_lengkap as guru_nama FROM kelas k 
                        LEFT JOIN guru g ON k.guru_id = g.id 
                        LEFT JOIN users u ON g.user_id = u.id 
                        ORDER BY k.tingkat, k.nama_kelas", $conn);
        $kelas_list = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    $kelas_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kelas - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .sidebar .nav-link i { width: 20px; margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table { background: white; }
        .action-buttons { display: flex; gap: 5px; }
        @media (max-width: 768px) { .sidebar { position: relative; width: 100%; min-height: auto; } .main-content { margin-left: 0; } }
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
            <a href="../kelas/" class="nav-link active"><i class="fas fa-school"></i> Data Kelas</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <?php endif; ?>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>🏫 Data Kelas</h4>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Kelas</a>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Kelas</th>
                                <th>Tingkat</th>
                                <th>Jurusan</th>
                                <th>Wali Kelas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kelas_list)): ?>
                                <tr><td colspan="6" class="text-center py-4">Tidak ada data kelas</td></tr>
                            <?php else: ?>
                                <?php foreach ($kelas_list as $idx => $kelas): ?>
                                    <tr>
                                        <td><?php echo ($idx + 1); ?></td>
                                        <td><strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong></td>
                                        <td>Kelas <?php echo $kelas['tingkat']; ?></td>
                                        <td><?php echo htmlspecialchars($kelas['jurusan'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($kelas['guru_nama'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="../siswa/index.php?kelas_id=<?php echo $kelas['id']; ?>" class="btn btn-sm btn-success" title="Lihat Siswa"><i class="fas fa-users"></i> Siswa</a>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <a href="edit.php?id=<?php echo $kelas['id']; ?>" class="btn btn-sm btn-info" title="Edit Kelas"><i class="fas fa-edit"></i></a>
                                                    <a href="delete.php?id=<?php echo $kelas['id']; ?>" class="btn btn-sm btn-danger" title="Hapus Kelas" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
