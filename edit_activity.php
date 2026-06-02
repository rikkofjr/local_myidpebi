<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/act_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$idp_id   = required_param('idp_id', PARAM_INT);
$act_id   = optional_param('act_id', 0, PARAM_INT);
$is_clone = optional_param('is_clone', 0, PARAM_INT); // Menangkap parameter mode duplikasi

$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);

if ($idp->userid != $USER->id || $idp->status >= 2) {
    throw new moodle_exception('nopermission');
}

$url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
if ($act_id) { $url->param('act_id', $act_id); }
if ($is_clone) { $url->param('is_clone', $is_clone); }

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title($act_id ? ($is_clone ? 'Duplikat Aktivitas' : 'Edit Aktivitas') : 'Tambah Aktivitas');

// 5. Breadcrumb Navigasi
$PAGE->navbar->add('Dashboard IDP', new moodle_url('/local/myidpebi/index.php'));
// Tambahkan link dinamis ke halaman View Details IDP yang sedang aktif saat ini
$view_details_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]);
$PAGE->navbar->add('Detail IDP (' . s($idp->nama_idp) . ')', $view_details_url);

// 🟢 SEBELUM LINE 29: Matikan deprecated strict notice PHP 8 secara sementara
$old_error_level = error_reporting();
error_reporting($old_error_level & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);

// 1. Inisialisasi Form TERLEBIH DAHULU agar elemen 'evidence_file' terdaftar di sistem Moodle Form
$mform = new \local_myidpebi\forms\act_form($url->out(false), [
    'status' => $idp->status,
    'act_id' => $act_id
]);

// 🟢 SETELAH LINE 32: Kembalikan level error asli Moodle setelah form selesai dimuat
error_reporting($old_error_level);

if ($act_id) {
    $activity = $DB->get_record('local_myidpebi_act', ['id' => $act_id], '*', MUST_EXIST);
    $form_data = new stdClass();
    
    // Logika Duplikasi: Jika mode clone aktif, kosongkan ID agar Moodle membuat baris baru
    if ($is_clone) {
        $form_data->id = 0;
    } else {
        $form_data->id = $activity->id;
    }
    
    $form_data->idp_id                          = $activity->idp_id;
    $form_data->aspek                           = $activity->aspek;
    $form_data->nilai_ipp                       = $activity->nilai_ipp;
    // FIXED: Menyesuaikan properti DB asal ke field form split (_performance & _kompetensi)
    $form_data->tuntutan_sekarang_performance   = $activity->tuntutan_sekarang_performance ?? '';
    $form_data->tuntutan_sekarang_kompetensi    = $activity->tuntutan_sekarang_kompetensi ?? '';
    $form_data->tuntutan_berikutnya_performance = $activity->tuntutan_berikutnya_performance ?? '';
    $form_data->tuntutan_berikutnya_kompetensi  = $activity->tuntutan_berikutnya_kompetensi ?? '';
    $form_data->tuntutan_lingkungan_performance = $activity->tuntutan_lingkungan_performance ?? '';
    $form_data->tuntutan_lingkungan_kompetensi  = $activity->tuntutan_lingkungan_kompetensi ?? '';

    $form_data->learning_activity            = (int)$activity->learning_activity;
    $form_data->nama_activity                   = $activity->nama_activity;
    $form_data->waktu_teks                      = $activity->waktu_teks;
    $form_data->jumlah_jp_perencanaan           = $activity->jumlah_jp_perencanaan;

    // Tampilkan angka JP lama di form jika sedang EDIT biasa. 
    // Jika sedang DUPLIKAT (is_clone), form JP tetap dikosongkan (0).
    if (!$is_clone) {
        $form_data->jumlah_jp_realisasi       = $activity->jumlah_jp_realisasi;
    } else {
        $form_data->jumlah_jp_realisasi       = 0;
    }
    
    // Siapkan draft file hanya jika dalam mode edit biasa (bukan kloning)
    if (!$is_clone && $idp->status == 1 && $activity->evidence_fileid) {
        $draftitemid = file_get_submitted_draft_itemid('evidence_file');
        file_prepare_draft_area(
            $draftitemid, 
            context_system::instance()->id, 
            'local_myidpebi', 
            'evidence', 
            $activity->id, 
            ['subdirs' => 0, 'maxbytes' => 2048*1024, 'maxfiles' => 1]
        );
        $form_data->evidence_file = $draftitemid;
    }
    
    $mform->set_data($form_data);
} else {
    $form_data = new stdClass();
    $form_data->idp_id = $idp_id;
    $mform->set_data($form_data);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]));
} else if ($data = $mform->get_data()) {
    $act = new stdClass();
    $act->idp_id = $idp_id;
    
    // Logika Rencana (Selalu tangkap data form untuk record baru/duplikat maupun edit)
    $act->aspek               = $data->aspek;
    $act->nilai_ipp           = $data->nilai_ipp;
    // FIXED: Mengambil data pecahan form untuk disimpan ke kolom DB masing-masing
    $act->tuntutan_sekarang_performance   = $data->tuntutan_sekarang_performance ?? '-';
    $act->tuntutan_sekarang_kompetensi    = $data->tuntutan_sekarang_kompetensi ?? '-';
    $act->tuntutan_berikutnya_performance = $data->tuntutan_berikutnya_performance ?? '-';
    $act->tuntutan_berikutnya_kompetensi  = $data->tuntutan_berikutnya_kompetensi ?? '-';
    $act->tuntutan_lingkungan_performance = $data->tuntutan_lingkungan_performance ?? '-';
    $act->tuntutan_lingkungan_kompetensi  = $data->tuntutan_lingkungan_kompetensi ?? '-';

    $act->learning_activity = (int)$data->learning_activity;
    $act->nama_activity       = $data->nama_activity;
    $act->waktu_teks          = $data->waktu_teks;
    $act->jumlah_jp_perencanaan          = $data->jumlah_jp_perencanaan;

    // Logika Realisasi JP (Hanya disimpan jika status dokumen berjalan dan bukan duplikasi baru)
    if ($idp->status == 1 && !$is_clone) {
        // Jika form mengirimkan angka JP, pakai angka tersebut. Jika kosong/null, gunakan fallback ke angka 0
        $input_jp = isset($data->jumlah_jp_realisasi) ? (int)$data->jumlah_jp_realisasi : 0;
        
        // JALUR EDIT: Jika karyawan mengedit data lama dan inputan baru di form kosong, pertahankan data JP lama dari DB
        if ($act_id && $input_jp === 0) {
            $act->jumlah_jp_realisasi = isset($activity->jumlah_jp_realisasi) ? (int)$activity->jumlah_jp_realisasi : 0;
        } else {
            $act->jumlah_jp_realisasi = $input_jp;
        }
    } else {
        // Jika masih berstatus Draft (0) atau sedang menduplikat baru, paksa JP ke 0
        $act->jumlah_jp_realisasi = 0;
    }

    // Eksekusi CRUD Database dengan dukungan Duplikasi
    if ($act_id && !$is_clone) {
        $act->id = $act_id;
        $DB->update_record('local_myidpebi_act', $act);
        $current_id = $act_id;
    } else {
        $act->evidence_fileid = 0;
        $act->deleted = 0;
        $current_id = $DB->insert_record('local_myidpebi_act', $act);
    }

    // --- MANAJEMEN PENYIMPANAN BERKAS FISIK (Hanya saat edit biasa di status berjalan) ---
    if ($idp->status == 1 && !$is_clone) {
        $draftitemid = isset($data->evidence_file) ? $data->evidence_file : 0;
        
        if ($draftitemid) {
            file_save_draft_area_files(
                $draftitemid, 
                context_system::instance()->id, 
                'local_myidpebi', 
                'evidence', 
                $current_id, 
                ['subdirs' => 0, 'maxbytes' => 2048*1024, 'maxfiles' => 1]
            );
            $DB->set_field('local_myidpebi_act', 'evidence_fileid', $draftitemid, ['id' => $current_id]);
        }
    }

    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]), "Aktivitas berhasil disimpan.");
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();