# Sistem Absensi SMA - Quick Start Guide

## 🚀 Memulai Cepat

### 1. Setup Database
```bash
# Buka terminal/command prompt
mysql -u root -p < database.sql

# Atau melalui phpMyAdmin:
# - Buka phpMyAdmin di browser
# - Buat database baru: "absensi_sma"
# - Import file database.sql
```

### 2. Konfigurasi Database
Edit file `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Ganti dengan username MySQL Anda
define('DB_PASS', '');            // Ganti dengan password MySQL Anda
define('DB_NAME', 'absensi_sma');
```

### 3. Jalankan Setup Script
Akses: `http://localhost/presensi_sma/install.php`
- Ubah password admin default
- Klik "Setup Selesai"
- Hapus file `install.php` setelah setup

### 4. Login
Akses: `http://localhost/presensi_sma/public/login.php`
- Username: `admin`
- Password: (password yang Anda set saat setup)

## 📊 Data yang Perlu Diinput

### Urutan Penggunaan:
1. **Data Guru** - Tambahkan semua guru
2. **Data Kelas** - Buat semua kelas dan assign wali kelas
3. **Mata Pelajaran** - Buat mata pelajaran dan assign guru
4. **Jadwal Pelajaran** - Buat jadwal untuk setiap kelas (opsional, bisa dibuat otomatis)
5. **Data Siswa** - Tambahkan siswa dan assign ke kelas
6. **Input Absensi** - Mulai input absensi harian

## 💡 Tips Penggunaan

### Untuk Admin:
- Gunakan menu "Data Siswa", "Data Guru", etc untuk input data master
- Lihat "Laporan Absensi" untuk monitoring kehadiran
- Update data regular sesuai kebutuhan

### Untuk Guru:
- Login dengan akun guru
- Pilih kelas di menu "Absensi"
- Input absensi setiap jam pelajaran
- Lihat rekap absensi kelas Anda

### Best Practices:
- Backup database secara berkala
- Gunakan password yang kuat untuk admin
- Jangan share akun guru
- Update data siswa setiap tahun ajaran baru
- Hapus file `install.php` setelah setup awal

## 🔐 Keamanan

- Semua password ter-encrypt dengan bcrypt
- CSRF protection pada semua form
- SQL injection prevention dengan prepared statements
- Input sanitization
- Session timeout otomatis

## 📱 Akses Multi-Device

Aplikasi bisa diakses dari:
- Desktop/Laptop (Firefox, Chrome, Safari, Edge)
- Tablet (iOS Safari, Android Chrome)
- Mobile (responsive design)

## ❓ FAQ

**Q: Lupa password admin?**
A: Buka database langsung dan update table users

**Q: Bagaimana cara menambah guru baru?**
A: Login sebagai admin → Menu Data Guru → Tambah Guru

**Q: Bisa input absensi mundur?**
A: Ya, saat input absensi Anda bisa pilih tanggal apapun

**Q: Laporan bisa diprint?**
A: Ya, klik tombol print atau gunakan fitur print browser (Ctrl+P)

**Q: Perlu backup database?**
A: Ya, sangat disarankan. Gunakan phpMyAdmin atau command line

## 📚 Dokumentasi Lengkap

Lihat file `README.md` untuk dokumentasi lengkap sistem.

---

**Selamat menggunakan Sistem Absensi SMA!** 🎉
