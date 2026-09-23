<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Site Admin atau Manager yang boleh masuk
require_login();
$context = context_system::instance();
$is_manager = has_capability('local/myidpebi:reset_idp', $context);

if (!is_siteadmin() && !$is_manager) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Tangkap Parameter ID IDP
$idp_id = required_param('id', PARAM_INT);
$idp = $DB->get_record('local_myidpebi', ['id' => $idp_id], '*', MUST_EXIST);
$karyawan = $DB->get_record('user', ['id' => $idp->userid], 'id, firstname, lastname, username, email', MUST_EXIST);

// 3. Setup Halaman Moodle
$url = new moodle_url('/local/myidpebi/admin_edit_idp.php', ['id' => $idp_id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Dashboard Kendali IDP : '.$idp->nama_idp.' '.$karyawan->firstname.'');
$PAGE->set_heading('Dashboard Kendali IDP :'.$idp->nama_idp.' '.$karyawan->firstname.' ');

// 🟢 INTEGRASI LIB.PHP: Ambil data Atasan Langsung terbaru dari Profil Kustom secara real-time
$atasan_profile_raw = local_myidpebi_get_atasan_username($idp->userid);
$atasan_profile_user = !empty($atasan_profile_raw) ? local_myidpebi_get_user_by_config($atasan_profile_raw) : false;

// 🟢 LOGIKA PROSES RESET (Saat Form Dihantar/Submit)
if (optional_param('action', '', PARAM_ALPHA) === 'confirmreset' && confirm_sesskey()) {
    
    // Ambil catatan alasan reset dari form
    $notes = required_param('reset_notes', PARAM_TEXT);

    $oldstatus = $idp->status;
    $newstatus = 0;

    // 1. Update data IDP
    $reset_data = new stdClass();
    $reset_data->id                  = $idp->id;
    $reset_data->status              = $newstatus; 
    $reset_data->atasan_id           = $atasan_profile_user ? (int)$atasan_profile_user->id : $idp->atasan_id; 
    $reset_data->approved_by         = 0;
    $reset_data->verified_by         = 0;
    $reset_data->verified_ldc_by     = 0;
    $reset_data->skor_efektivitas    = 0.00;
    $reset_data->skor_atasan         = 0.00;
    $reset_data->kesimpulan_karyawan = null;
    $reset_data->kesimpulan_atasan   = null;
    $reset_data->kesimpulan_hcm      = null;
    $reset_data->timemodified        = time();

    $DB->update_record('local_myidpebi', $reset_data);

    // 2. 🟢 CATAT KE TABEL LOG IDP (Sertakan alasan dari modal/form)
    local_myidpebi_add_log(
        $idp->id,
        $USER->id,
        'reset_admin',
        $oldstatus,
        $newstatus,
        $notes
    );

    // 3. 🟢 CATAT KE MOODLE SYSTEM EVENT LOG
    $event = \local_myidpebi\event\idp_status_changed::create([
        'objectid' => $idp->id,
        'userid'   => $USER->id,
        'context'  => $context,
        'other'    => [
            'status_code' => $newstatus,
            'notes'       => $notes
        ]
    ]);
    $event->trigger();

    $monitor_url = new moodle_url('/local/myidpebi/admin_monitor.php');
    redirect($monitor_url, 'Dokumen IDP ' . s($idp->nama_idp) . ' berhasil di-reset ke status Draft.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// 4. MEMULAI OUTPUT TAMPILAN HALAMAN UI
echo $OUTPUT->header();
echo '<div class="container mt-4">';

// Tombol Kembali
$back_url = new moodle_url('/local/myidpebi/admin_monitor.php');
echo '<a href="' . $back_url . '" class="btn btn-secondary mb-3"><i class="fa fa-arrow-left"></i> Kembali ke Monitoring</a>';

echo '<div class="row">';
echo '  <div class="col-md-7">';
echo '      <div class="card shadow-sm mb-4">';
echo '          <div class="card-header bg-dark"><h5 class="text-white"><i class="fa fa-info-circle"></i> Detail Dokumen IDP Karyawan</h5></div>';
echo '          <div class="card-body">';
echo '              <table class="table table-hover table-striped">';
echo '                  <tr><th>Nama Program</th><td><strong>' . s($idp->nama_idp) . '</strong></td></tr>';
echo '                  <tr><th>Pemilik / Karyawan</th><td>' . s($karyawan->firstname . ' ' . $karyawan->lastname) . ' (' . s($karyawan->username) . ')</td></tr>';
echo '                  <tr><th>Email Karyawan</th><td>' . s($karyawan->email) . '</td></tr>';
echo '                  <tr><th>Periode IDP</th><td>' . userdate($idp->mulai_date, '%d %B %Y') . ' s/d ' . userdate($idp->akhir_date, '%d %B %Y') . '</td></tr>';
echo '                  <tr>';
echo '                      <th>Status Dokumen</th>';
echo '                      <td>';
                            $status_info = local_myidpebi_get_status_info($idp->status);
echo                        $status_info->badge;
echo '                      </td>';
echo '                  </tr>';
echo '              </table>';
echo '          </div>';
echo '      </div>';

echo '      <div class="card shadow-sm mb-4">';
echo '          <div class="card-header bg-info text-white"><h5><i class="fa fa-calculator"></i> Data Evaluasi Nilai Saat Ini (Akan Dihapus)</h5></div>';
echo '          <div class="card-body">';
echo '              <div class="row text-center">';
echo '                  <div class="col-md-6">';
echo '                      <div class="border rounded p-3 bg-light">';
echo '                          <h6>Skor Mandiri Karyawan</h6>';
echo '                          <h3 class="text-primary">' . number_format($idp->skor_efektivitas, 2) . '%</h3>';
echo '                      </div>';
echo '                  </div>';
echo '                  <div class="col-md-6">';
echo '                      <div class="border rounded p-3 bg-light">';
echo '                          <h6>Skor Penilaian Atasan</h6>';
echo '                          <h3 class="text-success">' . number_format($idp->skor_atasan, 2) . '%</h3>';
echo '                      </div>';
echo '                  </div>';
echo '              </div>';
echo '          </div>';
echo '      </div>';
echo '  </div>'; // End col-md-7

echo '  <div class="col-md-5">';
echo '      <div class="card shadow-sm border-warning mb-4">';
echo '          <div class="card-header bg-warning text-dark"><h5><i class="fa fa-users"></i> Sinkronisasi Struktur Atasan</h5></div>';
echo '          <div class="card-body">';
                // Ambil info pembimbing lama dokumen ini
                $pembimbing_lama = $DB->get_record('user', ['id' => $idp->atasan_id], 'firstname, lastname, username');
echo '              <h6>Pembimbing di Dokumen (Lama):</h6>';
echo '              <p class="alert alert-secondary mb-3"><i class="fa fa-user"></i> ' . ($pembimbing_lama ? s($pembimbing_lama->firstname . ' ' . $pembimbing_lama->lastname . ' (NIK: ' . $pembimbing_lama->username . ')') : 'Tidak Ada') . '</p>';

echo '              <h6>Atasan Langsung di Sistem Profil (Terbaru):</h6>';
                if ($atasan_profile_user) {
echo '                  <p class="alert alert-success mb-1"><i class="fa fa-check-circle"></i> ' . s($atasan_profile_user->firstname . ' ' . $atasan_profile_user->lastname . ' (NIK: ' . $atasan_profile_user->username . ')') . '</p>';
echo '                  <small class="text-muted text-success"><i class="fa fa-arrow-right"></i> Dokumen otomatis akan dipindahkan ke atasan baru ini setelah reset.</small>';
                } else {
echo '                  <p class="alert alert-danger mb-0"><i class="fa fa-times-circle"></i> Data profil kustom ("' . s($atasan_profile_raw) . '") tidak cocok dengan user Moodle manapun.</p>';
                }
echo '          </div>';
echo '      </div>';

echo '      <div class="card shadow-sm border-danger">';
echo '          <div class="card-header bg-danger text-white"><h5><i class="fa fa-refresh"></i> Eksekusi IDP</h5></div>';
echo '          <div class="card-body text-center">';
echo '              <p class="text-danger font-weight-bold">Tindakan ini akan mengembalikan alur program ke posisi awal <br/> Aktivitas Pembelajaran tidak tereset</p>';

// 🟢 TOMBOL UNTUK MEMBUKA MODAL POP-UP
echo '  <button type="button" class="btn btn-danger btn-lg btn-block text-white mb-2" data-toggle="modal" data-target="#resetModal" data-bs-toggle="modal" data-bs-target="#resetModal">';
echo '      <i class="fa fa-refresh"></i> Jalankan Reset';
echo '  </button>';
echo '              <span class="small text-muted"><i class="fa fa-lock"></i> Dilindungi Token Keamanan Sesskey Moodle</span>';
echo '          </div>';
echo '      </div>';
echo '  </div>'; // End col-md-5
echo '</div>'; // End Row
echo '</div>'; // End Container

// 🟢 POP-UP MODAL BOOTSTRAP UNTUK INPUT CATATAN RESET
?>
<div class="modal fade" id="resetModal" tabindex="-1" role="dialog" aria-labelledby="resetModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form action="<?php echo $PAGE->url; ?>" method="post">
        <input type="hidden" name="action" value="confirmreset">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="resetModalLabel"><i class="fa fa-exclamation-triangle mr-2"></i>Konfirmasi Reset Dokumen IDP</h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body text-left">
          <div class="alert alert-warning mb-3">
            <small><strong>Perhatian:</strong> Tindakan ini akan mengembalikan status menjadi Draft (0), menghapus persetujuan atasan, dan mengosongkan nilai asesmen. Karyawan dapat melihat alasan ini pada riwayat IDP.</small>
          </div>
          
          <div class="form-group">
            <label for="reset_notes" class="font-weight-bold">Alasan Melakukan Reset <span class="text-danger">*</span></label>
            <textarea class="form-control" id="reset_notes" name="reset_notes" rows="4" placeholder="Tuliskan catatan detail mengapa IDP ini perlu di-reset (misal: penyesuaian target kompetensi baru / perbaikan kesalahan data)..." required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger"><i class="fa fa-refresh mr-1"></i> Ya, Reset IDP Ini</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php

echo $OUTPUT->footer();