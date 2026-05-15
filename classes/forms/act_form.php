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

        $mform->addElement('header', 'act_header', 'Detail Aktivitas');
        
        $options = ['Workshop'=>'Workshop','Magang'=>'Magang','Coaching'=>'Coaching','Seminar'=>'Seminar','Sertifikasi'=>'Sertifikasi','Belajar Mandiri'=>'Belajar Mandiri'];
        $mform->addElement('select', 'jenis_kegiatan', 'Jenis', $options);
        $mform->addElement('text', 'nama_activity', 'Nama Aktivitas');
        $mform->setType('nama_activity', PARAM_TEXT);
        $mform->addElement('text', 'waktu_teks', 'Waktu Pelaksanaan');
        $mform->setType('waktu_teks', PARAM_TEXT);
        
        $mform->addElement('text', 'jumlah_jp', 'Jumlah JP');
        $mform->setType('jumlah_jp', PARAM_INT);
        $mform->addElement('filepicker', 'evidence_file', 'Upload Evidence', null, ['maxbytes'=>2048*1024, 'accepted_types'=>['.pdf','.jpg','.png']]);

        // LOGIKA UX:
        if ($status == 0) {
            $mform->freeze(['jumlah_jp', 'evidence_file']);
        } else if ($status == 1) {
            if ($act_id) {
                // Jika EDIT data lama: Kunci rencana
                $mform->freeze(['jenis_kegiatan', 'nama_activity', 'waktu_teks']);
            } else {
                // Jika TAMBAH BARU: Buka rencana, kunci realisasi
                $mform->freeze(['jumlah_jp', 'evidence_file']);
            }
        }

        $this->add_action_buttons(true, 'Simpan');
    }
}