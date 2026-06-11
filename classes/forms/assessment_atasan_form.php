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

        // 🟢 PROSES DINAMIS: Ambil daftar pertanyaan atasan aktif dari database via lib helper
        $questions = local_myidpebi_get_active_questions('atasan');

        if (!empty($questions)) {
            $counter = 1;
            foreach ($questions as $q) {
                // Beri nama element field form secara urut seperti q1, q2, q3 secara dinamis
                $element_name = 'q' . $counter;
                $label_text = $counter . '. ' . s($q->question_text);

                // Tambahkan elemen select dropdown ke form atasan
                $mform->addElement('select', $element_name, $label_text, $skala_opsi);
                
                // Set aturan wajib diisi (Required)
                $mform->addRule($element_name, null, 'required', null, 'client');
                
                $counter++;
            }
        } else {
            // Antisipasi jika data master kuesioner atasan kosong
            $mform->addElement('html', '<div class="alert alert-danger">Belum ada butir kuesioner Atasan aktif yang dikonfigurasi oleh Administrator.</div>');
        }

        $mform->addElement('header', 'header_catatan', 'Catatan & Kesimpulan Akhir Atasan');
        
        // Field kesimpulan_atasan sesuai dengan struktur install.xml Anda
        $mform->addElement('textarea', 'kesimpulan_atasan', 'Catatan Evaluasi / Rekomendasi oleh Atasan', ['rows' => 4, 'cols' => 60]);
        $mform->setType('kesimpulan_atasan', PARAM_TEXT);
        $mform->addRule('kesimpulan_atasan', null, 'required', null, 'client');

        // Tombol Submit Form
        $this->add_action_buttons(true, 'Verifikasi & Tutup Dokumen IDP');
    }
}