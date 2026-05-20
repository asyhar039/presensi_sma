<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

try {
    $result = query("SELECT m.*, u.nama_lengkap as guru_nama FROM mata_pelajaran m 
                    JOIN guru g ON m.guru_id = g.id 
                    JOIN users u ON g.user_id = u.id 
                    ORDER BY m.nama_mapel", $conn);
    $mapel_list = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $mapel_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mata Pelajaran - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table { background: white; }
        .action-buttons { display: flex; gap: 5px; }
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
            <a href="../mapel/" class="nav-link active"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>📖 Mata Pelajaran</h4>
            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Mapel</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Mapel</th>
                                <th>Kode Mapel</th>
                                <th>Guru Pengampu</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mapel_list)): ?>
                                <tr><td colspan="5" class="text-center py-4">Tidak ada data mata pelajaran</td></tr>
                            <?php else: ?>
                                <?php foreach ($mapel_list as $idx => $mapel): ?>
                                    <tr>
                                        <td><?php echo ($idx + 1); ?></td>
                                        <td><strong><?php echo htmlspecialchars($mapel['nama_mapel']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($mapel['kode_mapel'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($mapel['guru_nama']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit.php?id=<?php echo $mapel['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                                <a href="delete.php?id=<?php echo $mapel['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
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
