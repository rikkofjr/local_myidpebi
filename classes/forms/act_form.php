<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class act_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $status = $this->_customdata['status'];
        $act_id = $this->_customdata['act_id']; // Cek apakah Edit atau Baru

        $mform->addElement('hidden', 'idp_id');
        $mform->setType('idp_id', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // ==========================================
        // GROUP FORM 1: ANALISIS KOMPETENSI & RENCANA
        // ==========================================
        $mform->addElement('header', 'group_form_1', 'Group Form 1: Analisis Kompetensi & Rencana');
        
        $mform->addElement('text', 'aspek', 'Aspek');
        $mform->setType('aspek', PARAM_TEXT);
        
        $mform->addElement('text', 'nilai_ipp', 'Nilai');
        $mform->setType('nilai_ipp', PARAM_TEXT);
        
        $mform->addElement('textarea', 'tuntutan_sekarang', 'Tuntutan pada posisi sekarang', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_sekarang', PARAM_RAW);
        
        $mform->addElement('textarea', 'tuntutan_berikutnya', 'Tuntutan pada posisi berikutnya', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_berikutnya', PARAM_RAW);
        
        $mform->addElement('textarea', 'tuntutan_lingkungan', 'Tuntutan karena perubahan lingkungan', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_lingkungan', PARAM_RAW);
        
        $mform->addElement('textarea', 'area_pengembangan', 'Area pengembangan yang perlu dikembangkan', ['rows' => 3, 'cols' => 50]);
        $mform->setType('area_pengembangan', PARAM_RAW);

        $options = [
            'Pendidikan Tinggi' => 'Pendidikan Tinggi',
            'Pelatihan Internal' => 'Pelatihan Internal',
            'Seminar' => 'Seminar',
            'Workshop' => 'Workshop',
            'Bimbingan Teknis' => 'Bimbingan Teknis',
            'Pertukaran Karyawan' => 'Pertukaran Karyawan',
            'Magang' => 'Magang',
            'Benchmarking' => 'Benchmarking',
            'Pelatihan Jarak Jauh' => 'Pelatihan Jarak Jauh',
            'Coaching' => 'Coaching',
            'Mentoring' => 'Mentoring',
            'Secondments' => 'Secondments',
            'Melakukan sharing session' => 'Melakukan sharing session',
            'Komunitas Belajar' => 'Komunitas Belajar',
            'Mengikuti sharing session dari asosiasi' => 'Mengikuti sharing session dari asosiasi',
            'Menjadi Narasumber dalam asosiasi' => 'Menjadi Narasumber dalam asosiasi',
            'Outbond' => 'Outbond',
            'Daily Practice' => 'Daily Practice',
            'Belajar Mandiri' => 'Belajar Mandiri',
            'Mereview buku/artikel' => 'Mereview buku/artikel',
            'Keterlibatan Project' => 'Keterlibatan Project'
        ];
        $mform->addElement('select', 'jenis_kegiatan', 'Aktivitas pengembangan yang akan dilakukan', $options);
        
        $mform->addElement('text', 'nama_activity', 'Detail Kegiatan');
        $mform->setType('nama_activity', PARAM_TEXT);
        
        $mform->addElement('text', 'waktu_teks', 'Periode');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        // ==========================================
        // GROUP FORM 2: REALISASI & EVIDENCE
        // ==========================================
        $mform->addElement('header', 'group_form_2', 'Group Form 2: Realisasi Pengembangan (Diisi Setelah Berjalan)');
        
        $mform->addElement('text', 'jumlah_jp', 'Jam Pelajaran (JP)', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp', PARAM_INT);
        $mform->addHelpButton('jumlah_jp', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        
        $mform->addElement('filepicker', 'evidence_file', 'Evidence Pengembangan', null, ['maxbytes'=>2048*1024, 'accepted_types'=>['.pdf','.jpg','.png']]);

        // ==========================================
        // LOGIKA UX ASLI ANDA (100% DIPERTAHANKAN)
        // ==========================================
        $is_clone = optional_param('is_clone', 0, PARAM_INT);

        if ($status == 0) {
            $mform->freeze(['jumlah_jp', 'evidence_file']);
        } else if ($status == 1) {
            if ($act_id && !$is_clone) {
                // Jika EDIT data lama: Kunci rencana (Group Form 1)
                $mform->freeze([
                    'aspek', 'nilai_ipp', 'tuntutan_sekarang', 'tuntutan_berikutnya', 
                    'tuntutan_lingkungan', 'area_pengembangan', 'jenis_kegiatan', 
                    'nama_activity', 'waktu_teks'
                ]);
            } else {
                // Jika TAMBAH BARU di status 1: Buka rencana, kunci realisasi
                $mform->freeze(['jumlah_jp', 'evidence_file']);
            }
        }

        $this->add_action_buttons(true, 'Simpan');
    }

    /**
     * 🟢 VALIDASI KUSTOM: Standar Batas Minimal & Maksimal JP Per Jenis Kegiatan
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // 1. Matriks aturan standar batas JP perusahaan (Bisa Anda ubah angkanya sesuai kebijakan)
        $jp_rules = [
            'Pendidikan Tinggi'         => ['min' => 1, 'max' => 20,   'label' => 'Pendidikan Tinggi'],
            'Pelatihan Internal'        => ['min' => 1, 'max' => 20,   'label' => 'Pelatihan Internal'],
            'Seminar'                   => ['min' => 1, 'max' => 4,   'label' => 'Seminar'],
            'Workshop'                  => ['min' => 1, 'max' => 5,  'label' => 'Workshop'],
            'Bimbingan Teknis'          => ['min' => 1, 'max' => 20,  'label' => 'Bimbingan Teknis'],
            'Pertukaran Karyawan'       => ['min' => 1, 'max' => 20,  'label' => 'Pertukaran Karyawan'],
            'Magang'                    => ['min' => 1, 'max' => 20,  'label' => 'Magang'],
            'Benchmarking'              => ['min' => 1, 'max' => 10,  'label' => 'Benchmarking'],
            'Pelatihan Jarak Jauh'      => ['min' => 1, 'max' => 20,  'label' => 'Pelatihan Jarak Jauh'],
            'Coaching'                  => ['min' => 1, 'max' => 2,   'label' => 'Coaching'],
            'Mentoring'                 => ['min' => 1, 'max' => 2,   'label' => 'Mentoring'],
            'Secondments'               => ['min' => 1, 'max' => 20,   'label' => 'Secondments'],
            'Melakukan sharing session' => ['min' => 1, 'max' => 2,   'label' => 'Melakukan sharing session'],
            'Komunitas Belajar'         => ['min' => 1, 'max' => 2,   'label' => 'Komunitas Belajar'],
            'Mengikuti sharing session dari asosiasi'=> ['min' => 4, 'max' => 4,   'label' => 'Mengikuti sharing session dari asosiasi'],
            'Menjadi Narasumber dalam asosiasi'=> ['min' => 5, 'max' => 5,   'label' => 'Menjadi Narasumber dalam asosiasi'],
            'Outbond'                   => ['min' => 1, 'max' => 20,   'label' => 'Outbond'],
            'Daily Practice'            => ['min' => null, 'max' => 1,   'label' => 'Daily Practice'],
            'Belajar Mandiri'           => ['min' => 1, 'max' => 2,   'label' => 'Belajar Mandiri'],
            'Mereview buku/artikel'           => ['min' => 1, 'max' => 4,   'label' => 'Mereview buku/artikel'],
            'Keterlibatan Project'           => ['min' => 1, 'max' => 10,   'label' => 'Keterlibatan Project'],
        ];

        // 2. Lakukan validasi hanya jika kolom JP sedang terbuka dan diisi oleh karyawan
        if (isset($data['jumlah_jp']) && $data['jumlah_jp'] !== '') {
            $jenis = isset($data['jenis_kegiatan']) ? $data['jenis_kegiatan'] : '';
            $jp_input = (int)$data['jumlah_jp'];

            // Jika jenis kegiatan yang dipilih karyawan ada di dalam daftar aturan
            if (array_key_exists($jenis, $jp_rules)) {
                $min   = $jp_rules[$jenis]['min'];
                $max   = $jp_rules[$jenis]['max'];
                $label = $jp_rules[$jenis]['label'];

                // Cek Batas Minimum
                if ($jp_input < $min) {
                    $errors['jumlah_jp'] = "Batas minimal input untuk kegiatan {$label} adalah {$min} JP.";
                }
                
                // Cek Batas Maksimum
                if ($jp_input > $max) {
                    $errors['jumlah_jp'] = "Batas maksimal input untuk kegiatan {$label} adalah {$max} JP (Input Anda: {$jp_input} JP).";
                }
            }
        }

        return $errors;
    }
}