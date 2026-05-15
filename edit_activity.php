<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/act_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$idp_id = required_param('idp_id', PARAM_INT);
$act_id = optional_param('act_id', 0, PARAM_INT);

$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);

if ($idp->userid != $USER->id || $idp->status >= 2) {
    throw new moodle_exception('nopermission');
}

$url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
if ($act_id) { $url->param('act_id', $act_id); }

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title($act_id ? 'Edit Aktivitas' : 'Tambah Aktivitas');

// Inisialisasi Form
// Kita kirimkan act_id ke form untuk logika freeze yang lebih cerdas
$mform = new \local_myidpebi\forms\act_form($url->out(false), [
    'status' => $idp->status,
    'act_id' => $act_id
]);

if ($act_id) {
    $existing_act = $DB->get_record('local_myidpebi_act', ['id' => $act_id, 'idp_id' => $idp_id], '*', MUST_EXIST);
    $mform->set_data($existing_act);
} else {
    $mform->set_data(['idp_id' => $idp_id]);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]));
} else if ($data = $mform->get_data()) {
    $act = new stdClass();
    $act->idp_id = $idp_id;
    
    // Logika Simpan: Jika Tambah Baru, ambil field rencana. Jika Edit, field rencana tidak berubah (karena di freeze)
    if (!$act_id || $idp->status == 0) {
        $act->jenis_kegiatan = $data->jenis_kegiatan;
        $act->nama_activity  = $data->nama_activity;
        $act->waktu_teks     = $data->waktu_teks;
    }

    if ($idp->status == 1) {
        $act->jumlah_jp = $data->jumlah_jp;
    }

    if ($act_id) {
        $act->id = $act_id;
        $DB->update_record('local_myidpebi_act', $act);
        $current_id = $act_id;
    } else {
        $act->jumlah_jp = 0;
        $act->evidence_fileid = 0;
        $act->deleted = 0;
        $current_id = $DB->insert_record('local_myidpebi_act', $act);
    }

    // Evidence
    if ($idp->status == 1) {
        $draftitemid = file_get_submitted_draft_itemid('evidence_file');
        if ($draftitemid) {
            file_save_draft_area_files($draftitemid, context_system::instance()->id, 'local_myidpebi', 'evidence', $current_id, ['subdirs' => 0]);
            $DB->set_field('local_myidpebi_act', 'evidence_fileid', $draftitemid, ['id' => $current_id]);
        }
    }

    redirect(new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]), "Tersimpan.");
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();