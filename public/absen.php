<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/helpers.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$jadwal_id = intval($_GET['jadwal_id'] ?? 0);
$tanggal = sanitize($_GET['tanggal'] ?? '');

$error_session = false;
$session_details = null;
$student = $_SESSION['student'] ?? null;
$is_class_matched = false;

// Fetch schedule details if provided in URL
if ($jadwal_id > 0 && !empty($tanggal)) {
    try {
        $stmt = $conn->prepare("
            SELECT j.*, m.nama_mapel, k.nama_kelas, u.nama_lengkap as nama_guru 
            FROM jadwal_pelajaran j 
            JOIN mata_pelajaran m ON j.mata_pelajaran_id = m.id 
            JOIN kelas k ON j.kelas_id = k.id 
            JOIN guru g ON j.guru_id = g.id 
            JOIN users u ON g.user_id = u.id 
            WHERE j.id = ?
        ");
        $stmt->bind_param("i", $jadwal_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_session = true;
        } else {
            $session_details = $result->fetch_assoc();
            
            // Format Indonesian date
            $timestamp = strtotime($tanggal);
            $day_eng = date('l', $timestamp);
            $month_eng = date('F', $timestamp);
            
            $days = [
                'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
                'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
            ];
            $months = [
                'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April',
                'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus',
                'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
            ];
            
            $hari_indo = $days[$day_eng] ?? $day_eng;
            $bulan_indo = $months[$month_eng] ?? $month_eng;
            $tanggal_indo = date('d', $timestamp) . ' ' . $bulan_indo . ' ' . date('Y', $timestamp);
        }
    } catch (Exception $e) {
        $error_session = true;
    }
}

// Fetch up-to-date student profile if logged in
$student_full = null;
$stats = [
    'Hadir' => 0,
    'Izin' => 0,
    'Sakit' => 0,
    'Alfa' => 0
];
$subject_stats = [];
$attendance_history = [];

if ($student) {
    try {
        $stmt_stud = $conn->prepare("
            SELECT s.*, k.nama_kelas 
            FROM siswa s 
            JOIN kelas k ON s.kelas_id = k.id 
            WHERE s.id = ? AND s.is_active = 1
        ");
        $stmt_stud->bind_param("i", $student['id']);
        $stmt_stud->execute();
        $student_full = $stmt_stud->get_result()->fetch_assoc();
        
        if (!$student_full) {
            // Student account is disabled or deleted
            unset($_SESSION['student']);
            $student = null;
        } else {
            // Keep session fresh
            $_SESSION['student']['nama_lengkap'] = $student_full['nama_lengkap'];
            $_SESSION['student']['nisn'] = $student_full['nisn'];
            $_SESSION['student']['kelas_id'] = $student_full['kelas_id'];
            
            // Check class match for active session
            if ($session_details) {
                $is_class_matched = (intval($student_full['kelas_id']) === intval($session_details['kelas_id']));
            }

            // Fetch summary stats
            $stmt_sum = $conn->prepare("
                SELECT status, COUNT(*) as count 
                FROM absensi 
                WHERE siswa_id = ? 
                GROUP BY status
            ");
            $stmt_sum->bind_param("i", $student_full['id']);
            $stmt_sum->execute();
            $sum_res = $stmt_sum->get_result();
            while ($row = $sum_res->fetch_assoc()) {
                $stats[$row['status']] = intval($row['count']);
            }

            // Fetch subject stats
            $stmt_subj = $conn->prepare("
                SELECT 
                    m.nama_mapel,
                    COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                    COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                    COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                    COUNT(CASE WHEN a.status = 'Alfa' THEN 1 END) as alfa
                FROM mata_pelajaran m
                JOIN jadwal_pelajaran j ON j.mata_pelajaran_id = m.id
                LEFT JOIN absensi a ON a.jadwal_id = j.id AND a.siswa_id = ?
                WHERE j.kelas_id = ?
                GROUP BY m.id, m.nama_mapel
                ORDER BY m.nama_mapel
            ");
            $stmt_subj->bind_param("ii", $student_full['id'], $student_full['kelas_id']);
            $stmt_subj->execute();
            $subj_res = $stmt_subj->get_result();
            while ($row = $subj_res->fetch_assoc()) {
                $subject_stats[] = $row;
            }

            // Fetch attendance history
            $stmt_hist = $conn->prepare("
                SELECT a.tanggal, a.status, a.keterangan, m.nama_mapel, u.nama_lengkap as nama_guru
                FROM absensi a
                JOIN jadwal_pelajaran j ON a.jadwal_id = j.id
                JOIN mata_pelajaran m ON j.mata_pelajaran_id = m.id
                JOIN guru g ON j.guru_id = g.id
                JOIN users u ON g.user_id = u.id
                WHERE a.siswa_id = ?
                ORDER BY a.tanggal DESC, a.created_at DESC
                LIMIT 8
            ");
            $stmt_hist->bind_param("i", $student_full['id']);
            $stmt_hist->execute();
            $hist_res = $stmt_hist->get_result();
            while ($row = $hist_res->fetch_assoc()) {
                $attendance_history[] = $row;
            }
        }
    } catch (Exception $e) {
        // Fallback silently
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Siswa - Sistem Presensi Mandiri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 10% 20%, rgb(6, 11, 38) 0%, rgb(16, 22, 54) 90.1%);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --neon-indigo: #6366f1;
            --neon-purple: #a855f7;
            --neon-emerald: #10b981;
            --neon-rose: #f43f5e;
            --neon-amber: #f59e0b;
        }

        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s, border-color 0.3s;
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #f8fafc;
            overflow-x: hidden;
            padding-bottom: 50px;
        }

        /* Glassmorphic elements */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            padding: 24px;
            margin-bottom: 24px;
        }

        .glass-nav-pill {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            color: #94a3b8;
            padding: 10px 16px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .glass-nav-pill:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #f1f5f9;
        }

        .glass-nav-pill.active {
            background: linear-gradient(135deg, var(--neon-indigo) 0%, var(--neon-purple) 100%);
            border-color: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        /* Floating header banner */
        .portal-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            border-bottom: 1px solid var(--glass-border);
            padding: 15px 0;
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        /* App brand */
        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--neon-indigo) 0%, var(--neon-purple) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .brand-title {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0;
            background: linear-gradient(to right, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Dashboard widgets */
        .live-clock-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(168, 85, 247, 0.05) 100%);
            border: 1px solid rgba(99, 102, 241, 0.15);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .live-clock-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, transparent 60%);
            z-index: 1;
        }

        .live-clock-text {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #ffffff;
            text-shadow: 0 0 15px rgba(99, 102, 241, 0.5);
            z-index: 2;
            position: relative;
        }

        .live-date-text {
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            z-index: 2;
            position: relative;
        }

        /* Stat cards */
        .stat-widget {
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* QR Scanner camera Styles */
        .scanner-container {
            position: relative;
            width: 100%;
            max-width: 380px;
            margin: 0 auto 20px auto;
            border-radius: 20px;
            overflow: hidden;
            border: 2px solid rgba(99, 102, 241, 0.25);
            box-shadow: 0 0 25px rgba(99, 102, 241, 0.15);
            background: #020617;
            display: none;
        }

        .scanner-laser {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, transparent, var(--neon-purple), var(--neon-indigo), var(--neon-purple), transparent);
            animation: scanning 2.2s linear infinite;
            z-index: 10;
            box-shadow: 0 0 8px var(--neon-indigo);
        }

        @keyframes scanning {
            0% { top: 2%; }
            50% { top: 98%; }
            100% { top: 2%; }
        }

        /* Subject cards progress bars */
        .subject-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .subject-progress {
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.05);
            overflow: hidden;
        }

        /* Glassmorphic Sesi Pelajaran Info Box style */
        .session-info-box {
            background: rgba(255, 255, 255, 0.03) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        .session-info-box .text-muted {
            color: #94a3b8 !important;
        }

        .session-info-box .text-white {
            color: #f1f5f9 !important;
        }

        /* Floating custom alert banner */
        .alert-custom-error {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.2);
            color: #fecdd3;
            border-radius: 12px;
            font-size: 14px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        /* Login Card specifics */
        .login-card {
            max-width: 450px;
            margin: 60px auto 0 auto;
        }

        .form-control,
        .form-floating > .form-control {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border-radius: 12px;
        }

        .form-control:focus,
        .form-floating > .form-control:focus {
            background: rgba(15, 23, 42, 0.8) !important;
            border-color: var(--neon-indigo) !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15) !important;
            color: #ffffff !important;
        }

        /* Prevent chrome / safari autofill from turning the inputs white */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active,
        textarea:-webkit-autofill,
        textarea:-webkit-autofill:hover,
        textarea:-webkit-autofill:focus,
        textarea:-webkit-autofill:active {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0 1000px #0b0f19 inset !important;
            box-shadow: 0 0 0 1000px #0b0f19 inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        /* HTML5-QRCode scanner elements styling overrides */
        #qr-reader button,
        #qr-reader__dashboard_section_csr button {
            background: linear-gradient(135deg, var(--neon-indigo) 0%, #4f46e5 100%) !important;
            border: none !important;
            color: white !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2) !important;
        }

        #qr-reader select {
            background-color: #0f172a !important;
            color: white !important;
            padding: 6px 12px !important;
            border-radius: 6px !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            width: auto !important;
            max-width: 90% !important;
            margin: 5px auto !important;
        }

        #qr-reader {
            border: none !important;
            color: white !important;
        }

        .form-floating > label {
            color: #94a3b8;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #818cf8;
        }

        .btn-action-primary {
            background: linear-gradient(135deg, var(--neon-indigo) 0%, #4f46e5 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.25);
        }

        .btn-action-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.35);
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        }

        .btn-action-primary:active {
            transform: translateY(0);
        }

        .error-shake {
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid #10b981;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            font-size: 36px;
            margin: 15px auto 20px auto;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);
            animation: popCheck 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }

        @keyframes popCheck {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Tab Transition */
        .tab-pane {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Toast feedback */
        .custom-toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            z-index: 9999;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .custom-toast.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Header Portal -->
    <header class="portal-header shadow-sm">
        <div class="container px-3 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <div class="brand-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h1 class="brand-title">PORTAL SISWA</h1>
                    <span style="font-size: 10px; color: #94a3b8; font-weight: 700; letter-spacing: 0.5px;">SMA NEGERI PRESENSI</span>
                </div>
            </div>
            
            <?php if ($student_full): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-md-block text-end">
                        <div class="fw-bold text-white small"><?php echo htmlspecialchars($student_full['nama_lengkap']); ?></div>
                        <div class="text-muted" style="font-size: 10px;">Kelas: <?php echo htmlspecialchars($student_full['nama_kelas']); ?></div>
                    </div>
                    <a href="student_logout.php?jadwal_id=<?php echo $jadwal_id; ?>&tanggal=<?php echo htmlspecialchars($tanggal); ?>" class="btn btn-sm btn-outline-danger px-3 rounded-pill" style="font-size: 12px; font-weight: 600;">
                        <i class="fas fa-sign-out-alt me-1"></i>Keluar
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container px-3">
        <!-- Error Session UI -->
        <?php if ($error_session): ?>
            <div class="glass-card text-center py-5 max-width-450 mx-auto" style="max-width: 480px;">
                <div class="text-danger mb-3" style="font-size: 48px;">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h4 class="fw-bold mb-2">Sesi Presensi Kadaluwarsa / Tidak Valid</h4>
                <p class="text-muted small px-3 mb-4">
                    QR Code yang Anda pindai tidak valid atau sudah kadaluwarsa. Silakan minta guru untuk memunculkan QR Code presensi terbaru.
                </p>
                <div class="px-4 d-grid gap-2">
                    <a href="absen.php" class="btn btn-action-primary rounded-pill py-2">
                        <i class="fas fa-home me-1"></i>Ke Dashboard Siswa
                    </a>
                </div>
            </div>
        <?php else: ?>
            
            <!-- STATE 1: Student is NOT Logged In -> Show Login Form -->
            <?php if (!$student_full): ?>
                <div class="glass-card login-card" id="login-container">
                    <div class="text-center mb-4">
                        <div class="success-checkmark bg-transparent border-0" style="width: auto; height: auto; margin-bottom: 12px;">
                            <div class="brand-logo mx-auto" style="width: 60px; height: 60px; font-size: 28px; border-radius: 15px;">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-1">Verifikasi Identitas Siswa</h4>
                        <p class="text-muted small">Silakan masuk untuk mengakses fitur portal siswa</p>
                    </div>

                    <div id="login-error-box" style="display: none;">
                        <div class="alert alert-custom-error">
                            <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                            <span id="login-error-message">Error</span>
                        </div>
                    </div>

                    <form id="student-login-form">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="login-username" name="username" placeholder="Nama Lengkap Anda" autocomplete="off" required>
                            <label for="login-username"><i class="fas fa-user me-2"></i>Nama Lengkap (Username)</label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <input type="password" class="form-control" id="login-password" name="password" placeholder="Nomor NISN Anda" required>
                            <label for="login-password"><i class="fas fa-key me-2"></i>Nomor NISN (Password)</label>
                        </div>

                        <button type="submit" class="btn btn-action-primary w-100 py-3" id="btn-submit-login">
                            <i class="fas fa-sign-in-alt me-1"></i> Masuk ke Portal
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <span class="text-muted" style="font-size: 11px;">
                            <i class="fas fa-info-circle"></i> Username adalah Nama Lengkap Anda (huruf kecil/kapital sama saja) dan Password adalah Nomor NISN terdaftar.
                        </span>
                    </div>
                </div>

            <!-- STATE 2: Student IS Logged In -> Show Dashboard with Navigation Tabs -->
            <?php else: ?>
                
                <div class="row">
                    <!-- Column 1: Navigation Pill Menu (Responsive Layout) -->
                    <div class="col-12 col-lg-3 mb-4">
                        <div class="glass-card p-3">
                            <div class="d-flex flex-row flex-lg-column gap-2 overflow-auto pb-2 pb-lg-0" id="portal-tab-bar">
                                <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="glass-nav-pill active flex-fill flex-lg-grow-0" id="tab-btn-dashboard">
                                    <i class="fas fa-home"></i> <span>Dashboard</span>
                                </a>
                                <a href="javascript:void(0)" onclick="switchTab('scan')" class="glass-nav-pill flex-fill flex-lg-grow-0" id="tab-btn-scan">
                                    <i class="fas fa-camera"></i> <span>Scan QR</span>
                                    <?php if ($session_details): ?>
                                        <span class="badge bg-danger rounded-pill ms-auto" style="font-size: 9px; animation: pulse 1.5s infinite;">1</span>
                                    <?php endif; ?>
                                </a>
                                <a href="javascript:void(0)" onclick="switchTab('laporan')" class="glass-nav-pill flex-fill flex-lg-grow-0" id="tab-btn-laporan">
                                    <i class="fas fa-chart-bar"></i> <span>Laporan</span>
                                </a>
                                <a href="javascript:void(0)" onclick="switchTab('profile')" class="glass-nav-pill flex-fill flex-lg-grow-0" id="tab-btn-profile">
                                    <i class="fas fa-user-edit"></i> <span>Profil & Sandi</span>
                                </a>
                            </div>
                        </div>

                        <!-- Mini profile status widget -->
                        <div class="glass-card p-3 d-none d-lg-block text-center">
                            <div class="live-clock-card mb-3">
                                <div class="live-clock-text" id="live-clock">00:00:00</div>
                                <div class="live-date-text" id="live-date">Memuat waktu...</div>
                            </div>
                            <span class="text-muted small" style="font-size: 11px;">Akun Siswa Aktif</span>
                        </div>
                    </div>

                    <!-- Column 2: Dashboard Content Panel -->
                    <div class="col-12 col-lg-9">
                        
                        <!-- TAB PANE 1: Dashboard Overview -->
                        <div class="tab-pane-content" id="pane-dashboard">
                            
                            <!-- Banner active session alert if student scans directly -->
                            <?php if ($session_details): ?>
                                <div class="glass-card border-0 mb-4" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(16, 185, 129, 0.1) 100%); border: 1px solid rgba(99,102,241,0.3) !important;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle bg-success bg-opacity-25 p-3 text-success border border-success border-opacity-25" style="animation: pulse 2s infinite;">
                                            <i class="fas fa-bolt fs-4"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="fw-bold mb-1 text-white">Sesi Presensi Aktif Terdeteksi!</h5>
                                            <p class="text-muted small mb-0">Segera lakukan konfirmasi kehadiran Anda untuk pelajaran <strong><?php echo htmlspecialchars($session_details['nama_mapel']); ?></strong>.</p>
                                        </div>
                                        <div>
                                            <button onclick="switchTab('scan')" class="btn btn-sm btn-success px-3 rounded-pill fw-bold">Isi Presensi</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Small Live Clock for Mobile View -->
                            <div class="d-block d-lg-none glass-card text-center py-3 mb-3">
                                <div class="fs-3 fw-bold text-white text-shadow" id="live-clock-mobile">00:00:00</div>
                                <div class="text-muted small" id="live-date-mobile">Memuat waktu...</div>
                            </div>

                            <!-- Stat Widgets Summary -->
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <div class="stat-widget" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.1);">
                                        <div class="stat-icon" style="background: var(--neon-emerald); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);"><i class="fas fa-check-circle"></i></div>
                                        <div>
                                            <div class="stat-value"><?php echo $stats['Hadir']; ?></div>
                                            <div class="stat-label">Hadir</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stat-widget" style="background: rgba(99, 102, 241, 0.05); border-color: rgba(99, 102, 241, 0.1);">
                                        <div class="stat-icon" style="background: var(--neon-indigo); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);"><i class="fas fa-envelope-open-text"></i></div>
                                        <div>
                                            <div class="stat-value"><?php echo $stats['Izin']; ?></div>
                                            <div class="stat-label">Izin</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stat-widget" style="background: rgba(245, 158, 11, 0.05); border-color: rgba(245, 158, 11, 0.1);">
                                        <div class="stat-icon" style="background: var(--neon-amber); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);"><i class="fas fa-procedures"></i></div>
                                        <div>
                                            <div class="stat-value"><?php echo $stats['Sakit']; ?></div>
                                            <div class="stat-label">Sakit</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="stat-widget" style="background: rgba(244, 63, 94, 0.05); border-color: rgba(244, 63, 94, 0.1);">
                                        <div class="stat-icon" style="background: var(--neon-rose); box-shadow: 0 4px 10px rgba(244, 63, 94, 0.3);"><i class="fas fa-times-circle"></i></div>
                                        <div>
                                            <div class="stat-value"><?php echo $stats['Alfa']; ?></div>
                                            <div class="stat-label">Alfa</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Student Bio details card -->
                            <div class="glass-card">
                                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2 text-white">
                                    <i class="fas fa-id-card-alt text-indigo text-primary"></i> Detail Informasi Siswa
                                </h5>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 border-end border-secondary border-opacity-10">
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">NAMA LENGKAP</small>
                                            <span class="fw-semibold text-white fs-6"><?php echo htmlspecialchars($student_full['nama_lengkap']); ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">NOMOR NISN (PASSWORD)</small>
                                            <span class="fw-semibold text-white fs-6"><?php echo htmlspecialchars($student_full['nisn']); ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">KELAS</small>
                                            <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 px-3 py-1.5"><?php echo htmlspecialchars($student_full['nama_kelas']); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 ps-md-4">
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">JENIS KELAMIN</small>
                                            <span class="fw-semibold text-white"><?php echo $student_full['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">NOMOR HANDPHONE</small>
                                            <span class="fw-semibold text-white"><?php echo !empty($student_full['no_telp']) ? htmlspecialchars($student_full['no_telp']) : '-'; ?></span>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted d-block" style="font-size: 11px;">ALAMAT TEMPAT TINGGAL</small>
                                            <span class="fw-semibold text-white small"><?php echo !empty($student_full['alamat']) ? htmlspecialchars($student_full['alamat']) : '-'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB PANE 2: Scan Presensi -->
                        <div class="tab-pane-content" id="pane-scan" style="display: none;">
                            
                            <!-- If active checkin is present in URL -->
                            <?php if ($session_details): ?>
                                <div class="glass-card" id="form-container">
                                    <div class="text-center mb-4">
                                        <div class="success-checkmark bg-transparent border-0 d-inline-flex" style="width: auto; height: auto; margin-bottom: 8px;">
                                            <div class="brand-logo" style="width: 50px; height: 50px; font-size: 20px;">
                                                <i class="fas fa-check-double"></i>
                                            </div>
                                        </div>
                                        <h5 class="fw-bold mb-1 text-white">Konfirmasi Absensi Sesi Pelajaran</h5>
                                        <p class="text-muted small">Pastikan data berikut sesuai dengan kelas Anda</p>
                                    </div>

                                    <div class="session-info-box border border-secondary border-opacity-10 rounded-4 p-3 mb-4">
                                        <div class="row g-2">
                                            <div class="col-4 text-muted small fw-semibold">Pelajaran</div>
                                            <div class="col-8 text-white fw-bold"><?php echo htmlspecialchars($session_details['nama_mapel']); ?></div>
                                            
                                            <div class="col-4 text-muted small fw-semibold">Kelas Pelajaran</div>
                                            <div class="col-8 text-white fw-semibold"><?php echo htmlspecialchars($session_details['nama_kelas']); ?></div>
                                            
                                            <div class="col-4 text-muted small fw-semibold">Guru Pengajar</div>
                                            <div class="col-8 text-white fw-semibold"><?php echo htmlspecialchars($session_details['nama_guru']); ?></div>
                                            
                                            <div class="col-4 text-muted small fw-semibold">Waktu</div>
                                            <div class="col-8 text-white small"><?php echo htmlspecialchars($hari_indo . ', ' . substr($session_details['jam_mulai'], 0, 5) . '-' . substr($session_details['jam_selesai'], 0, 5)); ?></div>
                                            
                                            <div class="col-4 text-muted small fw-semibold">Tanggal</div>
                                            <div class="col-8 text-white small"><?php echo htmlspecialchars($tanggal_indo); ?></div>
                                        </div>
                                    </div>

                                    <div id="error-alert-box" style="display: none;">
                                        <div class="alert alert-custom-error">
                                            <i class="fas fa-exclamation-triangle me-2 fs-5"></i>
                                            <span id="error-message">Error</span>
                                        </div>
                                    </div>

                                    <?php if (!$is_class_matched): ?>
                                        <div class="alert alert-danger border-0 py-3" style="background: rgba(244, 63, 94, 0.12); color: #fecdd3; border-radius: 16px; font-size: 13px;">
                                            <i class="fas fa-times-circle me-2 fs-5 text-danger"></i>
                                            <strong>Kelas Tidak Cocok!</strong> Anda terdaftar pada kelas <b><?php echo htmlspecialchars($student_full['nama_kelas']); ?></b>, sedangkan sesi presensi ini hanya untuk kelas <b><?php echo htmlspecialchars($session_details['nama_kelas']); ?></b>.
                                        </div>
                                        <button class="btn btn-secondary w-100 py-3 rounded-12 mt-2" disabled style="border-radius: 12px; font-weight: 600;">
                                            <i class="fas fa-ban me-1"></i> Presensi Tidak Diizinkan
                                        </button>
                                    <?php else: ?>
                                        <form id="absen-form">
                                            <input type="hidden" name="jadwal_id" value="<?php echo $jadwal_id; ?>">
                                            <input type="hidden" name="tanggal" value="<?php echo htmlspecialchars($tanggal); ?>">
                                            <input type="hidden" name="nisn" value="<?php echo htmlspecialchars($student_full['nisn']); ?>">
                                            
                                            <button type="submit" class="btn btn-submit btn-action-primary w-100 py-3" id="btn-submit-absen">
                                                <i class="fas fa-check-double me-1"></i> Konfirmasi Kehadiran Saya
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Success checkin presentation -->
                                <div class="glass-card result-screen" id="success-container" style="display: none;">
                                    <div class="success-checkmark">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <h4 class="fw-bold mb-2 text-white">Presensi Berhasil Dicatat!</h4>
                                    <p class="text-success fw-semibold mb-4" id="success-student-name">Halo!</p>
                                    
                                    <div class="session-info-box border border-secondary border-opacity-10 text-start rounded-4 p-3 mb-4">
                                        <div class="text-center text-muted small border-bottom border-secondary border-opacity-15 pb-2 mb-2">
                                            <i class="fas fa-receipt me-1 text-success"></i>Tanda Terima Presensi Mandiri
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-4 text-muted small">Pelajaran</div>
                                            <div class="col-8 text-white fw-semibold"><?php echo htmlspecialchars($session_details['nama_mapel']); ?></div>
                                            
                                            <div class="col-4 text-muted small">Waktu Catat</div>
                                            <div class="col-8 text-white" id="success-time">-</div>
                                            
                                            <div class="col-4 text-muted small">Keterangan</div>
                                            <div class="col-8 text-success"><span class="badge bg-success-subtle text-success">HADIR MANDIRI</span></div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info py-2 mb-4" style="font-size: 11px; background: rgba(99, 102, 241, 0.08); border-color: rgba(99, 102, 241, 0.15); color: #cbd5e1;">
                                        <i class="fas fa-info-circle me-1 text-info"></i> Kehadiran Anda telah dicatat secara langsung di layar komputer guru.
                                    </div>
                                    
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <a href="absen.php" class="btn btn-outline-secondary w-100 rounded-pill py-2" style="font-size: 12px;">Ke Dashboard Siswa</a>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <a href="student_logout.php?jadwal_id=<?php echo $jadwal_id; ?>&tanggal=<?php echo htmlspecialchars($tanggal); ?>" class="btn btn-outline-danger w-100 rounded-pill py-2" style="font-size: 12px;">Logout Sesi</a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Standard QR code scan viewport using webcam -->
                                <div class="glass-card text-center">
                                    <h5 class="fw-bold mb-1 text-white">📸 Pindai QR Code Presensi Guru</h5>
                                    <p class="text-muted small mb-4">Dekatkan kamera handphone Anda ke QR Code presensi di layar komputer guru</p>
                                    
                                    <div class="scanner-container" id="scanner-view">
                                        <div class="scanner-laser"></div>
                                        <div id="qr-reader" style="width: 100%; border: none;"></div>
                                    </div>
                                    
                                    <div class="d-grid gap-3 max-width-350 mx-auto" style="max-width: 320px;">
                                        <button onclick="startScanner()" class="btn btn-action-primary py-2.5" id="btn-start-scan">
                                            <i class="fas fa-camera me-1"></i> Mulai Scan Kamera
                                        </button>
                                        
                                        <button onclick="stopScanner()" class="btn btn-outline-danger py-2.5" id="btn-stop-scan" style="display: none; border-radius: 12px;">
                                            <i class="fas fa-stop me-1"></i> Matikan Kamera
                                        </button>
                                    </div>
                                    
                                    <hr class="border-secondary border-opacity-15 my-4">
                                    
                                    <div class="text-start">
                                        <h6 class="fw-bold mb-2 text-white" style="font-size: 13px;"><i class="fas fa-keyboard me-1 text-indigo"></i> Atau Masukkan Tautan Presensi Secara Manual</h6>
                                        <form id="manual-link-form" onsubmit="handleManualLink(event)">
                                            <div class="input-group">
                                                <input type="text" class="form-control border-secondary border-opacity-20 text-white rounded-start-3" style="font-size: 12px;" id="manual-link" placeholder="Contoh: http://.../absen.php?jadwal_id=..." required>
                                                <button class="btn btn-action-primary" style="border-radius: 0 10px 10px 0 !important; font-size: 12px;" type="submit">Buka Link</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>

                        <!-- TAB PANE 3: Laporan Kehadiran per Mapel & Riwayat -->
                        <div class="tab-pane-content" id="pane-laporan" style="display: none;">
                            
                            <!-- Statistics card per subject -->
                            <div class="glass-card">
                                <h5 class="fw-bold mb-3 text-white"><i class="fas fa-table text-info me-2"></i> Rekapitulasi Presensi per Mata Pelajaran</h5>
                                
                                <?php if (empty($subject_stats)): ?>
                                    <div class="text-center py-4 text-muted small">
                                        <i class="fas fa-book-open fs-3 d-block mb-2"></i> Belum ada mata pelajaran terdaftar untuk kelas Anda.
                                    </div>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($subject_stats as $sub): ?>
                                            <?php 
                                            $total = $sub['hadir'] + $sub['izin'] + $sub['sakit'] + $sub['alfa'];
                                            $pct = $total > 0 ? round(($sub['hadir'] / $total) * 100) : 0;
                                            ?>
                                            <div class="col-12 col-md-6">
                                                <div class="subject-card">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div class="fw-bold text-white small text-truncate" style="max-width: 70%;"><?php echo htmlspecialchars($sub['nama_mapel']); ?></div>
                                                        <span class="badge rounded-pill <?php echo $pct >= 80 ? 'bg-success-subtle text-success' : ($pct >= 50 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'); ?>" style="font-size: 10px; font-weight: 700;">
                                                            <?php echo $pct; ?>% Hadir
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="subject-progress mb-3">
                                                        <div class="progress-bar bg-success" style="width: <?php echo $pct; ?>%"></div>
                                                    </div>
                                                    
                                                    <div class="d-flex justify-content-between text-muted" style="font-size: 10px; font-weight: 600;">
                                                        <span class="text-success"><i class="fas fa-check-circle me-0.5"></i> Hdr: <?php echo $sub['hadir']; ?></span>
                                                        <span class="text-primary"><i class="fas fa-envelope-open-text me-0.5"></i> Izn: <?php echo $sub['izin']; ?></span>
                                                        <span class="text-warning"><i class="fas fa-procedures me-0.5"></i> Skt: <?php echo $sub['sakit']; ?></span>
                                                        <span class="text-danger"><i class="fas fa-times-circle me-0.5"></i> Alf: <?php echo $sub['alfa']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Attendance History List -->
                            <div class="glass-card">
                                <h5 class="fw-bold mb-3 text-white"><i class="fas fa-history text-indigo me-2"></i> Riwayat Kehadiran Terbaru</h5>
                                
                                <?php if (empty($attendance_history)): ?>
                                    <div class="text-center py-4 text-muted small">
                                        <i class="fas fa-history fs-3 d-block mb-2"></i> Belum ada riwayat kehadiran tercatat.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-dark table-hover table-borderless align-middle small text-white">
                                            <thead>
                                                <tr class="text-muted" style="font-size: 11px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                    <th>Tanggal</th>
                                                    <th>Pelajaran</th>
                                                    <th>Status</th>
                                                    <th>Keterangan</th>
                                                    <th>Guru</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($attendance_history as $hist): ?>
                                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02);">
                                                        <td class="fw-semibold"><?php echo date('d-m-Y', strtotime($hist['tanggal'])); ?></td>
                                                        <td class="fw-semibold text-indigo"><?php echo htmlspecialchars($hist['nama_mapel']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $badge = [
                                                                'Hadir' => '<span class="badge bg-success-subtle text-success">Hadir</span>',
                                                                'Izin' => '<span class="badge bg-primary-subtle text-primary">Izin</span>',
                                                                'Sakit' => '<span class="badge bg-warning-subtle text-warning">Sakit</span>',
                                                                'Alfa' => '<span class="badge bg-danger-subtle text-danger">Alfa</span>'
                                                            ];
                                                            echo $badge[$hist['status']] ?? $hist['status'];
                                                            ?>
                                                        </td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars($hist['keterangan'] ?? '-'); ?></td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars($hist['nama_guru']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- TAB PANE 4: Edit Profile and Password -->
                        <div class="tab-pane-content" id="pane-profile" style="display: none;">
                            
                            <div class="glass-card">
                                <h5 class="fw-bold mb-2 text-white"><i class="fas fa-user-edit text-indigo me-2"></i> Perbarui Profil & Sandi Mandiri</h5>
                                <p class="text-muted small mb-4">Anda dapat memperbarui informasi kontak Anda. NISN berfungsi langsung sebagai password login Anda.</p>
                                
                                <div id="profile-feedback-box" style="display: none;">
                                    <div class="alert alert-custom-error mb-4" id="profile-feedback-alert">
                                        <i class="fas fa-info-circle me-2 fs-5"></i>
                                        <span id="profile-feedback-message">Feedback</span>
                                    </div>
                                </div>

                                <form id="edit-student-profile-form">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="profile-nama" name="nama_lengkap" placeholder="Nama Lengkap" value="<?php echo htmlspecialchars($student_full['nama_lengkap']); ?>" required>
                                                <label for="profile-nama">Nama Lengkap Siswa</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="profile-nisn" name="nisn" placeholder="Nomor NISN" value="<?php echo htmlspecialchars($student_full['nisn']); ?>" required>
                                                <label for="profile-nisn">Nomor NISN (Sebagai Sandi)</label>
                                            </div>
                                            <small class="text-warning" style="font-size: 10px; font-weight: 600;"><i class="fas fa-exclamation-triangle mt-1 me-1"></i>Mengubah NISN akan merubah kata sandi masuk Anda.</small>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="profile-telp" name="no_telp" placeholder="No Handphone" value="<?php echo htmlspecialchars($student_full['no_telp'] ?? ''); ?>">
                                                <label for="profile-telp">Nomor Handphone (WhatsApp)</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <div class="form-floating">
                                                <textarea class="form-control" id="profile-alamat" name="alamat" placeholder="Alamat Lengkap" style="height: 58px;"><?php echo htmlspecialchars($student_full['alamat'] ?? ''); ?></textarea>
                                                <label for="profile-alamat">Alamat Tempat Tinggal</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 text-end mt-4">
                                            <button type="submit" class="btn btn-action-primary px-4 py-2.5" id="btn-save-profile">
                                                <i class="fas fa-save me-1"></i> Simpan Perubahan Profil
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div>

                    </div>
                </div>

            <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Interactive Floating Toast Container -->
    <div class="custom-toast" id="feedback-toast">
        <div class="d-flex align-items-center justify-content-center bg-success text-white rounded-circle p-1.5" id="toast-icon-box" style="width: 28px; height: 28px;">
            <i class="fas fa-check" id="toast-icon" style="font-size: 12px;"></i>
        </div>
        <div class="text-white small fw-bold" id="toast-text">Tindakan berhasil!</div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        // Track the current active tab
        let activeTab = 'dashboard';

        document.addEventListener('DOMContentLoaded', function() {
            // Clock system
            updateClock();
            setInterval(updateClock, 1000);

            // AJAX Student Login handler
            const loginForm = document.getElementById('student-login-form');
            if (loginForm) {
                const loginContainer = document.getElementById('login-container');
                const loginErrorBox = document.getElementById('login-error-box');
                const loginErrorMessage = document.getElementById('login-error-message');
                const btnSubmitLogin = document.getElementById('btn-submit-login');

                loginForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    loginErrorBox.style.display = 'none';
                    loginContainer.classList.remove('error-shake');

                    btnSubmitLogin.disabled = true;
                    btnSubmitLogin.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memverifikasi Akun...';

                    const formData = new FormData(loginForm);

                    fetch('student_login.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(res => {
                        if (res.status === 'success') {
                            showToast('Verifikasi sukses! Selamat Datang.', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1200);
                        } else {
                            btnSubmitLogin.disabled = false;
                            btnSubmitLogin.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i> Masuk ke Portal';
                            
                            loginErrorMessage.innerText = res.message;
                            loginErrorBox.style.display = 'block';
                            loginContainer.classList.add('error-shake');
                            playVibrate();
                        }
                    })
                    .catch(err => {
                        btnSubmitLogin.disabled = false;
                        btnSubmitLogin.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i> Masuk ke Portal';
                        loginErrorMessage.innerText = 'Gagal terhubung dengan server. Silakan periksa koneksi Anda.';
                        loginErrorBox.style.display = 'block';
                        loginContainer.classList.add('error-shake');
                    });
                });
            }

            // AJAX Attendance Submission handler
            const absenForm = document.getElementById('absen-form');
            if (absenForm) {
                const formContainer = document.getElementById('form-container');
                const successContainer = document.getElementById('success-container');
                const errorAlertBox = document.getElementById('error-alert-box');
                const errorMessage = document.getElementById('error-message');
                const btnSubmitAbsen = document.getElementById('btn-submit-absen');
                const successStudentName = document.getElementById('success-student-name');
                const successTime = document.getElementById('success-time');

                absenForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    errorAlertBox.style.display = 'none';
                    formContainer.classList.remove('error-shake');

                    btnSubmitAbsen.disabled = true;
                    btnSubmitAbsen.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Mengirim Presensi...';

                    const formData = new FormData(absenForm);

                    fetch('process_absen.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(res => {
                        btnSubmitAbsen.disabled = false;
                        btnSubmitAbsen.innerHTML = '<i class="fas fa-check-double me-1"></i> Konfirmasi Kehadiran Saya';

                        if (res.status === 'success') {
                            playBeep();
                            successStudentName.innerText = `Halo, ${res.data.nama_siswa}!`;
                            successTime.innerText = res.data.waktu_absen;
                            
                            formContainer.style.opacity = '0';
                            setTimeout(() => {
                                formContainer.style.display = 'none';
                                successContainer.style.display = 'block';
                                successContainer.style.opacity = '1';
                            }, 300);
                        } else {
                            errorMessage.innerText = res.message;
                            errorAlertBox.style.display = 'block';
                            formContainer.classList.add('error-shake');
                            playVibrate();
                        }
                    })
                    .catch(err => {
                        btnSubmitAbsen.disabled = false;
                        btnSubmitAbsen.innerHTML = '<i class="fas fa-check-double me-1"></i> Konfirmasi Kehadiran Saya';
                        errorMessage.innerText = 'Gagal memproses presensi. Silakan coba kembali.';
                        errorAlertBox.style.display = 'block';
                        formContainer.classList.add('error-shake');
                    });
                });
            }

            // AJAX Edit Profile handler
            const editProfileForm = document.getElementById('edit-student-profile-form');
            if (editProfileForm) {
                const btnSaveProfile = document.getElementById('btn-save-profile');
                const feedbackBox = document.getElementById('profile-feedback-box');
                const feedbackAlert = document.getElementById('profile-feedback-alert');
                const feedbackMessage = document.getElementById('profile-feedback-message');

                editProfileForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    feedbackBox.style.display = 'none';
                    btnSaveProfile.disabled = true;
                    btnSaveProfile.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Menyimpan Perubahan...';

                    const formData = new FormData(editProfileForm);

                    fetch('update_student_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(res => {
                        btnSaveProfile.disabled = false;
                        btnSaveProfile.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Perubahan Profil';

                        if (res.status === 'success') {
                            showToast(res.message, 'success');
                            feedbackAlert.className = 'alert alert-success border-0';
                            feedbackMessage.innerText = res.message;
                            feedbackBox.style.display = 'block';
                            
                            // Instantly update header text if updated
                            const headName = document.querySelector('.fw-bold.text-white.small');
                            if (headName) headName.innerText = res.data.nama_lengkap;
                        } else {
                            showToast(res.message, 'error');
                            feedbackAlert.className = 'alert alert-custom-error';
                            feedbackMessage.innerText = res.message;
                            feedbackBox.style.display = 'block';
                            playVibrate();
                        }
                    })
                    .catch(err => {
                        btnSaveProfile.disabled = false;
                        btnSaveProfile.innerHTML = '<i class="fas fa-save me-1"></i> Simpan Perubahan Profil';
                        showToast('Kesalahan server. Gagal memperbarui profil.', 'error');
                    });
                });
            }

            // Auto navigate to Scan tab if active session is scanned
            <?php if ($session_details): ?>
                switchTab('scan');
            <?php endif; ?>
        });

        // Tab Switching logic
        function switchTab(tabId) {
            activeTab = tabId;
            
            // Hide all panes
            document.querySelectorAll('.tab-pane-content').forEach(pane => {
                pane.style.display = 'none';
            });
            
            // Deactivate all tab buttons
            document.querySelectorAll('#portal-tab-bar a').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected pane and button active state
            document.getElementById(`pane-${tabId}`).style.display = 'block';
            document.getElementById(`tab-btn-${tabId}`).classList.add('active');
            
            // Stop camera scanner if navigating away from scan tab
            if (tabId !== 'scan') {
                stopScanner();
            }
        }

        // Live clock generator
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateString = now.toLocaleDateString('id-ID', options);
            
            // Standard
            const clockEl = document.getElementById('live-clock');
            const dateEl = document.getElementById('live-date');
            if (clockEl) clockEl.innerText = timeString;
            if (dateEl) dateEl.innerText = dateString;
            
            // Mobile
            const clockMobEl = document.getElementById('live-clock-mobile');
            const dateMobEl = document.getElementById('live-date-mobile');
            if (clockMobEl) clockMobEl.innerText = timeString;
            if (dateMobEl) dateMobEl.innerText = dateString;
        }

        // Web Audio API beep
        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioCtx.currentTime); // Sound pitch (A5 note)
                gainNode.gain.setValueAtTime(0.08, audioCtx.currentTime);
                
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.15); // beep duration 0.15s
            } catch (e) {
                console.log("Web Audio API not supported");
            }
        }

        function playVibrate() {
            if (navigator.vibrate) navigator.vibrate(100);
        }

        // Toast feedback trigger
        function showToast(message, type = 'success') {
            const toast = document.getElementById('feedback-toast');
            const toastText = document.getElementById('toast-text');
            const iconBox = document.getElementById('toast-icon-box');
            const icon = document.getElementById('toast-icon');

            toastText.innerText = message;
            
            if (type === 'success') {
                iconBox.className = 'd-flex align-items-center justify-content-center bg-success text-white rounded-circle p-1.5';
                icon.className = 'fas fa-check';
            } else {
                iconBox.className = 'd-flex align-items-center justify-content-center bg-danger text-white rounded-circle p-1.5';
                icon.className = 'fas fa-times';
            }

            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // QR Camera Scanner engine using html5-qrcode
        let html5QrcodeScanner = null;

        function startScanner() {
            const scannerView = document.getElementById('scanner-view');
            scannerView.style.display = 'block';
            
            html5QrcodeScanner = new Html5Qrcode("qr-reader");
            
            const config = { 
                fps: 10, 
                qrbox: function(width, height) {
                    const minEdge = Math.min(width, height);
                    const qrboxSize = Math.floor(minEdge * 0.7);
                    return { width: qrboxSize, height: qrboxSize };
                }
            };
            
            html5QrcodeScanner.start(
                { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => {
                    // Success detected
                    playBeep();
                    playVibrate();
                    stopScanner();
                    
                    // Parse text URL
                    if (decodedText.includes('absen.php')) {
                        showToast('QR Code Tautan Terdeteksi! Membuka...', 'success');
                        setTimeout(() => {
                            window.location.href = decodedText;
                        }, 800);
                    } else {
                        alert('QR Code Terbaca, namun bukan tautan presensi valid: ' + decodedText);
                        startScanner(); // Restart
                    }
                },
                (errorMessage) => {
                    // silent parsing errors
                }
            ).then(() => {
                document.getElementById('btn-start-scan').style.display = 'none';
                document.getElementById('btn-stop-scan').style.display = 'block';
            }).catch(err => {
                showToast("Gagal mengakses kamera: " + err, 'error');
                scannerView.style.display = 'none';
            });
        }

        function stopScanner() {
            if (html5QrcodeScanner && html5QrcodeScanner.isScanning) {
                html5QrcodeScanner.stop().then(() => {
                    document.getElementById('scanner-view').style.display = 'none';
                    document.getElementById('btn-start-scan').style.display = 'block';
                    document.getElementById('btn-stop-scan').style.display = 'none';
                }).catch(err => {
                    console.error("Gagal stop camera: ", err);
                });
            }
        }

        // Handle manual text link submission
        function handleManualLink(event) {
            event.preventDefault();
            const linkInput = document.getElementById('manual-link').value;
            if (linkInput.includes('absen.php')) {
                window.location.href = linkInput;
            } else {
                showToast('Tautan presensi tidak valid. Cek kembali tautan Anda.', 'error');
                playVibrate();
            }
        }
    </script>
</body>
</html>
