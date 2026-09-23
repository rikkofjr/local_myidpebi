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

//Ambil Profile Field Atasan
$shortname_config = get_config('local_myidpebi', 'profile_field_atasan');
$profile_field_shortname = !empty($shortname_config) ? $shortname_config : 'atasan_langsung';

// 🟢 TAMBAHKAN BARIS INI: Ambil target identitas (username atau email atau lainnya)
$identity_config = get_config('local_myidpebi', 'identity_field_atasan');
$field_target = !empty($identity_config) ? $identity_config : 'username';

// 2. Ambil data IDP dengan LEFT JOIN Atasan Langsung yang dinamis
$idp = $DB->get_record_sql("SELECT i.*, 
                                   p.firstname as p_fname, p.lastname as p_lname, p.username as p_nik,
                                   k.firstname as k_fname, k.lastname as k_lname,
                                   al.id as atasan_langsung_id, al.firstname as al_fname, al.lastname as al_lname, al.username as al_nik, al.email as al_email,
                                   app.firstname as app_fname, app.lastname as app_lname, app.username as app_nik,
                                   vif.firstname as vif_fname, vif.lastname as vif_lname, vif.username as vif_nik
                             FROM {local_myidpebi} i 
                             JOIN {user} k ON i.userid = k.id
                             JOIN {user} p ON i.atasan_id = p.id 
                             JOIN {user_info_field} uif ON uif.shortname = :profile_field_atasan
                             JOIN {user_info_data} uid ON uid.fieldid = uif.id AND uid.userid = k.id
                             
                             -- 🟢 PERBAIKAN DI SINI: al.username diganti menggunakan variabel $field_target
                             LEFT JOIN {user} al ON al.{$field_target} = uid.data
                             
                             LEFT JOIN {user} app ON i.approved_by = app.id
                             LEFT JOIN {user} vif ON i.verified_by = vif.id
                             WHERE i.id = :idp_id", 
                             [
                                 'idp_id' => $idp_id, 
                                 'profile_field_atasan' => $profile_field_shortname
                             ], 
                             MUST_EXIST);

// =========================================================================
// SEMENTARA: MASUKKAN KODE DEBUG INI UNTUK MELIHAT ISI DATA ASLI DATABASE
// =========================================================================
// echo "<div style='background:#fff; color:#000; padding:20px; z-index:9999; position:relative;'>";
// echo "<h3>Hasil Pengambilan Data Objek IDP:</h3>";
// echo "<pre>";
// print_r($idp);
// echo "</pre>";
// echo "</div>";
// die(); // Menghentikan halaman agar tidak me-render HTML bawah

if (!$idp) {
    die("Error Database: Data IDP tidak ditemukan.");
}

// 3.a Hitung Total JP 
//Total JP rencana
$total_jp_rencana = $DB->get_field_sql("SELECT SUM(jumlah_jp_perencanaan) 
                                              FROM {local_myidpebi_act} 
                                              WHERE idp_id = ? AND deleted = 0", [$idp_id]) ?: 0;

//Total JP Realisasi
$total_jp_verified = 0;
if ($idp->status == 3) {
    $total_jp_verified = $DB->get_field_sql("SELECT SUM(jumlah_jp_realisasi) 
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
$can_reset = has_capability('local/myidpebi:reset_idp', $PAGE->context);
$is_pembimbing = ($USER->id == $idp->atasan_id);
$is_atasan_langsung = (!empty($idp->atasan_langsung_id) && $USER->id == $idp->atasan_langsung_id);

// 1. LOGIKA AKSI APPROVAL (Status 0 -> 1)
if (optional_param('approve', 0, PARAM_INT) && ($is_pembimbing || $is_atasan_langsung) && confirm_sesskey()) {

    $oldstatus = $idp->status; // Simpan status lama (0)
    $newstatus = 1;            // Status baru (1)

    // Membuat objek bersih baru agar tidak bentrok dengan data query SQL ($idp)
    $upd = new stdClass();
    $upd->id = $idp_id;
    $upd->status = 1; 
    $upd->approved_by = $USER->id;
    $upd->timeapproved = time();
    
    $DB->update_record('local_myidpebi', $upd);

    //Insert ke dalam log IDP
    local_myidpebi_add_log(
        $idp_id,
        $USER->id,
        'approve_atasan',
        $oldstatus,
        $newstatus,
        'Program IDP disetujui oleh Atasan/Pembimbing.');

    // Insert ke dalam log Moodle
    $event = \local_myidpebi\event\idp_status_changed::create([
        'objectid' => $idp_id,
        'userid'   => $USER->id,
        'context'  => context_system::instance(),
        'other'    => [
            'status_code' => 1 
        ]
    ]);
    $event->trigger();

    // redirect halaman
    redirect($url, 'IDP telah disetujui.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// 2. LOGIKA AKSI VERIFIKASI SELESAI (Status 1 -> 2)
if (optional_param('verify', 0, PARAM_INT) && ($is_pembimbing || $is_atasan_langsung) && confirm_sesskey()) {

    $oldstatus = $idp->status; // Simpan status lama
    $newstatus = 2;            // Status baru 

    $upd = new stdClass();
    $upd->id = $idp_id;
    $upd->status = $newstatus; 
    $upd->verified_by = $USER->id;
    $upd->timeverified = time();
    
    $DB->update_record('local_myidpebi', $upd);

    //Insert ke dalam log IDP
    local_myidpebi_add_log(
        $idp_id,
        $USER->id,
        'verifikasi_atasan',
        $oldstatus,
        $newstatus,
        'Program IDP diverifikasi oleh Atasan.');

    // Insert ke dalam log
    $event = \local_myidpebi\event\idp_status_changed::create([
        'objectid' => $idp_id,
        'userid'   => $USER->id,
        'context'  => context_system::instance(),
        'other'    => [
            'status_code' => 2 
        ]
    ]);
    $event->trigger();

    // Redirect halaman
    redirect($url, 'IDP telah diverifikasi selesai.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// 3. LOGIKA AKSI VERIFIKASI SELESAI OLEH LDC (Status 2 -> 3)
if (optional_param('verify_ldc', 0, PARAM_INT) && ($can_reset) && confirm_sesskey()) {

    $upd = new stdClass();
    $upd->id = $idp_id;
    $upd->status = 3; 
    $upd->verified_ldc_by = $USER->id;
    $upd->timeverified = time();
    
    $DB->update_record('local_myidpebi', $upd);

    //Insert ke dalam log IDP
    $oldstatus = $idp->status; // Simpan status lama
    $newstatus = 3;            // Status baru 
    local_myidpebi_add_log(
        $idp_id,
        $USER->id,
        'verifikasi_ldc',
        $oldstatus,
        $newstatus,
        'Program IDP diverifikasi  oleh LDC.');

    // Insert ke dalam log
    $event = \local_myidpebi\event\idp_status_changed::create([
        'objectid' => $idp_id,
        'userid'   => $USER->id,
        'context'  => context_system::instance(),
        'other'    => [
            'status_code' => 3 
        ]
    ]);
    $event->trigger();

    // Redirect halaman
    redirect($url, 'IDP telah diverifikasi selesai.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Setelah seluruh logika aksi aman dan tidak ada redirect yang dipicu, baru render header
echo $OUTPUT->header();

// ///ada update--


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
    echo '                    <span class="text-warning font-weight-bold">Buatlah aktivitas perencanaan :</span><br>Jika sudah Ok, minta persetujuan dari Atasan.';
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
    echo '                    Kunci pasif. Terbuka otomatis setelah rencana disetujui Pembimbing.';
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


// --- TAMPILAN DETAIL INFORMASI IDP KARYAWAN ---
echo '<div class="card mb-4 border-left-primary shadow-sm"><div class="card-body">';
$status_info = local_myidpebi_get_status_info($idp->status);

echo "<h4 class='text-primary mb-3'><i class='fa fa-folder-open'></i> {$idp->nama_idp}</h4>";

echo '<div class="row">';
echo '  <div class="col-md-12">';
echo '      <div class="mb-2"><strong>Status:</strong><br>' . $status_info->badge . '</div>';
echo '      <div class="mb-2"><strong>Atasan Langsung :</strong><br>' . ($idp->p_nik ?: '-') . ' - ' . $idp->p_fname . ' ' . $idp->p_lname . '</div>';
echo '      <div class="mb-2"><strong>Atasan Langsung (Sistem):</strong><br>' . ($idp->al_nik ?: '-') . ' - ' . $idp->al_fname . ' ' . $idp->al_lname . '</div>';
echo '      <div class="mb-2"><strong>Periode Program:</strong><br>' . userdate($idp->mulai_date, '%d %b %Y') . ' s/d ' . userdate($idp->akhir_date, '%d %b %Y') . '</div>';
echo '      <div class="mb-2"><strong>Total Perencanaan JP</strong><br>' . $total_jp_rencana. '</div>';
echo '      <div class="mb-2 ' . ($idp->status == 2 ? 'text-success' : 'text-muted') . '">';
echo '          <strong>Total JP Terverifikasi:</strong><br>';
echo '          <span class="h5 font-weight-bold">' . $total_jp_verified . ' JP</span>';
if ($idp->status < 2) {
    echo ' <small>(Akan dihitung setelah verifikasi selesai)</small>';
}
echo '      </div>';


//table analisa kompetensi

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover shadow-sm">';
echo '  <thead class="table-light text-center align-middle">
            <tr>
                <th rowspan="2">Resume Kapabilitas</th>
                <th colspan="2">Tuntutan Pada Posisi Saat Ini</th>
                <th colspan="2">Tuntutan Pada Posisi Berikutnya </th>
                <th rowspan="2">Tuntutan Karena Perubahan Lingkungan</th>
                <th rowspan="2">Area Pengembangan</th>
                <th rowspan="2">Hasil Pengembangan Yang Dituju</th>
            </tr>
            <tr>
                <th> Perfomance</th>
                <th> Kompetensi</th>
                <th> Perfomance</th>
                <th> Kompetensi</th>
            </tr>
        </thead>
        <tbody><tr>';

echo '<td>' . s($idp->resume_kapabilitas) . '</td>';
echo '<td>' . s($idp->tuntutan_sekarang_performance) . '</td>';
echo '<td>' . s($idp->tuntutan_sekarang_kompetensi) . '</td>';
echo '<td>' . s($idp->tuntutan_berikutnya_performance) . '</td>';
echo '<td>' . s($idp->tuntutan_berikutnya_kompetensi) . '</td>';
echo '<td>' . s($idp->tuntutan_lingkungan) . '</td>';
echo '<td>' . s($idp->area_pengembangan_ditingkatkan) . '</td>';
echo '<td>' . s($idp->area_pengembangan_diharapkan) . '</td>';

echo '</tr></tbody>';
echo '</table>';
echo '</div>';


// --- DAFTAR AKTIVITAS ---
echo '<div class="d-flex justify-content-between align-items-center mt-5 mb-3">';
echo '  <h4 class="m-0">Rincian Aktivitas</h4>';

if ($idp->status < 2 && $USER->id == $idp->userid) {

    //gunakan dibawah ini
    $add_url = new moodle_url('/local/myidpebi/edit_activity.php', ['idp_id' => $idp_id]);
    echo '<a href="'.$add_url.'" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah Aktivitas</a>';
}
echo '</div>';
echo '<p class="text-muted"><small>* Klik tombol pencil untuk melakukan perubahan aktifitas</small></p>';


$sql_act = "SELECT a.*, m.learning_activity as nama_learning_activity, m.bentuk_evidence 
            FROM {local_myidpebi_act} a
            LEFT JOIN {local_myidpebi_learning_activity} m ON a.learning_activity = m.id
            WHERE a.idp_id = :idp_id 
            ORDER BY a.deleted ASC, a.id ASC"; // Menjamin data aktif (0) di atas, data terhapus (1) mengumpul di bawah

$activities = $DB->get_records_sql($sql_act, ['idp_id' => $idp_id]);

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-hover shadow-sm">';
echo '  <thead class="table-light text-center align-middle">
    <tr>
        <th rowspan="2" class="align-middle">Aktivitas Pengembangan</th>
        <th rowspan="2" class="align-middle">Detil Aktivitas</th>
        <th colspan="2">Perencanaan Aktivitas</th>
        <th colspan="2">Realisasi Aktivitas</th>
        <th rowspan="2" class="align-middle">Evidence</th>
        <th rowspan="2" class="align-middle">Aksi</th>
    </tr>
    <tr>
        <th>Target JP</th>
        <th>Periode Rencana</th>
        <th>Capaian JP</th>
        <th>Periode Pelaksanaan</th>
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

        // =========================================================================
        // KONDISIONAL KOTAK TEKS PANJANG (DIKUNCI DENGAN SCROLLBOX INTERNAL AGAR TIDAK MELAR)
        // =========================================================================
        $textarea_box_style = 'style="max-height: 95px; overflow-y: auto; font-size: 15px; line-height: 1.4; padding: 6px; background: rgba(0,0,0,0.03); border-radius: 4px; border: 1px solid #e9ecef; white-space: pre-wrap; min-width: 150px;"';


        echo "  <td {$text_style} class='text-center bold'>{$a->nama_learning_activity}</td>";
        echo "  <td {$text_style}>{$a->nama_activity}</td>";
        echo "  <td {$text_style} class='text-center'>{$a->jumlah_jp_perencanaan}</td>";
        echo "<td {$text_style}>" . userdate($a->perencanaan_tanggal_mulai, '%d-%m-%Y') . " s/d " . userdate($a->perencanaan_tanggal_selesai, '%d-%m-%Y') . "</td>";
        echo "  <td {$text_style} class='text-center'>{$a->jumlah_jp_realisasi}</td>";
        $realisasi_periode = '-';
        if (!empty($a->realisasi_pelaksanaan_mulai) && !empty($a->realisasi_pelaksanaan_selesai)) {
            $realisasi_periode = userdate($a->realisasi_pelaksanaan_mulai, '%d-%m-%Y') . " <span class=text-muted small>s/d</span><br> " . userdate($a->realisasi_pelaksanaan_selesai, '%d-%m-%Y');
        }
        echo "<td {$text_style} style=white-space: nowrap;>{$realisasi_periode}</td>";
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


// =========================================================================
// LOGIKA TAMPILAN EVALUASI MANDIRI (SELF-ASSESSMENT)
// =========================================================================
$is_owner = ($USER->id == $idp->userid);
echo '<div class="mt-4 p-3 border-top bg-light">';

if ((float)$idp->skor_efektivitas > 0) {
    // 1. Jika kuesioner SUDAH DIISI, tampilkan card hasil skornya
    echo '<div class="card mb-4 border-success">';
    echo '  <div class="card-header bg-success text-white"><strong><i class="fa fa-check-circle"></i> Hasil Self-Assessment</strong></div>';
    echo '  <div class="card-body">';
    echo '      <h4 class="card-title text-success">Skor Efektivitas IDP: ' . number_format($idp->skor_efektivitas, 2) . '</h4>';
    echo '      <p class="card-text"><strong>Kesimpulan IDP :</strong><br>' . s($idp->kesimpulan_karyawan) . '</p>';
    echo '  </div>';
    echo '</div>';
    // milik atasan
    echo '<div class="card mb-4 border-success">';
    echo '  <div class="card-header bg-warning text-white"><strong><i class="fa fa-check-circle"></i> Hasil Penilaian Atasan</strong></div>';
    echo '  <div class="card-body">';
    echo '      <h4 class="card-title text-success">Penilaian Atasan: ' . number_format($idp->skor_atasan, 2) . '</h4>';
    echo '      <p class="card-text"><strong>Kesimpulan IDP atasan:</strong><br>' . s($idp->kesimpulan_atasan) . '</p>';
    echo '  </div>';
    echo '</div>';
} else if ($idp->status == 1 && $is_owner) {
    // 2. Jika BELUM DIISI, status sedang Running (1), dan dia pemiliknya, tampilkan Tombol Pemicu
    $assessment_url = new moodle_url('/local/myidpebi/assessment.php', ['id' => $idp_id]);
    echo '<div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">';
    echo '  <div>';
    echo '      <h5><i class="fa fa-exclamation-triangle"></i> Self Assement IDP</h5>';
    echo '      <p class="mb-0 text-black">Lakukan self assement Jika anda telah mengisi semua rincian aktifitas dan telah mengkonsultasikan dengan pembimbing anda.</p>';
    echo '  </div>';
    echo '  <a href="' . $assessment_url . '" class="btn btn-success text-white" onclick="return confirm(\'Apakah Anda yakin sudah mengecek ulang seluruh rincian aktivitas IDP Anda dan mengonsultasikannya dengan atasan?\');">';
    echo '      <i class="fa fa-pencil-square-o"></i> Isi Evaluasi Efektivitas IDP';
    echo '  </a>';
    echo '</div>';
}
echo '</div>';

// =========================================================================
// Tombol Aksi Atasan/Pembimbing Lintas Otorisasi dengan Konfirmasi
// =========================================================================
if ($is_pembimbing || $is_atasan_langsung) {
    echo '<div class="mt-4 p-3 border-top bg-light">';
    
    if ($idp->status == 0) {
        $approve_url = new moodle_url($url, ['approve' => 1, 'sesskey' => sesskey()]);
        $confirm_msg = "Apakah Anda yakin ingin MENYETUJUI program IDP ini untuk segera dilaksanakan?";
        
        // Menggunakan d-flex agar teks instruksi di kiri dan tombol di kanan sejajar sempurna
        echo '<div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center shadow-sm mb-0" role="alert">';
        echo '    <div class="mb-3 mb-md-0 mr-md-3">';
        echo '        <h5 class="alert-heading mb-1"><i class="fa fa-info-circle mr-2"></i>Konfirmasi Persetujuan IDP</h5>';
        echo '        <p class="mb-0 text-black">Pastikan karyawan ybs. sudah melakukan konsultasi dengan Anda mengenai kegiatan yang akan dijalankan.</p>';
        echo '    </div>';
        echo '    <div class="text-nowrap">';
        echo '        <a href="'.$approve_url.'" class="btn btn-primary px-4 py-2" onclick="return confirm(\''.$confirm_msg.'\')"><i class="fa fa-check mr-2"></i>Setujui Program</a>';
        echo '    </div>';
        echo '</div>';

    } else if ($idp->status == 1 && $idp->skor_efektivitas>= 1 ) {
        // mengarah ke halaman form kuesioner assessment_atasan.php
        $verify_url = new moodle_url('/local/myidpebi/assessment_atasan.php', ['id' => $idp->id]);
        
        // Menggunakan alert-warning/alert-success untuk membedakan tahapan verifikasi penutupan dokumen
        echo '<div class="alert alert-warning d-flex flex-column flex-md-row justify-content-between align-items-md-center shadow-sm mb-0" role="alert">';
        echo '    <div class="mb-3 mb-md-0 mr-md-3">';
        echo '        <h5 class="alert-heading mb-1"><i class="fa fa-flag-checkered mr-2"></i>Verifikasi & Penilaian Efektivitas Program</h5>';
        echo '        <p class="mb-0 text-black">Lakukan pengisian kuesioner penilaian efektivitas dan verifikasi akhir jika semua rincian aktivitas sudah diperiksa dengan lengkap.</p>';
        echo '    </div>';
        echo '    <div class="text-nowrap">';
        // 🟢 PERBAIKAN: Hilangkan onclick confirm() karena konfirmasi/submit akan ditangani langsung di dalam halaman form kuesioner
        echo '        <a href="'.$verify_url.'" class="btn btn-success px-4 py-2 text-white"><i class="fa fa-pencil-square-o mr-2"></i>Isi Penilaian & Verifikasi</a>';
        echo '    </div>';
        echo '</div>';
    }
    
    echo '</div>'; // Penutup bg-light
}
echo '</div></div>'; // Penutup card Aksi Atasan/Pembimbing Lintas Otorisasi dengan Konfirmasi

// =========================================================================
// Tombol Aksi LDC/HCM melakukan Verifikasi Akhir
// =========================================================================
// Cek capability untuk reset/penutupan langsung oleh LDC/Admin
$can_reset = has_capability('local/myidpebi:reset_idp', $PAGE->context);

if ($can_reset) {
    echo '<div class="mt-4 p-3 border-top bg-light">';
        $reset_page_url = new moodle_url('/local/myidpebi/admin_edit_idp.php', ['id' => $idp_id]);
        
        if ($idp->status == 2) {
        echo '<div class="alert alert-secondary d-flex flex-column flex-md-row justify-content-between align-items-md-center shadow-sm mt-3 mb-0" role="alert">';
        echo '    <div class="mb-3 mb-md-0 mr-md-3">';
        echo '        <h5 class="alert-heading mb-1 text-dark"><i class="fa fa-shield mr-2"></i>Akses Khusus Admin / LDC</h5>';
        echo '        <p class="mb-0 text-black">Gunakan fitur ini untuk verifikasi final LDC atau melakukan reset status IDP.</p>';
        echo '    </div>';
        echo '    <div class="text-nowrap d-flex gap-2">';
        
        // Tombol A: Verifikasi Akhir LDC (Hanya muncul jika status sudah diverifikasi atasan / Status 2)
        
            $set_status_3_url = new moodle_url($url, ['verify_ldc' => 1, 'sesskey' => sesskey()]);
            $confirm_status_3 = "Apakah Anda yakin ingin memverifikasi/mengunci dokumen IDP ini?";
            echo '        <a href="'.$set_status_3_url.'" class="btn btn-success text-white px-3 py-2 mr-2" onclick="return confirm(\''.$confirm_status_3.'\')"><i class="fa fa-check-circle mr-1"></i>Verifikasi Akhir</a>';
            // Tombol B: Reset / Edit Admin (Mengarah ke admin_edit_idp.php)
            echo '        <a href="'.$reset_page_url.'" class="btn btn-warning px-3 py-2"><i class="fa fa-refresh mr-1"></i>Reset / Edit Admin</a>';
        
            echo '    </div>';
        echo '</div>';
        }
    
    echo '</div>'; // Penutup bg-light
}
echo '</div></div>';



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

// =========================================================================
// TAMPILAN TIMELINE AUDIT LOG DARI TABEL local_myidpebi_log
// =========================================================================
$logs = $DB->get_records_sql("
    SELECT l.*, u.firstname, u.lastname, u.username
      FROM {local_myidpebi_log} l
      JOIN {user} u ON u.id = l.actor_id
     WHERE l.idp_id = :idpid
  ORDER BY l.timecreated DESC
", ['idpid' => $idp_id]);

if ($logs) {
    echo '<div class="mt-4 p-3 bg-light border rounded shadow-sm">';
    echo '  <h6 class="text-secondary font-weight-bold mb-3"><i class="fa fa-history"></i> Riwayat & Timeline Aksi Status:</h6>';
    echo '  <ul class="list-unstyled mb-0">';
    
    foreach ($logs as $log) {
        $actorname = fullname($log) . ' (' . $log->username . ')';
        $time = userdate($log->timecreated, '%d %b %Y, %H:%M');
        $old_info = local_myidpebi_get_status_info($log->old_status);
        $new_info = local_myidpebi_get_status_info($log->new_status);
        
        echo '  <li class="mb-2 pb-2 border-bottom">';
        echo '      <div class="d-flex justify-content-between align-items-center">';
        echo "          <span><strong>{$actorname}</strong> melakukan aksi <code>{$log->action_type}</code></span>";
        echo "          <small class='text-muted'>{$time}</small>";
        echo '      </div>';
        echo "      <div class='small text-muted mt-1'>";
        echo "          Perubahan Status: {$old_info->badge} <i class='fa fa-arrow-right mx-1'></i> {$new_info->badge}";
        if (!empty($log->notes)) {
            echo "<br><em>Catatan: \"{$log->notes}\"</em>";
        }
        echo '      </div>';
        echo '  </li>';
    }
    
    echo '  </ul>';
    echo '</div>';
}

echo '</div>';

echo $OUTPUT->footer();