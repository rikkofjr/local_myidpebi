<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/assessment_atasan_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Tangkap parameter ID Induk IDP
$idp_id = required_param('id', PARAM_INT);

// 2. Ambil data dokumen IDP dari database
$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);

// 3. 🟢 SINKRONISASI LOGIKA DINAMIS (Mendukung Mode NIK / Email / ID)
// Ambil konfigurasi shortname profile field dari Admin UI
$shortname_config = get_config('local_myidpebi', 'profile_field_atasan');
$profile_field_shortname = !empty($shortname_config) ? $shortname_config : 'atasan_langsung';

// Ambil konfigurasi tipe data identitas dari Admin UI (username / email / id)
$identity_config = get_config('local_myidpebi', 'identity_field_atasan');
$field_target = !empty($identity_config) ? $identity_config : 'username';

// 🟢 PERBAIKAN UTAMA: u.username diganti menjadi u.{$field_target} agar adaptif terhadap Email
$sql_atasan = "SELECT u.id 
               FROM {user} u
               JOIN {user_info_data} d ON u.{$field_target} = d.data
               JOIN {user_info_field} f ON d.fieldid = f.id
               WHERE d.userid = ? AND f.shortname = ? AND u.deleted = 0";

$atasan_langsung_id = $DB->get_field_sql($sql_atasan, [$idp->userid, $profile_field_shortname]);

// 4. PROTEKSI HAK AKSES GANDA (Mengenali Pembimbing, Atasan Langsung Berbasis Email, atau Admin)
$is_pembimbing_ditunjuk = ($idp->atasan_id == $USER->id);
$is_atasan_langsung     = ($atasan_langsung_id && $atasan_langsung_id == $USER->id);
$is_admin               = is_siteadmin();

if (!$is_pembimbing_ditunjuk && !$is_atasan_langsung && !$is_admin) {
    throw new moodle_exception('nopermission', 'local_myidpebi', '', 'Hanya pembimbing yang ditunjuk, atasan langsung karyawan, atau admin yang dapat mengisi evaluasi ini.');
}


// 5. Batasan Status: Kuesioner atasan hanya boleh diisi saat status = 1 (IDP Berjalan/Realisasi)
if ($idp->status != 1) {
    throw new moodle_exception('nomodify', 'debug', '', 'Evaluasi atasan hanya dapat diisi pada program IDP yang sedang berjalan.');
}

// 6.  WAJIB KLIK DARI TOMBOL VIEW_DETAILS.PHP
// Mengecek apakah form sedang dalam proses menyimpan data (HTTP POST)
$is_submitting = ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!$is_submitting) {
    // 🔍 Pengecekan Referer HANYA dilakukan saat pertama kali halaman dibuka (HTTP GET)
    $referer = get_local_referer(false); 
    
    if (empty($referer) || strpos($referer, 'view_details.php') === false || strpos($referer, 'id=' . $idp_id) === false) {
        // Alirkan kembali ke halaman detail dengan pesan peringatan keras
        $redirect_back = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]);
        redirect(
            $redirect_back, 
            'Akses Ditolak! anda harus mengakses melalui tombol "Isi Penilaian & Verifikasi" resmi yang tersedia.', 
            null, 
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Inisialisasi URL Halaman dan Pengaturan Page Moodle
$url = new moodle_url('/local/myidpebi/assessment_atasan.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Evaluasi Atasan - IDP');
$PAGE->set_heading('Evaluasi & Penilaian Efektivitas IDP');

// Panggil form kuesioner dengan melempar ID IDP ke custom data
$mform = new \local_myidpebi\forms\assessment_atasan_form(null, ['idp_id' => $idp_id]);

// Jika form dibatalkan (klik tombol Cancel)
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]));
}

// Jika form disubmit dan data valid
if ($data = $mform->get_data()) {
    $total_skor_user = 0;
    $jumlah_soal = 0;
    
    // Hitung skor kuesioner secara dinamis (q1, q2, dst)
    foreach ($data as $key => $value) {
        if (strpos($key, 'q') === 0 && is_numeric(substr($key, 1))) {
            $total_skor_user += (int)$value;
            $jumlah_soal++;
        }
    }
    
    $total_skor_maksimal = $jumlah_soal * 5;
    $skor_persentase = ($total_skor_maksimal > 0) ? ($total_skor_user / $total_skor_maksimal) * 100 : 0;

    // Siapkan objek data untuk di-update ke tabel induk local_myidpebi
    $update_idp = new stdClass();
    $update_idp->id                 = $idp_id;
    $update_idp->skor_atasan        = number_format($skor_persentase, 2, '.', '');
    $update_idp->kesimpulan_atasan  = $data->kesimpulan_atasan;
    $update_idp->verified_by        = $USER->id; // Mencatat siapa yang melakukan submit penilaian (Bisa Putra / Aegidius)
    $update_idp->status             = 2;         // Tuntas / Selesai (Verified)

    // Eksekusi update data ke database Moodle
    $DB->update_record('local_myidpebi', $update_idp);

    // Kembalikan ke halaman rincian dengan notifikasi sukses
    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]), 'Penilaian efektivitas berhasil disimpan dan dokumen IDP resmi ditutup (Tuntas).', null, \core\output\notification::NOTIFY_SUCCESS);
}

// --- BAGIAN DISPLAY TAMPILAN ---
echo $OUTPUT->header();

// Tampilkan form kuesioner ke layar
$mform->display();

echo $OUTPUT->footer();