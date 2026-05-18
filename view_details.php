<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Inisialisasi Parameter
$idp_id = optional_param('id', 0, PARAM_INT);

if (!$idp_id) {
    throw new moodle_exception('missingparameter', 'debug', '', 'ID');
}

// 2. Ambil data IDP, info Pembimbing (atasan_id), serta Atasan Langsung dari Custom Profile Field
$idp = $DB->get_record_sql("SELECT i.*, 
                                   p.firstname as p_fname, p.lastname as p_lname, p.username as p_nik,
                                   k.firstname as k_fname, k.lastname as k_lname,
                                   al.id as atasan_langsung_id, al.firstname as al_fname, al.lastname as al_lname, al.username as al_nik,
                                   app.firstname as app_fname, app.lastname as app_lname, app.username as app_nik,
                                   vif.firstname as vif_fname, vif.lastname as vif_lname, vif.username as vif_nik
                             FROM {local_myidpebi} i 
                             JOIN {user} k ON i.userid = k.id
                             JOIN {user} p ON i.atasan_id = p.id 
                             /* Join custom profile field karyawan untuk mencari NIK Atasan Langsung */
                             LEFT JOIN {user_info_data} uid ON uid.userid = k.id
                             LEFT JOIN {user_info_field} uif ON uid.fieldid = uif.id AND uif.shortname = 'atasan_langsung'
                             /* Map NIK tersebut ke user Moodle asli */
                             LEFT JOIN {user} al ON al.username = uid.data
                             LEFT JOIN {user} app ON i.approved_by = app.id
                             LEFT JOIN {user} vif ON i.verified_by = vif.id
                             WHERE i.id = ?", [$idp_id]);

if (!$idp) {
    die("Error Database: Data IDP tidak ditemukan.");
}

// 3. Hitung Total JP dari aktivitas yang sudah diverifikasi (tidak didelete)
$total_jp_verified = 0;
if ($idp->status == 2) {
    $total_jp_verified = $DB->get_field_sql("SELECT SUM(jumlah_jp) 
                                              FROM {local_myidpebi_act} 
                                              WHERE idp_id = ? AND deleted = 0", [$idp_id]) ?: 0;
}

// 4. Konfigurasi Halaman & URL
$url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Rincian Aktivitas IDP');
$PAGE->set_heading($idp->nama_idp);

// 5. Breadcrumb Navigasi
$PAGE->navbar->add('Dashboard IDP', new moodle_url('/local/myidpebi/index.php'));
$PAGE->navbar->add('Rincian Aktivitas');

// --- LOGIKA ACTION ---
$delete_act = optional_param('delete_act', 0, PARAM_INT);
if ($delete_act && confirm_sesskey()) {
    $DB->set_field('local_myidpebi_act', 'deleted', 1, ['id' => $delete_act, 'idp_id' => $idp_id]);
    redirect($url, 'Aktivitas berhasil dibatalkan.');
}

// Validasi Hak Otorisasi "ATAU" (Pembimbing Terpilih ATAU Atasan Langsung Profil)
$is_pembimbing = ($USER->id == $idp->atasan_id);
$is_atasan_langsung = (!empty($idp->atasan_langsung_id) && $USER->id == $idp->atasan_langsung_id);

if (optional_param('approve', 0, PARAM_INT) && ($is_pembimbing || $is_atasan_langsung) && confirm_sesskey()) {
    $idp->status = 1; 
    $idp->approved_by = $USER->id; // Log Audit Trail
    $DB->update_record('local_myidpebi', $idp);
    redirect($url, 'IDP telah disetujui.');
}

if (optional_param('verify', 0, PARAM_INT) && ($is_pembimbing || $is_atasan_langsung) && confirm_sesskey()) {
    $idp->status = 2; 
    $idp->verified_by = $USER->id; // Log Audit Trail
    $DB->update_record('local_myidpebi', $idp);
    redirect($url, 'IDP telah diverifikasi selesai.');
}

echo $OUTPUT->header();

// --- TAMPILAN DETAIL INFORMASI IDP (100% Sesuai UI/UX Anda) ---
echo '<div class="card mb-4 border-left-primary shadow-sm"><div class="card-body">';
$status_info = local_myidpebi_get_status_info($idp->status);

echo "<h4 class='text-primary mb-3'><i class='fa fa-folder-open'></i> {$idp->nama_idp}</h4>";

echo '<div class="row">';
echo '  <div class="col-md-12">';
echo '      <div class="mb-2"><strong>Status:</strong><br>' . $status_info->badge . '</div>';
echo '      <div class="mb-2"><strong>Pembimbing / Coach:</strong><br>' . ($idp->p_nik ?: '-') . ' - ' . $idp->p_fname . ' ' . $idp->p_lname . '</div>';
echo '      <div class="mb-2"><strong>Atasan Langsung (Sistem):</strong><br>' . ($idp->al_nik ?: '-') . ' - ' . $idp->al_fname . ' ' . $idp->al_lname . '</div>';
echo '      <div class="mb-2"><strong>Periode Program:</strong><br>' . userdate($idp->mulai_date, '%d %b %Y') . ' s/d ' . userdate($idp->akhir_date, '%d %b %Y') . '</div>';
echo '      <div class="mb-2 ' . ($idp->status == 2 ? 'text-success' : 'text-muted') . '">';
echo '          <strong>Total JP Terverifikasi:</strong><br>';
echo '          <span class="h5 font-weight-bold">' . $total_jp_verified . ' JP</span>';
if ($idp->status < 2) {
    echo ' <small>(Akan dihitung setelah verifikasi selesai)</small>';
}
echo '      </div>';

// Menampilkan Riwayat Siapa yang melakukan klik persetujuan nyata (Audit Log UI)
if ($idp->status > 0) {
    echo '<div class="mt-3 p-2 bg-light border rounded small">';
    echo '  <h6 class="text-secondary font-weight-bold mb-1"><i class="fa fa-history"></i> Riwayat Persetujuan Sistem:</h6>';
    if (!empty($idp->approved_by)) {
        echo "  <div class='text-muted'>• Disetujui oleh: <strong>{$idp->app_nik} - {$idp->app_fname} {$idp->app_lname}</strong></div>";
    }
    if (!empty($idp->verified_by)) {
        echo "  <div class='text-muted'>• Diverifikasi oleh: <strong>{$idp->vif_nik} - {$idp->vif_fname} {$idp->vif_lname}</strong></div>";
    }
    echo '</div>';
}

echo '</div>';

// Tombol Aksi Atasan/Pembimbing Lintas Otorisasi dengan Konfirmasi
if ($is_pembimbing || $is_atasan_langsung) {
    echo '<div class="mt-4 p-3 border-top bg-light text-right">';
    if ($idp->status == 0) {
        $approve_url = new moodle_url($url, ['approve' => 1, 'sesskey' => sesskey()]);
        $confirm_msg = "Apakah Anda yakin ingin MENYETUJUI program IDP ini untuk segera dilaksanakan?";
        echo '<a href="'.$approve_url.'" class="btn btn-primary" onclick="return confirm(\''.$confirm_msg.'\')"><i class="fa fa-check"></i> Setujui Program</a>';
    } else if ($idp->status == 1) {
        $verify_url = new moodle_url($url, ['verify' => 1, 'sesskey' => sesskey()]);
        $confirm_msg = "Apakah Anda yakin ingin memverifikasi bahwa program IDP ini telah SELESAI?";
        echo '<a href="'.$verify_url.'" class="btn btn-success" onclick="return confirm(\''.$confirm_msg.'\')"><i class="fa fa-flag-checkered"></i> Verifikasi Selesai</a>';
    }
    echo '</div>';
}
echo '</div></div>';

// --- DAFTAR AKTIVITAS ---
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '  <h4 class="m-0">Rincian Aktivitas</h4>';

if ($idp->status < 2 && $USER->id == $idp->userid) {
    // $add_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
    // // Jika status = 1 (Proses), beri tanda act_id = -1 ke URL untuk memberi tahu halaman edit_activity agar membuka gerbang JP
    // if ($idp->status == 1) {
    //     $add_url->param('act_id', -1);
    // }
    // echo '<a href="'.$add_url.'" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Aktivitas</a>';
    
    //gunakan dibawah ini
    $add_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
    echo '<a href="'.$add_url.'" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Aktivitas</a>';
}
echo '</div>';

$activities = $DB->get_records('local_myidpebi_act', ['idp_id' => $idp_id], 'deleted ASC, id ASC');

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover shadow-sm">';
echo '  <thead class="thead-light text-center">
            <tr>
                <th>Jenis</th>
                <th>Aktivitas</th>
                <th>JP</th>
                <th>Waktu</th>
                <th>Evidence</th>
                <th width="120">Aksi</th>
            </tr>
        </thead>
        <tbody>';

if ($activities) {
    foreach ($activities as $a) {
        $is_deleted = ($a->deleted == 1);
        $row_class = $is_deleted ? 'table-secondary text-muted' : '';
        $text_style = $is_deleted ? 'style="text-decoration: line-through;"' : '';
        
        $file_link = "-";
        if (!$is_deleted) {
            $fs = get_file_storage();
            $files = $fs->get_area_files(context_system::instance()->id, 'local_myidpebi', 'evidence', $a->id, 'itemid, filepath, filename', false);
            if ($files) {
                $file = reset($files);
                $furl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
                $file_link = '<a href="'.$furl.'" target="_blank" class="btn btn-sm btn-info"><i class="fa fa-download"></i> File</a>';
            }
        }

        echo "<tr class='{$row_class}'>";
        echo "  <td {$text_style} class='text-center'>{$a->jenis_kegiatan}</td>";
        echo "  <td {$text_style}>{$a->nama_activity}</td>";
        echo "  <td {$text_style} class='text-center'>{$a->jumlah_jp}</td>";
        echo "  <td {$text_style}>{$a->waktu_teks}</td>";
        echo "  <td class='text-center'>{$file_link}</td>";
        echo "  <td class='text-center'>";
        
        if ($idp->status < 2 && $USER->id == $idp->userid && !$is_deleted) {
            $edit_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id, 'act_id' => $a->id]);
            $del_url = new moodle_url($url, ['delete_act' => $a->id, 'sesskey' => sesskey()]);
            echo "<a href='{$edit_url}' class='btn btn-sm btn-warning mr-1'><i class='fa fa-edit'></i></a>";
            echo "<a href='{$del_url}' class='btn btn-sm btn-danger' onclick='return confirm(\"Batalkan aktivitas ini?\")'><i class='fa fa-trash'></i></a>";
        } else if ($is_deleted) {
            echo '<span class="badge badge-secondary">Dibatalkan</span>';
        } else {
            echo '<i class="fa fa-lock text-muted"></i>';
        }
        echo "  </td>";
        echo "</tr>";
    }
} else {
    echo '<tr><td colspan="6" class="text-center text-muted p-4">Belum ada rincian aktivitas.</td></tr>';
}

echo '  </tbody></table></div>';
echo $OUTPUT->footer();