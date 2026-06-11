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
        $mform->addElement('html', '<div class=\"alert alert-info\">
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

        // 🟢 PROSES DINAMIS: Ambil daftar pertanyaan karyawan aktif dari database via lib helper
        $questions = local_myidpebi_get_active_questions('karyawan');

        if (!empty($questions)) {
            $counter = 1;
            foreach ($questions as $q) {
                // Beri nama element field form secara urut seperti q1, q2, q3 secara dinamis
                $element_name = 'q' . $counter;
                $label_text = $counter . '. ' . s($q->question_text);

                // Tambahkan elemen select dropdown pilihan skala ke form
                $mform->addElement('select', $element_name, $label_text, $skala_options);
                
                // Set agar form ini wajib diisi oleh pengguna (Required)
                $mform->addRule($element_name, null, 'required', null, 'client');
                
                $counter++;
            }
        } else {
            // Antisipasi jika tabel kosong, tampilkan pesan peringatan
            $mform->addElement('html', '<div class="alert alert-danger">Belum ada butir kuesioner aktif yang dikonfigurasi oleh Administrator.</div>');
        }

        // Kolom Catatan Kualitatif / Testimoni Karyawan
        $mform->addElement('textarea', 'kesimpulan_karyawan', 'Tuliskan kesimpulan ringkas atau testimoni Anda terkait program IDP ini:', ['rows' => 4, 'cols' => 50, 'class' => 'form-control']);
        $mform->setType('kesimpulan_karyawan', PARAM_TEXT);
        $mform->addRule('kesimpulan_karyawan', null, 'required', null, 'client');

        // Tombol Kirim / Aksi Aksi Form
        $this->add_action_buttons(true, 'Simpan & Kirim Evaluasi');
    }
}