<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $PAGE, $OUTPUT, $USER;

// Proteksi Akses: Karyawan harus login ke Moodle
require_login();

$baseurl = new moodle_url('/local/myidpebi/view_learning_activity.php');
$PAGE->set_url($baseurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Kamus Panduan Aktivitas Pembelajaran IDP');
$PAGE->set_heading('Kamus Panduan Aktivitas Pembelajaran IDP');

// Atur agar halaman tampil penuh tanpa blok samping jika tema mendukung
$PAGE->set_pagelayout('report'); 

echo $OUTPUT->header();

// Mengambil seluruh data dari database master
$records = $DB->get_records('local_myidpebi_learning_activity', null, 'bentuk_cdp ASC, tipe_aktivitas_cdp ASC');

echo '<div class="container-fluid mt-3">';
echo '  <div class="card shadow-sm">';
echo '      <div class="card-header bg-dark text-white py-3">';
echo '          <h5 class="mb-0 text-white"><i class="fa fa-graduation-cap"></i> Kamus Panduan Aktivitas Pembelajaran IDP</h5>';
echo '      </div>';
echo '      <div class="card-body p-0">';
echo '          <div class="table-responsive">';
echo '              <table class="table table-striped table-bordered table-hover mb-0" style="font-size: 13.5px;">';
echo '                  <thead class="thead-light">';
echo '                      <tr>';
echo '                          <th>Tipe Aktivitas</th>';
echo '                          <th>Aktivitas Pembelajaran</th>';
echo '                          <th class="text-center" width="12%">Batas Maksimum</th>';
echo '                          <th width="40%">Panduan Pelaksanaan & Bentuk Laporan (Evidence)</th>';
echo '                      </tr>';
echo '                  </thead>';
echo '                  <tbody>';

if (!empty($records)) {
    foreach ($records as $r) {
        // Mengubah karakter pipa "|" menjadi baris baru agar rapi dibaca karyawan
        $clean_evidence = str_replace('|', '<br class="mb-1">', s($r->bentuk_evidence));
        
        echo '<tr>';
        echo '  <td class="align-middle"><b>' . s($r->tipe_aktivitas_cdp) . '</b></td>';
        echo '  <td class="align-middle text-dark font-weight-bold">' . s($r->learning_activity) . '</td>';
        echo '  <td class="text-center align-middle bg-light"><strong>' . (int)$r->jp_max . ' JP</strong></td>';
        echo '  <td class="align-middle text-muted" style="line-height: 1.5;">' . $clean_evidence . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="5" class="text-center text-muted p-4">Belum ada data panduan aktivitas yang diinput oleh Admin.</td></tr>';
}

echo '                  </tbody>';
echo '              </table>';
echo '          </div>';
echo '      </div>';
echo '  </div>';
echo '  <div class="mt-3 text-right">';
echo '      <button type="button" class="btn btn-secondary" onclick="window.close();"><i class="fa fa-times"></i> Tutup Halaman</button>';
echo '  </div>';
echo '</div>';

echo $OUTPUT->footer();