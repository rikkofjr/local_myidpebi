<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/idp_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Admin atau Manager yang punya hak akses laporan
require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('moodle/site:viewreports', $context)) {
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

// Panggil form idp_form yang sudah kita sempurnakan bersama sebelumnya
$mform = new \local_myidpebi\forms\idp_form($url->out(false));

if ($mform->is_cancelled()) {
    // Jika batal, kembalikan ke panel monitoring admin
    redirect(new moodle_url('/local/myidpebi/admin_monitor.php'));
} else if ($data = $mform->get_data()) {
    
    // Siapkan data objek untuk update database secara paksa oleh Admin
    $update = new stdClass();
    $update->id          = $idp->id;
    $update->nama_idp    = $data->nama_idp;
    $update->nik_atasan  = $data->nik_atasan; // Menyimpan teks NIK Pembimbing baru dari autocomplete lokal
    $update->mulai_date  = $data->mulai_date;
    $update->akhir_date  = $data->akhir_date;

    // Tambahan Opsional: Jika kolom pembimbing di database Anda menggunakan user id angka (atasan_id), 
    // kita sinkronkan sekalian di sini agar alur approval otomatis berpindah ke orang baru.
    $new_pembimbing = $DB->get_record('user', ['username' => trim($data->nik_atasan), 'deleted' => 0]);
    if ($new_pembimbing) {
        $update->atasan_id = $new_pembimbing->id;
    }

    // Eksekusi update ke database
    $DB->update_record('local_myidpebi', $update);

    // Kembalikan ke halaman monitor dengan notifikasi sukses warna hijau
    redirect(new moodle_url('/local/myidpebi/admin_monitor.php'), 'Data IDP Karyawan berhasil disesuaikan oleh Admin berdasarkan SOP Perubahan.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Masukkan data lama IDP ke dalam form agar langsung tampil saat halaman dibuka
$mform->set_data($idp);

echo $OUTPUT->header();

// Beri alert penanda warna kuning sebagai pengingat bahwa ini adalah aksi Override Admin
echo '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <strong>Perhatian Terkait Regulasi:</strong> Anda bertindak sebagai Administrator. Perubahan pada form ini ditujukan khusus untuk keperluan koreksi data resmi (seperti pergantian pembimbing mutasi) di tengah jalan.</div>';

$mform->display();

echo $OUTPUT->footer();