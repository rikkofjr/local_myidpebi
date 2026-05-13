<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class act_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // Hidden field untuk IDP ID (Relasi ke tabel utama)
        $mform->addElement('hidden', 'idp_id');
        $mform->setType('idp_id', PARAM_INT);

        // Hidden field untuk ID Aktivitas (Hanya terisi saat mode EDIT)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'act_header', 'Form Aktivitas');
        
        $options = [
            'Workshop' => 'Workshop', 'Magang' => 'Magang', 
            'Coaching' => 'Coaching', 'Seminar' => 'Seminar',
            'Sertifikasi' => 'Sertifikasi', 'Belajar Mandiri' => 'Belajar Mandiri'
        ];
            
        $mform->addElement('select', 'jenis_kegiatan', 'Jenis', $options);
        $mform->addElement('text', 'nama_activity', 'Nama Aktivitas');
        $mform->setType('nama_activity', PARAM_TEXT);
        
        $mform->addElement('text', 'jumlah_jp', 'JP (Jam)');
        $mform->setType('jumlah_jp', PARAM_INT);
        
        $mform->addElement('text', 'waktu_teks', 'Waktu Pelaksanaan');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        // Filepicker untuk evidence
        $mform->addElement('filepicker', 'evidence_file', 'Upload Evidence (PDF/JPG)', null, ['maxbytes' => 2048*1024]);

        $this->add_action_buttons(true, 'Simpan Aktivitas');
    }
}