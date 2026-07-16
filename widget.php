<?php
defined('MOODLE_INTERNAL') || die();
global $DB, $USER, $OUTPUT;

// Pastikan file lib.php dipanggil untuk menggunakan fungsi pembantu status
require_once(__DIR__ . '/lib.php');[cite: 14]

// =========================================================================
// 1. DATA AGREGAT UNTUK PERAN ATASAN
// =========================================================================
$sql_atasan = "SELECT 
                    COUNT(CASE WHEN status = 0 THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 1 THEN 1 END) as progress_count
               FROM {local_myidpebi} 
               WHERE atasan_id = :atasanid";[cite: 12, 13]
$atasan_stats = $DB->get_record_sql($sql_atasan, ['atasanid' => $USER->id]);[cite: 13]

// =========================================================================
// 2. DATA UTAMA UNTUK PERAN KARYAWAN (IDP SAYA)
// =========================================================================
$my_idp = $DB->get_record('local_myidpebi', ['userid' => $USER->id], '*', IGNORE_MULTIPLE);[cite: 12, 13]
?>

<div class="row myidpebi-dashboard-widget">
    
    <!-- ─── PANEL ATASAN (Hanya muncul jika memiliki bimbingan aktif) ─── -->
    <?php if ($atasan_stats && ($atasan_stats->pending_count > 0 || $atasan_stats->progress_count > 0)): ?>
        <div class="col-12 mb-3">
            <div class="card shadow-sm border-left-info">
                <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                    <h6 class="m-0 font-weight-bold text-info"><i class="fa fa-users"></i> Persetujuan IDP Tim Anda (Atasan)</h6>
                    <a href="<?php echo new moodle_url('/local/myidpebi/manage.php'); ?>" class="btn btn-sm btn-info text-white"><i class="fa fa-chevron-right"></i> Buka Manajemen Kelola</a>[cite: 13]
                </div>
                <div class="card-body py-3">
                    <div class="row">
                        <div class="col-6 text-center border-right">
                            <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">Menunggu Approval</div>[cite: 13]
                            <div class="h4 mb-0 font-weight-bold <?php echo ($atasan_stats->pending_count > 0) ? 'text-danger' : 'text-secondary'; ?>">
                                <?php echo (int)$atasan_stats->pending_count; ?> <small style="font-size:13px;">Dokumen</small>
                            </div>
                        </div>
                        <div class="col-6 text-center">
                            <div class="text-xs font-weight-bold text-muted text-uppercase mb-1">Sedang Berjalan</div>[cite: 13]
                            <div class="h4 mb-0 font-weight-bold text-warning">
                                <?php echo (int)$atasan_stats->progress_count; ?> <small style="font-size:13px;">Karyawan</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ─── PANEL KARYAWAN (IDP SAYA) ─── -->
    <div class="col-12 mb-3">
        <div class="card shadow-sm border-left-primary">
            <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fa fa-user"></i> Program Pengembangan Saya (IDP)</h6>
                <?php if ($my_idp): ?>
                    <a href="<?php echo new moodle_url('/local/myidpebi/view_details.php', ['id' => $my_idp->id]); ?>" class="btn btn-sm btn-outline-primary">Buka Rincian</a>[cite: 13]
                <?php endif; ?>
            </div>
            <div class="card-body py-3">
                <?php if ($my_idp): 
                    // Ambil informasi badge status dari lib.php
                    $status_info = local_myidpebi_get_status_info($my_idp->status);[cite: 14]
                    
                    // Hitung total akumulasi perencanaan & realisasi JP dari sub-tabel aktivitas
                    $total_rencana = $DB->get_field_sql("SELECT SUM(jumlah_jp_perencanaan) FROM {local_myidpebi_act} WHERE idp_id = ? AND deleted = 0", [$my_idp->id]) ?: 0;[cite: 12]
                    $total_realisasi = $DB->get_field_sql("SELECT SUM(jumlah_jp_realisasi) FROM {local_myidpebi_act} WHERE idp_id = ? AND deleted = 0", [$my_idp->id]) ?: 0;[cite: 12]
                    
                    // Kalkulasi rasio persentase untuk kemajuan progress bar
                    $progress_percent = ($total_rencana > 0) ? round(($total_realisasi / $total_rencana) * 100) : 0;
                    $progress_percent = min($progress_percent, 100); // Kunci batas atas di 100%
                ?>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="text-muted d-block" style="font-size: 11px;">Nama Kegiatan Program:</span>
                            <strong class="text-dark d-block mb-1" style="font-size: 14px;"><?php echo s($my_idp->nama_idp); ?></strong>[cite: 12, 13]
                            <span class="text-muted" style="font-size: 11px;">
                                Target Batas Akhir: <span class="text-dark font-weight-bold"><?php echo userdate($my_idp->akhir_date, '%d %b %Y'); ?></span>[cite: 12, 13]
                            </span>
                        </div>
                        <div class="text-right">
                            <?php echo $status_info->badge; ?>[cite: 14]
                        </div>
                    </div>

                    <!-- Visualisasi Progress Bar Ketercapaian Target JP -->
                    <div class="mt-3">
                        <div class="d-flex justify-content-between text-muted mb-1" style="font-size: 11px;">
                            <span>Progress Akumulasi Jam Pembelajaran (JP):</span>
                            <strong><?php echo $total_realisasi; ?> / <?php echo $total_rencana; ?> JP (<?php echo $progress_percent; ?>%)</strong>
                        </div>
                        <div class="progress" style="height: 10px; background-color: #e9ecef;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: <?php echo $progress_percent; ?>%;" 
                                 aria-valuenow="<?php echo $progress_percent; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-2">
                        <p class="mb-0" style="font-size: 13px;">Anda belum memiliki usulan program IDP aktif pada periode saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>