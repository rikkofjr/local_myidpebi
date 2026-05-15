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

// 2. Inisialisasi Parameter
$page_num      = optional_param('page', 0, PARAM_INT);
$per_page      = 20; 
$search        = optional_param('search', '', PARAM_TEXT);
$status_filter = optional_param('status_filter', -1, PARAM_INT);

$url = new moodle_url('/local/myidpebi/admin_monitor.php');
if ($search) $url->param('search', $search);
if ($status_filter !== -1) $url->param('status_filter', $status_filter);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Monitoring Global IDP');
$PAGE->set_heading('Panel Kontrol Administrator IDP');

// --- NAVIGASI BREADCRUMB ---
$PAGE->navbar->add('Dashboard IDP', new moodle_url('/local/myidpebi/index.php'));
$PAGE->navbar->add('Monitoring Global');

echo $OUTPUT->header();

// --- 3. FILTER UNTUK ADMIN (Menggunakan Fungsi Pusat lib.php) ---
$st0 = local_myidpebi_get_status_info(0)->text;
$st1 = local_myidpebi_get_status_info(1)->text;
$st2 = local_myidpebi_get_status_info(2)->text;

echo '<div class="card mb-4 border-primary shadow-sm"><div class="card-body">';
echo '<h6 class="card-title text-primary"><i class="fa fa-search"></i> Pencarian Global</h6>';
echo '<form method="get" action="admin_monitor.php" class="form-inline">';
echo '<input type="text" name="search" class="form-control mr-2" style="width:300px" placeholder="Nama Karyawan / NIK / Nama Atasan..." value="'.s($search).'">';
echo '<select name="status_filter" class="form-control mr-2">
        <option value="-1">-- Semua Status --</option>
        <option value="0" '.($status_filter === 0 ? 'selected' : '').'>'.$st0.'</option>
        <option value="1" '.($status_filter === 1 ? 'selected' : '').'>'.$st1.'</option>
        <option value="2" '.($status_filter === 2 ? 'selected' : '').'>'.$st2.'</option>
      </select>';
echo '<button type="submit" class="btn btn-primary">Terapkan Filter</button>';
if ($search || $status_filter !== -1) {
    echo '<a href="admin_monitor.php" class="btn btn-link text-danger">Bersihkan</a>';
}
echo '</form></div></div>';

// --- 4. QUERY DATA ---
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (u.firstname LIKE :s1 OR u.lastname LIKE :s2 OR u.username LIKE :s3 OR a.firstname LIKE :s4)";
    $params['s1'] = $params['s2'] = $params['s3'] = $params['s4'] = "%$search%";
}

if ($status_filter !== -1) {
    $where .= " AND i.status = :status";
    $params['status'] = $status_filter;
}

$sql_count = "SELECT COUNT(i.id) FROM {local_myidpebi} i JOIN {user} u ON i.userid = u.id LEFT JOIN {user} a ON i.atasan_id = a.id $where";
$total_records = $DB->count_records_sql($sql_count, $params);

$sql = "SELECT i.*, 
               u.firstname, u.lastname, u.username as nik,
               a.firstname as atasan_fn, a.lastname as atasan_ln,
               (SELECT SUM(act.jumlah_jp) FROM {local_myidpebi_act} act WHERE act.idp_id = i.id) as total_jp
        FROM {local_myidpebi} i
        JOIN {user} u ON i.userid = u.id
        LEFT JOIN {user} a ON i.atasan_id = a.id
        $where
        ORDER BY i.timecreated DESC";

$records = $DB->get_records_sql($sql, $params, $page_num * $per_page, $per_page);

// --- 5. TAMPILAN TABEL ADMIN ---
if ($records) {
    echo '<div class="d-flex justify-content-between align-items-center mb-2">';
    echo '<span>Menampilkan <strong>'.$total_records.'</strong> data program IDP</span>';
    echo '</div>';

    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);

    echo '<table class="table table-bordered table-hover shadow-sm" style="font-size: 0.9rem;">';
    echo '<thead class="thead-dark"><tr>
            <th>NIK</th>
            <th>Karyawan</th>
            <th>Nama Program</th>
            <th>Atasan / Coach</th>
            <th>Status</th>
            <th>Total JP</th>
            <th>Aksi</th>
          </tr></thead><tbody>';

    foreach ($records as $idp) {
        $status_info = local_myidpebi_get_status_info($idp->status);
        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        
        // Ikon kunci untuk status Verified
        $lock_icon = ($idp->status == 2) ? ' <i class="fa fa-lock text-muted" title="Data Terkunci"></i>' : '';

        echo '<tr>';
        echo "<td>{$idp->nik}</td>";
        echo "<td><strong>{$idp->firstname} {$idp->lastname}</strong></td>";
        echo "<td>{$idp->nama_idp}{$lock_icon}</td>";
        echo "<td>{$idp->atasan_fn} {$idp->atasan_ln}</td>";
        
        // Menampilkan Badge Otomatis
        echo "<td>" . $status_info->badge . "</td>";
        
        // Total JP hanya muncul angka jika status 2 (Verified)
        echo "<td><strong>" . ($idp->status == 2 ? ($idp->total_jp ?: 0) : '-') . "</strong></td>";
        
        echo "<td><a href='{$view_url}' class='btn btn-sm btn-info'><i class='fa fa-eye'></i> </a></td>";
        echo '</tr>';
    }
    echo '</tbody></table>';
    
    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);
} else {
    echo $OUTPUT->notification('Tidak ditemukan data IDP di seluruh sistem.', 'info');
}

echo '<div class="mt-3"><a href="index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Kembali ke Dashboard</a></div>';
echo $OUTPUT->footer();