<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
global $DB;

class act_form extends \moodleform {
    public function definition() {
        global $DB;
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
        
        $mform->addElement('textarea', 'tuntutan_sekarang_performance', 'Tuntutan pada posisi sekarang (Performance)', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan (berdasarkan indikator kinerja, kompetensi, leadership, atau value) yang perlu ditingkatkan pada posisi saat ini']);
        $mform->setType('tuntutan_sekarang_performance', PARAM_RAW);

        $mform->addElement('textarea', 'tuntutan_sekarang_kompetensi', 'Tuntutan pada posisi sekarang (Kompetensi)', ['rows' => 3, 'cols' => 50, 'placeholder' => 'Tuliskan prioritas/fokus area pengembangan (berdasarkan indikator kinerja, kompetensi, leadership, atau value) yang perlu ditingkatkan pada posisi saat ini']);
        $mform->setType('tuntutan_sekarang_performance_kompetensi', PARAM_RAW);

        $mform->addElement('html', '<hr />');
        
        $mform->addElement('textarea', 'tuntutan_berikutnya_performance', 'Tuntutan pada posisi berikutnya (Performance)', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_berikutnya_performance', PARAM_RAW);

        $mform->addElement('textarea', 'tuntutan_berikutnya_kompetensi', 'Tuntutan pada posisi berikutnya (Kompetensi)', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_berikutnya_kompetensi', PARAM_RAW);

        $mform->addElement('html', '<hr />');
        
        $mform->addElement('textarea', 'tuntutan_lingkungan_performance', 'Tuntutan karena perubahan lingkungan (Performance)', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_lingkungan_performance', PARAM_RAW);
        
        $mform->addElement('textarea', 'tuntutan_lingkungan_kompetensi', 'Tuntutan karena perubahan lingkungan (Kompetensi)', ['rows' => 3, 'cols' => 50]);
        $mform->setType('tuntutan_lingkungan_kompetensi', PARAM_RAW);

        $mform->addElement('html', '<hr />');
        

        // Mengambil master data dari tabel baru local_myidpebi_learning_activity
        $master_kegiatan = $DB->get_records('local_myidpebi_learning_activity', null, 'bentuk_cdp ASC', 'id, learning_activity, tipe_aktivitas_cdp');
        
        $jenis_opsi = ['' => '-- Pilih Jenis Kegiatan --'];
        if ($master_kegiatan) {
            foreach ($master_kegiatan as $mk) {
                $jenis_opsi[$mk->id] = $mk->tipe_aktivitas_cdp .' - '. $mk->learning_activity;
            }
        }
        $mform->addElement('select', 'learning_activity', 'Aktivitas pengembangan yang akan dilakukan', $jenis_opsi);
        $mform->setType('learning_activity', PARAM_INT);

        $mform->addElement('text', 'nama_activity', 'Detail Kegiatan');
        $mform->setType('nama_activity', PARAM_TEXT);

        $mform->addElement('text', 'jumlah_jp_perencanaan', 'Jam Pelajaran (JP) Perencanaan', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp_perencanaan', PARAM_INT);
        $mform->addHelpButton('jumlah_jp_perencanaan', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        $mform->addRule('jumlah_jp_perencanaan', 'Kolom ini hanya boleh diisi oleh angka!', 'numeric', null, 'client');

        $mform->addElement('text', 'waktu_teks', 'Periode');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        // ==========================================
        // GROUP FORM 2: REALISASI & EVIDENCE
        // ==========================================
        $mform->addElement('header', 'group_form_2', 'Group Form 2: Realisasi Pengembangan (Diisi Setelah Berjalan)');
        
        $mform->addElement('text', 'jumlah_jp_realisasi', 'Jam Pelajaran (JP) Realisasi', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp_realisasi', PARAM_INT);
        $mform->addHelpButton('jumlah_jp_realisasi', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        $mform->addRule('jumlah_jp_realisasi', 'Kolom ini hanya boleh diisi oleh angka!', 'numeric', null, 'client');

        
        if ($status == 1 && $act_id) {
            // Ambil ID learning_activity langsung dari tabel aktivitas karyawan
            $current_act = $DB->get_record('local_myidpebi_act', ['id' => $act_id], 'learning_activity');
            
            if ($current_act) {
                // Tarik teks panduan evidence dari tabel master berdasarkan ID tersebut
                $master_rule = $DB->get_record('local_myidpebi_learning_activity', ['id' => $current_act->learning_activity], 'bentuk_evidence');
                
                if ($master_rule && !empty($master_rule->bentuk_evidence)) {
                    // Langsung cetak notes teks merah tepat di atas box upload
                    $mform->addElement('static', 'note_evidence', '<strong>💡 Panduan Dokumen Bukti:</strong>', '<span class="text-danger">' . s($master_rule->bentuk_evidence) . '</span>');
                }
            }
        }
        $mform->addElement('filepicker', 'evidence_file', 'Evidence Pengembangan', null, ['maxbytes'=>2048*1024, 'accepted_types'=>['.pdf','.jpg','.png']]);

        // ==========================================
        // LOGIKA UX
        // ==========================================
        $is_clone = optional_param('is_clone', 0, PARAM_INT);

        if ($status == 0) {
            $mform->freeze(['jumlah_jp_realisasi']);
            if ($mform->elementExists('evidence_file')) {
                $mform->getElement('evidence_file')->freeze();
            }
        } else if ($status == 1) {
            if ($act_id && !$is_clone) {
                // Jika EDIT data lama: Kunci rencana (Group Form 1)
                $mform->freeze([
                    'aspek', 'nilai_ipp', 
                    'tuntutan_sekarang_performance', 'tuntutan_sekarang_kompetensi', 
                    'tuntutan_berikutnya_performance', 'tuntutan_berikutnya_kompetensi', 
                    'tuntutan_lingkungan_performance', 'tuntutan_lingkungan_kompetensi', 
                    'learning_activity', 'nama_activity', 'waktu_teks', 'jumlah_jp_perencanaan'
                ]);
            } else {
                // Jika TAMBAH BARU di status 1: Buka rencana, kunci realisasi
                $mform->freeze(['jumlah_jp_realisasi']);
                if ($mform->elementExists('evidence_file')) {
                    $mform->getElement('evidence_file')->freeze();
                }
            }
        }

        $mform->addElement('submit', 'submitbutton', 'Simpan'); // Baris asli Anda selanjutnya (Line 105)
    }

    /**
     * 🟢 VALIDASI KUSTOM: Standar Batas Minimal & Maksimal JP Per Jenis Kegiatan
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        // 🟢 PERBAIKAN 2: Tangkap ID kegiatan (Tipe angka)
        $activity_id = isset($data['learning_activity']) ? (int)$data['learning_activity'] : 0;

        if ($activity_id > 0) {
            // Pencarian ke database sekarang langsung menembak 'id' (Sangat cepat & aman)
            $rule = $DB->get_record('local_myidpebi_learning_activity', ['id' => $activity_id]);

            if ($rule) {
                $min = (int)$rule->jp_min;
                $max = (int)$rule->jp_max;
                $activity_name = $rule->learning_activity; // Mengambil nama aslinya untuk pesan error

                // 1. Validasi Batas JP Perencanaan
                if (isset($data['jumlah_jp_perencanaan']) && $data['jumlah_jp_perencanaan'] !== '') {
                    $jp_rencana = (int)$data['jumlah_jp_perencanaan'];
                    if ($jp_rencana < $min) {
                        $errors['jumlah_jp_perencanaan'] = "Batas minimal input perencanaan untuk '{$activity_name}' adalah {$min} JP.";
                    }
                    if ($jp_rencana > $max) {
                        $errors['jumlah_jp_perencanaan'] = "Batas maksimal input perencanaan untuk '{$activity_name}' adalah {$max} JP.";
                    }
                }

                // 2. Validasi Batas JP Realisasi
                if (isset($data['jumlah_jp_realisasi']) && $data['jumlah_jp_realisasi'] !== '') {
                    $jp_realisasi = (int)$data['jumlah_jp_realisasi'];
                    if ($jp_realisasi < $min) {
                        $errors['jumlah_jp_realisasi'] = "Batas minimal input realisasi untuk '{$activity_name}' adalah {$min} JP.";
                    }
                    if ($jp_realisasi > $max) {
                        $errors['jumlah_jp_realisasi'] = "Batas maksimal input realisasi untuk '{$activity_name}' adalah {$max} JP.";
                    }
                }
            }
        }

        return $errors;
    }
}