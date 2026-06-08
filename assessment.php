<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/assessment_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Tangkap parameter ID Induk IDP
$idp_id = required_param('id', PARAM_INT);

// 2. Ambil data dokumen IDP dari database
$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);

// 3. Proteksi Hak Akses: Hanya pemilik IDP yang boleh mengisi self-assessment
if ($idp->userid != $USER->id) {
    throw new moodle_exception('nopermission', 'debug', '', 'Hanya pemilik IDP yang dapat mengisi evaluasi mandiri.');
}

// 4. Batasan Status: Kuesioner hanya boleh diisi saat status = 1 (IDP Berjalan/Realisasi)
if ($idp->status != 1) {
    throw new moodle_exception('nomodify', 'debug', '', 'Evaluasi hanya dapat diisi pada program IDP yang sedang berjalan.');
}

// 5.  WAJIB KLIK DARI TOMBOL VIEW_DETAILS.PHP
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
            'Akses Ditolak! Anda wajib mengakses halaman ini melalui tombol "Isi Evaluasi Efektivitas IDP" resmi yang tersedia.', 
            null, 
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Inisialisasi URL Halaman dan Pengaturan Page Moodle
$url = new moodle_url('/local/myidpebi/assessment.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Evaluasi Efektivitas IDP');
$PAGE->set_heading('Self-Assessment Efektivitas IDP');

// Inisialisasi form assessment yang dibuat di Tahap 2
$mform = new \local_myidpebi\forms\assessment_form($url->out(false));

// Atur data awal (passing ID ke hidden field form)
$mform->set_data(['id' => $idp_id]);

if ($mform->is_cancelled()) {
    // Jika batal, kembalikan ke halaman rincian aktivitas
    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]));
} else if ($data = $mform->get_data()) {
    
   // =========================================================================
    // 🧮 LOGIKA KALKULASI RUMUS MATEMATIKA PERSENTASE SKOR EFEKTIVITAS (DINAMIS)
    // =========================================================================
    $total_skor_user = 0;
    $jumlah_soal = 0;

    // Looping otomatis untuk membaca semua input data form yang berawalan huruf 'q' (q1, q2, dst)
    foreach ($data as $key => $value) {
        if (strpos($key, 'q') === 0 && is_numeric(substr($key, 1))) {
            $total_skor_user += (int)$value;
            $jumlah_soal++;
        }
    }
    
    // 🟢 FORMULA BARU: Total skor maksimal dihitung berdasarkan (Jumlah Soal yang Ada * 5)
    $total_skor_maksimal = $jumlah_soal * 5;
    
    // Hitung persentase efektivitas, amankan dari error pembagian dengan nol (division by zero)
    $skor_persentase = ($total_skor_maksimal > 0) ? ($total_skor_user / $total_skor_maksimal) * 100 : 0;

    // 5. Siapkan objek data untuk di-update ke tabel induk local_myidpebi
    $update_idp = new stdClass();
    $update_idp->id                  = $idp_id;
    $update_idp->skor_efektivitas    = number_format($skor_persentase, 2, '.', '');
    $update_idp->kesimpulan_karyawan = $data->kesimpulan_karyawan;

    // Eksekusi update data ke database Moodle
    $DB->update_record('local_myidpebi', $update_idp);

    // Alirkan navigasi kembali ke halaman view_details dengan notifikasi sukses
    redirect(
        new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]), 
        'Evaluasi efektivitas mandiri berhasil disimpan. Skor efektivitas Anda: ' . number_format($skor_persentase, 2) . '%', 
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render halaman HTML Moodle
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();