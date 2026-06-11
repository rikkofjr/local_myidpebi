<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER, $PAGE, $OUTPUT;

// 1. PROTEKSI AKSES: Hanya Site Admin atau Manager yang boleh mengelola kuesioner
require_login();
$context = context_system::instance();
$is_manager = has_capability('moodle/site:viewreports', $context);

if (!is_siteadmin() && !$is_manager) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Tangkap Parameter Operasi CRUD
$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$type   = optional_param('type', 'karyawan', PARAM_ALPHA); // karyawan atau atasan

// 3. Setup Halaman Moodle
$url = new moodle_url('/local/myidpebi/admin_questions.php', ['type' => $type]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Kelola Pertanyaan Kuesioner IDP');
$PAGE->set_heading('Panel Pengaturan Pertanyaan Efektivitas IDP');

// =========================================================================
// 🟢 LOGIKA PROSES BEKANG (CRUD ACTIONS)
// =========================================================================

// A. AKSI TAMBAH / EDIT DATA
if (($action === 'insert' || $action === 'update') && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $question_text = required_param('question_text', PARAM_TEXT);
    $q_sort        = required_param('q_sort', PARAM_INT);
    $q_type        = required_param('q_type', PARAM_ALPHA);

    $record = new stdClass();
    $record->q_type        = $q_type;
    $record->q_sort        = $q_sort;
    $record->question_text = trim($question_text);

    if ($action === 'insert') {
        $record->is_active   = 1;
        $record->timecreated = time();
        $DB->insert_record('local_myidpebi_questions', $record);
        $msg = 'Pertanyaan kuesioner baru berhasil ditambahkan.';
    } else {
        $record->id = $id;
        $DB->update_record('local_myidpebi_questions', $record);
        $msg = 'Perubahan butir pertanyaan berhasil disimpan.';
    }

    redirect(new moodle_url($PAGE->url), $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// B. AKSI SAKLAR AKTIF / NONAKTIF (TOGGLE STATUS)
if ($action === 'toggle' && $id > 0 && confirm_sesskey()) {
    $current_q = $DB->get_record('local_myidpebi_questions', ['id' => $id], 'id, is_active', MUST_EXIST);
    
    $update_status = new stdClass();
    $update_status->id        = $current_q->id;
    $update_status->is_active = ($current_q->is_active == 1) ? 0 : 1;
    
    $DB->update_record('local_myidpebi_questions', $update_status);
    redirect(new moodle_url($PAGE->url), 'Status aktifasi pertanyaan berhasil diperbarui.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// C. AKSI HAPUS PERTANYAAN
if ($action === 'delete' && $id > 0 && confirm_sesskey()) {
    $DB->delete_records('local_myidpebi_questions', ['id' => $id]);
    redirect(new moodle_url($PAGE->url), 'Pertanyaan berhasil dihapus dari sistem master.', null, \core\output\notification::NOTIFY_SUCCESS);
}
// =========================================================================
// 🔵 MULAI OUTPUT TAMPILAN FRONTEND (UI Halaman)
// =========================================================================

// --- NAVIGASI BREADCRUMB ---
$PAGE->navbar->add('Admin Panel', new moodle_url('/local/myidpebi/admin_panel.php'));
$PAGE->navbar->add('Learning Activity');

echo $OUTPUT->header();
echo '<div class="container mt-4">';

// --- NAVIGASI TAB KATEGORI KUESIONER ---
echo '<ul class="nav nav-tabs mb-4">';
echo '  <li class="nav-item"><a class="nav-link ' . ($type === 'karyawan' ? 'active font-weight-bold' : '') . '" href="' . new moodle_url($PAGE->url, ['type' => 'karyawan']) . '"><i class="fa fa-user"></i> Kuesioner Mandiri Karyawan</a></li>';
echo '  <li class="nav-item"><a class="nav-link ' . ($type === 'atasan' ? 'active font-weight-bold' : '') . '" href="' . new moodle_url($PAGE->url, ['type' => 'atasan']) . '"><i class="fa fa-users"></i> Kuesioner Penilaian Atasan</a></li>';
echo '</ul>';

// Ambil data untuk mode EDIT jika ID dilempar ke URL
$edit_q = null;
if ($action === 'edit' && $id > 0) {
    $edit_q = $DB->get_record('local_myidpebi_questions', ['id' => $id]);
}

// --- PANEL FORM INPUT (TAMBAH / EDIT) ---
$form_action = $edit_q ? 'update' : 'insert';
echo '<div class="card shadow-sm mb-4">';
echo '  <div class="card-header bg-light"><strong><i class="fa fa-pencil"></i> ' . ($edit_q ? 'Edit Data Pertanyaan' : 'Tambah Pertanyaan Baru') . '</strong></div>';
echo '  <div class="card-body">';
echo '      <form action="' . new moodle_url($PAGE->url, ['action' => $form_action]) . '" method="POST" class="form-inline">';
echo '          <input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '          <input type="hidden" name="q_type" value="' . s($type) . '">';
if ($edit_q) {
    echo '      <input type="hidden" name="id" value="' . $edit_q->id . '">';
}

// Kolom Nomor Urut (Sort Order)
$default_sort = $edit_q ? $edit_q->q_sort : ($DB->count_records('local_myidpebi_questions', ['q_type' => $type]) + 1);
echo '          <div class="form-group mr-2">';
echo '              <label class="mr-2">No Urut:</label>';
echo '              <input type="number" name="q_sort" class="form-control" style="width:80px;" value="' . $default_sort . '" required>';
echo '          </div>';

// Kolom Teks Pertanyaan
$current_text = $edit_q ? $edit_q->question_text : '';
echo '          <div class="form-group mr-2 flex-grow-1" style="width: 60%;">';
echo '              <label class="mr-2">Teks Pertanyaan:</label>';
echo '              <input type="text" name="question_text" class="form-control w-100" placeholder="Masukkan butir kuesioner disini..." value="' . s($current_text) . '" required>';
echo '          </div>';

// Tombol Aksi Form
echo '          <button type="submit" class="btn ' . ($edit_q ? 'btn-warning text-dark' : 'btn-primary') . '"><i class="fa fa-save"></i> ' . ($edit_q ? 'Simpan Perubahan' : 'Tambahkan') . '</button>';
if ($edit_q) {
    echo '      <a href="' . $PAGE->url . '" class="btn btn-secondary ml-2">Batal</a>';
}
echo '      </form>';
echo '  </div>';
echo '</div>';


// --- TABEL DAFTAR MASTER PERTANYAAN (DIBACA DARI DATABASE) ---
echo '<div class="card shadow-sm">';
echo '  <div class="card-header bg-dark"><h5 class="text-white"><i class="fa fa-list"></i> Daftar Pertanyaan Aktif Saat Ini</h5></div>';
echo '  <div class="card-body p-0">';

// Mengambil data real dari database menggunakan Query diurutkan berdasarkan q_sort
$all_questions = $DB->get_records('local_myidpebi_questions', ['q_type' => $type], 'q_sort ASC');

if (!empty($all_questions)) {
    echo '<table class="table table-bordered table-striped table-hover mb-0">';
    echo '  <thead class="thead-light"><tr>';
    echo '      <th width="8%" class="text-center">No Urut</th>';
    echo '      <th>Isi Butir Pertanyaan Kuesioner</th>';
    echo '      <th width="15%" class="text-center">Status Tampil</th>';
    echo '      <th width="18%" class="text-center">Aksi</th>';
    echo '  </tr></thead>';
    echo '  <tbody>';
    
    foreach ($all_questions as $q) {
        echo '<tr>';
        echo '  <td class="text-center font-weight-bold">' . s($q->q_sort) . '</td>';
        echo '  <td>' . s($q->question_text) . '</td>';
        
        // Kolom Status (Badge Aktif / Nonaktif)
        echo '  <td class="text-center">';
        if ($q->is_active == 1) {
            echo '  <span class="badge badge-success p-2"><i class="fa fa-check"></i> Aktif</span>';
        } else {
            echo '  <span class="badge badge-secondary p-2"><i class="fa fa-eye-slash"></i> Nonaktif</span>';
        }
        echo '  </td>';
        
        // Kolom Aksi Tombol Kendali Lapisan CRUD
        echo '  <td class="text-center">';
        
        // 1. URL Edit
        $edit_url = new moodle_url($PAGE->url, ['action' => 'edit', 'id' => $q->id]);
        echo '      <a href="' . $edit_url . '" class="btn btn-sm btn-info mr-1" title="Edit Teks"><i class="fa fa-edit"></i></a>';
        
        // 2. URL Saklar Status (Toggle)
        $toggle_url = new moodle_url($PAGE->url, ['action' => 'toggle', 'id' => $q->id, 'sesskey' => sesskey()]);
        $toggle_class = ($q->is_active == 1) ? 'btn-secondary' : 'btn-success';
        $toggle_icon = ($q->is_active == 1) ? 'fa-eye-slash' : 'fa-eye';
        echo '      <a href="' . $toggle_url . '" class="btn btn-sm ' . $toggle_class . ' mr-1" title="Matikan/Hidupkan"><i class="fa ' . $toggle_icon . '"></i></a>';
        
        // 3. URL Hapus Permanen
        $delete_url = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $q->id, 'sesskey' => sesskey()]);
        echo '      <a href="' . $delete_url . '" class="btn btn-sm btn-danger" title="Hapus Permanen" onclick="return confirm(\'Apakah Anda yakin ingin menghapus pertanyaan ini dari database master?\');"><i class="fa fa-trash"></i></a>';
        
        echo '  </td>';
        echo '</tr>';
    }
    
    echo '  </tbody>';
    echo '</table>';
} else {
    echo '<div class="p-4 text-center text-muted"><i class="fa fa-folder-open-o fa-2x"></i><br>Belum ada data pertanyaan di dalam database master. Silakan tambahkan melalui form di atas.</div>';
}

echo '  </div>';
echo '</div>'; // End Card

echo '</div>'; // End Container
echo $OUTPUT->footer();