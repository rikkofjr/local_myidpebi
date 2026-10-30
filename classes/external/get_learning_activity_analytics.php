<?php
namespace local_myidpebi\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class get_learning_activity_analytics extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'year' => new external_value(PARAM_INT, 'Tahun periode kontrak IDP (opsional, default tahun sekarang)', VALUE_DEFAULT, 0)
        ]);
    }

    public static function execute($year = 0) {
        global $DB;

        // Validasi akses context system
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:viewreports', $context);

        if (empty($year)) {
            $year = (int)date('Y');
        }

        // Batas awal dan akhir tahun kalender untuk mengecek masa berlaku kontrak IDP
        $year_start = strtotime("$year-01-01 00:00:00");
        $year_end   = strtotime("$year-12-31 23:59:59");

        // Hitung total dokumen IDP yang memiliki rentang periode kontrak aktif pada tahun tersebut
        $idp_sql = "SELECT COUNT(DISTINCT id) 
                    FROM {local_myidpebi} 
                    WHERE mulai_date <= :year_end AND akhir_date >= :year_start";
        $total_idp_evaluated = $DB->count_records_sql($idp_sql, [
            'year_start' => $year_start,
            'year_end'   => $year_end,
        ]);

        // Agregasi Target JP vs Realisasi JP per jenis aktivitas dari master local_myidpebi_learning_activity
       $sql = "SELECT m.id as master_id,
                       m.learning_activity as activity_name,
                       m.bentuk_cdp,
                       m.tipe_aktivitas_cdp,
                       COUNT(DISTINCT act.idp_id) as total_karyawan_mengambil,
                       SUM(act.jumlah_jp_perencanaan) as total_jp_target,
                       SUM(act.jumlah_jp_realisasi) as total_jp_realisasi
                  FROM {local_myidpebi_act} act
                  JOIN {local_myidpebi} idp ON act.idp_id = idp.id
                  JOIN {local_myidpebi_learning_activity} m ON act.learning_activity_id COLLATE utf8mb4_unicode_ci = CAST(m.id AS CHAR(100)) COLLATE utf8mb4_unicode_ci
                 WHERE act.deleted = 0 
                   AND idp.mulai_date <= :year_end 
                   AND idp.akhir_date >= :year_start
              GROUP BY m.id, m.learning_activity, m.bentuk_cdp, m.tipe_aktivitas_cdp
              ORDER BY total_jp_realisasi DESC";

        $records = $DB->get_records_sql($sql, [
            'year_start' => $year_start,
            'year_end'   => $year_end,
        ]);

        $analytics = [];
        foreach ($records as $rec) {
            $jp_target = !empty($rec->total_jp_target) ? (float)$rec->total_jp_target : 0.00;
            $jp_realisasi = !empty($rec->total_jp_realisasi) ? (float)$rec->total_jp_realisasi : 0.00;
            
            $percentage = ($jp_target > 0) ? round(($jp_realisasi / $jp_target) * 100, 2) : 0.00;

            $analytics[] = [
                'master_id' => (int)$rec->master_id,
                'learning_activity_name' => $rec->activity_name,
                'bentuk_cdp' => $rec->bentuk_cdp ?: '-',
                'tipe_aktivitas_cdp' => $rec->tipe_aktivitas_cdp ?: '-',
                'total_karyawan_mengambil' => (int)$rec->total_karyawan_mengambil,
                'total_jp_target' => $jp_target,
                'total_jp_realisasi' => $jp_realisasi,
                'realization_percentage' => $percentage,
            ];
        }

        return [
            'filter_year' => $year,
            'evaluation_basis' => 'IDP Contract Period (mulai_date s.d. akhir_date)',
            'total_idp_evaluated' => (int)$total_idp_evaluated,
            'activity_analytics' => $analytics,
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'filter_year' => new external_value(PARAM_INT, 'Tahun filter kontrak IDP'),
            'evaluation_basis' => new external_value(PARAM_TEXT, 'Keterangan metode filter'),
            'total_idp_evaluated' => new external_value(PARAM_INT, 'Total dokumen IDP yang dievaluasi'),
            'activity_analytics' => new external_multiple_structure(
                new external_single_structure([
                    'master_id' => new external_value(PARAM_INT, 'ID master activity'),
                    'learning_activity_name' => new external_value(PARAM_TEXT, 'Nama jenis kegiatan'),
                    'bentuk_cdp' => new external_value(PARAM_TEXT, 'Bentuk CDP'),
                    'tipe_aktivitas_cdp' => new external_value(PARAM_TEXT, 'Tipe aktivitas CDP'),
                    'total_karyawan_mengambil' => new external_value(PARAM_INT, 'Jumlah IDP yang mencantumkan kegiatan ini'),
                    'total_jp_target' => new external_value(PARAM_FLOAT, 'Total akumulasi target JP perencanaan'),
                    'total_jp_realisasi' => new external_value(PARAM_FLOAT, 'Total akumulasi realisasi JP'),
                    'realization_percentage' => new external_value(PARAM_FLOAT, 'Persentase pencapaian realisasi JP (%)'),
                ])
            )
        ]);
    }
}