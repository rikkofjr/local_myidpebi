<?php
namespace local_myidpebi\forms;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class assessment_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;

        // Hidden field untuk membawa ID Dokumen IDP Induk
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Header Form Kuesioner
        $mform->addElement('header', 'header_assessment', 'Evaluasi Mandiri (Self-Assessment) Efektivitas IDP');
        
        // Deskripsi pengantar kuesioner
        $mform->addElement('html', '<div class="alert alert-info">
            Silakan lakukan penilaian mandiri atas dampak aktivitas pengembangan yang telah Anda jalankan pada periode ini. 
            Pilihlah skala 1-5 yang paling mencerminkan kondisi Anda saat ini.
        </div>');

        // Pilihan Skala Likert 1-5
        $skala_options = [
            1 => '1 - Sangat Tidak Merasakan Dampak',
            2 => '2 - Kurang Merasakan Dampak',
            3 => '3 - Cukup Merasakan Dampak',
            4 => '4 - Merasakan Dampak Positif',
            5 => '5 - Sangat Merasakan Dampak Positif'
        ];

        // Daftar Pertanyaan Kuesioner Evaluasi
        $mform->addElement('select', 'q1', '1. IDP sudah tepat dalam mengidentifikasi tuntutan pengembangan yang Anda butuhkan', $skala_options);
        $mform->addRule('q1', null, 'required', null, 'client');

        $mform->addElement('select', 'q2', '2. Aktivitas pengembangan IDP sudah sesuai dengan kebutuhan Anda', $skala_options);
        $mform->addRule('q2', null, 'required', null, 'client');

        $mform->addElement('select', 'q3', '3. IDP sudah tepat dalam mengidentifikasi hasil pengembangan yang diharapkan', $skala_options);
        $mform->addRule('q3', null, 'required', null, 'client');

        $mform->addElement('select', 'q4', '4. IDP memberikan dampak yang baik terhadap pengembangan diri Anda', $skala_options);
        $mform->addRule('q4', null, 'required', null, 'client');

        $mform->addElement('select', 'q5', '5. IDP memberikan dampak yang positif terhadap peningkatan kinerja Anda', $skala_options);
        $mform->addRule('q5', null, 'required', null, 'client');

        $mform->addElement('select', 'q6', '6. IDP dapat diimplementasikan secara keseluruhan dengan lancar', $skala_options);
        $mform->addRule('q6', null, 'required', null, 'client');

        $mform->addElement('select', 'q7', '7. Anda mendapatkan dukungan dari para pihak terkait dalam menjalankan IDP', $skala_options);
        $mform->addRule('q7', null, 'required', null, 'client');
        
        $mform->addElement('select', 'q8', '8. Anda merasa lebih siap untuk mendapatkan tanggung jawab yang lebih besar setelah menjalankan IDP', $skala_options);
        $mform->addRule('q8', null, 'required', null, 'client');
        
        $mform->addElement('select', 'q9', '9. Anda ingin melanjutkan pengembangan di masa datang melalui IDP', $skala_options);
        $mform->addRule('q9', null, 'required', null, 'client');
        
        $mform->addElement('select', 'q10', '10. IDP yang Anda jalankan selaras dengan tuntutan perusahaan atau dept/sub dept yang Anda tempati', $skala_options);
        $mform->addRule('q10', null, 'required', null, 'client');

        // Kolom Catatan Kualitatif / Testimoni Karyawan
        $mform->addElement('textarea', 'kesimpulan_karyawan', 'Tuliskan kesimpulan ringkas atau testimoni Anda terkait program IDP ini:', ['rows' => 4, 'cols' => 50, 'class' => 'form-control']);
        $mform->setType('kesimpulan_karyawan', PARAM_TEXT);
        $mform->addRule('kesimpulan_karyawan', null, 'required', null, 'client');

        // Tombol Aksi (Simpan Evaluasi / Kembali)
        $this->add_action_buttons(true, 'Simpan & Hitung Skor Efektivitas');
    }
}