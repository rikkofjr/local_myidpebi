<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

// 1. Inisialisasi Parameter
$page_num = optional_param('page', 0, PARAM_INT);
$per_page = 10;
$delete_idp_id = optional_param('delete_idp', 0, PARAM_INT);

$url = new moodle_url('/local/myidpebi/index.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Dashboard IDP');
$PAGE->set_heading('My IDP EBI');

// --- LOGIKA HAPUS IDP UTAMA ---
if ($delete_idp_id && confirm_sesskey()) {
    // Pastikan hanya bisa hapus jika status masih 0 (Menunggu)
    $check = $DB->get_record('local_myidpebi', ['id' => $delete_idp_id, 'userid' => $USER->id]);
    if ($check && $check->status == 0) {
        // Hapus juga aktivitas terkait agar tidak jadi sampah di database
        $DB->delete_records('local_myidpebi_act', ['idp_id' => $delete_idp_id]);
        $DB->delete_records('local_myidpebi', ['id' => $delete_idp_id]);
        redirect($url, 'Program IDP berhasil dihapus.');
    } else {
        \core\notification::error("Hanya IDP dengan status 'Menunggu' yang dapat dihapus.");
    }
}

echo $OUTPUT->header();

// Navigasi ke halaman Approval Atasan (Rata Kanan) - 100% KODE ASLI ANDA
$is_manager = has_capability('moodle/site:viewreports', context_system::instance());
echo '<div class="d-flex justify-content-end mb-3">';
if (is_siteadmin() || $is_manager) {
    echo '<div class="mb-2 mr-2"><a href="admin_monitor.php" class="btn btn-danger btn-block">PANEL ADMINISTRATOR GLOBAL</a></div>';
}
// Tombol ke halaman terpisah add_idp.php
echo '<div class="mb-2 mr-2"><a href="add_idp.php" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> Buat Ajukan IDP Baru</a></div>';
echo '<a href="manage.php" class="btn btn-outline-primary">Halaman Approval</a></div>';

// --- 3. TABEL DAFTAR IDP ---
echo $OUTPUT->heading('Daftar IDP Saya', 3);
echo '<p class="text-muted"><small>* Klik Program untuk rincian. Edit/Hapus hanya tersedia saat status Menunggu.</small></p>';

$params = ['userid' => $USER->id];
$sql_count = "SELECT COUNT(id) FROM {local_myidpebi} WHERE userid = :userid";
$total_records = $DB->count_records_sql($sql_count, $params);

$sql = "SELECT i.*, u.firstname, u.lastname, 
               (SELECT SUM(a.jumlah_jp_realisasi) FROM {local_myidpebi_act} a WHERE a.idp_id = i.id) as total_jp
        FROM {local_myidpebi} i 
        LEFT JOIN {user} u ON i.atasan_id = u.id 
        WHERE i.userid = :userid 
        ORDER BY i.timecreated DESC";

$records = $DB->get_records_sql($sql, $params, $page_num * $per_page, $per_page);

if ($records) {
    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);
    echo '<table class="table table-bordered table-striped"><thead><tr>';
    echo '<th>Program IDP</th><th>Pembimbing</th><th>Mulai</th><th>Target</th><th>Total JP</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
    
    foreach ($records as $idp) {
        // 🟢 SINKRONISASI TOTAL: Memanggil fungsi master dari lib.php
        $status_info = local_myidpebi_get_status_info($idp->status);

        // Atur tampilan angka JP mengikuti aturan status dari lib.php
        if ($idp->status == 2) {
            $display_jp = $idp->total_jp ?: 0;
        } else {
            $display_jp = '-';
        }

        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        
        echo "<tr><td><strong><a href='{$view_url}'>{$idp->nama_idp}</a></strong></td>";
        echo "<td>{$idp->firstname}</td>";
        echo "<td>".userdate($idp->mulai_date, '%d %b %y')."</td>";
        echo "<td>".userdate($idp->akhir_date, '%d %b %y')."</td>";
        echo "<td>{$display_jp}</td>";
        
        // 🟢 SINKRONISASI TOTAL: Menggunakan properti badge dari lib.php secara utuh
        echo "<td>".$status_info->badge."</td>";
        echo "<td>";
        
        // Tombol Edit & Hapus hanya muncul jika status masih 0 (Menunggu)
        if ($idp->status == 0) {
            $edit_url = new moodle_url('/local/myidpebi/add_idp.php', ['edit_idp' => $idp->id]);
            $del_url = new moodle_url($url, ['delete_idp' => $idp->id, 'sesskey' => sesskey()]);
            echo "<a href='{$edit_url}' class='btn btn-sm btn-link p-0 mr-2'>Edit</a>";
            echo "<a href='{$del_url}' class='btn btn-sm btn-link text-danger p-0' onclick='return confirm(\"Hapus seluruh program ini?\")'>Hapus</a>";
        } else {
            echo '<small class="text-muted">Locked</small>';
        }
        
        echo "</td></tr>";
    }
    echo '</tbody></table>';
    echo $OUTPUT->paging_bar($total_records, $page_num, $per_page, $url);
} else {
    echo $OUTPUT->notification('Belum ada data IDP.', 'info');
}

echo $OUTPUT->footer();