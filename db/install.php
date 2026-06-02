<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Fungsi bawaan Moodle yang dieksekusi otomatis tepat setelah tabel install.xml sukses dibangun
 */
function xmldb_local_myidpebi_install() {
    global $DB;

    // Definisikan opsi default yang Anda miliki beserta batas JP-nya
    $default_seeds = [
        ['bentuk_cdp' => 'Jalur Pendidikan','tipe_aktivitas_cdp' => 'Formal', 'learning_activity' => 'Pendidikan Tinggi (S1, S2, S3)', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (klasikal)','tipe_aktivitas_cdp' => 'Formal', 'learning_activity' => 'Pelatihan Internal', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (klasikal)','tipe_aktivitas_cdp' => 'Formal', 'learning_activity' => 'Seminar/konfrensi', 'jp_min' => 0, 'max_jp' => 4],
        ['bentuk_cdp' => 'Jalur pelatihan (klasikal)','tipe_aktivitas_cdp' => 'Formal', 'learning_activity' => 'Workshop/lokalkarya', 'jp_min' => 0, 'max_jp' => 5],
        ['bentuk_cdp' => 'Jalur pelatihan (klasikal)','tipe_aktivitas_cdp' => 'Formal', 'learning_activity' => 'Kursus, Penataran, Bimbingan Teknis', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Pertukaran Karyawan', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Magang/praktik kerja', 'jp_min' => 0, 'max_jp' => 10],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Benchmarking', 'jp_min' => 0, 'max_jp' => 10],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Pelatihan Jarak Jauh (Teknikal)', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Coaching', 'jp_min' => 0, 'max_jp' => 2],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Mentoring', 'jp_min' => 0, 'max_jp' => 2],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Secondments', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Work-based learning', 'learning_activity' => 'Training/sharing session', 'jp_min' => 0, 'max_jp' => 5],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Profesional activity','learning_activity' => 'Komunitas belajar', 'jp_min' => 0, 'max_jp' => 2],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Profesional activity','learning_activity' => 'Peserta sharing session/training oleh asosiasi', 'jp_min' => 0, 'max_jp' => 4],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Profesional activity','learning_activity' => 'Narasumber dalam asosiasi', 'jp_min' => 0, 'max_jp' => 5],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Formal','learning_activity' => 'Outbond', 'jp_min' => 0, 'max_jp' => 20],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Formal','learning_activity' => 'Daily Practice', 'jp_min' => 0, 'max_jp' => 1],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Self directed learning', 'learning_activity' => 'Course HokaLearning', 'jp_min' => 0, 'max_jp' => 3],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Self directed learning', 'learning_activity' => 'Belajar Mandiri', 'jp_min' => 0, 'max_jp' => 2],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Self directed learning', 'learning_activity' => 'Mereview buku/artikel', 'jp_min' => 0, 'max_jp' => 4],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Others', 'learning_activity' => 'Keterlibatan Project (PM)', 'jp_min' => 0, 'max_jp' => 10],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Others', 'learning_activity' => 'Keterlibatan Project (Coordinator)', 'jp_min' => 0, 'max_jp' => 5],
        ['bentuk_cdp' => 'Jalur pelatihan (non klasikal)', 'tipe_aktivitas_cdp' => 'Others', 'learning_activity' => 'Keterlibatan Project (anggota)', 'jp_min' => 0, 'max_jp' => 2],
    ];

    // Lakukan looping untuk menyisipkan data ke dalam tabel baru
    foreach ($default_seeds as $seed) {
        // Cek dulu untuk memastikan data belum pernah ada (pengaman ganda)
        if (!$DB->record_exists('local_myidpebi_learning_activity', ['learning_activity' => $seed['learning_activity']])) {
            $record = new stdClass();
            $record->bentuk_cdp = $seed['bentuk_cdp'];
            $record->tipe_aktivitas_cdp = $seed['tipe_aktivitas_cdp'];
            $record->learning_activity = $seed['learning_activity'];
            $record->jp_min         = $seed['jp_min'];
            $record->jp_max         = $seed['max_jp'];
            $record->timecreated    = time();
            
            $DB->insert_record('local_myidpebi_learning_activity', $record);
        }
    }
}