<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class idp_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;

        // Hidden field untuk ID (Hanya terisi saat mode EDIT)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'header_idp', 'Informasi Program IDP');
        
        $mform->addElement('text', 'nama_idp', 'Nama Program IDP');
        $mform->setType('nama_idp', PARAM_TEXT);
        $mform->addRule('nama_idp', null, 'required', null, 'client');

        $mform->addElement('text', 'nik_atasan', 'NIK Atasan');
        $mform->setType('nik_atasan', PARAM_RAW);
        $mform->addRule('nik_atasan', null, 'required', null, 'client');

        $mform->addElement('date_selector', 'mulai_date', 'Tanggal Mulai');
        $mform->addElement('date_selector', 'akhir_date', 'Target Selesai');

        $this->add_action_buttons(true, 'Simpan Program');
    }
}