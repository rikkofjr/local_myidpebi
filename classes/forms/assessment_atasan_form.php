<?php
namespace local_myidpebi\forms;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class assessment_atasan_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        
        // Menangkap ID Dokumen IDP dari parameter pembungkus
        $idp_id = $this->_customdata['idp_id'];

        $mform->addElement('hidden', 'id', $idp_id);
        $mform->setType('id', PARAM_INT);

        // Header Kuesioner Evaluasi Atasan
        $mform->addElement('header', 'header_evaluasi', 'Kuesioner Penilaian Efektivitas IDP oleh Pembimbing / Atasan');

        // Nilai skala pilihan opsi kuesioner (Skala 1 - 5)
        $skala_opsi = [
            1 => '1 - Sangat Tidak Efektif',
            2 => '2 - Tidak Efektif',
            3 => '3 - Cukup Efektif',
            4 => '4 - Efektif',
            5 => '5 - Sangat Efektif'
        ];

        // Daftar butir pertanyaan kuesioner evaluasi oleh atasan (Gunakan q1, q2, dst agar dibaca dinamis)
        $mform->addElement('select', 'q1', '1. Apakah aktivitas pengembangan yang dijalankan relevan dengan tuntutan kompetensi posisi?', $skala_opsi);
        $mform->addElement('select', 'q2', '2. Seberapa besar peningkatan performa (kinerja) karyawan pasca pelaksanaan program?', $skala_opsi);
        $mform->addElement('select', 'q3', '3. Apakah durasi Jam Pelajaran (JP) yang dicapai sudah mencukupi target pengembangan?', $skala_opsi);
        $mform->addElement('select', 'q4', '4. Karyawan menunjukkan komitmen dan kemandirian tinggi dalam menyelesaikan target IDP?', $skala_opsi);

        // Atur agar kuesioner wajib diisi
        $mform->addRule('q1', null, 'required', null, 'client');
        $mform->addRule('q2', null, 'required', null, 'client');
        $mform->addRule('q3', null, 'required', null, 'client');
        $mform->addRule('q4', null, 'required', null, 'client');

        $mform->addElement('header', 'header_catatan', 'Catatan & Kesimpulan Akhir Atasan');
        
        // Field kesimpulan_atasan sesuai dengan struktur install.xml Anda
        $mform->addElement('textarea', 'kesimpulan_atasan', 'Catatan Evaluasi / Rekomendasi oleh Atasan', ['rows' => 4, 'cols' => 60]);
        $mform->setType('kesimpulan_atasan', PARAM_TEXT);
        $mform->addRule('kesimpulan_atasan', 'Catatan evaluasi wajib diisi oleh atasan.', 'required', null, 'client');

        $this->add_action_buttons(true, 'Kirim Penilaian & Verifikasi Tuntas');
    }
}