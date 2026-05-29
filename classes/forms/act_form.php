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
        

        // Mengambil master data dari tabel baru local_myidpebi_jenis_kegiatan
        $master_kegiatan = $DB->get_records('local_myidpebi_jenis_kegiatan', null, 'jenis_kegiatan ASC', 'id, jenis_kegiatan');
        
        $jenis_opsi = ['' => '-- Pilih Jenis Kegiatan --'];
        if ($master_kegiatan) {
            foreach ($master_kegiatan as $mk) {
                $jenis_opsi[$mk->jenis_kegiatan] = $mk->jenis_kegiatan;
            }
        }
        $mform->addElement('select', 'jenis_kegiatan', 'Aktivitas pengembangan yang akan dilakukan', $jenis_opsi);
        
        $mform->addElement('text', 'nama_activity', 'Detail Kegiatan');
        $mform->setType('nama_activity', PARAM_TEXT);

        $mform->addElement('text', 'jumlah_jp_perencanaan', 'Jam Pelajaran (JP) Perencanaan', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp_perencanaan', PARAM_INT);
        $mform->addHelpButton('jumlah_jp_perencanaan', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        
        $mform->addElement('text', 'waktu_teks', 'Periode');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        // ==========================================
        // GROUP FORM 2: REALISASI & EVIDENCE
        // ==========================================
        $mform->addElement('header', 'group_form_2', 'Group Form 2: Realisasi Pengembangan (Diisi Setelah Berjalan)');
        
        $mform->addElement('text', 'jumlah_jp_realisasi', 'Jam Pelajaran (JP) Realisasi', ['placeholder' => 'Masukkan angka JP...']);
        $mform->setType('jumlah_jp_realisasi', PARAM_INT);
        $mform->addHelpButton('jumlah_jp_realisasi', 'help_jp', 'local_myidpebi'); // Opsional jika ingin ada tombol tanya info
        
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
                    'jenis_kegiatan', 'nama_activity', 'waktu_teks', 'jumlah_jp_perencanaan'
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

        // Pastikan jenis kegiatan dipilih terlebih dahulu untuk mengambil aturan databasenya
        if (!empty($data['jenis_kegiatan'])) {
            $jenis = $data['jenis_kegiatan'];

            // Query mengambil batasan Min & Max JP dari tabel master kustom Anda
            $rule = $DB->get_record('local_myidpebi_jenis_kegiatan', ['jenis_kegiatan' => $jenis], 'id, jp_min, jp_max');

            if ($rule) {
                $min = (int)$rule->jp_min;
                $max = (int)$rule->jp_max;

                // 🟢 1. VALIDASI UNTUK JP PERENCANAAN
                if (isset($data['jumlah_jp_perencanaan']) && $data['jumlah_jp_perencanaan'] !== '') {
                    $jp_rencana = (int)$data['jumlah_jp_perencanaan'];
                    
                    if ($jp_rencana < $min) {
                        $errors['jumlah_jp_perencanaan'] = "Batas minimal input perencanaan untuk '" . s($jenis) . "' adalah {$min} JP.";
                    }
                    if ($jp_rencana > $max) {
                        $errors['jumlah_jp_perencanaan'] = "Batas maksimal input perencanaan untuk '" . s($jenis) . "' adalah {$max} JP.";
                    }
                }

                // 🟢 2. VALIDASI UNTUK JP REALISASI
                if (isset($data['jumlah_jp_realisasi']) && $data['jumlah_jp_realisasi'] !== '') {
                    $jp_realisasi = (int)$data['jumlah_jp_realisasi'];
                    
                    if ($jp_realisasi < $min) {
                        $errors['jumlah_jp_realisasi'] = "Batas minimal input realisasi untuk '" . s($jenis) . "' adalah {$min} JP.";
                    }
                    if ($jp_realisasi > $max) {
                        $errors['jumlah_jp_realisasi'] = "Batas maksimal input realisasi untuk '" . s($jenis) . "' adalah {$max} JP.";
                    }
                }
            }
        }

        return $errors;
    }
}