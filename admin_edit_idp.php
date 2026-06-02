<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/idp_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Admin atau Manager yang punya hak akses laporan
require_login();

$context = context_system::instance();
$is_manager = has_capability('moodle/site:viewreports', $context);
if (!is_siteadmin() && !$is_manager) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// Ambil ID IDP yang mau diedit dari parameter URL
$idp_id = required_param('id', PARAM_INT);
$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);

$url = new moodle_url('/local/myidpebi/admin_edit_idp.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Edit Program IDP Karyawan');
$PAGE->set_heading('Form Koreksi Data IDP (Override Admin)');

// Panggil form idp_form
$mform = new \local_myidpebi\forms\idp_form($url->out(false));

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/myidpebi/admin_monitor.php'));
} else if ($data = $mform->get_data()) {
    $update = new stdClass();
    $update->id          = $idp_id;
    $update->nama_idp    = $data->nama_idp;
    $update->mulai_date  = $data->mulai_date;
    $update->akhir_date  = $data->akhir_date;

    // Sinkronisasi Pembimbing (atasan_id) berdasarkan input NIK Pembimbing
    $new_pembimbing = $DB->get_record('user', ['username' => trim($data->nik_atasan), 'deleted' => 0]);
    if ($new_pembimbing) {
        $update->atasan_id = $new_pembimbing->id;
    }

    // Eksekusi update data utama IDP
    $DB->update_record('local_myidpebi', $update);

    // 🟢 OVERRIDE ADMIN: Simpan perubahan NIK Atasan Langsung ke Custom Profile Field Karyawan Pemilik IDP
    if (!empty($data->atasan_anda)) {
        $field_id = $DB->get_field('user_info_field', 'id', ['shortname' => 'atasan_langsung']);
        if ($field_id) {
            $profile_data = $DB->get_record('user_info_data', ['userid' => $idp->userid, 'fieldid' => $field_id]);
            if ($profile_data) {
                $profile_data->data = trim($data->atasan_anda);
                $DB->update_record('user_info_data', $profile_data);
            } else {
                $new_profile = new stdClass();
                $new_profile->userid = $idp->userid;
                $new_profile->fieldid = $field_id;
                $new_profile->data = trim($data->atasan_anda);
                $DB->insert_record('user_info_data', $new_profile);
            }
        }
    }

    redirect(new moodle_url('/local/myidpebi/admin_monitor.php'), 'Data IDP Karyawan dan Atasan Langsung berhasil disesuaikan oleh Admin.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// 🟢 SINKRONISASI UTAMA: Ambil NIK Atasan Langsung dari Karyawan Pemilik IDP (bukan Admin yang login)
$sql_atasan = "SELECT d.data 
               FROM {user_info_data} d
               JOIN {user_info_field} f ON d.fieldid = f.id
               WHERE d.userid = ? AND f.shortname = ?";
$karyawan_atasan_nik = $DB->get_field_sql($sql_atasan, [$idp->userid, 'atasan_langsung']);

if ($karyawan_atasan_nik) {
    $idp->atasan_anda = trim($karyawan_atasan_nik);
}

// Ambil juga NIK Pembimbing (atasan_id) lama agar terisi otomatis di form admin
$pembimbing_lama = $DB->get_record('user', ['id' => $idp->atasan_id], 'username');
if ($pembimbing_lama) {
    $idp->nik_atasan = $pembimbing_lama->username;
}

// 🟢 EKSEKUSI SATU KALI SAJA: Lemparkan data objek yang sudah matang ke form sebelum dirender
$mform->set_data($idp);

echo $OUTPUT->header();

echo '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <strong>Perhatian Terkait Hak Otoritas Admin:</strong> Halaman ini digunakan khusus untuk melakukan koreksi darurat / data adjustment apabila terjadi kesalahan input atau rotasi jabatan pembimbing di lingkungan instansi.</div>';

$mform->display();
echo $OUTPUT->footer();