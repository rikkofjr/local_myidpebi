<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Site Admin atau Manager yang boleh masuk ke panel ini
require_login();
$context = context_system::instance();
$is_manager = has_capability('moodle/site:viewreports', $context);

if (!is_siteadmin() && !$is_manager) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Setup Halaman Moodle
$url = new moodle_url('/local/myidpebi/admin_panel.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Panel Kontrol IDP');
$PAGE->set_heading('Panel Kontrol IDP');

// 3. Tarik Statistik Ringkas Real-time untuk Dashboard Info
$total_idp       = $DB->count_records('local_myidpebi');
$total_draft     = $DB->count_records('local_myidpebi', ['status' => 0]);
$total_berjalan  = $DB->count_records('local_myidpebi', ['status' => 1]);
$total_selesai   = $DB->count_records('local_myidpebi', ['status' => 2]);
$total_pertanyaan_atasan = $DB->count_records('local_myidpebi_questions', ['q_type' => 'atasan']);
$total_pertanyaan_karyawan = $DB->count_records('local_myidpebi_questions', ['q_type' => 'karyawan']);

echo $OUTPUT->header();
echo '<div class="container mt-4">';

// --- BARIS 1: METRIK STATISTIK CEPAT ---
echo '<div class="row mb-4">';
echo '  <div class="col-md-3">';
echo '      <div class="card bg-primary text-white text-center p-3 shadow-sm">';
echo '          <h6>Total Dokumen IDP</h6><h3>' . $total_idp . '</h3>';
echo '      </div>';
echo '  </div>';
echo '  <div class="col-md-3">';
echo '      <div class="card bg-secondary text-white text-center p-3 shadow-sm">';
echo '          <h6>Status Draft (0)</h6><h3>' . $total_draft . '</h3>';
echo '      </div>';
echo '  </div>';
echo '  <div class="col-md-3">';
echo '      <div class="card bg-warning text-dark text-center p-3 shadow-sm">';
echo '          <h6>Status Berjalan (1)</h6><h3>' . $total_berjalan . '</h3>';
echo '      </div>';
echo '  </div>';
echo '  <div class="col-md-3">';
echo '      <div class="card bg-success text-white text-center p-3 shadow-sm">';
echo '          <h6>Status Selesai (2)</h6><h3>' . $total_selesai . '</h3>';
echo '      </div>';
echo '  </div>';
echo '</div>';

echo '<hr class="mb-4">';

// --- BARIS 2: MENU NAVIGASI UTAMA (CARD KONTROL) ---
echo '<div class="row">';

// 📌 CARD 1: MONITORING & OVERRIDE RESET
$monitor_url = new moodle_url('/local/myidpebi/admin_monitor.php');
echo '  <div class="col-md-4 mb-4">';
echo '      <div class="card h-100 shadow-sm border-dark">';
echo '          <div class="card-body d-flex flex-column">';
echo '              <h5 class="card-title text-dark"><i class="fa fa-television fa-2x text-primary mr-2"></i> Monitoring IDP</h5>';
echo '              <p class="card-text text-muted flex-grow-1">Pantau seluruh dokumen IDP milik karyawan, filter berdasarkan departemen, serta lakukan tindakan <strong>Emergency Reset</strong> jika alur dokumen tersangkut.</p>';
echo '              <a href="' . $monitor_url . '" class="btn btn-primary btn-block mt-3">Buka Monitoring <i class="fa fa-arrow-circle-right"></i></a>';
echo '          </div>';
echo '      </div>';
echo '  </div>';

// 📌 CARD 2: KELOLA PERTANYAAN KUESIONER (Dinamis Baru)
$questions_url = new moodle_url('/local/myidpebi/admin_questions.php');
echo '  <div class="col-md-4 mb-4">';
echo '      <div class="card h-100 shadow-sm border-dark">';
echo '          <div class="card-body d-flex flex-column">';
echo '              <h5 class="card-title text-dark"><i class="fa fa-list-ol fa-2x text-success mr-2"></i> Kelola Kuesioner</h5>';
echo '              <p class="card-text text-muted flex-grow-1">
                        Ubah, tambah, atau nonaktifkan butir pertanyaan kuesioner evaluasi efektivitas secara dinamis untuk Mandiri Karyawan maupun Penilaian Atasan. <br/>
                        (Saat ini: ' . $total_pertanyaan_atasan . ' kuesioner atasan)<br/>
                        (Saat ini: ' . $total_pertanyaan_karyawan . ' kuesioner karyawan)<br/>
                    
                    </p>';
echo '              <a href="' . $questions_url . '" class="btn btn-success btn-block mt-3">Buka Pengaturan Soal <i class="fa fa-arrow-circle-right"></i></a>';
echo '          </div>';
echo '      </div>';
echo '  </div>';

// 📌 CARD 3: CONFIGURATION SETTINGS (Site Administration Shortcut)
$settings_url = new moodle_url('/admin/settings.php', ['section' => 'local_myidpebi']);
echo '  <div class="col-md-4 mb-4">';
echo '      <div class="card h-100 shadow-sm border-dark">';
echo '          <div class="card-body d-flex flex-column">';
echo '              <h5 class="card-title text-dark"><i class="fa fa-cogs fa-2x text-danger mr-2"></i> Konfigurasi Sistem</h5>';
echo '              <p class="card-text text-muted flex-grow-1">Atur pemetaan field kustom atasan langsung, pilih mode identitas utama (pencarian via NIK / Email), serta ubah parameter global plugin lainnya.</p>';
echo '              <a href="' . $settings_url . '" class="btn btn-danger btn-block mt-3">Buka UI Settings <i class="fa fa-arrow-circle-right"></i></a>';
echo '          </div>';
echo '      </div>';
echo '  </div>';

// 📌 CARD 4: CONFIGURATION LEARNING ACTIVITY
$learning_activity_url = new moodle_url('/local/myidpebi/admin_learning_activity.php');
echo '  <div class="col-md-4 mb-4">';
echo '      <div class="card h-100 shadow-sm border-dark">';
echo '          <div class="card-body d-flex flex-column">';
echo '              <h5 class="card-title text-dark"><i class="fa fa-book fa-2x text-danger mr-2"></i> Learning Activity</h5>';
echo '              <p class="card-text text-muted flex-grow-1">Atur aktivitas pembelajaran, JP dari aktivitas tersebut, dan evidence apa yang digunakan sesuai dengan jenis aktivitas pembelajaran.</p>';
echo '              <a href="' . $learning_activity_url . '" class="btn btn-danger btn-block mt-3">Buka <i class="fa fa-arrow-circle-right"></i></a>';
echo '          </div>';
echo '      </div>';
echo '  </div>';

echo '</div>'; // End Row

echo '</div>'; // End Container

echo $OUTPUT->footer();