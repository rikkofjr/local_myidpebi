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


// =========================================================================
// ADDED: UI PROGRESS TRACKER ALUR STATUS IDP (TEKS SINKRON DENGAN LIB.PHP)
// =========================================================================
$tracker_width = ($idp->status == 0) ? '0%' : (($idp->status == 1) ? '50%' : '100%');
$bg_step1 = ($idp->status >= 0) ? '#28a745' : '#ffffff';
$color_step1 = ($idp->status >= 0) ? '#ffffff' : '#6c757d';
$icon_step1 = ($idp->status > 0) ? 'fa-check' : 'fa-pencil-square-out';
$class_step1 = ($idp->status == 0) ? 'text-primary' : 'text-success';

$bg_step2 = ($idp->status >= 1) ? '#28a745' : '#ffffff';
$color_step2 = ($idp->status >= 1) ? '#ffffff' : '#6c757d';
$border_step2 = ($idp->status == 1) ? '#ffc107 !important' : '#dee2e6';
$icon_step2 = ($idp->status > 1) ? 'fa-check' : 'fa-play-circle';
$class_step2 = ($idp->status == 1) ? 'text-primary font-weight-bold' : (($idp->status > 1) ? 'text-success' : 'text-muted');

$bg_step3 = ($idp->status == 2) ? '#28a745' : '#ffffff';
$color_step3 = ($idp->status == 2) ? '#ffffff' : '#6c757d';
$border_step3 = ($idp->status == 2) ? '#28a745 !important' : '#dee2e6';
$class_step3 = ($idp->status == 2) ? 'text-success' : 'text-muted';

echo '<div class="card mb-3 shadow-sm border-0">';
echo '    <div class="card-body bg-light rounded p-4">';
echo '        <h6 class="text-secondary font-weight-bold mb-3">';
echo '            <i class="fa fa-map-signs"></i> Alur Tahapan Dokumen IDP Anda:';
echo '        </h6>';
echo '        <div class="d-flex justify-content-between align-items-center position-relative flex-column flex-md-row">';
echo '            <div class="position-absolute d-none d-md-block" style="top: 25px; left: 10%; right: 10%; height: 4px; background-color: #e9ecef; z-index: 1;">';
echo '                <div style="width: '.$tracker_width.'; height: 100%; background-color: #28a745; transition: width 0.5s ease;"></div>';
echo '            </div>';
echo '            <div class="text-center position-relative mb-3 mb-md-0" style="z-index: 2; flex: 1;">';
echo '                <div class="rounded-circle d-inline-flex align-items-center justify-content-center border border-2 shadow-sm" style="width: 50px; height: 50px; background-color: '.$bg_step1.'; color: '.$color_step1.';">';
echo '                    <i class="fa '.$icon_step1.' fa-lg"></i>';
echo '                </div>';
echo '                <h6 class="mt-2 mb-1 font-weight-bold '.$class_step1.'">1. Perencanaan</h6>';
echo '                <small class="text-muted d-block" style="line-height: 1.2; font-size: 11px;">';
if ($idp->status == 0) {
    echo '                    <span class="text-warning font-weight-bold">Buatlah aktivitas perencanaan :</span><br>Jika sudah Ok, minta persetujuan dari Pembimbing.';
} else {
    echo '                    Rencana IDP anda telah disetujui.';
}
echo '                </small>';
echo '            </div>';
echo '            <div class="text-center position-relative mb-3 mb-md-0" style="z-index: 2; flex: 1;">';
echo '                <div class="rounded-circle d-inline-flex align-items-center justify-content-center border border-2 shadow-sm" style="width: 50px; height: 50px; background-color: '.$bg_step2.'; color: '.$color_step2.'; border-color: '.$border_step2.';">';
echo '                    <i class="fa '.$icon_step2.' fa-lg"></i>';
echo '                </div>';
echo '                <h6 class="mt-2 mb-1 font-weight-bold '.$class_step2.'">2. Realisasi & JP</h6>';
echo '                <small class="text-muted d-block" style="line-height: 1.2; font-size: 11px;">';
if ($idp->status == 0) {
    echo '                    Kunci pasif. Terbuka otomatis setelah rencana disetujui Atasan.';
} else if ($idp->status == 1) {
    echo '                    <span class="text-warning font-weight-bold">Langkah Anda Sekarang:</span>
                                <br>Laksanakan aktivitas, isi input Jam Pelajaran (JP), dan upload evidence dari aktivitas tersebut. <br/>Jika sudah terisi lakukan self assement ';
} else {
    echo '                    Realisasi JP & Evidence sudah di verifikasi atasan.';
}
echo '                </small>';
echo '            </div>';
echo '            <div class="text-center position-relative" style="z-index: 2; flex: 1;">';
echo '                <div class="rounded-circle d-inline-flex align-items-center justify-content-center border border-2 shadow-sm" style="width: 50px; height: 50px; background-color: '.$bg_step3.'; color: '.$color_step3.'; border-color: '.$border_step3.';">';
echo '                    <i class="fa fa-trophy fa-lg"></i>';
echo '                </div>';
echo '                <h6 class="mt-2 mb-1 font-weight-bold '.$class_step3.'">3. Validasi Tuntas</h6>';
echo '                <small class="text-muted d-block" style="line-height: 1.2; font-size: 11px;">';
if ($idp->status < 1) {
    echo '                    Kunci pasif.';
} else if ($idp->status == 1) {
    echo '                    <span class="text-info font-weight-bold">Tahap Berikutnya:</span><br>Setelah semua aktivitas direalisasikan. Pembimbing akan melakukan verifikasi';
} else {
    echo '                    <span class="text-success font-weight-bold"><i class="fa fa-lock"></i> Selesai:</span><br>Seluruh program pengembangan IDP selesai & terkunci.';
}
echo '                </small>';
echo '            </div>';
echo '        </div>';
echo '    </div>';
echo '</div>';


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



// =========================================================================
// 🟢 LOGIKA TAMPILAN EVALUASI MANDIRI (SELF-ASSESSMENT)
// =========================================================================
$is_owner = ($USER->id == $idp->userid);

if ((float)$idp->skor_efektivitas > 0) {
    // 1. Jika kuesioner SUDAH DIISI, tampilkan card hasil skornya
    echo '<div class="card mb-4 border-success">';
    echo '  <div class="card-header bg-success text-white"><strong><i class="fa fa-check-circle"></i> Hasil Self-Assessment</strong></div>';
    echo '  <div class="card-body">';
    echo '      <h4 class="card-title text-success">Skor Efektivitas IDP: ' . number_format($idp->skor_efektivitas, 2) . '%</h4>';
    echo '      <p class="card-text"><strong>Testimoni / Kesimpulan Karyawan:</strong><br>' . s($idp->kesimpulan_karyawan) . '</p>';
    echo '  </div>';
    echo '</div>';
} else if ($idp->status == 1 && $is_owner) {
    // 2. Jika BELUM DIISI, status sedang Running (1), dan dia pemiliknya, tampilkan Tombol Pemicu
    $assessment_url = new moodle_url('/local/myidpebi/assessment.php', ['id' => $idp_id]);
    echo '<div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">';
    echo '  <div>';
    echo '      <h5><i class="fa fa-exclamation-triangle"></i> Self Assement IDP</h5>';
    echo '      <p class="mb-0">Lakukan self assement Jika anda telah mengisi semua rincian aktifitas dan telah mengkonsultasikan dengan pembimbing anda.</p>';
    echo '  </div>';
    echo '  <a href="' . $assessment_url . '" class="btn btn-success text-white">';
    echo '      <i class="fa fa-pencil-square-o"></i> Isi Evaluasi Efektivitas IDP';
    echo '  </a>';
    echo '</div>';
}
// =========================================================================

//wait
// Menampilkan Riwayat Siapa yang melakukan klik persetujuan nyata (Audit Log UI)
if ($idp->status > 0) {
    echo '<div class="mt-3 p-2 bg-light border rounded small">';
    echo '  <h6 class="text-secondary font-weight-bold mb-1"><i class="fa fa-history"></i> Riwayat Persetujuan Sistem:</h6>';
    
    if (!empty($idp->approved_by)) {
        $status1_info = local_myidpebi_get_status_info(1);
        echo "  <div class='text-muted'>• Status [<strong>{$status1_info->text}</strong>] oleh: <strong>{$idp->app_nik} - {$idp->app_fname} {$idp->app_lname}</strong></div>";
    }
    
    if (!empty($idp->verified_by)) {
        $status2_info = local_myidpebi_get_status_info(2);
        echo "  <div class='text-muted'>• Status [<strong>{$status2_info->text}</strong>] oleh: <strong>{$idp->vif_nik} - {$idp->vif_fname} {$idp->vif_lname}</strong></div>";
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
echo '<p class="text-muted"><small>* Klik tombol pencil untuk melakukan perubahan aktifitas</small></p>';


$activities = $DB->get_records('local_myidpebi_act', ['idp_id' => $idp_id], 'deleted ASC, id ASC');

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover shadow-sm">';
echo '  <thead class="thead-light text-center">
            <tr>
                <th>Aspek</th>
                <th>Nilai</th>
                <th>Tuntutan Pada Posisi <br/> Sekarang</th>
                <th>Tuntutan Pada Posisi <br/> Berikutnya</th>
                <th>Tuntutan Karena <br/> Lingkungan</th>
                <th>Area Pengembangan yang perlu dikembangkan</th>
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
        echo "  <td {$text_style}>{$a->aspek}</td>";
        echo "  <td {$text_style}>{$a->nilai_ipp}</td>";

        // =========================================================================
        // KONDISIONAL KOTAK TEKS PANJANG (DIKUNCI DENGAN SCROLLBOX INTERNAL AGAR TIDAK MELAR)
        // =========================================================================
        $textarea_box_style = 'style="max-height: 95px; overflow-y: auto; font-size: 15px; line-height: 1.4; padding: 6px; background: rgba(0,0,0,0.03); border-radius: 4px; border: 1px solid #e9ecef; white-space: pre-wrap; min-width: 150px;"';

        echo "  <td class='align-middle'><div {$textarea_box_style}>" . s($a->tuntutan_sekarang) . "</div></td>";
        echo "  <td class='align-middle'><div {$textarea_box_style}>" . s($a->tuntutan_berikutnya) . "</div></td>";
        echo "  <td class='align-middle'><div {$textarea_box_style}>" . s($a->tuntutan_lingkungan) . "</div></td>";
        echo "  <td class='align-middle'><div {$textarea_box_style}>" . s($a->area_pengembangan) . "</div></td>";

        // echo "  <td {$text_style}>{$a->tuntutan_sekarang}</td>";
        // echo "  <td {$text_style}>{$a->tuntutan_berikutnya}</td>";
        // echo "  <td {$text_style}>{$a->tuntutan_lingkungan}</td>";
        // echo "  <td {$text_style}>{$a->area_pengembangan}</td>";


        echo "  <td {$text_style} class='text-center'>{$a->jenis_kegiatan}</td>";
        echo "  <td {$text_style}>{$a->nama_activity}</td>";
        echo "  <td {$text_style} class='text-center'>{$a->jumlah_jp}</td>";
        echo "  <td {$text_style}>{$a->waktu_teks}</td>";
        echo "  <td class='text-center'>{$file_link}</td>";
        echo "  <td class='text-center'>";
        
        if ($idp->status < 2 && $USER->id == $idp->userid && !$is_deleted) {
            $edit_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id, 'act_id' => $a->id]);
            $del_url = new moodle_url($url, ['delete_act' => $a->id, 'sesskey' => sesskey()]);
            $clone_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id, 'act_id' => $a->id, 'is_clone' => 1]);

            echo "<a href='{$edit_url}' class='btn btn-sm btn-warning mr-1' title='Edit'><i class='fa fa-edit'></i></a>";
            echo "<a href='{$del_url}' class='btn btn-sm btn-danger' title='Delete/Batal' onclick='return confirm(\"Batalkan aktivitas ini?\")'><i class='fa fa-trash'></i></a>";
            echo "<a href='{$clone_url}' class='btn btn-sm btn-info mr-1' title='Duplikat' onclick='return confirm(\"Apakah Anda yakin ingin menduplikat/mengkloning aktivitas ini?\")'><i class='fa fa-clone'></i></a>";

        } else if ($is_deleted) {
            echo '<span class="badge badge-secondary">Dibatalkan</span>';
        } else {
            echo '<i class="fa fa-lock text-muted"></i>';
        }
        echo "  </td>";
        echo "</tr>";
    }
} else {
    echo '<tr><td colspan="12" class="text-center text-muted p-4">Belum ada rincian aktivitas.</td></tr>';
}

echo '  </tbody></table></div>';
echo $OUTPUT->footer();