<?php
namespace local_myidpebi\event;

defined('MOODLE_INTERNAL') || die();

class idp_status_changed extends \core\event\base {

    /**
     * Inisialisasi dasar event log
     */
    protected function init() {
        $this->data['objecttable'] = 'local_myidpebi';
        $this->data['crud'] = 'u'; 
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Nama event yang muncul pada daftar pilihan filter report log Moodle
     */
    public static function get_name() {
        return "Individual Development Plan (IDP) Status Changed";
    }

   /**
     * Deskripsi teks log yang masuk ke database
     */
    public function get_description() {
        // 1. Paksa Moodle untuk me-load file lib.php agar fungsi globalnya tersedia di halaman report log
        require_once(__DIR__ . '/../../lib.php');

        $statuscode = isset($this->other['status_code']) ? (int)$this->other['status_code'] : 0;
        
        // 2. Panggil fungsi master bahasa menggunakan global scope backslash (\)
        $status_info = \local_myidpebi_get_status_info($statuscode);
        $status_text = $status_info->text;

        return "User dengan ID {$this->userid} mengubah status dokumen IDP ID {$this->objectid} menjadi '{$status_text}'.";
    }

    /**
     * Link pintas saat log diklik di panel admin
     */
    public function get_url() {
        return new \moodle_url('/local/myidpebi/view_details.php', ['id' => $this->objectid]);
    }
}