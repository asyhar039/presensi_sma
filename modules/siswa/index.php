<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();
$message = getFlashMessage();

// Filters
$kelas_id = isset($_GET['kelas_id']) ? intval($_GET['kelas_id']) : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    // Build SQL Where clauses based on filters and role
    $where_clauses = ["s.is_active = 1"];
    
    if ($kelas_id > 0) {
        $where_clauses[] = "s.kelas_id = $kelas_id";
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(s.nama_lengkap LIKE '%" . $conn->real_escape_string($search) . "%' OR s.nisn LIKE '%" . $conn->real_escape_string($search) . "%')";
    }
    
    if ($user['role'] === 'guru') {
        $g_res = query("SELECT id FROM guru WHERE user_id = {$user['id']}", $conn);
        if ($g_res && $g_res->num_rows > 0) {
            $g_data = $g_res->fetch_assoc();
            $guru_id = $g_data['id'];
            
            // Guru can only see classes they teach or where they are Wali Kelas
            $where_clauses[] = "s.kelas_id IN (
                SELECT DISTINCT kelas_id FROM jadwal_pelajaran WHERE guru_id = $guru_id
                UNION
                SELECT DISTINCT id FROM kelas WHERE guru_id = $guru_id
            )";
        } else {
            // If user is a guru but has no guru record, they see nothing
            $where_clauses[] = "1 = 0";
        }
    }
    
    $where_str = implode(" AND ", $where_clauses);
    
    // Get total count
    $count_result = query("SELECT COUNT(DISTINCT s.id) as total FROM siswa s WHERE $where_str", $conn);
    $total = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total / $per_page);

    // Get data
    $result = query("SELECT DISTINCT s.*, k.nama_kelas FROM siswa s 
                    JOIN kelas k ON s.kelas_id = k.id 
                    WHERE $where_str 
                    ORDER BY s.nama_lengkap 
                    LIMIT $offset, $per_page", $conn);
    $siswa_list = $result->fetch_all(MYSQLI_ASSOC);
    
    // Get kelas list for filter option (guru sees only their classes, admin sees all)
    if ($user['role'] === 'admin') {
        $kelas_options = getAllKelas($conn);
    } else {
        $g_res = query("SELECT id FROM guru WHERE user_id = {$user['id']}", $conn);
        if ($g_res && $g_res->num_rows > 0) {
            $guru_id = $g_res->fetch_assoc()['id'];
            $res_opt = query("SELECT DISTINCT k.* FROM kelas k 
                            LEFT JOIN jadwal_pelajaran jp ON k.id = jp.kelas_id 
                            WHERE jp.guru_id = $guru_id OR k.guru_id = $guru_id
                            ORDER BY k.tingkat, k.nama_kelas", $conn);
            $kelas_options = $res_opt->fetch_all(MYSQLI_ASSOC);
        } else {
            $kelas_options = [];
        }
    }
} catch (Exception $e) {
    $siswa_list = [];
    $kelas_options = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .sidebar .nav-link i { width: 20px; margin-right: 10px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table { background: white; }
        .table thead { background: #f8f9fa; border-bottom: 2px solid #dee2e6; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons a, .action-buttons button { padding: 5px 10px; font-size: 12px; }
        @media (max-width: 768px) { .sidebar { position: relative; width: 100%; min-height: auto; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo"><h5>📚 ABSENSI SMA</h5></div>
        <nav>
            <a href="../../public/dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="../siswa/" class="nav-link active"><i class="fas fa-users"></i> Data Siswa</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../guru/" class="nav-link"><i class="fas fa-chalkboard-user"></i> Data Guru</a>
            <?php endif; ?>
            <a href="../kelas/" class="nav-link"><i class="fas fa-school"></i> Data Kelas</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="../mapel/" class="nav-link"><i class="fas fa-book"></i> Mata Pelajaran</a>
                <a href="../jadwal/" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <?php endif; ?>
            <a href="../absensi/" class="nav-link"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>📋 Data Siswa</h4>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Siswa</a>
            <?php endif; ?>
        </div>

        <?php if ($message) echo $message; ?>

        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Pilih Kelas</label>
                        <select class="form-select" name="kelas_id" onchange="this.form.submit()">
                            <option value="">- Semua Kelas -</option>
                            <?php foreach ($kelas_options as $k): ?>
                                <option value="<?php echo $k['id']; ?>" <?php echo $kelas_id === $k['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($k['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cari Nama / NISN</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" placeholder="Masukkan nama atau NISN..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Cari</button>
                            <?php if ($kelas_id > 0 || !empty($search)): ?>
                                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Jenis Kelamin</th>
                                <th>No. Telp</th>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <th>Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($siswa_list)): ?>
                                <tr><td colspan="<?php echo $user['role'] === 'admin' ? '7' : '6'; ?>" class="text-center py-4">Tidak ada data siswa</td></tr>
                            <?php else: ?>
                                <?php foreach ($siswa_list as $idx => $siswa): ?>
                                    <tr>
                                        <td><?php echo ($offset + $idx + 1); ?></td>
                                        <td><strong><?php echo htmlspecialchars($siswa['nisn']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($siswa['nama_lengkap']); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                        <td><?php echo $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                                        <td><?php echo htmlspecialchars($siswa['no_telp'] ?? '-'); ?></td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit.php?id=<?php echo $siswa['id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-edit"></i> Edit</a>
                                                    <a href="delete.php?id=<?php echo $siswa['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus?')"><i class="fas fa-trash"></i> Hapus</a>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center mt-3">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=1<?php echo $kelas_id > 0 ? '&kelas_id='.$kelas_id : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">First</a></li>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $kelas_id > 0 ? '&kelas_id='.$kelas_id : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Previous</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $kelas_id > 0 ? '&kelas_id='.$kelas_id : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $kelas_id > 0 ? '&kelas_id='.$kelas_id : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Next</a></li>
                                <li class="page-item"><a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $kelas_id > 0 ? '&kelas_id='.$kelas_id : ''; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>">Last</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
