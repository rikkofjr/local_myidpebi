<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class idp_form extends \moodleform {
    public function definition() {
        global $DB, $USER;
        $mform = $this->_form;

        // Hidden field untuk ID (Hanya terisi saat mode EDIT)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'header_idp', 'Informasi Program IDP');
        
        $mform->addElement('text', 'nama_idp', 'Program IDP');
        $mform->setType('nama_idp', PARAM_TEXT);
        $mform->addRule('nama_idp', null, 'required', null, 'client');

        // --- AMBIL DATA USER UNTUK AUTOCOMPLETE LOKAL ---
        // Mengambil daftar semua user aktif di Moodle
        $users = $DB->get_records_menu('user', ['deleted' => 0, 'suspended' => 0], 'firstname', 'id, ' . $DB->sql_fullname());
        $options = [];
        foreach ($users as $userid => $fullname) {
            $username = $DB->get_field('user', 'username', ['id' => $userid]);
            // Value dropdown diatur menggunakan 'username' (NIK) agar 100% cocok dengan variabel nik_atasan Anda
            $options[$username] = $fullname . ' (' . $username . ')';
        }

        // Pengaturan Autocomplete berbasis data lokal (Aman dari kendala hak akses/permissions)
        $autocomplete_options = [
            'multiple' => false,
            'placeholder' => 'Ketik Nama atau NIK Pembimbing...',
        ];

        // MEMPERTAHANKAN NAMA ELEMEN ASLI ANDA: 'nik_atasan'
        $mform->addElement('autocomplete', 'nik_atasan', 'Atasan Anda', $options, $autocomplete_options);
        $mform->addRule('nik_atasan', null, 'required', null, 'client');
        $mform->addHelpButton('nik_atasan', 'help_pembimbing', 'local_myidpebi');

        //Kunci pembimbing menjadi atasan langsung
        $mform->freeze('nik_atasan');

        // Logika Otomatisasi Tanggal Awal & Akhir Tahun
        $current_year = date('Y'); // Mengambil tahun aktif saat form dibuka (contoh: 2026)
        
        // Membuat selector tanggal default mengarah ke awal dan akhir tahun
        $default_start = strtotime("{$current_year}-01-01 00:00:00");
        $default_end   = strtotime("{$current_year}-12-31 23:59:59");

        // Definisikan elemen tanggal selector asli Anda
        $mform->addElement('date_selector', 'mulai_date', 'Tanggal Mulai');
        $mform->setDefault('mulai_date', $default_start); // Set otomatis 1 Januari
        $mform->freeze('mulai_date'); // Kunci agar karyawan tidak bisa mengubahnya

        $mform->addElement('date_selector', 'akhir_date', 'Target Selesai');
        $mform->setDefault('akhir_date', $default_end); // Set otomatis 31 Desember
        $mform->freeze('akhir_date'); // Kunci agar karyawan tidak bisa mengubahnya

        $this->add_action_buttons(true, 'Simpan Program');
    }

    /**
     * Mengatur data bawaan form, otomatis mengambil NIK Atasan Langsung dari Custom Profile Field
     * KODE ASLI ANDA TETAP UTUH 100%
     */
    public function set_data($data) {
        global $DB, $USER;
        
        if (is_array($data)) {
            $data = (object)$data;
        }

        // Jika ini adalah penambahan baru (ID tidak ada), cari NIK Atasan Langsung dari Custom Profile Field
        if (empty($data->id)) {
            $sql = "SELECT d.data 
                    FROM {user_info_data} d
                    JOIN {user_info_field} f ON d.fieldid = f.id
                    WHERE d.userid = ? AND f.shortname = ?";
            
            $atasan_langsung_nik = $DB->get_field_sql($sql, [$USER->id, 'atasan_langsung']);
            
            if ($atasan_langsung_nik) {
                $data->nik_atasan = trim($atasan_langsung_nik);
            }
        }

        parent::set_data($data);
    }

    /**
     * VALIDASI FORM TAMBAHAN:
     * Menghadang karyawan agar tidak memasukkan NIK diri sendiri
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);

        // Memeriksa jika NIK (username) yang dipilih sama dengan NIK user yang sedang login
        if (isset($data['nik_atasan']) && trim($data['nik_atasan']) === $USER->username) {
            $errors['nik_atasan'] = 'Anda tidak diperbolehkan memilih diri Anda sendiri sebagai Pembimbing/Coach.';
        }

        return $errors;
    }
}