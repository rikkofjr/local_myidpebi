<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$url = new moodle_url('/local/myidpebi/index.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Dashboard IDP');
$PAGE->set_heading('My IDP EBI');

echo $OUTPUT->header();

// Navigasi ke halaman Approval untuk Atasan.
echo '<div class="mb-3"><a href="manage.php" class="btn btn-outline-primary">Halaman Approval Atasan</a></div>';

// Form Pembuatan IDP Utama.
$mform = new \local_myidpebi\forms\idp_form();
$mform->set_data(['nik_atasan' => local_myidpebi_get_atasan_username($USER->id)]);

if ($fromform = $mform->get_data()) {
    $input_nik = trim($fromform->nik_atasan);
    $atasan = $DB->get_record('user', ['username' => $input_nik], 'id');
    
    if (!$atasan) {
        \core\notification::error("NIK Atasan tidak ditemukan!");
    } else {
        $data = new stdClass();
        $data->userid = $USER->id;
        $data->atasan_id = $atasan->id;
        $data->nama_idp = $fromform->nama_idp;
        $data->mulai_date = $fromform->mulai_date;
        $data->akhir_date = $fromform->akhir_date;
        $data->status = 0;
        $data->timecreated = time();
        
        $DB->insert_record('local_myidpebi', $data);
        redirect($url, 'IDP berhasil dibuat.', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$mform->display();

echo $OUTPUT->heading('Daftar IDP Saya', 3);
echo '<p class="text-muted"><small>* Klik pada Nama Kegiatan untuk mengelola rincian aktivitas.</small></p>';

// Perubahan Query: Menambahkan JOIN ke tabel aktivitas untuk menghitung total JP
$sql = "SELECT i.*, u.firstname, u.lastname, 
               (SELECT SUM(a.jumlah_jp) FROM {local_myidpebi_act} a WHERE a.idp_id = i.id) as total_jp
        FROM {local_myidpebi} i 
        LEFT JOIN {user} u ON i.atasan_id = u.id 
        WHERE i.userid = ? 
        ORDER BY i.timecreated DESC";

$records = $DB->get_records_sql($sql, [$USER->id]);

if ($records) {
    echo '<table class="table table-bordered table-striped"><thead><tr>';
    echo '<th>Nama Kegiatan / IDP</th><th>Atasan</th><th>Target Selesai</th><th>Total JP</th><th>Status</th></tr></thead><tbody>';
    
    foreach ($records as $idp) {
        // 1. Logika Status Warna (Point 1)
        // Status 0: Menunggu (Kuning/Warning)
        // Status 1: Disetujui (Kuning/Warning - Sesuai permintaan Anda agar sama dengan view_details)
        // Status 2: Terverifikasi Selesai (Hijau/Success)
        
        if ($idp->status == 2) {
            $status_text = 'Selesai Diverifikasi';
            $badge = 'badge-success';
            $display_jp = $idp->total_jp ?: 0; // Tampilkan total JP jika sudah verifikasi
        } else if ($idp->status == 1) {
            $status_text = 'Disetujui Atasan';
            $badge = 'badge-warning'; // Disamakan menjadi kuning
            $display_jp = '-'; // Belum diverifikasi (Point 2)
        } else {
            $status_text = 'Menunggu Approval';
            $badge = 'badge-warning';
            $display_jp = '-';
        }

        $nama_atasan = $idp->firstname . ' ' . $idp->lastname;
        $target_date = userdate($idp->akhir_date, '%d %b %Y');

        echo "<tr><td>";
        $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
        echo '<strong><a href="'.$view_url.'">'.$idp->nama_idp.'</a></strong>';
        echo "</td>";
        
        echo "<td>{$nama_atasan}</td>";
        echo "<td>{$target_date}</td>";
        echo "<td><strong>{$display_jp}</strong></td>"; // Kolom JP Baru
        echo "<td><span class='badge {$badge}'>{$status_text}</span></td></tr>";
    }
    echo '</tbody></table>';
} else {
    echo $OUTPUT->notification('Belum ada data IDP.', 'info');
}

echo $OUTPUT->footer();