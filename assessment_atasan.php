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

// 3. Proteksi Hak Akses: Hanya Atasan/Pembimbing yang ditunjuk atau Admin yang boleh menilai
if ($idp->atasan_id != $USER->id && !is_siteadmin()) {
    throw new moodle_exception('nopermission', 'debug', '', 'Hanya atasan / pembimbing yang ditunjuk yang dapat mengisi evaluasi ini.');
}

// 4. Batasan Status: Kuesioner atasan hanya boleh diisi saat status = 1 (IDP Berjalan/Realisasi)
if ($idp->status != 1) {
    throw new moodle_exception('nomodify', 'debug', '', 'Evaluasi atasan hanya dapat diisi pada program IDP yang sedang berjalan.');
}

// Inisialisasi URL Halaman dan Pengaturan Page Moodle
$url = new moodle_url('/local/myidpebi/assessment_atasan.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Evaluasi Efektivitas IDP oleh Atasan');
$PAGE->set_heading('Assessment Efektivitas IDP oleh Atasan');

// Inisialisasi form assessment_atasan_form yang baru dibuat
$mform = new \local_myidpebi\forms\assessment_atasan_form($url->out(false), ['idp_id' => $idp_id]);

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
    
    // FORMULA: Total skor maksimal dihitung berdasarkan (Jumlah Soal yang Ada * 5)
    $total_skor_maksimal = $jumlah_soal * 5;
    
    // Hitung persentase efektivitas, amankan dari error pembagian dengan nol (division by zero)
    $skor_persentase = ($total_skor_maksimal > 0) ? ($total_skor_user / $total_skor_maksimal) * 100 : 0;

    // 5. Siapkan objek data untuk di-update ke tabel induk local_myidpebi (Sesuai field kustom Anda)
    $update_idp = new stdClass();
    $update_idp->id                 = $idp_id;
    $update_idp->skor_atasan        = number_format($skor_persentase, 2, '.', '');
    $update_idp->kesimpulan_atasan  = $data->kesimpulan_atasan;
    $update_idp->verified_by        = $USER->id; // Mencatat siapa atasan yang memverifikasi tuntas
    $update_idp->status             = 2;         // Mengubah status dokumen menjadi 2 (Tuntas / Selesai)

    // Eksekusi update data ke database Moodle
    $DB->update_record('local_myidpebi', $update_idp);

    // Alirkan navigasi kembali ke halaman view_details dengan notifikasi sukses
    redirect(
        new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]), 
        'Evaluasi efektivitas oleh atasan berhasil disimpan. Skor efektivitas penilaian: ' . number_format($skor_persentase, 2) . '%', 
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Render halaman HTML Moodle
echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();