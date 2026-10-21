<?php
namespace local_myidpebi\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/myidpebi/lib.php');
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class get_all_user_idps extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'status_filter' => new external_value(PARAM_INT, 'Filter status (-1 untuk semua)', VALUE_DEFAULT, -1)
        ]);
    }

    public static function execute($status_filter = -1) {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:viewreports', $context);

        $where = "";
        $params = [];
        if ($status_filter !== -1) {
            $where = "WHERE i.status = :status";
            $params['status'] = $status_filter;
        }

        $sql = "SELECT i.*, 
                       u.username as k_username, u.firstname as k_firstname, u.lastname as k_lastname,
                       p.username as p_username, p.firstname as p_firstname, p.lastname as p_lastname
                FROM {local_myidpebi} i
                JOIN {user} u ON i.userid = u.id
                LEFT JOIN {user} p ON i.atasan_id = p.id
                {$where}
                ORDER BY i.timecreated DESC";

        $records = $DB->get_records_sql($sql, $params);
        $result = [];

        foreach ($records as $rec) {
            $status_info = local_myidpebi_get_status_info($rec->status);

            $result[] = [
                'idp_id' => (int)$rec->id,
                'nama_idp' => $rec->nama_idp,
                'status_code' => (int)$rec->status,
                'status_text' => $status_info->text,
                'mulai_date' => (int)$rec->mulai_date,
                'akhir_date' => (int)$rec->akhir_date,
                'total_jp_verified' => (float)$rec->total_jp_verified,
                'user' => [
                    'userid' => (int)$rec->userid,
                    'username' => $rec->k_username,
                    'firstname' => $rec->k_firstname,
                    'lastname' => $rec->k_lastname,
                ],
                'pembimbing' => [
                    'pembimbing_id' => (int)$rec->atasan_id,
                    'username' => $rec->p_username ?: '-',
                    'firstname' => !empty($rec->p_firstname) ? $rec->p_firstname : '-',
                    'lastname' => !empty($rec->p_lastname) ? $rec->p_lastname : '-',
                ]
            ];
        }

        return $result;
    }

    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'idp_id' => new external_value(PARAM_INT, 'ID dokumen IDP'),
                'nama_idp' => new external_value(PARAM_TEXT, 'Nama program IDP'),
                'status_code' => new external_value(PARAM_INT, 'Kode status'),
                'status_text' => new external_value(PARAM_TEXT, 'Teks status resmi dari lib'),
                'mulai_date' => new external_value(PARAM_INT, 'Timestamp tanggal mulai'),
                'akhir_date' => new external_value(PARAM_INT, 'Timestamp tanggal selesai'),
                'total_jp_verified' => new external_value(PARAM_FLOAT, 'Total JP terverifikasi'),
                'user' => new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'ID User Karyawan'),
                    'username' => new external_value(PARAM_TEXT, 'Username Karyawan'),
                    'firstname' => new external_value(PARAM_TEXT, 'Nama Depan Karyawan'),
                    'lastname' => new external_value(PARAM_TEXT, 'Nama Belakang Karyawan'),
                ]),
                'pembimbing' => new external_single_structure([
                    'pembimbing_id' => new external_value(PARAM_INT, 'ID User Pembimbing'),
                    'username' => new external_value(PARAM_TEXT, 'Username Pembimbing'),
                    'firstname' => new external_value(PARAM_TEXT, 'Nama Depan Pembimbing'),
                    'lastname' => new external_value(PARAM_TEXT, 'Nama Belakang Pembimbing'),
                ])
            ])
        );
    }
}