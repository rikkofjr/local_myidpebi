<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_myidpebi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // ====================
    // Penambahan tanggal perencanaan dan realisasi 
    // ====================


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
    // =========================================================================
    // perubahan tipe data pada kolom nama_activity
    // =========================================================================
    if ($oldversion < 2026080400) {

        // 1. Definisikan Tabel Target
        $table = new xmldb_table('local_myidpebi_act');

        // 2. Definisikan field nama_activity dengan tipe XMLDB_TYPE_TEXT
        $field = new xmldb_field('nama_activity', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // 3. Eksekusi perubahan tipe data di database jika kolomnya ada
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // Savepoint untuk versi 2026080400
        upgrade_plugin_savepoint(true, 2026080400, 'local', 'myidpebi');
    }
    // =========================================================================
    // penambahan colom resume kapabilitas
    // =========================================================================
    if ($oldversion < 2026091800) {

        // 1. Definisikan Tabel Target
        $table = new xmldb_table('local_myidpebi');

        // 2. Definisikan field dengan tipe XMLDB_TYPE_TEXT
        $field = new xmldb_field(
            'resume_kapabilitas', 
            XMLDB_TYPE_TEXT, 
            null, 
            null, 
            null, 
            null, 
            null, 
            'verified_ldc_by' // Diletakkan setelah kolom verified_ldc_by
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Savepoint untuk versi 2026080400
        upgrade_plugin_savepoint(true, 2026091800, 'local', 'myidpebi');
    }
    // =========================================================================
    // penambahan table log
    // =========================================================================
    if ($oldversion < 2026092101) {

        // Definisi tabel local_myidpebi_log.
        $table = new xmldb_table('local_myidpebi_log');

        // Menambahkan fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('idp_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('actor_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('old_status', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('new_status', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Menambahkan Primary Key dan Foreign Key.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_idp', XMLDB_KEY_FOREIGN, ['idp_id'], 'local_myidpebi', ['id']);

        // Eksekusi pembuatan tabel jika belum ada di DB.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Simpan titik versi upgrade myidpebi.
        upgrade_plugin_savepoint(true, 2026092101, 'local', 'myidpebi');
    }
    return true;
}