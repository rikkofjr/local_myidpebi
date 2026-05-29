<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Fungsi bawaan Moodle yang dieksekusi otomatis tepat setelah tabel install.xml sukses dibangun
 */
function xmldb_local_myidpebi_install() {
    global $DB;

    // Definisikan opsi default yang Anda miliki beserta batas JP-nya
    $default_seeds = [
        ['jenis_kegiatan' => 'Assignment/Rotasi/Mutasi', 'jp_min' => 0, 'max_jp' => 12],
        ['jenis_kegiatan' => 'Detasemen/Materi Terbuka', 'jp_min' => 0, 'max_jp' => 8],
        ['jenis_kegiatan' => 'Proyek Khusus/Gugus Tugas', 'jp_min' => 0, 'max_jp' => 6],
        ['jenis_kegiatan' => 'Pelatihan internal/External', 'jp_min' => 0, 'max_jp' => 24],
        ['jenis_kegiatan' => 'Benchmarking', 'jp_min' => 0, 'max_jp' => 4],
        ['jenis_kegiatan' => 'Sharing Session', 'jp_min' => 0, 'max_jp' => 2],
        ['jenis_kegiatan' => 'Coaching & Mentoring', 'jp_min' => 0, 'max_jp' => 4],
        ['jenis_kegiatan' => 'Daily Practice', 'jp_min' => 0, 'max_jp' => 2],
        ['jenis_kegiatan' => 'Belajar Mandiri', 'jp_min' => 0, 'max_jp' => 2],
        ['jenis_kegiatan' => 'Mereview buku/artikel', 'jp_min' => 0, 'max_jp' => 4],
        ['jenis_kegiatan' => 'Keterlibatan Project', 'jp_min' => 0, 'max_jp' => 10],
    ];

    // Lakukan looping untuk menyisipkan data ke dalam tabel baru
    foreach ($default_seeds as $seed) {
        // Cek dulu untuk memastikan data belum pernah ada (pengaman ganda)
        if (!$DB->record_exists('local_myidpebi_jenis_kegiatan', ['jenis_kegiatan' => $seed['jenis_kegiatan']])) {
            $record = new stdClass();
            $record->jenis_kegiatan = $seed['jenis_kegiatan'];
            $record->jp_min         = $seed['jp_min'];
            $record->jp_max         = $seed['max_jp'];
            $record->timecreated    = time();
            
            $DB->insert_record('local_myidpebi_jenis_kegiatan', $record);
        }
    }
}