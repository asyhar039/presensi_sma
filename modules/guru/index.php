<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireAdmin();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $count_result = query("SELECT COUNT(*) as total FROM guru", $conn);
    $total = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total / $per_page);

    $result = query("SELECT g.*, u.nama_lengkap, u.email, u.username FROM guru g 
                    JOIN users u ON g.user_id = u.id 
                    ORDER BY u.nama_lengkap 
                    LIMIT $offset, $per_page", $conn);
    $guru_list = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $guru_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
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
            <a href="../guru/" class="nav-link active"><i class="fas fa-chalkboard-user"></i> Data Guru</a>
            <a href="../kelas/" class="nav-link"><i class="fas fa-school"></i> Data Kelas</a>
            <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
            <a href="../jadwal/" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>👨‍🏫 Data Guru</h4>
            <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Guru</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>NIP</th>
                                <th>No. Telp</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guru_list)): ?>
                                <tr><td colspan="7" class="text-center py-4">Tidak ada data guru</td></tr>
                            <?php else: ?>
                                <?php foreach ($guru_list as $idx => $guru): ?>
                                    <tr>
                                        <td><?php echo ($offset + $idx + 1); ?></td>
                                        <td><?php echo htmlspecialchars($guru['nama_lengkap']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($guru['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($guru['email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($guru['nip'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($guru['no_telp'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit.php?id=<?php echo $guru['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a>
                                                <a href="delete.php?id=<?php echo $guru['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i></a>
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
