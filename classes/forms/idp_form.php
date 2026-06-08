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
        $mform->setType('nama_idp', PARAM_TEXT, ['disabled' => true]);
        $mform->addRule('nama_idp', null, 'required', null, 'client');

        // --- AMBIL DATA USER UNTUK AUTOCOMPLETE LOKAL (DINAMIS UI ADMIN) ---
        $users = $DB->get_records_menu('user', ['deleted' => 0, 'suspended' => 0], 'firstname', 'id, ' . $DB->sql_fullname());
        $options = [];

        // 1. Cek target identitas yang sedang aktif di Admin UI
        $identity_config = get_config('local_myidpebi', 'identity_field_atasan');
        $field_target = !empty($identity_config) ? $identity_config : 'username';

        foreach ($users as $userid => $fullname) {
            // 2. Ambil data spesifik user berdasarkan kolom target yang aktif (bisa username, email, atau id)
            $user_data = $DB->get_record('user', ['id' => $userid], 'username, email, id');
            
            if ($user_data) {
                $value_key = trim($user_data->{$field_target}); // Mengambil nilai sesuai config admin
                
                // 3. Tampilkan label dropdown yang informatif agar user tidak bingung
                if ($field_target == 'email') {
                    $options[$value_key] = $fullname . ' [' . $user_data->email . ']';
                } else if ($field_target == 'id') {
                    $options[$value_key] = $fullname . ' [ID: ' . $user_data->id . ']';
                } else {
                    $options[$value_key] = $fullname . ' (' . $user_data->username . ')';
                }
            }
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


        // ==========================================
        // Isi rencana pengembangan
        // ==========================================
        $mform->addElement('html', '<b>Tuntutan pada posisi sekarang (Performance & Kompetensi)</b>');
        
        $mform->addElement('textarea', 'tuntutan_sekarang_performance', '     a. Performance', ['rows' => 3, 'cols' => 100, 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan berdasarkan perormance yang perlu ditingkatkan pada posisi saat ini']);
        $mform->setType('tuntutan_sekarang_performance', PARAM_RAW);
        $mform->addRule('tuntutan_sekarang_performance', 'Harus Diisi', 'required', null, 'client');

        $mform->addElement('textarea', 'tuntutan_sekarang_kompetensi', '     b. Kompetensi', ['rows' => 3, 'cols' => 100, 'width'=> '100%', 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan berdasarkan kompetensi yang perlu ditingkatkan pada posisi saat ini']);
        $mform->setType('tuntutan_sekarang_kompetensi', PARAM_RAW);
        $mform->addRule('tuntutan_sekarang_kompetensi', 'Harus Diisi', 'required', null, 'client');

        $mform->addElement('html', '<hr />');
        
        $mform->addElement('html', '<b>Tuntutan pada posisi berikutnya (Performance & Kompetensi)</b>');

        $mform->addElement('textarea', 'tuntutan_berikutnya_performance', '     a. Performance', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan berdasarkan perormance yang perlu ditingkatkan pada posisi berikutnya']);
        $mform->setType('tuntutan_berikutnya_performance', PARAM_RAW);

        $mform->addElement('textarea', 'tuntutan_berikutnya_kompetensi', '     b. Kompetensi', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan berdasarkan kompetensi yang perlu ditingkatkan pada posisi berikutnya']);
        $mform->setType('tuntutan_berikutnya_kompetensi', PARAM_RAW);

        $mform->addElement('html', '<hr />');

        $mform->addElement('textarea', 'tuntutan_lingkungan', 'Tuntutan karena perubahan lingkungan', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan Tuntutan karena perubahan lingkungan']);
        $mform->setType('tuntutan_lingkungan', PARAM_RAW);
        
        $mform->addElement('textarea', 'area_pengembangan_ditingkatkan', 'Area pengembangan yang perlu ditingkatkan', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan Area pengembangan yang perlu ditingkatkan']);
        $mform->setType('area_pengembangan_ditingkatkan', PARAM_RAW);
        
        $mform->addElement('textarea', 'area_pengembangan_diharapkan', 'Area pengembangan yang perlu diharapkan', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan Area pengembangan yang perlu ditingkatkan']);
        $mform->setType('area_pengembangan_diharapkan', PARAM_RAW);
        

        $mform->addElement('html', '<hr />');

        $this->add_action_buttons(true, 'Simpan Program');
    }

   /**
     * SINKRONISASI TIPE DATA IDENTITAS ATASAN (Email / Username / ID)
     * Mengonversi data mentah dari profil / database agar sesuai dengan kebutuhan Autocomplete
     */
    public function set_data($data) {
        global $DB, $USER;
        
        if (is_array($data)) {
            $data = (object)$data;
        }

        // 1. Ambil konfigurasi tipe identitas yang sedang aktif di Admin UI (username / email / id)
        $identity_config = get_config('local_myidpebi', 'identity_field_atasan');
        $field_target = !empty($identity_config) ? $identity_config : 'username';

        // 🟢 KONDISI A: JIKA TAMBAH BARU (Form IDP masih kosong / Baru dibuat)
        if (empty($data->id)) {

            // =================================================================
            // 🎛️ KONFIGURASI FORMAT NAMA IDP
            // =================================================================
            $config_sumber_ui = get_config('local_myidpebi', 'nama_idp_sumber');
            $config_field_ui  = get_config('local_myidpebi', 'nama_idp_field');

            // Fallback default jika di setting admin belum sempat diisi / kosong
            $config_sumber = !empty($config_sumber_ui) ? $config_sumber_ui : 'user_table';
            $config_field  = !empty($config_field_ui) ? $config_field_ui : 'firstname';
            // =================================================================
            // Ambil data komponen nama secara dinamis via helper lib.php
            $komponen_nama = \local_myidpebi_get_user_field_value($USER->id, $config_sumber, $config_field);
            if (empty($komponen_nama)) {
                $komponen_nama = 'User';
            }

            // Set default nama_idp menjadi: IDP-{Hasil dari UI}-Tahun
            $tahun_sekarang = date('Y');
            $data->nama_idp = "IDP-" . $komponen_nama . "-" . $tahun_sekarang;

            // =================================================================
            // 🎛️ KONFIGURASI ATASAN LANGSUNG
            // =================================================================

            // Memanggil fungsi pustaka eksternal dengan backslash (\) global root
            $atasan_langsung_raw = \local_myidpebi_get_atasan_username($USER->id);
            
            if (!empty($atasan_langsung_raw)) {
                // Memanggil fungsi helper universal dari lib.php dengan backslash (\) global root
                $atasan_user = \local_myidpebi_get_user_by_config($atasan_langsung_raw);
                
                if ($atasan_user) {
                    // Ekstrak kolom yang sesuai (berisi email jika tipe data identitas di-setting ke 'alamat email')
                    $data->nik_atasan = trim($atasan_user->{$field_target});
                } else {
                    // Fallback jika tidak ditemukan di user master Moodle, masukkan data mentah apa adanya
                    $data->nik_atasan = trim($atasan_langsung_raw);
                }
            }
        } 
        // 🟢 KONDISI B: JIKA MODE EDIT (Mengambil data lama dari tabel local_myidpebi)
        else if (!empty($data->atasan_id)) {
            // Ambil record user pembimbing berdasarkan atasan_id (ID master user Moodle)
            $pembimbing_lama = $DB->get_record('user', ['id' => $data->atasan_id], 'id, username, email');
            if ($pembimbing_lama) {
                // Berikan nilai field sesuai target (email) agar cocok dengan data value dropdown autocomplete
                $data->nik_atasan = trim($pembimbing_lama->{$field_target});
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

        // 1. Cek tipe identitas yang sedang aktif di Admin UI (username, email, atau id)
        $identity_config = get_config('local_myidpebi', 'identity_field_atasan');
        $field_target = !empty($identity_config) ? $identity_config : 'username';

        // 2. Ambil nilai identitas dari user yang sedang login saat ini
        $user_identity_value = isset($USER->{$field_target}) ? trim($USER->{$field_target}) : '';

        // 3. Bandingkan dengan apa yang diinput/terpilih di form kuesioner pembimbing
        if (!empty($data['nik_atasan']) && !empty($user_identity_value)) {
            if (trim($data['nik_atasan']) == $user_identity_value) {
                // Sesuaikan pesan error berdasarkan tipe field yang sedang aktif agar user tidak bingung
                if ($field_target == 'email') {
                    $errors['nik_atasan'] = "Anda tidak diperbolehkan memasukkan Alamat Email Anda sendiri sebagai Pembimbing.";
                } else if ($field_target == 'id') {
                    $errors['nik_atasan'] = "Anda tidak diperbolehkan memilih akun Anda sendiri sebagai Pembimbing.";
                } else {
                    $errors['nik_atasan'] = "Anda tidak diperbolehkan memasukkan NIK diri Anda sendiri sebagai Pembimbing.";
                }
            }
        }

        return $errors;
    }
}