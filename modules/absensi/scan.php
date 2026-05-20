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
        $jadwal_result = query("SELECT j.*, m.nama_mapel FROM jadwal_pelajaran j JOIN mata_pelajaran m ON j.mata_pelajaran_id = m.id WHERE j.kelas_id = $kelas_id AND j.guru_id = {$guru['id']}", $conn);
        $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
    } else {
        $jadwal_list = [];
    }
} else {
    $jadwal_result = query("SELECT j.*, m.nama_mapel FROM jadwal_pelajaran j JOIN mata_pelajaran m ON j.mata_pelajaran_id = m.id WHERE j.kelas_id = $kelas_id", $conn);
    $jadwal_list = $jadwal_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi QR Code - Sistem Absensi SMA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f7fa; }
        .sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; position: fixed; width: 250px; left: 0; top: 0; overflow-y: auto; }
        .sidebar .logo { text-align: center; color: white; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 5px; padding: 12px 15px; display: block; text-decoration: none; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 20px; }
        .topbar { background: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .scan-history { max-height: 350px; overflow-y: auto; }
        
        .qr-code-wrapper {
            position: relative;
            background: white;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: inline-block;
            transition: all 0.3s ease;
        }
        .qr-code-wrapper:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.95; }
            100% { transform: scale(1); opacity: 1; }
        }
        .animate-pulse {
            animation: pulse 2s infinite ease-in-out;
        }
        .status-badge-container {
            display: inline-block;
            border-radius: 50px;
            font-weight: 600;
            padding: 6px 16px;
            font-size: 14px;
        }
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
                <a href="../jadwal/" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Pelajaran</a>
            <?php endif; ?>
            <a href="../absensi/" class="nav-link active"><i class="fas fa-clipboard-list"></i> Absensi</a>
            <a href="../absensi/laporan.php" class="nav-link"><i class="fas fa-chart-bar"></i> Laporan Absensi</a>
            <hr style="border-color: rgba(255,255,255,0.2);">
            <a href="../../public/logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h4>📢 Presensi QR Code - <?php echo htmlspecialchars($kelas['nama_kelas']); ?></h4>
            <div>
                <a href="input.php?kelas_id=<?php echo $kelas['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-keyboard"></i> Input Manual</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="card mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-qrcode text-primary me-2"></i>QR Code Presensi</h5>
                    </div>
                    <div class="card-body p-4 text-center">
                        <div class="alert alert-warning" id="setup-warning" style="margin-bottom: 0;">
                            <i class="fas fa-exclamation-triangle"></i> Silakan pilih Tanggal dan Jadwal terlebih dahulu di panel sebelah kanan untuk menampilkan QR Code.
                        </div>
                        
                        <!-- QR Code Container (hidden by default) -->
                        <div id="qr-container" style="display: none;">
                            <div class="status-badge-container bg-success text-white mb-3 animate-pulse">
                                <span class="spinner-grow spinner-grow-sm me-1" role="status" aria-hidden="true" style="width: 12px; height: 12px;"></span>
                                Presensi Aktif
                            </div>
                            
                            <div>
                                <div class="qr-code-wrapper border mb-3">
                                    <img id="qr-image" src="" alt="QR Code Absensi" class="img-fluid" style="width: 280px; height: 280px;">
                                </div>
                            </div>
                            
                            <div class="text-muted small mb-4">
                                <i class="fas fa-info-circle text-info"></i> Tampilkan QR Code ini di depan kelas agar siswa dapat memindainya melalui ponsel masing-masing.
                            </div>
                            
                            <!-- Shareable link section -->
                            <div class="input-group mb-2 mx-auto" style="max-width: 480px;">
                                <input type="text" id="raw-url" class="form-control bg-light" readonly style="font-size: 13px;">
                                <button class="btn btn-outline-primary" type="button" id="btn-copy">
                                    <i class="fas fa-copy"></i> Salin Tautan
                                </button>
                            </div>
                            <div id="copy-toast" class="text-success small" style="display: none; font-weight: 500;">
                                <i class="fas fa-check-circle"></i> Tautan berhasil disalin!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3"><i class="fas fa-sliders text-secondary me-2"></i>Pengaturan Absensi</h5>
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
                                        echo htmlspecialchars($jadwal['nama_mapel']) . " (" . $jadwal['hari'] . " " . substr($jadwal['jam_mulai'], 0, 5) . "-" . substr($jadwal['jam_selesai'], 0, 5) . ")";
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary w-100 py-2 fs-6 fw-semibold" id="btn-start-scan" disabled>
                            <i class="fas fa-play me-1"></i> Aktifkan Presensi QR
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check text-success me-2"></i>Siswa Berhasil Diabsen</h5>
                        <span class="badge bg-primary rounded-pill" id="attendees-count">0</span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush scan-history" id="history-list">
                            <li class="list-group-item text-center text-muted py-4" id="empty-history">
                                <i class="fas fa-users d-block fs-3 mb-2 opacity-50"></i>
                                Belum ada data presensi masuk
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const jadwalSelect = document.getElementById('jadwal_id');
            const tanggalInput = document.getElementById('tanggal');
            const btnStart = document.getElementById('btn-start-scan');
            const setupWarning = document.getElementById('setup-warning');
            const qrContainer = document.getElementById('qr-container');
            const qrImage = document.getElementById('qr-image');
            const rawUrlInput = document.getElementById('raw-url');
            const btnCopy = document.getElementById('btn-copy');
            const copyToast = document.getElementById('copy-toast');
            
            const historyList = document.getElementById('history-list');
            const emptyHistory = document.getElementById('empty-history');
            const attendeesCount = document.getElementById('attendees-count');
            
            let isQRActive = false;
            let pollInterval = null;
            let checkedInNisns = [];

            // Audio logic for beep notification
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            function playBeep() {
                try {
                    const oscillator = audioCtx.createOscillator();
                    const gainNode = audioCtx.createGain();
                    
                    oscillator.type = 'sine';
                    oscillator.frequency.value = 900;
                    gainNode.gain.value = 0.15;
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioCtx.destination);
                    
                    oscillator.start();
                    setTimeout(() => {
                        oscillator.stop();
                    }, 120);
                } catch (e) {
                    console.log('Audio Context error: ', e);
                }
            }

            // Enable/disable the activation button
            function checkSetup() {
                if (jadwalSelect.value && tanggalInput.value) {
                    btnStart.disabled = false;
                    if (!isQRActive) {
                        setupWarning.style.display = 'block';
                        setupWarning.innerHTML = '<i class="fas fa-info-circle text-primary"></i> Sesi terkonfigurasi. Silakan klik tombol <b>Aktifkan Presensi QR</b> untuk memulai.';
                        setupWarning.className = "alert alert-info";
                    }
                } else {
                    btnStart.disabled = true;
                    setupWarning.style.display = 'block';
                    setupWarning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Silakan pilih Tanggal dan Jadwal terlebih dahulu di panel sebelah kanan untuk menampilkan QR Code.';
                    setupWarning.className = "alert alert-warning";
                    stopQRFlow();
                }
            }

            jadwalSelect.addEventListener('change', checkSetup);
            tanggalInput.addEventListener('change', checkSetup);

            // Copy Link function
            btnCopy.addEventListener('click', function() {
                rawUrlInput.select();
                rawUrlInput.setSelectionRange(0, 99999); // For mobile devices
                navigator.clipboard.writeText(rawUrlInput.value).then(function() {
                    copyToast.style.display = 'block';
                    setTimeout(() => {
                        copyToast.style.display = 'none';
                    }, 3000);
                });
            });

            // Start QR code generation & polling
            function startQRFlow() {
                isQRActive = true;
                
                // UI changes
                setupWarning.style.display = 'none';
                qrContainer.style.display = 'block';
                
                // Disable inputs to avoid changing configs while scanning
                jadwalSelect.disabled = true;
                tanggalInput.disabled = true;
                
                btnStart.innerHTML = '<i class="fas fa-stop me-1"></i> Hentikan Presensi QR';
                btnStart.classList.replace('btn-primary', 'btn-danger');

                // Dynamic URL generation for student
                const protocol = window.location.protocol;
                const host = window.location.host;
                
                // Dynamically get the base path (e.g. /presensi_sma)
                const currentPath = window.location.pathname;
                const modulesIdx = currentPath.indexOf('/modules/absensi/');
                const basePath = modulesIdx !== -1 ? currentPath.substring(0, modulesIdx) : '/presensi_sma';
                
                const absenUrl = `${protocol}//${host}${basePath}/public/absen.php?jadwal_id=${jadwalSelect.value}&tanggal=${tanggalInput.value}`;
                
                // Set text input URL
                rawUrlInput.value = absenUrl;

                // Load QR Image
                const qrApiUrl = `https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=${encodeURIComponent(absenUrl)}`;
                qrImage.src = qrApiUrl;

                // Initialize history array
                checkedInNisns = [];
                
                // Load attendees instantly and set interval
                fetchAttendees();
                pollInterval = setInterval(fetchAttendees, 3000);
            }

            // Stop QR flow and polling
            function stopQRFlow() {
                isQRActive = false;
                
                // UI changes
                qrContainer.style.display = 'none';
                
                // Enable inputs
                jadwalSelect.disabled = false;
                tanggalInput.disabled = false;
                
                btnStart.innerHTML = '<i class="fas fa-play me-1"></i> Aktifkan Presensi QR';
                btnStart.classList.replace('btn-danger', 'btn-primary');

                // Clear interval
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
                
                checkSetup();
            }

            // Polling function to get checked-in students
            function fetchAttendees() {
                const jadwalId = jadwalSelect.value;
                const tanggal = tanggalInput.value;

                if (!jadwalId || !tanggal) return;

                fetch(`get_attendees.php?jadwal_id=${jadwalId}&tanggal=${tanggal}`)
                    .then(response => response.json())
                    .then(res => {
                        if (res.status === 'success') {
                            renderAttendees(res.data);
                        }
                    })
                    .catch(err => console.error('Error fetching attendees:', err));
            }

            // Render list of attendees
            function renderAttendees(data) {
                if (data.length === 0) {
                    historyList.innerHTML = `
                        <li class="list-group-item text-center text-muted py-4" id="empty-history">
                            <i class="fas fa-users d-block fs-3 mb-2 opacity-50"></i>
                            Belum ada data presensi masuk
                        </li>
                    `;
                    attendeesCount.innerText = '0';
                    checkedInNisns = [];
                    return;
                }

                attendeesCount.innerText = data.length;
                
                let html = '';
                let playBeepSound = false;

                data.forEach(student => {
                    // If we have a new NISN checked in, trigger beep!
                    if (!checkedInNisns.includes(student.nisn)) {
                        checkedInNisns.push(student.nisn);
                        // Only play sound if this is not the first load of many
                        if (checkedInNisns.length > 1 || isQRActive) {
                            playBeepSound = true;
                        }
                    }

                    html += `
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3 border-start border-success border-4">
                            <div>
                                <h6 class="my-0 fw-semibold text-dark">${escapeHTML(student.nama_lengkap)}</h6>
                                <small class="text-muted"><i class="fas fa-id-card me-1"></i>${escapeHTML(student.nisn)}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill mb-1">
                                    <i class="fas fa-check-circle me-1"></i>Hadir
                                </span>
                                <div class="text-muted" style="font-size: 11px;">
                                    <i class="far fa-clock me-1"></i>${escapeHTML(student.waktu_absen)}
                                </div>
                            </div>
                        </li>
                    `;
                });

                historyList.innerHTML = html;

                if (playBeepSound && isQRActive) {
                    playBeep();
                }
            }

            // Simple HTML escape helper
            function escapeHTML(str) {
                return str.replace(/[&<>'"]/g, 
                    tag => ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag] || tag)
                );
            }

            btnStart.addEventListener('click', function() {
                if (!isQRActive) {
                    startQRFlow();
                } else {
                    stopQRFlow();
                }
            });

            checkSetup();
        });
    </script>
</body>
</html>
