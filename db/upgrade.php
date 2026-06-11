<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Fungsi upgrade otomatis untuk plugin local_myidpebi
 * @param int $oldversion Versi plugin lama yang tercatat di database Moodle
 * @return bool
 */
function xmldb_local_myidpebi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // 🟢 EKSEKUSI JIKA VERSI SEBELUMNYA LEBIH RENDAH DARI 2026061100
    if ($oldversion < 2026061100) {

        // Define table local_myidpebi_questions
        $table = new xmldb_table('local_myidpebi_questions');

        // Menambahkan Fields (Kolom-Kolom Tabel)
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('q_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'karyawan');
        $table->add_field('q_sort', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('question_text', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Menambahkan Primary Key
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Eksekusi pembuatan tabel ke Database Moodle jika belum ada
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 🟢 SEEDING DATA: Memasukkan pertanyaan default asli Anda agar Admin tidak repot input dari nol
        
        // 1. Pertanyaan Default Karyawan (diambil dari assessment_form.php Anda)
        $pertanyaan_karyawan = [
            'Anda memahami dengan jelas target pengembangan diri yang tertuang dalam IDP ini',
            'Aktivitas pengembangan yang dirancang dalam IDP ini menunjang penyelesaian tugas utama Anda saat ini',
            'Aktivitas pengembangan yang dirancang dalam IDP ini mempersiapkan kompetensi Anda untuk penugasan di masa datang',
            'Alokasi waktu (Jam Pelajaran) yang ditargetkan sudah realistis untuk Anda penuhi di tengah rutinitas kerja',
            'Anda mendapatkan materi/pembelajaran/fasilitas penunjang yang berkualitas selama pelaksanaan IDP',
            'Metode pengembangan yang digunakan (seperti training, mentoring, atau self-learning) sangat efektif membantu proses belajar Anda',
            'Anda mendapatkan dukungan dari para pihak terkait dalam menjalankan IDP',
            'Anda merasa lebih siap untuk mendapatkan tanggung jawab yang lebih besar setelah menjalankan IDP',
            'Anda ingin melanjutkan pengembangan di masa datang melalui IDP',
            'IDP yang Anda jalankan selaras dengan tuntutan perusahaan atau dept/sub dept yang Anda tempati'
        ];

        $sort = 1;
        foreach ($pertanyaan_karyawan as $q_text) {
            $record = new stdClass();
            $record->q_type        = 'karyawan';
            $record->q_sort        = $sort++;
            $record->question_text = $q_text;
            $record->is_active     = 1;
            $record->timecreated   = time();
            $DB->insert_record('local_myidpebi_questions', $record);
        }

        // 2. Pertanyaan Default Atasan (diambil dari assessment_atasan_form.php Anda)
        $pertanyaan_atasan = [
            'Apakah aktivitas pengembangan yang dijalankan relevan dengan tuntutan kompetensi posisi?',
            'Seberapa besar peningkatan performa (kinerja) karyawan pasca pelaksanaan program?',
            'Apakah durasi Jam Pelajaran (JP) yang dicapai sudah mencukupi target pengembangan?',
            'Karyawan menunjukkan komitmen dan kemandirian tinggi dalam menyelesaikan target IDP?'
        ];

        $sort = 1;
        foreach ($pertanyaan_atasan as $q_text) {
            $record = new stdClass();
            $record->q_type        = 'atasan';
            $record->q_sort        = $sort++;
            $record->question_text = $q_text;
            $record->is_active     = 1;
            $record->timecreated   = time();
            $DB->insert_record('local_myidpebi_questions', $record);
        }

        // Selesaikan blok upgrade versi ini
        upgrade_plugin_savepoint(true, 2026061100, 'local', 'myidpebi');
    }

    return true;
}