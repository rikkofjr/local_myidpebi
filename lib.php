<?php
defined('MOODLE_INTERNAL') || die();

function local_myidpebi_get_atasan_username($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'atasan_langsung']);
    if (!$fieldid) return '';
    $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
    return $data ? trim($data) : '';
}

/**
 * Fungsi Pusat untuk Definisi Status IDP
 * 0 = Menunggu (Pending)
 * 1 = Disetujui / Dalam Proses (In Progress)
 * 2 = Selesai Diverifikasi (Verified)
 */
function local_myidpebi_get_status_info($statuscode) {
    $status = new stdClass();

    switch ($statuscode) {
        case 0:
            $status->text = 'Menunggu Approval';
            $status->class = 'badge-secondary'; // Abu-abu
            break;
        case 1:
            $status->text = 'Disetujui / Proses';
            $status->class = 'badge-warning'; // Kuning
            break;
        case 2:
            $status->text = 'Selesai Diverifikasi';
            $status->class = 'badge-success'; // Hijau
            break;
        default:
            $status->text = 'Unknown';
            $status->class = 'badge-dark';
    }

    // Menghasilkan HTML badge siap pakai
    $status->badge = '<span class="badge ' . $status->class . '">' . $status->text . '</span>';
    
    return $status;
}