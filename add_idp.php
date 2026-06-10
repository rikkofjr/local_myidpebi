<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Tangkap Parameter (0 berarti mode CREATE, jika ada ID berarti mode EDIT)
$edit_idp_id = optional_param('edit_idp', 0, PARAM_INT);

// 2. Inisialisasi URL Halaman add_idp.php
$url = new moodle_url('/local/myidpebi/add_idp.php');
if ($edit_idp_id) {
    $url->param('edit_idp', $edit_idp_id);
}

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

// Atur judul halaman berdasarkan mode (Tambah atau Edit)
$page_title = $edit_idp_id ? 'Edit Program IDP' : 'Tambah Program IDP Baru';
$PAGE->set_title($page_title);
$PAGE->set_heading($page_title);

// 3. Inisialisasi Form Moodle idp_form
$mform = new \local_myidpebi\forms\idp_form();

// Jika dalam mode EDIT, proteksi hak akses dan tuangkan data lama ke dalam form
if ($edit_idp_id) {
    $current_idp = $DB->get_record('local_myidpebi', ['id' => $edit_idp_id, 'userid' => $USER->id]);
    
    if (!$current_idp) {
        throw new moodle_exception('invalidrecord', 'debug', '', 'Data IDP tidak ditemukan.');
    }
    
    if ($current_idp->status != 0) {
        throw new moodle_exception('nomodify', 'debug', '', 'IDP yang sudah berjalan tidak dapat diubah.');
    }

    // Konversi 'atasan_id' di database menjadi 'nik_atasan' agar dikenali oleh form autocomplete
    $atasan_username = $DB->get_field('user', 'username', ['id' => $current_idp->atasan_id]);
    if ($atasan_username) {
        $current_idp->nik_atasan = $atasan_username;
    }

    $mform->set_data($current_idp);
    
} else {
    // 🟢 MODE SELEKSI OTOMATIS SAAT BUAT BARU
    // Ambil string NIK Atasan langsung melalui fungsi di lib.php
    $atasan_nik = local_myidpebi_get_atasan_username($USER->id);
    
    if (!empty($atasan_nik)) {
        // Set key 'nik_atasan' sesuai dengan nama elemen autocomplete di idp_form.php
        $mform->set_data([
            'nik_atasan' => trim($atasan_nik)
        ]);
    }
}

// 4. PROSES EKSEKUSI PENYIMPANAN DATA FORM
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/myidpebi/index.php'));
} else if ($fromform = $mform->get_data()) {
    
    $idp = new stdClass();
    $idp->nama_idp   = $fromform->nama_idp;
    $idp->userid     = $USER->id;
    $idp->mulai_date = $fromform->mulai_date;
    $idp->akhir_date = $fromform->akhir_date;
    $idp->tuntutan_sekarang_performance = $fromform->tuntutan_sekarang_performance;
    $idp->tuntutan_sekarang_kompetensi = $fromform->tuntutan_sekarang_kompetensi;
    $idp->tuntutan_berikutnya_performance = $fromform->tuntutan_berikutnya_performance;
    $idp->tuntutan_berikutnya_kompetensi = $fromform->tuntutan_berikutnya_kompetensi;
    $idp->tuntutan_lingkungan = $fromform->tuntutan_lingkungan;
    $idp->area_pengembangan_ditingkatkan = $fromform->area_pengembangan_ditingkatkan;
    $idp->area_pengembangan_diharapkan = $fromform->area_pengembangan_diharapkan;
    
    // Menentukan ID Atasan menggunakan konfigurasi dinamis dari Admin UI
    $atasan_id = 0;
    if (!empty($fromform->nik_atasan)) {
        // Memanggil helper universal dari lib.php (Bisa membaca NIK/Email/ID secara otomatis)
        $atasan_user = local_myidpebi_get_user_by_config($fromform->nik_atasan);
        if ($atasan_user) {
            $atasan_id = (int)$atasan_user->id;
        }
    }
    // isi atasannya
    $idp->atasan_id = $atasan_id;

    // Deteksi ID lama secara akurat dari data internal Form yang disubmit
    // Prioritaskan mengambil dari $fromform->id terlebih dahulu
    $actual_idp_id = !empty($fromform->id) ? (int)$fromform->id : (int)$edit_idp_id;

    if ($actual_idp_id > 0) {
        // --- PROSES UPDATE ---
        $idp->id = $actual_idp_id;
        $DB->update_record('local_myidpebi', $idp);
        
        $redirect_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $actual_idp_id]);
        redirect($redirect_url, 'Program IDP berhasil diperbarui.', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // --- PROSES INSERT ---
        $idp->status      = 0;
        $idp->timecreated = time();
        
        $new_idp_id = $DB->insert_record('local_myidpebi', $idp);
        
        $redirect_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $new_idp_id]);
        redirect($redirect_url, 'Program IDP berhasil dibuat, silahkan minta atasan / pembimbing anda untuk approval', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// 5. MEMULAI OUTPUT TAMPILAN HTML MOODLE
echo $OUTPUT->header();

echo '<div class="card shadow-sm">';
echo '  <div class="card-body">';
echo '      <h3 class="mb-4">' . ($edit_idp_id ? '<i class="fa fa-edit text-warning"></i> Edit Rencana IDP' : '<i class="fa fa-plus-circle text-primary"></i> Tambah Program IDP Baru') . '</h3>';
$mform->display();
echo '  </div>';
echo '</div>';

echo $OUTPUT->footer();