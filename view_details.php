<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$idp_id = optional_param('id', 0, PARAM_INT);
if (!$idp_id) {
    throw new moodle_exception('missingparameter', 'debug', '', 'ID');
}

$idp = $DB->get_record_sql("SELECT i.*, u.firstname, u.lastname 
                             FROM {local_myidpebi} i 
                             JOIN {user} u ON i.atasan_id = u.id 
                             WHERE i.id = ?", [$idp_id], MUST_EXIST);

$url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Rincian Aktivitas');
$PAGE->set_heading($idp->nama_idp);

// Logika Soft Delete
$delete_act = optional_param('delete_act', 0, PARAM_INT);
if ($delete_act && confirm_sesskey()) {
    $DB->set_field('local_myidpebi_act', 'deleted', 1, ['id' => $delete_act, 'idp_id' => $idp_id]);
    redirect($url, 'Aktivitas telah dibatalkan.');
}

// Logika Approval & Verifikasi
if ($approve = optional_param('approve', 0, PARAM_INT) && $USER->id == $idp->atasan_id && confirm_sesskey()) {
    $idp->status = 1; 
    $DB->update_record('local_myidpebi', $idp);
    redirect($url, 'IDP disetujui!');
}

if ($verify = optional_param('verify', 0, PARAM_INT) && $USER->id == $idp->atasan_id && confirm_sesskey()) {
    $idp->status = 2; 
    $DB->update_record('local_myidpebi', $idp);
    redirect($url, 'IDP diverifikasi selesai!');
}

echo $OUTPUT->header();

// Info IDP
echo '<div class="card mb-4 shadow-sm"><div class="card-body">';
$status_info = local_myidpebi_get_status_info($idp->status);
echo "<h5>{$idp->nama_idp}</h5>";
echo "<p>Status: {$status_info->badge}</p>";

if ($USER->id == $idp->atasan_id) {
    if ($idp->status == 0) {
        echo '<a href="'.new moodle_url($url, ['approve'=>1, 'sesskey'=>sesskey()]).'" class="btn btn-primary">Setujui Program</a>';
    } else if ($idp->status == 1) {
        echo '<a href="'.new moodle_url($url, ['verify'=>1, 'sesskey'=>sesskey()]).'" class="btn btn-success">Verifikasi Selesai</a>';
    }
}
echo '</div></div>';

// Judul & Tombol Tambah (Tetap Muncul di Status 0 dan 1)
echo '<div class="d-flex justify-content-between align-items-center mb-2">';
echo '<h4>Daftar Aktivitas</h4>';
if ($idp->status < 2 && $USER->id == $idp->userid) {
    $add_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
    echo '<a href="'.$add_url.'" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Aktivitas</a>';
}
echo '</div>';

// Load aktivitas: Urutkan agar yang deleted tampil di paling bawah
$activities = $DB->get_records('local_myidpebi_act', ['idp_id' => $idp_id], 'deleted ASC, id ASC');

echo '<table class="table table-bordered">
        <thead><tr class="bg-light"><th>Jenis</th><th>Aktivitas</th><th>JP</th><th>Waktu</th><th>Evidence</th><th>Aksi</th></tr></thead>
        <tbody>';

if ($activities) {
    foreach ($activities as $a) {
        $is_deleted = ($a->deleted == 1);
        $style = $is_deleted ? 'class="table-secondary text-muted" style="text-decoration: line-through;"' : '';
        
        $file_link = "-";
        if (!$is_deleted) {
            $fs = get_file_storage();
            $files = $fs->get_area_files(context_system::instance()->id, 'local_myidpebi', 'evidence', $a->id, 'itemid', false);
            if ($files) {
                $file = reset($files);
                $furl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                $file_link = '<a href="'.$furl.'" target="_blank" class="btn btn-sm btn-info">Lihat File</a>';
            }
        }

        echo "<tr ".($is_deleted ? 'class="table-secondary"' : '').">
                <td $style>{$a->jenis_kegiatan}</td>
                <td $style>{$a->nama_activity}</td>
                <td $style>{$a->jumlah_jp}</td>
                <td $style>{$a->waktu_teks}</td>
                <td class='text-center'>{$file_link}</td>
                <td class='text-center'>";
        
        if ($idp->status < 2 && $USER->id == $idp->userid && !$is_deleted) {
            $edit_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id, 'act_id' => $a->id]);
            $del_url = new moodle_url($url, ['delete_act' => $a->id, 'sesskey' => sesskey()]);
            echo "<a href='{$edit_url}' class='btn btn-sm btn-warning mr-1'><i class='fa fa-edit'></i></a>";
            echo "<a href='{$del_url}' class='btn btn-sm btn-danger' onclick='return confirm(\"Batalkan aktivitas ini?\")'><i class='fa fa-trash'></i></a>";
        } else if ($is_deleted) {
            echo '<span class="badge badge-secondary">Dibatalkan</span>';
        }
        echo "</td></tr>";
    }
}
echo '</tbody></table>';

echo $OUTPUT->footer();