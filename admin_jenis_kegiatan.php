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
$baseurl = new moodle_url('/local/myidpebi/admin_jenis_kegiatan.php');
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_title('Admin: Kelola Batasan JP Kegiatan');
$PAGE->set_heading('Manajemen Aturan JP Jenis Kegiatan');

// --- 4. PROSES LOGIKA AKSI (POST DATA) ---
if ($action == 'save' && data_submitted() && confirm_sesskey()) {
    $jenis_kegiatan = required_param('jenis_kegiatan', PARAM_TEXT);
    $jp_min         = required_param('jp_min', PARAM_INT);
    $jp_max         = required_param('jp_max', PARAM_INT);

    $record = new stdClass();
    $record->jenis_kegiatan = trim($jenis_kegiatan);
    $record->jp_min         = $jp_min;
    $record->jp_max         = $jp_max;

    if ($id > 0) {
        // Mode Update
        $record->id = $id;
        $DB->update_record('local_myidpebi_jenis_kegiatan', $record);
        $msg = "Aturan jenis kegiatan berhasil diperbarui.";
    } else {
        // Mode Insert Baru
        $record->timecreated = time();
        $DB->insert_record('local_myidpebi_jenis_kegiatan', $record);
        $msg = "Jenis kegiatan baru berhasil ditambahkan.";
    }
    redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

if ($action == 'delete' && $id > 0 && confirm_sesskey()) {
    $DB->delete_records('local_myidpebi_jenis_kegiatan', ['id' => $id]);
    redirect($baseurl, 'Jenis kegiatan berhasil dihapus.', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Memulai Output Tampilan
echo $OUTPUT->header();

// Tab navigasi utama dashboard admin Anda (UI Terintegrasi)
echo '<ul class="nav nav-tabs mb-4">';
echo '  <li class="nav-item"><a class="nav-link" href="' . new moodle_url('/local/myidpebi/admin_dashboard.php') . '"><i class="fa fa-pie-chart"></i> Ringkasan & Tren</a></li>';
echo '  <li class="nav-item"><a class="nav-link" href="' . new moodle_url('/local/myidpebi/admin_monitor.php') . '"><i class="fa fa-desktop"></i> Monitoring Dokumen</a></li>';
echo '  <li class="nav-item"><a class="nav-link active" href="' . $baseurl . '"><i class="fa fa-cogs"></i> Kelola Jenis Kegiatan</a></li>';
echo '</ul>';

// --- 5. RENDER FORM TAMBAH / EDIT ---
if ($action == 'add' || $action == 'edit') {
    $current = new stdClass();
    $current->jenis_kegiatan = '';
    $current->jp_min = 0;
    $current->jp_max = 0;

    if ($id > 0) {
        $current = $DB->get_record('local_myidpebi_jenis_kegiatan', ['id' => $id], '*', MUST_EXIST);
    }
    ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-4">
                <i class="fa <?php echo ($id > 0) ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> 
                <?php echo ($id > 0) ? 'Ubah Jenis Kegiatan' : 'Tambah Jenis Kegiatan Baru'; ?>
            </h5>
            <form method="POST" action="<?php echo $baseurl; ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

                <div class="form-group row mb-3">
                    <label class="col-md-3 col-form-label font-weight-bold">Nama Jenis Kegiatan</label>
                    <div class="col-md-6">
                        <input type="text" name="jenis_kegiatan" class="form-control" value="<?php echo s($current->jenis_kegiatan); ?>" required placeholder="Contoh: Belajar Mandiri">
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
    $rules = $DB->get_records('local_myidpebi_jenis_kegiatan', null, 'jenis_kegiatan ASC');
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
                    <th>Nama Jenis Kegiatan</th>
                    <th width="150">Min JP</th>
                    <th width="150">Max JP</th>
                    <th width="150">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rules): ?>
                    <?php $no = 1; foreach ($rules as $r): ?>
                        <tr>
                            <td class="text-center align-middle"><?php echo $no++; ?></td>
                            <td class="align-middle font-weight-bold text-dark"><?php echo s($r->jenis_kegiatan); ?></td>
                            <td class="text-center align-middle bg-light"><?php echo (int)$r->jp_min; ?> JP</td>
                            <td class="text-center align-middle bg-light"><?php echo (int)$r->jp_max; ?> JP</td>
                            <td class="text-center align-middle">
                                <a href="<?php echo new moodle_url($baseurl, ['action' => 'edit', 'id' => $r->id]); ?>" class="btn btn-sm btn-warning mr-1" title="Ubah"><i class="fa fa-edit"></i></a>
                                <a href="<?php echo new moodle_url($baseurl, ['action' => 'delete', 'id' => $r->id, 'sesskey' => sesskey()]); ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus batasan JP untuk jenis kegiatan ini?')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted p-4">Belum ada data jenis kegiatan yang dikonfigurasi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

echo $OUTPUT->footer();