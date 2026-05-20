# Sistem Absensi SMA

Aplikasi web untuk manajemen absensi siswa dan guru di sekolah menengah atas (SMA).

## 📋 Fitur Utama

### 1. **Login & Authentikasi**
- Login dengan role Admin dan Guru
- Session management yang aman
- Password encryption dengan bcrypt

### 2. **Manajemen Data**
- **Data Siswa**: CRUD siswa, organisir berdasarkan kelas
- **Data Guru**: CRUD guru, penugasan mata pelajaran
- **Data Kelas**: Manajemen kelas dan wali kelas
- **Mata Pelajaran**: Penugasan guru ke mata pelajaran

### 3. **Absensi**
- Input absensi per kelas dan jadwal pelajaran
- 4 status absensi: Hadir, Izin, Sakit, Alfa
- Keterangan untuk setiap absensi

### 4. **Laporan**
- Rekap absensi bulanan per siswa
- Statistik absensi (Hadir, Izin, Sakit, Alfa)
- Filter berdasarkan bulan, tahun, dan kelas

### 5. **Security**
- CSRF Token untuk semua form
- Input sanitization
- SQL Prepared Statements
- Role-based access control

## 🛠️ Instalasi

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Web Server (Apache/Nginx)

### Langkah Instalasi

1. **Setup Database**
   - Buka MySQL/phpMyAdmin
   - Import file `database.sql`
   ```bash
   mysql -u root -p < database.sql
   ```

2. **Konfigurasi Database**
   - Edit file `config/database.php`
   - Sesuaikan kredensial database:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'absensi_sma');
   ```

3. **Setup Web Server**
   - Copy folder project ke document root server
   - Pastikan PHP modules ter-install: `mysqli`, `session`

4. **Jalankan Aplikasi**
   - Akses melalui browser: `http://localhost/presensi_sma/public/`
   - Atau akses langsung login: `http://localhost/presensi_sma/public/login.php`

## 👤 Default Login

Akun admin default telah dibuat di database:

```
Username: admin
Password: admin123
```

⚠️ **PENTING**: Ubah password admin setelah pertama kali login!

## 📁 Struktur Folder

```
absensi-sma/
├── config/
│   └── database.php          # Konfigurasi database
├── app/
│   ├── auth.php              # Authentikasi & login
│   ├── middleware.php        # Middleware & security
│   └── helpers.php           # Helper functions
├── modules/
│   ├── siswa/                # Modul Data Siswa
│   ├── guru/                 # Modul Data Guru
│   ├── kelas/                # Modul Data Kelas
│   ├── mapel/                # Modul Mata Pelajaran
│   └── absensi/              # Modul Absensi
├── public/
│   ├── index.php             # Redirect page
│   ├── login.php             # Halaman login
│   ├── dashboard.php         # Dashboard
│   └── logout.php            # Logout
├── assets/
│   ├── css/
│   │   └── style.css         # Stylesheet
│   └── js/
│       └── script.js         # JavaScript
└── database.sql              # Database schema
```

## 🔄 Workflow Aplikasi

### Sebagai Admin
1. Login dengan akun admin
2. Kelola data: Siswa, Guru, Kelas, Mata Pelajaran
3. Lihat laporan absensi semua siswa
4. Verifikasi data absensi

### Sebagai Guru
1. Login dengan akun guru
2. Input absensi untuk kelas yang mengajar
3. Lihat laporan absensi siswa di kelas
4. Kelola mata pelajaran (jika diperlukan)

### Proses Absensi
1. Pilih kelas → pilih jadwal pelajaran
2. Pilih tanggal absensi
3. Input status setiap siswa (Hadir/Izin/Sakit/Alfa)
4. Simpan data
5. Sistem otomatis update rekap bulanan

## 📊 Tabel Database

### users
- id, username, password, email, nama_lengkap, role, is_active, created_at, updated_at

### guru
- id, user_id, nip, no_telp, alamat, jenis_kelamin, tanggal_lahir, created_at

### siswa
- id, nisn, nama_lengkap, jenis_kelamin, tanggal_lahir, no_telp, alamat, kelas_id, is_active, created_at, updated_at

### kelas
- id, nama_kelas, tingkat, jurusan, guru_id, created_at

### mata_pelajaran
- id, nama_mapel, kode_mapel, guru_id, created_at

### jadwal_pelajaran
- id, kelas_id, mata_pelajaran_id, guru_id, hari, jam_mulai, jam_selesai, created_at

### absensi
- id, siswa_id, jadwal_id, tanggal, status, keterangan, dicatat_oleh, created_at, updated_at

### rekap_absensi
- id, siswa_id, bulan, tahun, total_hadir, total_izin, total_sakit, total_alfa, created_at, updated_at

## 🎨 Teknologi yang Digunakan

- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Vanilla JS
- **Security**: bcrypt, CSRF Token, Prepared Statements

## 📝 Catatan Penting

1. **Backup Database**: Lakukan backup database secara berkala
2. **Password Admin**: Ubah password default admin
3. **Maintenance**: Bersihkan data absensi lama secara berkala
4. **Hosting**: Pastikan server mendukung PHP dan MySQL
5. **SSL**: Gunakan HTTPS pada production environment

## 🐛 Troubleshooting

### Error: Connection failed
- Periksa konfigurasi database di `config/database.php`
- Pastikan MySQL service berjalan
- Verifikasi username dan password database

### Error: Database tidak ditemukan
- Import `database.sql` ke MySQL
- Pastikan nama database sesuai dengan config

### Login gagal
- Pastikan database sudah ter-import dengan data default
- Clear browser cache
- Periksa file `app/auth.php`

## 📞 Support

Untuk pertanyaan atau bantuan, silakan hubungi developer atau administrator sistem.

## 📄 Lisensi

Aplikasi ini dibuat untuk keperluan pendidikan dan internal sekolah.

---

**Dibuat dengan ❤️ untuk Sekolah Menengah Atas**
