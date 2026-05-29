<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Inisialisasi Parameter untuk Filter & Pagination
$page_num  = optional_param('page', 0, PARAM_INT);
$per_page  = 30; // Jumlah data per halaman
$search    = optional_param('search', '', PARAM_TEXT);
$status_filter = optional_param('status_filter', -1, PARAM_INT);

$url = new moodle_url('/local/myidpebi/manage.php');
if ($search) $url->param('search', $search);
if ($status_filter !== -1) $url->param('status_filter', $status_filter);

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Daftar Bimbingan IDP');
$PAGE->set_heading('Daftar Bimbingan IDP');

echo $OUTPUT->header();

// --- 2. BAGIAN FILTER (Point 1) ---
echo '<div class="card mb-3"><div class="card-body">';
echo '<form method="get" action="manage.php" class="form-inline">';
echo '<input type="text" name="search" class="form-control mr-2" placeholder="Cari Nama / NIK..." value="'.s($search).'">';
echo '<select name="status_filter" class="form-control mr-2">
        <option value="-1">-- Semua Status --</option>
        <option value="0" '.($status_filter === 0 ? 'selected' : '').'>Menunggu Approval</option>
        <option value="1" '.($status_filter === 1 ? 'selected' : '').'>Disetujui / Proses</option>
        <option value="2" '.($status_filter === 2 ? 'selected' : '').'>Selesai Diverifikasi</option>
      </select>';
echo '<button type="submit" class="btn btn-primary">Filter</button>';
if ($search || $status_filter !== -1) {
    echo '<a href="manage.php" class="btn btn-link">Reset</a>';
}
echo '</form>';
echo '</div></div>';

// --- 3. MEMBANGUN QUERY SQL (Point 3: Urutan Terbaru) ---
$where = "WHERE i.atasan_id = :atasanid";
$params = ['atasanid' => $USER->id];

if ($search) {
    // Mencari berdasarkan nama atau username (NIK)
    $where .= " AND (u.firstname LIKE :search1 OR u.lastname LIKE :search2 OR u.username LIKE :search3)";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

if ($status_filter !== -1) {
    $where .= " AND i.status = :status";
    $params['status'] = $status_filter;
}

$sql_count = "SELECT COUNT(i.id) FROM {local_myidpebi} i JOIN {user} u ON i.userid = u.id $where";
$total_records = $DB->count_records_sql($sql_count, $params);

$sql = "SELECT i.*, u.firstname, u.lastname, u.username as nik 
        FROM {local_myidpebi} i
        JOIN {user} u ON i.userid = u.id
        $where
        ORDER BY i.timecreated DESC"; // Point 3: Data terbaru di atas

$records = $DB->get_records_sql($sql, $params, $page_num * $per_page, $per_page);

// --- 4. TAMPILAN TABEL ---
// --- TAMPILAN TABEL ---
if ($records) {
    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);

    echo '<table class="table table-bordered table-hover">';
    echo '<thead class="thead-dark"><tr>
            <th>NIK</th>
            <th>Nama Bawahan</th>
            <th>Kegiatan IDP</th>
            <th>Mulai</th>
            <th>Target Selesai</th>
            <th>Total JP</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr></thead>';
    echo '<tbody>';
    foreach ($records as $idp) {
        // Logika Status & Warna Badge
        if ($idp->status == 2) {
            $status_text = 'Selesai Diverifikasi';
            $badge_class = 'badge-success';
            
            // Hitung total JP (mengambil data dari tabel aktivitas)
            $total_jp = $DB->get_field_sql("SELECT SUM(jumlah_jp_realisasi) FROM {local_myidpebi_act} WHERE idp_id = ?", [$idp->id]);
            $display_jp = $total_jp ?: 0;
        } else if ($idp->status == 1) {
            $status_text = 'Disetujui / Proses';
            $badge_class = 'badge-warning';
            $display_jp = '-'; // Belum diverifikasi
        } else {
            $status_text = 'Menunggu Approval';
            $badge_class = 'badge-secondary';
            $display_jp = '-'; // Belum diverifikasi
        }
        
        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        
        echo '<tr>';
        echo "<td>{$idp->nik}</td>";
        echo "<td>{$idp->firstname} {$idp->lastname}</td>";
        echo "<td><strong><a href='{$view_url}'>{$idp->nama_idp}</a></strong></td>";
        echo "<td>" . userdate($idp->mulai_date, '%d %b %Y') . "</td>"; // Kolom Mulai
        echo "<td>" . userdate($idp->akhir_date, '%d %b %Y') . "</td>";
        echo "<td><strong>{$display_jp}</strong></td>"; // Kolom Total JP
        echo "<td><span class='badge {$badge_class}'>{$status_text}</span></td>";
        echo "<td><a href='{$view_url}' class='btn btn-sm btn-outline-primary'>Buka Detail</a></td>";
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);
} else {
    echo $OUTPUT->notification('Tidak ada data bimbingan IDP.', 'info');
}

echo '<div class="mt-3"><a href="index.php" class="btn btn-secondary">Kembali ke Dashboard Saya</a></div>';

echo $OUTPUT->footer();