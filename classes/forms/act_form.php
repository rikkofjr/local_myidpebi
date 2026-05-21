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
            'Workshop'=>'Workshop',
            'Magang'=>'Magang',
            'Coaching'=>'Coaching',
            'Seminar'=>'Seminar',
            'Sertifikasi'=>'Sertifikasi',
            'Training'=>'Training',
            'Assignment'=>'Assignment',
            'Belajar Mandiri'=>'Belajar Mandiri'
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
            'Coaching'        => ['min' => 1,  'max' => 3,   'label' => 'Coaching'],
            'Magang'          => ['min' => 10, 'max' => 40,  'label' => 'Magang'],
            'Seminar'         => ['min' => 2,  'max' => 8,   'label' => 'Seminar'],
            'Workshop'        => ['min' => 2,  'max' => 16,  'label' => 'Workshop'],
            'Sertifikasi'     => ['min' => 8,  'max' => 50,  'label' => 'Sertifikasi'],
            'Training'        => ['min' => 4,  'max' => 40,  'label' => 'Training'],
            'Assignment'      => ['min' => 5,  'max' => 30,  'label' => 'Assignment'],
            'Belajar Mandiri' => ['min' => 1,  'max' => 10,  'label' => 'Belajar Mandiri'],
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