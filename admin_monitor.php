<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Admin atau Manager (has_capability viewreports)
require_login();
$context = context_system::instance();

if (!is_siteadmin() && !has_capability('moodle/site:viewreports', $context)) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Inisialisasi Parameter Filter
$page_num      = optional_param('page', 0, PARAM_INT);
$per_page      = 30; 
$search        = optional_param('search', '', PARAM_TEXT);
$status_filter = optional_param('status_filter', -1, PARAM_INT);

// 🟢 BARU: Tangkap parameter filter organisasi dinamis
$org_filter    = optional_param('org_filter', '', PARAM_TEXT); 

$url = new moodle_url('/local/myidpebi/admin_monitor.php');
if ($search) $url->param('search', $search);
if ($status_filter !== -1) $url->param('status_filter', $status_filter);
if ($org_filter) $url->param('org_filter', $org_filter);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Monitoring Global IDP');
$PAGE->set_heading('Panel Kontrol Administrator IDP');

// --- NAVIGASI BREADCRUMB ---
$PAGE->navbar->add('Admin Panel', new moodle_url('/local/myidpebi/admin_panel.php'));
$PAGE->navbar->add('Monitoring IDP', $url);

// 🟢 3. MEMBACA KONFIGURASI ADAPTIF (Berdasarkan Gambar UI Settings Anda)
$sumber_org = get_config('local_myidpebi', 'sumber_field_organisasi'); // 'user_table' atau 'custom_profile'
$field_org  = get_config('local_myidpebi', 'profile_field_organisasi'); // e.g., 'department' atau 'nama_divisi'

if (empty($sumber_org)) { $sumber_org = 'user_table'; }
if (empty($field_org)) { $field_org = 'department'; }

// 🟢 4. QUERY MENGAMBIL DAFTAR OPSIDROP DOWN FILTER SECARA UNIK (DISTINCT)
$org_options = ['' => ' All'];
if ($sumber_org === 'user_table') {
    $sql_opsi = "SELECT DISTINCT $field_org FROM {user} WHERE deleted = 0 AND $field_org IS NOT NULL AND $field_org != '' ORDER BY $field_org ASC";
    if ($records_opsi = $DB->get_fieldset_sql($sql_opsi)) {
        foreach ($records_opsi as $val) { $org_options[$val] = $val; }
    }
} else if ($sumber_org === 'custom_profile') {
    $sql_opsi = "SELECT DISTINCT d.data FROM {user_info_data} d 
                 JOIN {user_info_field} f ON d.fieldid = f.id 
                 WHERE f.shortname = ? AND d.data IS NOT NULL AND d.data != '' ORDER BY d.data ASC";
    if ($records_opsi = $DB->get_fieldset_sql($sql_opsi, [$field_org])) {
        foreach ($records_opsi as $val) { $org_options[$val] = $val; }
    }
}

// 5. MEMBANGUN QUERY UTAMA (DENGAN PENYARINGAN KOMPLEKS)
$params = [];
$whereClause = "WHERE u.deleted = 0";

if ($status_filter !== -1) {
    $whereClause .= " AND i.status = :status";
    $params['status'] = $status_filter;
}

if (!empty($search)) {
    $whereClause .= " AND (u.username LIKE :search1 OR u.firstname LIKE :search2 OR u.lastname LIKE :search3 OR i.nama_idp LIKE :search4)";
    $params['search1'] = '%'.$search.'%';
    $params['search2'] = '%'.$search.'%';
    $params['search3'] = '%'.$search.'%';
    $params['search4'] = '%'.$search.'%';
}

// 🟢 6. LOGIKA FILTER SQL BERDASARKAN PARAMETER DINAMIS YANG DIPILIH
$join_org = "";
if (!empty($org_filter)) {
    if ($sumber_org === 'user_table') {
        $whereClause .= " AND u.{$field_org} = :org_val";
        $params['org_val'] = $org_filter;
    } else if ($sumber_org === 'custom_profile') {
        $join_org = " JOIN {user_info_data} org_d ON u.id = org_d.userid
                      JOIN {user_info_field} org_f ON org_d.fieldid = org_f.id AND org_f.shortname = :org_field ";
        $whereClause .= " AND org_d.data = :org_val";
        $params['org_field'] = $field_org;
        $params['org_val']   = $org_filter;
    }
}

// Kalibrasi select data utama beserta kalkulasi JP menggunakan JOIN adaptif
$sql_select = "SELECT i.*, u.username as nik, u.firstname, u.lastname, 
                      atasan.firstname as atasan_fn, atasan.lastname as atasan_ln,
                      (SELECT SUM(jumlah_jp_realisasi) FROM {local_myidpebi_act} WHERE idp_id = i.id AND deleted = 0) as total_jp
               FROM {local_myidpebi} i
               JOIN {user} u ON i.userid = u.id
               JOIN {user} atasan ON i.atasan_id = atasan.id
               $join_org
               $whereClause
               ORDER BY i.timecreated DESC";

$total_records = $DB->count_records_sql("SELECT COUNT(*) FROM {local_myidpebi} i JOIN {user} u ON i.userid = u.id $join_org $whereClause", $params);
$records = $DB->get_records_sql($sql_select, $params, $page_num * $per_page, $per_page);

// 7. MEMULAI OUTPUT HEADERS
echo $OUTPUT->header();
echo '<div class="container-fluid mt-2">';

// --- PANEL BAR PENCARIAN & FILTER ---
echo '<div class="card bg-light mb-3 shadow-sm"><div class="card-body">';
echo '<form method="GET" action="'.$PAGE->url.'" class="form-inline d-flex flex-wrap justify-content-between">';

echo '  <div class="d-flex flex-wrap align-items-center">';
// Pencarian Textbox
echo '      <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Cari NIK, Nama, Judul IDP..." value="'.s($search).'">';

// Dropdown Filter Status
$status_options = [-1 => '— Semua Status —', 0 => 'Menunggu Approval', 1 => 'Disetujui / Proses', 2 => 'Selesai Diverifikasi'];
echo html_writer::select($status_options, 'status_filter', $status_filter, false, ['class' => 'form-control mr-2 mb-2']);

// 🟢 BARU: Dropdown Filter Organisasi Dinamis Hasil Distinct Database
echo html_writer::select($org_options, 'org_filter', $org_filter, false, ['class' => 'form-control mr-2 mb-2']);

echo '      <button type="submit" class="btn btn-primary mb-2 mr-1"><i class="fa fa-search"></i> Filter</button>';
echo '      <a href="/local/myidpebi/admin_monitor.php" class="btn btn-secondary mb-2">Reset</a>';
echo '  </div>';

// Tombol Kembali ke Admin Hub Panel
echo '  <a href="/local/myidpebi/admin_panel.php" class="btn btn-dark mb-2"><i class="fa fa-dashboard"></i> Panel Utama</a>';
echo '</form>';
echo '</div></div>';

// --- TABLE DATA VIEW ---
echo '<div class="card shadow-sm"><div class="card-body p-0">';
if (!empty($records)) {
    echo '<table class="table table-bordered table-striped table-hover mb-0">';
    echo '  <thead class="thead-dark"><tr>';
    echo '      <th>NIK Karyawan</th><th>Nama Karyawan</th><th>Nama Program IDP</th>';
    echo '      <th>Pembimbing / Atasan</th><th>Status Alur</th><th>Total JP</th>';
    echo '      <th>Skor Mandiri</th><th>Skor Atasan</th><th class="text-center">Aksi Kendali</th>';
    echo '  </tr></thead><tbody>';

    foreach ($records as $idp) {
        $status_info = local_myidpebi_get_status_info($idp->status);
        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        $edit_url = new moodle_url('/local/myidpebi/admin_edit_idp.php', ['id' => $idp->id]);
        $lock_icon = ($idp->status == 2) ? ' <i class="fa fa-lock text-muted" title="Data Terkunci"></i>' : '';

        echo '<tr>';
        echo "  <td>" . s($idp->nik) . "</td>";
        echo "  <td><strong>" . s($idp->firstname . ' ' . $idp->lastname) . "</strong></td>";
        echo "  <td>" . s($idp->nama_idp) . $lock_icon . "</td>";
        echo "  <td>" . s($idp->atasan_fn . ' ' . $idp->atasan_ln) . "</td>";
        echo "  <td>" . $status_info->badge . "</td>";
        echo "  <td><strong>" . ($idp->status == 2 ? ($idp->total_jp ?: 0) : '-') . "</strong></td>";
        echo "  <td>" . number_format($idp->skor_efektivitas, 2) . "%</td>";
        echo "  <td>" . number_format($idp->skor_atasan, 2) . "%</td>";
        echo "  <td class='text-center'>";
        echo "      <a href='{$view_url}' class='btn btn-sm btn-info mr-1' title='Lihat Rincian'><i class='fa fa-eye'></i></a>";
        echo "      <a href='{$edit_url}' class='btn btn-sm btn-danger' title='Reset Emergency'><i class='fa fa-refresh'></i></a>";
        echo "  </td>";
        echo '</tr>';
    }
    echo '  </tbody></table>';
} else {
    echo '<div class="p-5 text-center text-muted"><i class="fa fa-folder-open-o fa-3x"></i><br class="mt-2">Tidak ditemukan dokumen IDP karyawan yang cocok dengan kriteria filter tersebut.</div>';
}
echo '</div></div>';

// --- PAGINATION FOOTER ---
echo '<div class="mt-3">';
echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $PAGE->url);
echo '</div>';

echo '</div>';
echo $OUTPUT->footer();