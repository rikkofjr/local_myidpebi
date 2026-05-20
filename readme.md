# MYIDPEBI - Moodle Plugin untuk Individual Development Plan (IDP)

**MYIDPEBI** adalah plugin kustom Moodle (mendukung versi 4.x ke atas) yang dirancang khusus untuk mengelola, menyusun, dan memantau **Individual Development Plan (IDP)** atau Rencana Pengembangan Diri karyawan di dalam organisasi. 

Plugin ini mendigitalisasi proses perencanaan karier, dengan mengintegrasikan alur kerja persetujuan struktural langsung di dalam LMS Moodle.

---

## 🚀 Fitur Utama

### 👥 Peran Karyawan (User)
* **Penyusunan IDP Mandiri:** Karyawan dapat membuat dan merencanakan IDP sesuai dengan kebutuhan pengembangan masing-masing.
* **Manajemen Aktivitas & Kursus:** Mengelola detail aktivitas dari setiap IDP yang dibuat.
* **Duplikasi Aktivitas:** Fitur efisiensi yang memungkinkan karyawan menduplikasi aktivitas pengembangan yang serupa ke dalam rencana baru tanpa harus mengisi dari awal.
* **Unggah Dokumen *Evidence*:** Karyawan dapat mengunggah dokumen atau bukti fisik sebagai indikator penyelesaian aktivitas pengembangan.

### 👔 Peran Atasan / Pembimbing (Supervisor / Mentor)
* **Penentuan Pembimbing Fleksibel:** Secara *default*, sistem akan mengidentifikasi Atasan Langsung sebagai pembimbing. Namun, jika pembimbing adalah orang lain, penentuan dapat dilakukan secara spesifik menggunakan *username* yang diisi didalam atasan_langsung (profile custom field) profile karayawan pada  Moodle tersebut.
* **Alur Persetujuan (Approval Workflow):** Pembimbing memiliki hak penuh untuk meninjau dan melakukan *Approve* terhadap draf IDP yang diajukan oleh karyawan.
* **Verifikasi Dokumen & Bukti (*Evidence Verification*):** Pembimbing dapat memvalidasi dan memverifikasi dokumen *evidence* yang diunggah oleh karyawan.
* **Kalkulasi Jam Pelajaran (JP):** Setelah pembimbing memberikan *Verifikasi* akhir pada aktivitas, Jam Pelajaran (JP) akan otomatis tervalidasi dan tampil dalam rekapitulasi pengembangan karyawan.

### 🛡️ Sistem & Keamanan (Backend)
* **Audit Trail & Logging:** Sistem mencatat setiap riwayat perubahan, pengajuan, persetujuan, dan modifikasi data IDP ke dalam struktur database khusus untuk kebutuhan audit internal organisasi.

---

## 🛠️ Prasyarat & Instalasi

### 📌 Prasyarat Sistem
* **Moodle:** Versi 4.0 atau yang lebih baru.
* **Database Moodle:** Memiliki hak akses untuk penambahan tabel kustom (untuk fitur *audit trail* dan penyimpanan data IDP).

### ⚙️ Konfigurasi Custom Profile Field Moodle
Agar plugin dapat mengidentifikasi struktur organisasi dan atasan langsung secara otomatis, Anda **wajib** menambahkan bidang profil kustom (*Custom Profile Field*) pada Moodle:
1. Masuk sebagai Administrator di Moodle.
2. Buka menu **Site administration > Users > Accounts > User profile fields**.
3. Buat field baru berjenis **Text input** dengan ketentuan:
   * **Short name:** `atasan_langsung`
   * **Name:** Atasan Langsung (atau sesuaikan dengan kebutuhan)
4. Pada field ini, isikan **username Moodle** dari atasan langsung masing-masing karyawan.

### 💾 Langkah Instalasi
1. Unduh (Download) repositori ini dalam format `.zip`.
2. Masuk ke Moodle menggunakan akun Administrator.
3. Buka menu **Site administration > Plugins > Install plugins**.
4. Unggah file ZIP tersebut ke bagian **Install plugin from ZIP file**, lalu klik tombol **Install plugin from ZIP file**.
5. Ikuti petunjuk di layar untuk menyelesaikan proses pembaruan database (*Upgrade Moodle database*).

---

## 📝 Catatan Pembaruan (Changelog)

### [v1.2]
* **Fitur Duplikasi:** Penambahan fitur duplikasi aktivitas oleh karyawan untuk mempercepat proses input rencana berulang.
* **Database Audit Trail:** Implementasi tabel log terpisah untuk mencatat riwayat perubahan data (*audit trail*) pada formulir rencana pengembangan diri.
* **Alur Validasi JP:** Integrasi logika tampilan Jam Pelajaran (JP) yang terkunci sebelum dokumen bukti fisik diverifikasi oleh pembimbing.