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
$mform = new \local_myidpebi\forms\idp_form(null, ['edit_idp' => $edit_idp_id]);

// Jika dalam mode EDIT, proteksi hak akses dan tuangkan data lama ke dalam form
if ($edit_idp_id) {
    $current_idp = $DB->get_record('local_myidpebi', ['id' => $edit_idp_id]);
    
    if (!$current_idp) {
        throw new moodle_exception('invalidrecord', 'debug', '', 'Data IDP tidak ditemukan.');
    }
    
    // Keamanan: Pastikan yang mengedit adalah benar-benar pemilik IDP tersebut
    if ($current_idp->userid != $USER->id) {
        throw new moodle_exception('nopermission', 'debug', '', 'Anda tidak memiliki hak akses untuk mengubah IDP ini.');
    }
    
    // Pastikan data lama hanya bisa diedit jika statusnya masih Draft (0)
    if ($current_idp->status != 0) {
        throw new moodle_exception('nomodify', 'debug', '', 'IDP yang sudah berjalan atau selesai tidak dapat diubah lagi.');
    }

    // Ambil NIK atasan berdasarkan ID untuk ditampilkan di form autocomplete asli Anda
    $current_idp->nik_atasan = $DB->get_field('user', 'username', ['id' => $current_idp->atasan_id]);
    $mform->set_data($current_idp);
}

// 4. PROSES EKSEKUSI PENYIMPANAN DATA FORM
if ($mform->is_cancelled()) {
    // Jika batal, kembalikan ke halaman list utama (index.php)
    redirect(new moodle_url('/local/myidpebi/index.php'));
} else if ($fromform = $mform->get_data()) {
    $idp = new stdClass();
    $idp->nama_idp   = $fromform->nama_idp;
    $idp->userid     = $USER->id;
    $idp->mulai_date = $fromform->mulai_date;
    $idp->akhir_date = $fromform->akhir_date;
    $idp->status     = 0; // Default awal selalu 0 (Draft)

    // Mengonversi nama atasan (username/NIK) menjadi User ID Moodle sesuai referensi asli Anda
    $atasan_userid = $DB->get_field('user', 'id', ['username' => trim($fromform->nik_atasan)], IGNORE_MISSING);
    $idp->atasan_id = $atasan_userid ?: 0;

    if ($edit_idp_id) {
        // --- PROSES UPDATE (EDIT DATA LAMA) ---
        $idp->id = $edit_idp_id;
        $DB->update_record('local_myidpebi', $idp);
        
        // Redirect langsung ke halaman rincian program tersebut (view_details.php)
        $redirect_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $edit_idp_id]);
        redirect($redirect_url, 'IDP Program berhasil diperbarui!', null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        // --- PROSES INSERT (BUAT BARU) ---
        $idp->timecreated = time();
        // Tangkap ID record baru yang berhasil diciptakan oleh database
        $new_idp_id = $DB->insert_record('local_myidpebi', $idp);
        
        // 🚀 KUNCI UX BARU: Langsung dialihkan ke view_details membawa ID baru hasil create
        $redirect_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $new_idp_id]);
        redirect($redirect_url, 'IDP Program berhasil diajukan! Silakan lengkapi daftar rencana aktivitas Anda.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// 5. MEMULAI OUTPUT TAMPILAN HTML MOODLE
echo $OUTPUT->header();

echo '<div class="card shadow-sm">';
echo '  <div class="card-body">';
echo '      <h3 class="mb-4">' . ($edit_idp_id ? '<i class="fa fa-edit text-warning"></i> Edit Rencana IDP' : '<i class="fa fa-plus-circle text-primary"></i> Buat Pengajuan IDP Baru') . '</h3>';
// Tampilkan Form
$mform->display();
echo '  </div>';
echo '</div>';

echo $OUTPUT->footer();