<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$url = new moodle_url('/local/myidpebi/manage.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Persetujuan IDP');
$PAGE->set_heading('Halaman Approval Atasan');

// Logika Approval.
$approve_id = optional_param('approve', 0, PARAM_INT);
if ($approve_id && confirm_sesskey()) {
    $idp_record = $DB->get_record('local_myidpebi', ['id' => $approve_id, 'atasan_id' => $USER->id]);
    if ($idp_record) {
        $idp_record->status = 1;
        $DB->update_record('local_myidpebi', $idp_record);
        redirect($url, 'IDP berhasil disetujui!', null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading('Daftar Pengajuan IDP Bawahan', 3);

$sql = "SELECT i.*, u.firstname, u.lastname 
        FROM {local_myidpebi} i
        JOIN {user} u ON i.userid = u.id
        WHERE i.atasan_id = ?
        ORDER BY i.status ASC, i.timecreated DESC";

$records = $DB->get_records_sql($sql, [$USER->id]);

if ($records) {
    echo '<table class="table table-bordered table-hover">';
    echo '<thead class="thead-dark"><tr><th>Nama Bawahan</th><th>Kegiatan IDP</th><th>Target Selesai</th><th>Status</th><th>Aksi</th></tr></thead>';
    echo '<tbody>';
    foreach ($records as $idp) {
        $status_text = ($idp->status == 1) ? 'Disetujui' : 'Menunggu Approval';
        $badge_class = ($idp->status == 1) ? 'badge badge-success' : 'badge badge-warning';
        
        echo '<tr>';
        echo "<td>{$idp->firstname} {$idp->lastname}</td>";
        echo "<td>";
             $view_url = new moodle_url('/local/myidpebi/view_details.php', ['id' => $idp->id]);
            echo '<strong><a href="'.$view_url.'">'.$idp->nama_idp.'</a></strong>';
        echo "</td>";
        echo "<td>" . userdate($idp->akhir_date, '%d %b %Y') . "</td>";
        echo "<td><span class='{$badge_class}'>{$status_text}</span></td>";
        echo '<td>';

        // if ($idp->status == 0) {
        //     $approve_url = new moodle_url($url, ['approve' => $idp->id, 'sesskey' => sesskey()]);
        //     echo '<a href="'.$approve_url.'" class="btn btn-sm btn-success">Setujui</a>';
        // } else {
        //     echo '<span class="text-muted">-</span>';
        // }
        
        echo '</td></tr>';
    }
    echo '</tbody></table>';
} else {
    echo $OUTPUT->notification('Tidak ada pengajuan IDP dari bawahan.', 'info');
}

echo '<div class="mt-3"><a href="index.php" class="btn btn-secondary">Kembali ke Dashboard Saya</a></div>';

echo $OUTPUT->footer();