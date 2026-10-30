<?php
namespace local_myidpebi\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/myidpebi/lib.php');
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use moodle_exception;

class get_global_analytics extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'year' => new external_value(PARAM_INT, 'Tahun berjalan (opsional, default tahun sekarang)', VALUE_DEFAULT, 0)
        ]);
    }

    public static function execute($year = 0) {
        global $DB;

        // Validasi akses context system
        $context = \context_system::instance();
        self::validate_context($context);

        // Validasi capability
        require_capability('moodle/site:viewreports', $context);

        if (empty($year)) {
            $year = (int)date('Y');
        }
        $year_start_timestamp = strtotime("$year-01-01");

        // Hitung metrik dokumen IDP
        $total_idp = $DB->count_records_select('local_myidpebi', "timecreated >= ?", [$year_start_timestamp]);
        $pending_approval = $DB->count_records('local_myidpebi', ['status' => 0]);
        $running_idp = $DB->count_records('local_myidpebi', ['status' => 1]);
        $verified_idp = $DB->count_records('local_myidpebi', ['status' => 2]);
        $completed_idp = $DB->count_records('local_myidpebi', ['status' => 3]);

        $completion_rate = ($total_idp > 0) ? round((($verified_idp + $completed_idp) / $total_idp) * 100, 2) : 0;

        // 🟢 Total JP Berdasarkan Kolom total_jp_verified di Tabel local_myidpebi (Hanya untuk Status 3)
        $total_jp_sql = "SELECT SUM(total_jp_verified) as total 
                         FROM {local_myidpebi} 
                         WHERE status = 3 AND timecreated >= ?";
        $jp_res = $DB->get_record_sql($total_jp_sql, [$year_start_timestamp]);
        $total_jp = !empty($jp_res->total) ? (float)$jp_res->total : 0.00;

        // Ambil info teks status dari lib.php
        $status0 = local_myidpebi_get_status_info(0);
        $status1 = local_myidpebi_get_status_info(1);
        $status2 = local_myidpebi_get_status_info(2);
        $status3 = local_myidpebi_get_status_info(3);

        return [
            'year' => $year,
            'total_idp' => $total_idp,
            'completion_rate' => $completion_rate,
            'total_jp_verified' => $total_jp,
            'status_breakdown' => [
                ['status_code' => 0, 'status_text' => $status0->text, 'count' => $pending_approval],
                ['status_code' => 1, 'status_text' => $status1->text, 'count' => $running_idp],
                ['status_code' => 2, 'status_text' => $status2->text, 'count' => $verified_idp],
                ['status_code' => 3, 'status_text' => $status3->text, 'count' => $completed_idp],
            ]
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'year' => new external_value(PARAM_INT, 'Tahun laporan'),
            'total_idp' => new external_value(PARAM_INT, 'Total dokumen IDP'),
            'completion_rate' => new external_value(PARAM_FLOAT, 'Persentase penyelesaian'),
            'total_jp_verified' => new external_value(PARAM_FLOAT, 'Total jam pelajaran terverifikasi dari kolom utama'),
            'status_breakdown' => new \external_multiple_structure(
                new external_single_structure([
                    'status_code' => new external_value(PARAM_INT, 'Kode status'),
                    'status_text' => new external_value(PARAM_TEXT, 'Teks status resmi'),
                    'count' => new external_value(PARAM_INT, 'Jumlah dokumen')
                ])
            )
        ]);
    }
}