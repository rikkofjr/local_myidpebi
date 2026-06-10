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
        // GROUP FORM : LANGKAH 2: PENYUSUNAN RENCANA PENGEMBANGAN INDIVIDU
        // ==========================================
        $mform->addElement('header', 'group_form_1', 'LANGKAH 2: PENYUSUNAN RENCANA PENGEMBANGAN INDIVIDU');

        
        // Mengambil master data dari tabel baru local_myidpebi_learning_activity
        $master_kegiatan = $DB->get_records('local_myidpebi_learning_activity', null, 'tipe_aktivitas_cdp ASC', 'id, learning_activity, tipe_aktivitas_cdp');
        
        $jenis_opsi = ['' => '-- Pilih Jenis Kegiatan --'];
        if ($master_kegiatan) {
            foreach ($master_kegiatan as $mk) {
                $jenis_opsi[$mk->id] = $mk->tipe_aktivitas_cdp .' - '. $mk->learning_activity;
            }
        }
        $mform->addElement('select', 'learning_activity', 'Aktivitas pengembangan yang akan dilakukan', $jenis_opsi);
        $mform->setType('learning_activity', PARAM_INT);
        $mform->addRule('learning_activity', 'Harus Diisi', 'required', null, 'client');
        

        $mform->addElement('text', 'nama_activity', 'Detail Kegiatan');
        $mform->setType('nama_activity', PARAM_TEXT);
        $mform->addRule('nama_activity', 'Harus Diisi', 'required', null, 'client');
        

        $mform->addElement('text', 'jumlah_jp_perencanaan', 'Jumlah Jam Pembelajaran/JP (Rencana)', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp_perencanaan', PARAM_INT);
        $mform->addHelpButton('jumlah_jp_perencanaan', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        $mform->addRule('jumlah_jp_perencanaan', 'Wajib diisi !', 'required', null, 'client');
        $mform->addRule('jumlah_jp_perencanaan', 'hanya boleh diisi oleh angka!', 'numeric', null, 'client');

        $mform->addElement('text', 'waktu_teks', 'Periode Pelaksanaan (Rencana)');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        // ==========================================
        // GROUP FORM 2: REALISASI & EVIDENCE
        // ==========================================
        $mform->addElement('header', 'group_form_2', 'LANGKAH 3: REALISASI PENGEMBANGAN INDIVIDU (Diisi secara periodik setelah terlaksana)');
        
        $mform->addElement('text', 'jumlah_jp_realisasi', 'Jam Pembelajaran/JP (Realisasi)', ['placeholder' => 'Masukkan angka JP...']);
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
                // 🟢 JIKA EDIT DATA LAMA DI STATUS berjalan: Kunci Rencana tanpa menggunakan freeze()
                
                // 1. Kunci input text 'nama_activity', 'waktu_teks', dan 'jumlah_jp_perencanaan' menggunakan readonly
                // Atribut ini mengunci inputan, tapi datanya TETAP TERKIRIM saat disave sehingga tidak memicu error required
                if ($mform->elementExists('nama_activity')) {
                    $mform->getElement('nama_activity')->updateAttributes(['
                    readonly' => 'readonly',
                    'style' => 'background-color: #e9ecef;']
                    
                    );
                }
                if ($mform->elementExists('waktu_teks')) {
                    $mform->getElement('waktu_teks')->updateAttributes([
                        'readonly' => 'readonly',
                        'style' => 'background-color: #e9ecef;']
                        
                        );
                }
                if ($mform->elementExists('jumlah_jp_perencanaan')) {
                    $mform->getElement('jumlah_jp_perencanaan')->updateAttributes([
                        'readonly' => 'readonly', 
                        'style' => 'background-color: #e9ecef;']
                        
                        );
                }

                // 2. Kunci input dropdown 'learning_activity' (atau 'learning_activity_id') menggunakan CSS pointer-events
                // Dropdown tidak mendukung readonly, jadi kita matikan interaksi klik-nya agar tidak bisa diganti karyawan
                if ($mform->elementExists('learning_activity')) {
                    $mform->getElement('learning_activity')->updateAttributes([
                        'style' => 'background-color: #e9ecef; pointer-events: none; touch-action: none;',
                        'tabindex' => '-1'
                    ]);
                }
                
            } else {
                // Jika TAMBAH BARU di status 1: Buka rencana, kunci realisasi
                $mform->freeze(['jumlah_jp_realisasi']);
                if ($mform->elementExists('evidence_file')) {
                    $mform->getElement('evidence_file')->freeze();
                }
            }
        }

        $mform->addElement('submit', 'submitbutton', 'Simpan'); //simpan 
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