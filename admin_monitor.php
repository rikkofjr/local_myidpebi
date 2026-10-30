<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Admin atau Manager
require_login();
$context = context_system::instance();

if (!is_siteadmin() && !has_capability('local/myidpebi:reset_idp', $context)) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. INISIALISASI PARAMETER FILTER
$page_num      = optional_param('page', 0, PARAM_INT);
$per_page      = 30; 
$search        = optional_param('search', '', PARAM_TEXT);
$status_filter = optional_param('status_filter', -1, PARAM_INT);
$org_filter    = optional_param('org_filter', '', PARAM_TEXT);
$date_from     = optional_param('date_from', '', PARAM_TEXT); 
$date_to       = optional_param('date_to', '', PARAM_TEXT);   

// TANGKAP PARAMETER DOWNLOAD
$download      = optional_param('download', '', PARAM_ALPHA);

$url = new moodle_url('/local/myidpebi/admin_monitor.php');
if ($search) $url->param('search', $search);
if ($status_filter !== -1) $url->param('status_filter', $status_filter);
if ($org_filter) $url->param('org_filter', $org_filter);
if ($date_from) $url->param('date_from', $date_from);
if ($date_to) $url->param('date_to', $date_to);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Monitoring Global IDP');
$PAGE->set_heading('Panel Kontrol Administrator IDP');

// --- NAVIGASI BREADCRUMB ---
$PAGE->navbar->add('Admin Panel', new moodle_url('/local/myidpebi/admin_panel.php'));
$PAGE->navbar->add('Monitoring IDP', $url);

// 3. MEMBACA KONFIGURASI ADAPTIF ORGANISASI
$sumber_org = get_config('local_myidpebi', 'sumber_field_organisasi');
$field_org  = get_config('local_myidpebi', 'profile_field_organisasi');

if (empty($sumber_org)) { $sumber_org = 'user_table'; }
if (empty($field_org)) { $field_org = 'department'; }

// 4. QUERY DROPDOWN FILTER ORGANISASI
$org_options = ['' => '— Semua Organisasi —'];
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

// 5. MEMBANGUN QUERY UTAMA (KLAUSA WHERE)
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

if (!empty($date_from)) {
    $time_from = strtotime($date_from . ' 00:00:00');
    if ($time_from !== false) {
        $whereClause .= " AND i.timecreated >= :time_from";
        $params['time_from'] = $time_from;
    }
}

if (!empty($date_to)) {
    $time_to = strtotime($date_to . ' 23:59:59');
    if ($time_to !== false) {
        $whereClause .= " AND i.timecreated <= :time_to";
        $params['time_to'] = $time_to;
    }
}

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

$sql_select = "SELECT i.*, u.username as nik, u.firstname, u.lastname, 
                      atasan.firstname as atasan_fn, atasan.lastname as atasan_ln
               FROM {local_myidpebi} i
               JOIN {user} u ON i.userid = u.id
               JOIN {user} atasan ON i.atasan_id = atasan.id
               $join_org
               $whereClause
               ORDER BY i.timecreated DESC";

// 6. 🟢 PROSES EKSPOR EXCEL (HTML TABLE FORMAT)
if (!empty($download)) {
    $all_records = $DB->get_records_sql($sql_select, $params);
    $filename = 'Monitoring_IDP_' . date('Ymd_His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><title>Monitoring IDP</title></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Header Tabel Excel
    echo '<tr style="background-color: #343a40; color: #ffffff; font-weight: bold; text-align: center;">';
    echo '<th>NIK Karyawan</th>';
    echo '<th>Nama Karyawan</th>';
    echo '<th>Penempatan</th>';
    echo '<th>Nama Program IDP</th>';
    echo '<th>Pembimbing / Atasan</th>';
    echo '<th>Tanggal Dibuat</th>';
    echo '<th>Status Alur</th>';
    echo '<th>Total JP Terverifikasi</th>';
    echo '<th>Skor Mandiri</th>';
    echo '<th>Skor Atasan</th>';
    echo '</tr>';

    if ($all_records) {
        foreach ($all_records as $row) {
            $status_info = local_myidpebi_get_status_info($row->status);
            $status_clean = isset($status_info->text) ? strip_tags($status_info->text) : 'Status ' . $row->status;

            echo '<tr>';
            echo '<td>' . s($row->nik) . '</td>';
            echo '<td>' . s($row->firstname . ' ' . $row->lastname) . '</td>';
            echo '<td>' . s($row->lastname) . '</td>';
            echo '<td>' . s($row->nama_idp) . '</td>';
            echo '<td>' . s($row->atasan_fn . ' ' . $row->atasan_ln) . '</td>';
            echo '<td align="center">' . date('d-m-Y', $row->timecreated) . '</td>';
            echo '<td>' . s($status_clean) . '</td>';
            echo '<td align="right">' . $row->total_jp_verified . '</td>';
            echo '<td align="right">' . number_format($row->skor_efektivitas, 2) . '</td>';
            echo '<td align="right">' . number_format($row->skor_atasan, 2) . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

// 7. PAGINATION UNTUK TAMPILAN WEB
$total_records = $DB->count_records_sql("SELECT COUNT(*) FROM {local_myidpebi} i JOIN {user} u ON i.userid = u.id $join_org $whereClause", $params);
$records = $DB->get_records_sql($sql_select, $params, $page_num * $per_page, $per_page);

// 8. OUTPUT UI HEADERS & FILTER PANEL
echo $OUTPUT->header();
echo '<div class="container">';
echo '  <div class="row justify-content-end border-0 shadow-sm rounded-lg p-3 mb-4 bg-light">';
echo '      <div class="col-md-3 text-right">';
echo '          <a href="/local/myidpebi/admin_panel.php" class="btn btn-dark mb-2"><i class="fa fa-dashboard"></i> Panel Utama</a>';
echo '      </div>';
echo '  </div>';
echo '</div>';

echo '<div class="container-fluid mt-2">';

// Panel Filter
echo '<div class="card bg-light mb-3 shadow-sm"><div class="card-body">';
echo '<h5>Filter Data IDP</h5>';
echo '<form method="GET" action="'.$PAGE->url.'" class="form-inline d-flex flex-wrap justify-content-between">';
echo '  <div class="d-flex flex-wrap align-items-center w-100">';
echo '      <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Cari NIK, Nama, Judul IDP..." value="'.s($search).'">';

$status_options = [
    -1 => '— Semua Status —', 
    0 => get_string('myidpebi:badge_status0', 'local_myidpebi'), 
    1 => get_string('myidpebi:badge_status1', 'local_myidpebi'), 
    2 => get_string('myidpebi:badge_status2', 'local_myidpebi'), 
    3 => get_string('myidpebi:badge_status3', 'local_myidpebi')
];
echo html_writer::select($status_options, 'status_filter', $status_filter, false, ['class' => 'form-control mr-2 mb-2']);
echo html_writer::select($org_options, 'org_filter', $org_filter, false, ['class' => 'form-control mr-2 mb-2']);

echo '      <div class="input-group mr-2 mb-2">';
echo '          <div class="p-1">Tanggal &nbsp;</div>';
echo '          <input type="date" name="date_from" class="form-control" title="Dari Tanggal" value="'.s($date_from).'">';
echo '          <div class="p-1">&nbsp s/d &nbsp;</div>';
echo '          <input type="date" name="date_to" class="form-control" title="Sampai Tanggal" value="'.s($date_to).'">';
echo '      </div>';

echo '      <button type="submit" class="btn btn-primary mb-2 mr-1"><i class="fa fa-search"></i> Filter</button>';
echo '      <a href="/local/myidpebi/admin_monitor.php" class="btn btn-secondary mb-2">Reset</a>';
echo '  </div>';
echo '</form>';
echo '</div></div>';

// 🟢 BARIS KHUSUS TOMBOL DOWNLOAD (TERPISAH & BERSIH)
$download_xls_url = new moodle_url($PAGE->url, ['download' => 'excel']);

echo '<div class="card bg-white mb-3 shadow-sm border-left-primary"><div class="card-body py-2 d-flex justify-content-between align-items-center">';
echo '  <div><i class="fa fa-download text-success mr-2"></i><strong>Ekspor Laporan:</strong> Mengunduh data lengkap sesuai filter aktif saat ini.</div>';
echo '  <div>';
echo '      <a href="'.$download_xls_url.'" class="btn btn-success btn-sm text-white"><i class="fa fa-file-excel-o"></i> Download Excel (.xls)</a>';
echo '  </div>';
echo '</div></div>';

// --- TABLE DATA VIEW (WEB UI) ---
echo '<div class="card shadow-sm"><div class="card-body p-0">';
if (!empty($records)) {
    echo '<table class="table table-bordered table-striped table-hover mb-0">';
    echo '  <thead class="thead-dark"><tr>';
    echo '      <th>NIK Karyawan</th><th>Nama Karyawan</th><th>Penempatan</th><th>Nama Program IDP</th>';
    echo '      <th>Pembimbing / Atasan</th><th>Tgl Dibuat</th><th>Status Alur</th><th>Total JP</th>';
    echo '      <th>Skor Mandiri</th><th>Skor Atasan</th><th class="text-center">Aksi Kendali</th>';
    echo '  </tr></thead><tbody>';

    foreach ($records as $idp) {
        $status_info = local_myidpebi_get_status_info($idp->status);
        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        $edit_url = new moodle_url('/local/myidpebi/admin_edit_idp.php', ['id' => $idp->id]);
        $lock_icon = ($idp->status == 2 || $idp->status == 3) ? ' <i class="fa fa-lock text-muted" title="Data Terkunci"></i>' : '';

        echo '<tr>';
        echo "  <td>" . s($idp->nik) . "</td>";
        echo "  <td>" . s($idp->firstname) . "</td>";
        echo "  <td>" . s($idp->lastname) . "</td>";
        echo "  <td>" . s($idp->nama_idp) . $lock_icon . "</td>";
        echo "  <td>" . s($idp->atasan_fn . ' ' . $idp->atasan_ln) . "</td>";
        echo "  <td>" . userdate($idp->timecreated, '%d %b %Y') . "</td>";
        echo "  <td>" . $status_info->badge . "</td>";
        echo "  <td><strong>" . number_format($idp->total_jp_verified) . "</strong></td>";
        echo "  <td>" . number_format($idp->skor_efektivitas, 2) . "</td>";
        echo "  <td>" . number_format($idp->skor_atasan, 2) . "</td>";
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

// PAGINATION FOOTER
echo '<div class="mt-3">';
echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $PAGE->url);
echo '</div>';

echo '</div>';
echo $OUTPUT->footer();