<?php
defined('MOODLE_INTERNAL') || die();

/**
 * 🟢 SINKRONISASI UNIVERSAL: Mengambil data mentah identitas atasan langsung dari profile field kustom
 * Ditulis fleksibel mengikuti settingan di Site Administration Moodle
 */
function local_myidpebi_get_atasan_username($userid) {
    global $DB;
    
    // 1. Ambil nama shortname dari konfigurasi Admin UI (jika kosong, gunakan default 'atasan_langsung')
    $shortname_config = get_config('local_myidpebi', 'profile_field_atasan');
    $profile_field_shortname = !empty($shortname_config) ? $shortname_config : 'atasan_langsung';

    // 2. Ambil ID field kustom tersebut dari database
    $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $profile_field_shortname]);
    if (!$fieldid) {
        return '';
    }
    
    // 3. Ambil data mentah (bisa berupa NIK, Email, atau ID sesuai yang diinput)
    $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
    return $data ? trim($data) : '';
}

/**
 * 🟢 JAWABAN SOLUSI: Fungsi ini yang sebelumnya hilang di lib.php Anda!
 * Mencari user Moodle berdasarkan konfigurasi field admin yang dinamis (Username / Email / ID)
 */
function local_myidpebi_get_user_by_config($value) {
    global $DB;
    
    $value = trim($value);
    if (empty($value)) {
        return false;
    }

    // Ambil tipe identitas dari konfigurasi admin UI (default: username)
    $identity_config = get_config('local_myidpebi', 'identity_field_atasan');
    $field_target = !empty($identity_config) ? $identity_config : 'username';

    // Jalankan pencarian dinamis sesuai target kolom ke tabel master mdl_user
    return $DB->get_record('user', [$field_target => $value, 'deleted' => 0]);
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
            $status->text = 'Menunggu Approval'; // Menunggu Approval Pembimbing/Atasan
            $status->class = 'badge-secondary'; // Abu-abu
            break;
        case 1:
            $status->text = 'Disetujui / Proses'; //Disetujui Oleh Pembimbing/Atasan
            $status->class = 'badge-warning'; // Kuning
            break;
        case 2:
            $status->text = 'Selesai Diverifikasi'; //Diverivikasi Oleh Pembimbing/Atasan
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


//Eksekusi File dari rincian aktivitas
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
    $is_owner = ($idp->userid == $USER->id);
    $is_atasan = ($idp->atasan_id == $USER->id);

    // 🟢 EKSEKUSI LOGIKA DINAMIS ATASAN LANGSUNG (sistem)
    $is_atasan_langsung = false;

    // 1. Ambil setting tipe identitas yang aktif di Admin UI (username atau email)
    $identity_config = get_config('local_myidpebi', 'identity_field_atasan');
    $field_target = !empty($identity_config) ? $identity_config : 'username';

    // 2. Ambil shortname profile field dari Admin UI
    $shortname_config = get_config('local_myidpebi', 'profile_field_atasan');
    $profile_field_shortname = !empty($shortname_config) ? $shortname_config : 'atasan_langsung';

    // 3. Jalankan query pencarian real-time untuk mendapatkan ID Atasan Langsung si pemilik IDP
    $sql_atasan = "SELECT u.id 
                   FROM {user} u
                   JOIN {user_info_data} d ON u.{$field_target} = d.data
                   JOIN {user_info_field} f ON d.fieldid = f.id
                   WHERE d.userid = ? AND f.shortname = ? AND u.deleted = 0";

    $atasan_langsung_id = $DB->get_field_sql($sql_atasan, [$idp->userid, $profile_field_shortname]);

    if ($atasan_langsung_id && $atasan_langsung_id == $USER->id) {
        $is_atasan_langsung = true;
    }


    if (!$is_admin && !$is_owner && !$is_atasan && !$is_atasan_langsung) {
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

/**
 * NAMA IDP YANG DIAJUKAN KARYAWAN
 * FORMAT IDP-[KOLOM USER YANG DIGUNAKAN ANDA]-TAHUN
 * Mengambil nilai field user secara dinamis untuk kebutuhan default value/template text.
 * Dukung field tabel user bawaan maupun Custom Profile Field.
 * * @param int $userid ID User Moodle
 * @param string $sumber_field 'user_table' atau 'custom_profile'
 * @param string $nama_field Nama kolom di tabel user ATAU shortname di custom profile field
 * @return string Nilai kembalian berupa teks bersih
 */
function local_myidpebi_get_user_field_value($userid, $sumber_field = 'user_table', $nama_field = 'firstname') {
    global $DB;

    if (empty($userid)) {
        return '';
    }

    if ($sumber_field === 'user_table') {
        // 🔹 JIKA MENGGUNAKAN TABEL USER UTAMA MOODLE (e.g., firstname, lastname, idnumber, department)
        $value = $DB->get_field('user', $nama_field, ['id' => $userid]);
        return $value ? trim($value) : '';

    } else if ($sumber_field === 'custom_profile') {
        // 🔹 JIKA MENGGUNAKAN CUSTOM PROFILE FIELD MOODLE
        $sql = "SELECT d.data 
                FROM {user_info_data} d
                JOIN {user_info_field} f ON d.fieldid = f.id
                WHERE d.userid = ? AND f.shortname = ?";
        $value = $DB->get_field_sql($sql, [$userid, $nama_field]);
        return $value ? trim($value) : '';
    }

    return '';
}