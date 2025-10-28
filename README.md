# 🗳️ Aplikasi Voting — Sistem Voting Modern untuk Sekolah/Organisasi

![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Halaman%20Utama%20Voter.png?raw=true)

Sistem voting berbasis web yang **aman**, **responsif**, dan **mudah digunakan**.
Dirancang khusus untuk keperluan **pemilihan ketua OSIS**, **ketua kelas**, atau **pemungutan suara organisasi** lainnya dengan tampilan modern dan efisien.

---

## ✨ Fitur Utama

* 🔐 **Login Admin Aman** — hanya admin yang dapat mengakses panel.
* 👥 **Kelola Kandidat** — tambahkan kandidat dengan foto dan deskripsi.
* 🧑‍🎓 **Kelola Pemilih** — input manual atau impor dari file CSV.
* 🖨️ **Cetak Kartu Peserta** — ekspor kartu pemilih ke PDF dengan **TCPDF**.
* ⏰ **Atur Jadwal Voting** — dengan sistem konfirmasi otomatis.
* 📊 **Dashboard Real-Time** — pantau hasil voting secara langsung.
* 🔄 **Reset Voting** — hapus data voting untuk memulai ulang.
* 📱 **Desain Responsif** — tampil optimal di semua perangkat.

---

## ⚙️ Instalasi Cepat

1. **Buat Database**

   ```sql
   CREATE DATABASE voting_db;
   USE voting_db;

   CREATE TABLE settings (
     id INT PRIMARY KEY,
     voting_start DATETIME,
     voting_end DATETIME
   );
   INSERT INTO settings VALUES (1, '2025-11-01 08:00:00', '2025-11-01 15:00:00');

   CREATE TABLE candidates (
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(100),
     description TEXT,
     photo VARCHAR(255) DEFAULT 'default.jpg'
   );

   CREATE TABLE voter_accounts (
     id INT AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(100),
     class VARCHAR(50),
     login_code VARCHAR(10) UNIQUE,
     used TINYINT(1) DEFAULT 0
   );

   CREATE TABLE votes (
     id INT AUTO_INCREMENT PRIMARY KEY,
     voter_id INT,
     candidate_id INT,
     voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

2. **Konfigurasi Website**

   * Letakkan semua file project di folder server lokal (misalnya: `htdocs/voting_app`).
   * Sesuaikan pengaturan database di file `config.php`:

     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'voting_db');
     ```

3. **Install TCPDF (Wajib untuk Cetak PDF)**

   * Unduh TCPDF dari [https://tcpdf.org](https://tcpdf.org) atau via GitHub:
     [https://github.com/tecnickcom/TCPDF](https://github.com/tecnickcom/TCPDF)
   * Ekstrak folder `tcpdf/` ke dalam direktori project:

     ```
     voting_app/
     └── tcpdf/
     ```
   * Pastikan file `require_once('tcpdf/tcpdf.php');` sudah dipanggil di bagian script yang mencetak PDF.

4. **Akses Halaman Admin**

   * Buka di browser:
     👉 `http://localhost/voting_app/admin.php`
   * Login default:
     **Username:** `admin`
     **Password:** `admin123` *(segera ganti untuk keamanan!)*

---

## 📁 Struktur Folder

```
📦 voting_app
├── 📁 assets/           # File CSS, JS, dan ikon
├── 📁 uploads/          # Menyimpan foto kandidat & logo
├── 📁 tcpdf/            # Library TCPDF untuk cetak PDF
├── 📄 admin.php         # Halaman utama admin
├── 📄 index.php         # Halaman voting untuk pemilih
├── 📄 result.php        # Hasil voting real-time
├── 📄 config.php        # Konfigurasi database
└── 📄 README.md         # Dokumentasi proyek
```

---

## 🧩 Kustomisasi

* **Logo Sekolah/Organisasi** → ganti file `uploads/logo.png`.
* **Warna Tampilan** → ubah variabel CSS di bagian `:root` pada file `<style>`.
* **Password Admin** → edit di file `config.php`:

  ```php
  define('ADMIN_PASSWORD', 'password_baru');
  ```

---

## 💡 Saran Penggunaan

* Gunakan browser modern (Chrome, Edge, Firefox).
* Jalankan di server lokal seperti **XAMPP** atau **Laragon**.
* Pastikan **TCPDF** sudah terinstal agar fitur cetak PDF berfungsi.
* Lakukan backup database sebelum melakukan reset voting.

---

## 📸 Preview Halaman

**🧾 Login Voter**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Login%20Voter.png?raw=true)

**🗳️ Halaman Utama Voter (Voting)**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Halaman%20Utama%20Voter.png?raw=true)

**🔑 Login Admin**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Login%20Admin.png?raw=true)

**🏠 Halaman Utama Admin (Dashboard)**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Halaman%20Utama%20Admin.png?raw=true)

**➕ Halaman Menambah Kandidat**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Halaman%20Menambah%20Kandidat.png?raw=true)

**👥 Halaman Menambah Akun Voter**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Menambah%20Data%20Voter.png?raw=true)

**⏰ Halaman Pengaturan Waktu Voting**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Pengaturan%20Waktu%20Voting.png?raw=true)

**📊 Halaman Hasil Voting**
![alt text](https://github.com/Narzetts/Modern-Voting-App/blob/main/preview/Hasil%20Voting.png?raw=true)

---

## 📄 Lisensi

Proyek ini bersifat **open-source** — bebas digunakan dan dimodifikasi untuk keperluan pendidikan atau organisasi non-komersial.

