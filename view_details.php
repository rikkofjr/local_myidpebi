<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/act_form.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Inisialisasi Parameter
$idp_id = optional_param('id', 0, PARAM_INT) ?: optional_param('idp_id', 0, PARAM_INT);
$edit_act_id = optional_param('edit_act', 0, PARAM_INT); // Parameter untuk deteksi mode edit

if (!$idp_id) {
    throw new moodle_exception('missingparameter', 'debug', '', 'ID');
}

// Ambil data IDP dan info atasan
$idp = $DB->get_record_sql("SELECT i.*, u.firstname, u.lastname 
                             FROM {local_myidpebi} i 
                             JOIN {user} u ON i.atasan_id = u.id 
                             WHERE i.id = ?", [$idp_id], MUST_EXIST);

$url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Rincian Aktivitas');
$PAGE->set_heading($idp->nama_idp);

// --- LOGIKA HAPUS AKTIVITAS ---
$delete_act = optional_param('delete_act', 0, PARAM_INT);
if ($delete_act && confirm_sesskey()) {
    $DB->delete_records('local_myidpebi_act', ['id' => $delete_act, 'idp_id' => $idp_id]);
    redirect($url, 'Aktivitas berhasil dihapus.');
}

// --- LOGIKA VERIFIKASI ATASAN ---
$verify = optional_param('verify', 0, PARAM_INT);
if ($verify && $USER->id == $idp->atasan_id && confirm_sesskey()) {
    $idp->status = 2; // Status 2 = Terverifikasi Selesai
    $DB->update_record('local_myidpebi', $idp);
    redirect($url, 'IDP telah berhasil diverifikasi selesai!');
}

echo $OUTPUT->header();

// --- TAMPILAN INFORMASI IDP ---
echo '<div class="card mb-4"><div class="card-body">';
echo '<h5 class="card-title">Informasi Program IDP</h5>';
echo '<div class="row">
        <div class="col-sm-3"><strong>Nama Program</strong></div><div class="col-sm-9">: '.$idp->nama_idp.'</div>
        <div class="col-sm-3"><strong>Atasan / Coach</strong></div><div class="col-sm-9">: '.$idp->firstname.' '.$idp->lastname.'</div>
        <div class="col-sm-3"><strong>Periode</strong></div><div class="col-sm-9">: '.userdate($idp->mulai_date, '%d %b %Y').' s/d '.userdate($idp->akhir_date, '%d %b %Y').'</div>
        <div class="col-sm-3"><strong>Status Akhir</strong></div><div class="col-sm-9">: '.($idp->status == 2 ? '<span class="badge badge-success">Selesai Diverifikasi</span>' : '<span class="badge badge-warning">Dalam Proses / Belum Diverifikasi</span>').'</div>
      </div>';
echo '</div></div>';

// Tombol Verifikasi untuk Atasan
if ($USER->id == $idp->atasan_id && $idp->status == 1) {
    $verify_url = new moodle_url($url, ['verify' => 1, 'sesskey' => sesskey()]);
    echo '<div class="alert alert-info d-flex justify-content-between align-items-center">
            <span>Apakah semua aktivitas di bawah ini sudah dilakukan oleh bawahan Anda?</span>
            <a href="'.$verify_url.'" class="btn btn-success" onclick="return confirm(\'Verifikasi semua aktivitas ini?\')">Verifikasi Selesai</a>
          </div>';
}

// --- LOGIKA FORM (INSERT & UPDATE) ---
if ($idp->status < 2 && $USER->id == $idp->userid) {
    $mform = new \local_myidpebi\forms\act_form($url->out(false));
    
    // Jika mode Edit, isi form dengan data lama
    if ($edit_act_id) {
        $existing_act = $DB->get_record('local_myidpebi_act', ['id' => $edit_act_id, 'idp_id' => $idp_id]);
        if ($existing_act) {
            $mform->set_data($existing_act);
            echo $OUTPUT->heading('Edit Aktivitas', 4);
        }
    } else {
        $mform->set_data(['idp_id' => $idp_id]);
        echo $OUTPUT->heading('Tambah Aktivitas Baru', 4);
    }

    if ($fromform = $mform->get_data()) {
        $act = new stdClass();
        $act->idp_id         = $idp_id;
        $act->jenis_kegiatan = $fromform->jenis_kegiatan;
        $act->nama_activity  = $fromform->nama_activity;
        $act->jumlah_jp      = $fromform->jumlah_jp;
        $act->waktu_teks     = $fromform->waktu_teks;

        if (!empty($fromform->id)) {
            // MODE UPDATE
            $act->id = $fromform->id;
            $DB->update_record('local_myidpebi_act', $act);
            $current_act_id = $act->id;
            $message = 'Aktivitas berhasil diperbarui!';
        } else {
            // MODE INSERT BARU
            $act->evidence_fileid = 0;
            $current_act_id = $DB->insert_record('local_myidpebi_act', $act);
            $message = 'Aktivitas berhasil ditambahkan!';
        }

        // Simpan Evidence (Berlaku untuk Insert maupun Update)
        $draftitemid = file_get_submitted_draft_itemid('evidence_file');
        if ($draftitemid) {
            file_save_draft_area_files($draftitemid, context_system::instance()->id, 'local_myidpebi', 'evidence', $current_act_id, ['subdirs' => 0]);
            $DB->set_field('local_myidpebi_act', 'evidence_fileid', $draftitemid, ['id' => $current_act_id]);
        }
        
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $mform->display();
}

// --- TABEL DAFTAR AKTIVITAS ---
echo '<hr class="my-4">';
echo $OUTPUT->heading('Rincian Aktivitas Terdaftar', 4);
$activities = $DB->get_records('local_myidpebi_act', ['idp_id' => $idp_id]);

if ($activities) {
    echo '<table class="table table-bordered table-striped"><thead><tr class="bg-light">
            <th>Jenis</th><th>Aktivitas</th><th>JP</th><th>Waktu</th><th>Evidence</th><th>Aksi</th>
          </tr></thead><tbody>';
    foreach ($activities as $a) {
        // Mendapatkan link file evidence
        $fs = get_file_storage();
        $files = $fs->get_area_files(context_system::instance()->id, 'local_myidpebi', 'evidence', $a->id, 'itemid, filepath, filename', false);
        $file_link = "-";
        if ($files) {
            $file = reset($files);
            $furl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
            $file_link = '<a href="'.$furl.'" target="_blank" class="btn btn-sm btn-info">Lihat File</a>';
        }

        echo "<tr>
                <td>{$a->jenis_kegiatan}</td>
                <td>{$a->nama_activity}</td>
                <td>{$a->jumlah_jp}</td>
                <td>{$a->waktu_teks}</td>
                <td>{$file_link}</td>
                <td>";
        
        // Aksi hanya muncul jika belum diverifikasi (status < 2) dan user adalah pemilik IDP
        if ($idp->status < 2 && $USER->id == $idp->userid) {
            $edit_url = new moodle_url($url, ['edit_act' => $a->id]);
            $del_url = new moodle_url($url, ['delete_act' => $a->id, 'sesskey' => sesskey()]);
            echo "<a href='{$edit_url}' class='btn btn-sm btn-warning mr-1'>Edit</a>";
            echo "<a href='{$del_url}' class='btn btn-sm btn-danger' onclick='return confirm(\"Hapus aktivitas ini?\")'>Hapus</a>";
        } else {
            echo '<span class="text-muted"><i class="fa fa-lock"></i> Terkunci</span>';
        }
        
        echo "</td></tr>";
    }
    echo '</tbody></table>';
} else {
    echo '<p class="text-muted">Belum ada rincian aktivitas.</p>';
}

echo '<div class="mt-4"><a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a></div>';
echo $OUTPUT->footer();