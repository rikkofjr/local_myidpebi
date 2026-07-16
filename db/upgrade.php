<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_myidpebi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Jalankan upgrade jika versi plugin sebelumnya lebih rendah dari 2026071300
    if ($oldversion < 2026071300) {

        // 1. Definisikan Tabel Target
        $table = new xmldb_table('local_myidpebi_act');

        // 2. Hapus kolom waktu_teks (free text) lama jika ada
        $field_old = new xmldb_field('waktu_teks');
        if ($dbman->field_exists($table, $field_old)) {
            $dbman->drop_field($table, $field_old);
        }

        // 3. Tambahkan 4 Kolom Tanggal Baru (Unix Timestamp)
        $fields_to_add = [
            'perencanaan_tanggal_mulai'   => 'Tanggal mulai rencana pengembangan',
            'perencanaan_tanggal_selesai' => 'Tanggal selesai rencana pengembangan',
            'realisasi_pelaksanaan_mulai'   => 'Tanggal mulai realisasi pelaksanaan',
            'realisasi_pelaksanaan_selesai' => 'Tanggal selesai realisasi pelaksanaan'
        ];

        foreach ($fields_to_add as $fieldname => $comment) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Kunci keberhasilan upgrade ke versi baru
        upgrade_plugin_savepoint(true, 2026071300, 'local', 'myidpebi');
    }

    return true;
}