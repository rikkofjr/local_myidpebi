<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_myidpebi', 'Pengaturan IDP EBI');


    // =========================================================================
    // 🎛️ CONFIG 1: FORMAT NAMA AUTOMATIS IDP
    // =========================================================================
    
    // Dropdown Tipe Sumber Data untuk Nama IDP
    $sumber_nama_options = [
        'user_table'     => 'Tabel User Utama Moodle (e.g. Firstname, Department, IDNumber)',
        'custom_profile' => 'Custom Profile Field Moodle (User Info Field)'
    ];
    $settings->add(new admin_setting_configselect(
        'local_myidpebi/nama_idp_sumber',
        'Sumber Kolom Nama IDP',
        'Pilih apakah sistem akan mengambil kata kunci nama dari kolom tabel user utama atau dari custom profile field.',
        'user_table',
        $sumber_nama_options
    ));

    // Text Input untuk nama kolom / shortname field kustom
    $settings->add(new admin_setting_configtext(
        'local_myidpebi/nama_idp_field',
        'Nama Kolom / Shortname Sumber',
        'Untuk format nama idp menjadi "IDP-[kolom anda pilih]-[tahun-berjalan]" Masukkan nama kolom database (jika memilih tabel user seperti: <b>firstname</b>, <b>idnumber</b>, <b>department</b>) atau isi dengan <b>shortname</b> dari custom profile field Anda.',
        'firstname',
        PARAM_ALPHANUMEXT
    ));

    // =========================================================================
    // 💼 CONFIG 2: PENGATURAN IDENTITAS ATASAN (identitas atasan diambil dari mana)
    // =========================================================================

    // 1. Setting Shortname Profile Field (Sudah kita buat sebelumnya)
    $settings->add(new admin_setting_configtext(
        'local_myidpebi/profile_field_atasan',
        'Shortname Profile Field Atasan',
        'Masukkan nama "shortname" dari User Profile Field yang menyimpan data Atasan Langsung.',
        'atasan_langsung',
        PARAM_ALPHANUMEXT
    ));

    // 🟢 2. TAMBAHAN BARU: Dropdown untuk menentukan apa isi dari kolom tersebut
    $options = [
        'username' => 'Username Moodle',
        'email'    => 'Alamat Email',
        'id'       => 'Moodle User ID (Angka Indeks)'
    ];
    
    $settings->add(new admin_setting_configselect(
        'local_myidpebi/identity_field_atasan', // Nama variabel config baru
        'Tipe Data Identitas Atasan',           // Judul di UI
        'Pilihlah kolom pencocokan yang digunakan di dalam User Profile Field tersebut untuk mengenali akun Moodle sang Atasan.', // Deskripsi
        'username',                             // Defaultnya tetap username (NIK)
        $options
    ));

    $ADMIN->add('localplugins', $settings);
}