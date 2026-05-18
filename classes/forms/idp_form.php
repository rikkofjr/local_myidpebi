<?php
namespace local_myidpebi\forms;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class idp_form extends \moodleform {
    public function definition() {
        global $DB, $USER;
        $mform = $this->_form;

        // Hidden field untuk ID (Hanya terisi saat mode EDIT)
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'header_idp', 'Informasi Program IDP');
        
        $mform->addElement('text', 'nama_idp', 'Nama Program IDP');
        $mform->setType('nama_idp', PARAM_TEXT);
        $mform->addRule('nama_idp', null, 'required', null, 'client');

        // Mengubah istilah tampilan menjadi NIK Pembimbing
        $mform->addElement('text', 'nik_atasan', 'NIK Pembimbing / Coach');
        $mform->setType('nik_atasan', PARAM_RAW);
        $mform->addRule('nik_atasan', null, 'required', null, 'client');
        $mform->addHelpButton('nik_atasan', 'help_pembimbing', 'local_myidpebi');

        $mform->addElement('date_selector', 'mulai_date', 'Tanggal Mulai');
        $mform->addElement('date_selector', 'akhir_date', 'Target Selesai');

        $this->add_action_buttons(true, 'Simpan Program');
    }

    /**
     * Mengatur data bawaan form, otomatis mengambil NIK Atasan Langsung dari Custom Profile Field
     */
    public function set_data($data) {
        global $DB, $USER;
        
        if (is_array($data)) {
            $data = (object)$data;
        }

        // Jika ini adalah penambahan baru (ID tidak ada), cari NIK Atasan Langsung dari Custom Profile Field
        if (empty($data->id)) {
            $sql = "SELECT d.data 
                    FROM {user_info_data} d
                    JOIN {user_info_field} f ON d.fieldid = f.id
                    WHERE d.userid = ? AND f.shortname = ?";
            
            $atasan_langsung_nik = $DB->get_field_sql($sql, [$USER->id, 'atasan_langsung']);
            
            if ($atasan_langsung_nik) {
                $data->nik_atasan = trim($atasan_langsung_nik);
            }
        }
        
        return parent::set_data($data);
    }
}