<?php
namespace local_myidpebi\event;

defined('MOODLE_INTERNAL') || die();

class idp_saved extends \core\event\base {

    protected function init() {
        $this->data['objecttable'] = 'local_myidpebi';
        
        // 🟢 Tangkap nilai 'crud' dari parameter other, default ke 'u' (update) jika tidak ada
        $this->data['crud'] = isset($this->other['crud']) ? $this->other['crud'] : 'u';
        
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    public static function get_name() {
        return "Individual Development Plan (IDP) Saved / Modified";
    }

    public function get_description() {
        $action = isset($this->other['action']) ? $this->other['action'] : 'update';

        if ($action === 'create') {
            return "User dengan ID {$this->userid} membuat dokumen IDP baru (ID: {$this->objectid}).";
        } else {
            return "User dengan ID {$this->userid} memperbarui rincian dokumen IDP ID {$this->objectid}.";
        }
    }

    public function get_url() {
        return new \moodle_url('/local/myidpebi/view_details.php', ['id' => $this->objectid]);
    }
}