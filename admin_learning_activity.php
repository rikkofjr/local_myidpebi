<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $PAGE, $OUTPUT, $USER;

// 1. Proteksi Akses: Hanya Admin atau Manager yang punya hak viewreports
require_login();
$context = context_system::instance();
if (!is_siteadmin() && !has_capability('moodle/site:viewreports', $context)) {
    throw new moodle_exception('nopermissiontoaccesspage', 'error');
}

// 2. Inisialisasi Parameter Aksi CRUD
$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);

// 3. Konfigurasi Navigasi & Halaman Moodle
$baseurl = new moodle_url('/local/myidpebi/admin_learning_activity.php');
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Manajemen Aturan JP Aktifitas Pembelajaran');
$PAGE->set_heading('Manajemen Aturan JP Aktifitas Pembelajaran');

// --- 4. PROSES LOGIKA AKSI (POST DATA) ---
if ($action == 'save' && data_submitted() && confirm_sesskey()) {
    $bentuk_cdp         = required_param('bentuk_cdp', PARAM_TEXT);
    $tipe_aktivitas_cdp         = required_param('tipe_aktivitas_cdp', PARAM_TEXT);
    $learning_activity         = required_param('learning_activity', PARAM_TEXT);
    $jp_min                 = required_param('jp_min', PARAM_INT);
    $jp_max                 = required_param('jp_max', PARAM_INT);
    $bentuk_evidence        = required_param('bentuk_evidence', PARAM_TEXT);

    $record = new stdClass();
    $record->bentuk_cdp = trim($bentuk_cdp);
    $record->tipe_aktivitas_cdp = trim($tipe_aktivitas_cdp);
    $record->learning_activity = trim($learning_activity);
    $record->jp_min         = $jp_min;
    $record->jp_max         = $jp_max;
    $record->bentuk_evidence = $bentuk_evidence;

    if ($id > 0) {
        // Mode Update
        $record->id = $id;
        $DB->update_record('local_myidpebi_learning_activity', $record);
        $msg = "Aturan Aktifitas Pembelajaran berhasil diperbarui.";
    } else {
        // Mode Insert Baru
        $record->timecreated = time();
        $DB->insert_record('local_myidpebi_learning_activity', $record);
        $msg = "Aktifitas Pembelajaran baru berhasil ditambahkan.";
    }
    redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action == 'delete' && $id > 0 && confirm_sesskey()) {
    $DB->delete_records('local_myidpebi_learning_activity', ['id' => $id]);
    redirect($baseurl, 'Aktifitas Pembelajaran berhasil dihapus.', null, \core\output\notification::NOTIFY_SUCCESS);
}
// --- NAVIGASI BREADCRUMB ---
$PAGE->navbar->add('Admin Panel', new moodle_url('/local/myidpebi/admin_panel.php'));
$PAGE->navbar->add('Learning Activity');

// Memulai Output Tampilan
echo $OUTPUT->header();



// --- 5. RENDER FORM TAMBAH / EDIT ---
if ($action == 'add' || $action == 'edit') {
    $current = new stdClass();
    $current->bentuk_cdp = '';
    $current->tipe_aktivitas_cdp = '';
    $current->learning_activity = '';
    $current->bentuk_evidence = '';
    $current->jp_min = 0;
    $current->jp_max = 0;

    if ($id > 0) {
        $current = $DB->get_record('local_myidpebi_learning_activity', ['id' => $id], '*', MUST_EXIST);
    }
    ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-4">
                <i class="fa <?php echo ($id > 0) ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> 
                <?php echo ($id > 0) ? 'Ubah Aktifitas Pembelajaran' : 'Tambah Aktifitas Pembelajaran Baru'; ?>
            </h5>
            <form method="POST" action="<?php echo $baseurl; ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Bentuk CDP</label>
                    <div class="col-md-6">
                        <input type="text" name="bentuk_cdp" class="form-control" value="<?php echo s($current->bentuk_cdp); ?>" required placeholder="Contoh: Jalur Pendidikan">
                    </div>
                </div>
                
                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Tipe Aktivitas CDP</label>
                    <div class="col-md-6">
                        <input type="text" name="tipe_aktivitas_cdp" class="form-control" value="<?php echo s($current->tipe_aktivitas_cdp); ?>" required placeholder="Contoh: Formal">
                    </div>
                </div>
                
                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Nama Aktifitas Pembelajaran</label>
                    <div class="col-md-6">
                        <input type="text" name="learning_activity" class="form-control" value="<?php echo s($current->learning_activity); ?>" required placeholder="Contoh: Belajar Mandiri">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Batas Minimal (JP)</label>
                    <div class="col-md-3">
                        <input type="number" name="jp_min" class="form-control" value="<?php echo (int)$current->jp_min; ?>" min="0" required>
                    </div>
                </div>

                <div class="form-group row mb-4">
                    <label class="col-md-3 col-form-label font-weight-bold">Batas Maksimal (JP)</label>
                    <div class="col-md-3">
                        <input type="number" name="jp_max" class="form-control" value="<?php echo (int)$current->jp_max; ?>" min="0" required>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Bentuk Evidence</label>
                    <div class="col-md-6">
                        <textarea name="bentuk_evidence" id=""><?php echo s($current->bentuk_evidence); ?></textarea>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="col-md-9 offset-md-3">
                        <a href="<?php echo $baseurl; ?>" class="btn btn-secondary mr-2">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">Simpan Kegiatan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
} 

// --- 6. RENDER DAFTAR TABEL (MODE LIST) ---
else {
    $rules = $DB->get_records('local_myidpebi_learning_activity', null, 'learning_activity ASC');
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="m-0 text-secondary font-weight-bold"><i class="fa fa-list"></i> Daftar Batasan JP Kegiatan</h5>
        <a href="<?php echo new moodle_url($baseurl, ['action' => 'add']); ?>" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> Tambah Kegiatan Baru
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover shadow-sm bg-white">
            <thead class="thead-light text-center">
                <tr>
                    <th width="60">No</th>
                    <th>Bentuk CDP</th>
                    <th>Tipe Aktivitas</small></th>
                    <th>Aktifitas Pembelajaran <small>Learning Activity</small></th>
                    <th width="150">Max JP</th>
                    <th width="150">Bentuk Evidence</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rules): ?>
                    <?php $no = 1; foreach ($rules as $r): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo $no++; ?></td>
                            <td class="align-middle font-weight-bold text-dark"><?php echo s($r->bentuk_cdp); ?></td>
                            <td class="align-middle font-weight-bold text-dark"><?php echo s($r->tipe_aktivitas_cdp); ?></td>
                            <td class="align-middle font-weight-bold text-dark"><?php echo s($r->learning_activity); ?></td>
                            <td class="text-center align-middle bg-light"><?php echo (int)$r->jp_max; ?> JP</td>
                            <td class="align-middle font-weight-bold text-dark"><?php echo s($r->bentuk_evidence); ?></td>
                            <td class="text-center align-middle">
                                <a href="<?php echo new moodle_url($baseurl, ['action' => 'edit', 'id' => $r->id]); ?>" class="btn btn-sm btn-warning mr-1" title="Ubah"><i class="fa fa-edit"></i></a>
                                <a href="<?php echo new moodle_url($baseurl, ['action' => 'delete', 'id' => $r->id, 'sesskey' => sesskey()]); ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus batasan JP untuk Aktifitas Pembelajaran ini?')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted p-4">Belum ada data Aktifitas Pembelajaran yang dikonfigurasi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

echo $OUTPUT->footer();