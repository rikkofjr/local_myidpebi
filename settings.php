<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Membuat halaman utama konfigurasi plugin
    $settings = new admin_settingpage('local_myidpebi', 'Pengaturan Master IDP PeBi');

    // =========================================================================
    // 📦 BAGIAN 1: FORMAT NAMA DOKUMEN IDP AUTOMATIS
    // =========================================================================
    // 🟢 SELESAI PERBAIKAN: Menggunakan admin_setting_heading (memakai underscore)
    $settings->add(new admin_setting_heading(
        'local_myidpebi/group_nama_idp',
        '1. Konfigurasi Format Penamaan Otomatis Dokumen IDP',
        '<div class="alert alert-info py-2 mb-3">
            <i class="fa fa-info-circle"></i> <b>Fitur ini digunakan untuk memberikan format otomatis pada nama dokumen IDP menjadi:</b><br> 
            <code>IDP-[Kolom / Shortname Yang Dipilih]-[Tahun Berjalan]</code> (Contoh: IDP-FIRSTNAME-2026).
         </div>'
    ));

    // Dropdown Tipe Sumber Data untuk Nama IDP
    $sumber_nama_options = [
        'user_table'     => 'Tabel User Utama Moodle (e.g. Firstname, Department, IDNumber)',
        'custom_profile' => 'Custom Profile Field Moodle (User Info Field)'
    ];
    $settings->add(new admin_setting_configselect(
        'local_myidpebi/nama_idp_sumber',
        'Sumber Kolom Target Nama IDP',
        'Pilih apakah sistem akan mengambil kata kunci nama dari kolom tabel user utama atau dari custom profile field.',
        'user_table',
        $sumber_nama_options
    ));

    // Text Input untuk nama kolom / shortname field kustom
    $settings->add(new admin_setting_configtext(
        'local_myidpebi/nama_idp_field',
        'Nama Kolom / Shortname Target Sumber',
        'Masukkan nama kolom database (jika memilih tabel user seperti: <b>firstname</b>, <b>idnumber</b>, <b>department</b>) atau jika memilih kustom field silakan isi dengan <b>shortname</b> dari custom profile field Anda.',
        'firstname',
        PARAM_ALPHANUMEXT
    ));


    // =========================================================================
    // 💼 BAGIAN 2: INTEGRASI IDENTITAS ATASAN LANGSUNG
    // =========================================================================
    // 🟢 SELESAI PERBAIKAN: Menggunakan admin_setting_heading (memakai underscore)
    $settings->add(new admin_setting_heading(
        'local_myidpebi/group_identitas_atasan',
        '<hr class="my-4">2. Sinkronisasi Struktur Identitas Atasan / Pembimbing',
        '<div class="alert alert-info py-2 mb-3">
            <i class="fa fa-id-card-o"></i> Atur pemetaan data akun atasan langsung agar sistem otomatis mengenali alur verifikasi dokumen target.
         </div>'
    ));

    // Setting Shortname Profile Field Atasan
    $settings->add(new admin_setting_configtext(
        'local_myidpebi/profile_field_atasan',
        'Shortname Profile Field Atasan',
        'Masukkan nama "shortname" dari User Profile Field Moodle yang menyimpan data Atasan Langsung karyawan.',
        'atasan_langsung',
        PARAM_ALPHANUMEXT
    ));

    // Dropdown Tipe Identitas Data Atasan
    $atasan_identity_options = [
        'username' => 'Username Moodle (Sangat Direkomendasikan)',
        'email'    => 'Alamat Email Akun',
        'id'       => 'Moodle User ID (Indeks Angka Unik)'
    ];
    $settings->add(new admin_setting_configselect(
        'local_myidpebi/identity_field_atasan', 
        'Tipe Data Pencocokan Identitas Atasan',           
        'Pilihlah jenis parameter data yang digunakan di dalam User Profile Field tersebut untuk mengenali target akun Moodle sang Atasan.', 
        'username',                             
        $atasan_identity_options
    ));


    // =========================================================================
    // 🏢 BAGIAN 3: DROP-DOWN FILTER STRUKTUR ORGANISASI KARYAWAN
    // =========================================================================
    // 🟢 SELESAI PERBAIKAN: Menggunakan admin_setting_heading (memakai underscore)
    $settings->add(new admin_setting_heading(
        'local_myidpebi/group_filter_organisasi',
        '<hr class="my-4">3. Konfigurasi Filter Klasterisasi Organisasi / Unit Kerja',
        '<div class="alert alert-info py-2 mb-3">
            <i class="fa fa-sliders"></i> <b>Fitur ini digunakan untuk memberikan opsi dropdown filter dinamis pada halaman Admin Monitor sesuai dengan pengelompokan yang Anda butuhkan.</b>
         </div>'
    ));

    // Dropdown Tipe Sumber Data untuk Struktur Organisasi Karyawan
    $sumber_org_options = [
        'user_table'     => 'Tabel User Utama Moodle (e.g. Department, Institution)',
        'custom_profile' => 'Custom Profile Field Moodle (e.g. Direktorat, Divisi, Branch)'
    ];
    $settings->add(new admin_setting_configselect(
        'local_myidpebi/sumber_field_organisasi',
        'Sumber Penarikan Klaster Kerja Karyawan',
        'Pilih dari mana sistem harus membaca pemetaan pengelompokan kerja karyawan untuk dropdown filter.',
        'user_table',
        $sumber_org_options
    ));

    // Text Input untuk nama kolom database / shortname field profil kustom organisasi
    $settings->add(new admin_setting_configtext(
        'local_myidpebi/profile_field_organisasi',
        'Nama Kolom / Shortname Struktur Organisasi',
        'Masukkan nama kolom jika memilih Tabel User (contoh: <b>department</b>) atau masukkan nama shortname kustom profile field jika memilih Custom Profile Field (contoh: <b>direktorat</b> atau <b>nama_divisi</b>).',
        'department',
        PARAM_ALPHANUMEXT
    ));


    // Daftarkan seluruh paket pengaturan di atas ke Menu Administrasi Moodle
    $ADMIN->add('localplugins', $settings);
}