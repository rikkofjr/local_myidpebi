<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Admin atau yang memiliki hak viewreports
require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('moodle/site:viewreports', $context)) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Inisialisasi Halaman
$url = new moodle_url('/local/myidpebi/admin_dashboard.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Dashboard Admin IDP');
$PAGE->set_heading('Dashboard Analisis IDP');

// --- RENDERING TAMPILAN ---
echo $OUTPUT->header();

// --- NAVIGASI TAB MENU (Menghubungkan Dashboard dan Monitor) ---
echo '<ul class="nav nav-tabs mb-4">';
echo '  <li class="nav-item"><a class="nav-item nav-link active" href="' . new moodle_url('/local/myidpebi/admin_dashboard.php') . '"><i class="fa fa-pie-chart"></i> Ringkasan & Tren</a></li>';
echo '  <li class="nav-item"><a class="nav-item nav-link" href="' . new moodle_url('/local/myidpebi/admin_monitor.php') . '"><i class="fa fa-table"></i> Monitoring Data Karyawan</a></li>';
echo '</ul>';

// =========================================================
// KUMPULAN QUERY AGREGAT AMAN (SESUAI SKEMA TABEL & LIB.PHP)
// =========================================================
$current_year = date('Y');
$year_start_timestamp = strtotime("$current_year-01-01");

// A. Total IDP Terbuat di Tahun Ini
$total_idp = $DB->count_records_select('local_myidpebi', "timecreated >= ?", [$year_start_timestamp]);

// B. Mengambil Definisi Teks & Class Resmi dari lib.php untuk masing-masing status
$status0_info = local_myidpebi_get_status_info(0); // Menunggu Approval
$status1_info = local_myidpebi_get_status_info(1); // Disetujui / Proses
$status2_info = local_myidpebi_get_status_info(2); // Selesai Diverifikasi

// C. Hitung Riwayat Kuantitas Per Status Dokumen
$pending_approval = $DB->count_records('local_myidpebi', ['status' => 0]);
$running_idp       = $DB->count_records('local_myidpebi', ['status' => 1]);
$verified_idp      = $DB->count_records('local_myidpebi', ['status' => 2]);

// D. Hitung Tingkat Penyelesaian (Completion Rate)
$completion_rate = ($total_idp > 0) ? round(($verified_idp / $total_idp) * 100, 1) : 0;

// E. Total JP Terpenuhi dari Dokumen Berstatus Selesai (Verified / 2)
$total_jp_sql = "SELECT SUM(act.jumlah_jp) as total 
                 FROM {local_myidpebi_act} act 
                 JOIN {local_myidpebi} idp ON act.idp_id = idp.id 
                 WHERE idp.status = 2 AND act.deleted = 0";
$jp_res = $DB->get_record_sql($total_jp_sql);
$total_jp = !empty($jp_res->total) ? $jp_res->total : 0;

// ==========================================
// BARIS 1: RENDERING KPI BLOCKS (GRID)
// ==========================================
?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1" style="color: rgba(255,255,255,0.7); font-size: 11px;">Total IDP (<?php echo $current_year; ?>)</h6>
                        <h2 class="font-weight-bold mb-0"><?php echo $total_idp; ?></h2>
                    </div>
                    <i class="fa fa-folder-open fa-3x" style="opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-secondary mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1" style="color: rgba(255,255,255,0.7); font-size: 11px;"><?php echo $status0_info->text; ?></h6>
                        <h2 class="font-weight-bold mb-0"><?php echo $pending_approval; ?></h2>
                    </div>
                    <i class="fa fa-clock-o fa-3x" style="opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1" style="color: rgba(255,255,255,0.7); font-size: 11px;">Completion Rate</h6>
                        <h2 class="font-weight-bold mb-0"><?php echo $completion_rate; ?>%</h2>
                    </div>
                    <i class="fa fa-check-circle fa-3x" style="opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-uppercase mb-1" style="color: rgba(255,255,255,0.7); font-size: 11px;">Total Realisasi JP</h6>
                        <h2 class="font-weight-bold mb-0"><?php echo $total_jp; ?> <small style="font-size: 14px;">Hours</small></h2>
                    </div>
                    <i class="fa fa-graduation-cap fa-3x" style="opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light"><strong><i class="fa fa-pie-chart"></i> Rasio Status Dokumen</strong></div>
            <div class="card-body d-flex flex-column justify-content-center">
                <table class="table table-sm table-inverse mb-0">
                    <tbody>
                        <tr>
                            <td><span class="badge <?php echo $status0_info->class; ?>"><?php echo $status0_info->text; ?></span></td>
                            <td class="text-right font-weight-bold"><?php echo $pending_approval; ?> IDP</td>
                        </tr>
                        <tr>
                            <td><span class="badge <?php echo $status1_info->class; ?>"><?php echo $status1_info->text; ?></span></td>
                            <td class="text-right font-weight-bold"><?php echo $running_idp; ?> IDP</td>
                        </tr>
                        <tr>
                            <td><span class="badge <?php echo $status2_info->class; ?>"><?php echo $status2_info->text; ?></span></td>
                            <td class="text-right font-weight-bold"><?php echo $verified_idp; ?> IDP</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light"><strong><i class="fa fa-tag"></i> Top Aspek Kompetensi Karyawan</strong></div>
            <div class="card-body">
                <?php
                $aspek_sql = "SELECT aspek, COUNT(*) as qty 
                              FROM {local_myidpebi_act} 
                              WHERE deleted = 0 AND aspek IS NOT NULL AND aspek != '' AND aspek != '-'
                              GROUP BY aspek 
                              ORDER BY qty DESC 
                              LIMIT 5";
                $aspek_records = $DB->get_records_sql($aspek_sql);
                
                if (!empty($aspek_records)) {
                    echo '<ul class="list-group list-group-flush">';
                    foreach ($aspek_records as $rec) {
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center p-2'>
                                <span style='font-size: 13px;'>" . s($rec->aspek) . "</span>
                                <span class='badge badge-primary badge-pill'>{$rec->qty} Kali</span>
                              </li>";
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="text-muted text-center my-3">Belum ada data kompetensi.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-light"><strong><i class="fa fa-tasks"></i> Top Jenis Kegiatan Pengembangan</strong></div>
            <div class="card-body">
                <?php
                $jenis_sql = "SELECT jenis_kegiatan, COUNT(*) as qty 
                              FROM {local_myidpebi_act} 
                              WHERE deleted = 0 AND jenis_kegiatan IS NOT NULL AND jenis_kegiatan != '' 
                              GROUP BY jenis_kegiatan 
                              ORDER BY qty DESC 
                              LIMIT 5";
                $jenis_records = $DB->get_records_sql($jenis_sql);
                
                if (!empty($jenis_records)) {
                    echo '<ul class="list-group list-group-flush">';
                    foreach ($jenis_records as $rec) {
                        echo "<li class='list-group-item d-flex justify-content-between align-items-center p-2'>
                                <span style='font-size: 13px;'> " . s($rec->jenis_kegiatan) . "</span>
                                <span class='badge badge-success badge-pill'>{$rec->qty} Act</span>
                              </li>";
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="text-muted text-center my-3">Belum ada data aktivitas.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
<?php

echo $OUTPUT->footer();