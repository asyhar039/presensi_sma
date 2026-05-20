<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/helpers.php';

requireLogin();

$user = getCurrentUser();
$kelas_id = intval($_GET['kelas_id'] ?? 0);

if ($kelas_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get kelas info
try {
    $result = query("SELECT * FROM kelas WHERE id = $kelas_id", $conn);
    if ($result->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    $kelas = $result->fetch_assoc();
} catch (Exception $e) {
    header("Location: index.php");
    exit();
}

// Get jadwal
if ($user['role'] !== 'admin') {
    $user_id = $user['id'];
    $result = query("SELECT g.id FROM guru g JOIN users u ON g.user_id = u.id WHERE u.id = $user_id", $conn);
    if ($result->num_rows > 0) {
        $guru = $result->fetch_assoc();
        $jadwal_result = query("SELECT * FROM jadwal_pelajaran WHERE kelas_id = $kelas_id AND guru_id = {$guru['id']}", $conn);
        $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $jadwal_list = [];
    }
} else {
    $jadwal_result = query("SELECT * FROM jadwal_pelajaran WHERE kelas_id = $kelas_id", $conn);
    $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Barcode Absensi - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        #reader { width: 100%; max-width: 600px; margin: 0 auto; border-radius: 10px; overflow: hidden; border: 2px solid #ddd; }
        .scan-history { max-height: 300px; overflow-y: auto; }
        .beep { display: none; }
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
            <h4>📷 Scan Barcode - <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h4>
            <div>
                <a href="input.php?kelas_id=<?php echo $kelas['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-keyboard"></i> Input Manual</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Kamera Scanner</h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="alert alert-warning" id="setup-warning">
                            <i class="fas fa-exclamation-triangle"></i> Silakan pilih Tanggal dan Jadwal terlebih dahulu di panel sebelah kanan untuk mengaktifkan kamera.
                        </div>
                        <div id="reader"></div>
                        <div id="scan-result" class="mt-3"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3">Pengaturan Absensi</h5>
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="tanggal" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jadwal Pelajaran</label>
                            <select class="form-select" id="jadwal_id">
                                <option value="">- Pilih Jadwal -</option>
                                <?php foreach ($jadwal_list as $jadwal): ?>
                                    <option value="<?php echo $jadwal['id']; ?>">
                                        <?php
                                        $mapel = query("SELECT nama_mapel FROM mata_pelajaran WHERE id = {$jadwal['mata_pelajaran_id']}", $conn)->fetch_assoc();
                                        echo htmlspecialchars($mapel['nama_mapel']) . " (" . $jadwal['hari'] . " " . substr($jadwal['jam_mulai'], 0, 5) . "-" . substr($jadwal['jam_selesai'], 0, 5) . ")";
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary w-100" id="btn-start-scan" disabled>
                            <i class="fas fa-play"></i> Mulai Scan
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Siswa Berhasil Diabsen</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush scan-history" id="history-list">
                            <li class="list-group-item text-center text-muted" id="empty-history">Belum ada data scan</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Beep Sound -->
    <audio id="beep-sound" class="beep">
        <source src="data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU" type="audio/wav">
    </audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const jadwalSelect = document.getElementById('jadwal_id');
            const tanggalInput = document.getElementById('tanggal');
            const btnStart = document.getElementById('btn-start-scan');
            const setupWarning = document.getElementById('setup-warning');
            const historyList = document.getElementById('history-list');
            const emptyHistory = document.getElementById('empty-history');
            const scanResult = document.getElementById('scan-result');
            
            const kelasId = <?php echo $kelas_id; ?>;
            let html5QrcodeScanner = null;
            let isScanning = false;
            let lastScannedCode = null;
            let scanTimeout = null;

            // Audio logic for beep
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            function playBeep() {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.type = 'sine';
                oscillator.frequency.value = 1000;
                gainNode.gain.value = 0.1;
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.start();
                setTimeout(() => {
                    oscillator.stop();
                }, 100);
            }

            // Enable start button if setup is complete
            function checkSetup() {
                if (jadwalSelect.value && tanggalInput.value) {
                    btnStart.disabled = false;
                    setupWarning.style.display = 'none';
                } else {
                    btnStart.disabled = true;
                    setupWarning.style.display = 'block';
                    if (isScanning && html5QrcodeScanner) {
                        html5QrcodeScanner.clear();
                        isScanning = false;
                        btnStart.innerHTML = '<i class="fas fa-play"></i> Mulai Scan';
                        btnStart.classList.replace('btn-danger', 'btn-primary');
                    }
                }
            }

            jadwalSelect.addEventListener('change', checkSetup);
            tanggalInput.addEventListener('change', checkSetup);

            function onScanSuccess(decodedText, decodedResult) {
                // Prevent duplicate scans within 3 seconds
                if (decodedText === lastScannedCode) return;
                
                lastScannedCode = decodedText;
                clearTimeout(scanTimeout);
                scanTimeout = setTimeout(() => {
                    lastScannedCode = null;
                }, 3000);

                const jadwalId = jadwalSelect.value;
                const tanggal = tanggalInput.value;

                if (!jadwalId || !tanggal) {
                    alert('Pilih jadwal dan tanggal terlebih dahulu!');
                    return;
                }

                playBeep();
                scanResult.innerHTML = `<div class="alert alert-info">Memproses: ${decodedText}...</div>`;

                // Send to backend
                const formData = new FormData();
                formData.append('nisn', decodedText);
                formData.append('kelas_id', kelasId);
                formData.append('jadwal_id', jadwalId);
                formData.append('tanggal', tanggal);

                fetch('process_scan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        scanResult.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Berhasil absen: <b>${data.nama_siswa}</b></div>`;
                        
                        // Add to history
                        if (emptyHistory) emptyHistory.style.display = 'none';
                        
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center bg-light';
                        li.innerHTML = `
                            <div>
                                <h6 class="my-0">${data.nama_siswa}</h6>
                                <small class="text-muted">${decodedText}</small>
                            </div>
                            <span class="badge bg-success rounded-pill">Hadir</span>
                        `;
                        historyList.insertBefore(li, historyList.firstChild);
                    } else {
                        scanResult.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Gagal: ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    scanResult.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan jaringan.</div>`;
                });
            }

            function onScanFailure(error) {
                // handle scan failure, usually better to ignore and keep scanning.
            }

            btnStart.addEventListener('click', function() {
                if (!isScanning) {
                    html5QrcodeScanner = new Html5QrcodeScanner(
                        "reader",
                        { fps: 10, qrbox: {width: 250, height: 250} },
                        /* verbose= */ false);
                    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                    isScanning = true;
                    btnStart.innerHTML = '<i class="fas fa-stop"></i> Hentikan Scan';
                    btnStart.classList.replace('btn-primary', 'btn-danger');
                } else {
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.clear();
                    }
                    isScanning = false;
                    btnStart.innerHTML = '<i class="fas fa-play"></i> Mulai Scan';
                    btnStart.classList.replace('btn-danger', 'btn-primary');
                    scanResult.innerHTML = '';
                }
            });
            
            checkSetup();
        });
    </script>
</body>
</html>
