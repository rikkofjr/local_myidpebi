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

/**
 * Serves the local_myidpebi files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_myidpebi_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB, $USER;

    // Pastikan user sudah login.
    require_login();

    // Cek konteks (kita menggunakan context_system di view_details.php).
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    // Cek area file.
    if ($filearea !== 'evidence') {
        return false;
    }

    // Argumen pertama dalam array $args biasanya adalah itemid (ID Aktivitas).
    $itemid = (int)array_shift($args);

    // Keamanan tambahan: Cek apakah user adalah pemilik IDP atau Atasan yang bersangkutan.
    $activity = $DB->get_record('local_myidpebi_act', array('id' => $itemid), '*', MUST_EXIST);
    $idp = $DB->get_record('local_myidpebi', array('id' => $activity->idp_id), '*', MUST_EXIST);

    $is_admin = is_siteadmin();
    $is_owner = ($idp->userid == $USER->id);gi
    $is_atasan = ($idp->atasan_id == $USER->id);

    if (!$is_admin && !$is_owner && !$is_atasan) {
        send_file_not_found();
    }

    // Ambil file dari storage.
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/local_myidpebi/$filearea/$itemid/$relativepath";

    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Kirim file ke browser.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}