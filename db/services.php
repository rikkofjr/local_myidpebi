<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_myidpebi_get_global_analytics' => [
        'classname'   => 'local_myidpebi\external\get_global_analytics',
        'methodname'  => 'execute',
        'classpath'   => 'local/myidpebi/classes/external/get_global_analytics.php',
        'description' => 'Menyediakan data statistik global IDP untuk Executive Dashboard.',
        'type'        => 'read',
        'capabilities' => 'local/myidpebi:reset_idp',
        'ajax'        => true,
    ],
    'local_myidpebi_get_all_user_idps' => [
        'classname'   => 'local_myidpebi\external\get_all_user_idps',
        'methodname'  => 'execute',
        'classpath'   => 'local/myidpebi/classes/external/get_all_user_idps.php',
        'description' => 'Menarik daftar seluruh dokumen IDP milik semua karyawan beserta status dan informasi pembimbing.',
        'type'        => 'read',
        'capabilities' => 'local/myidpebi:reset_idp',
        'ajax'        => true,
    ],
    'local_myidpebi_get_learning_activity_analytics' => [
        'classname'   => 'local_myidpebi\external\get_learning_activity_analytics',
        'methodname'  => 'execute',
        'classpath'   => 'local/myidpebi/classes/external/get_learning_activity_analytics.php',
        'description' => 'Menyediakan analitik target vs realisasi JP per jenis aktivitas pembelajaran berdasarkan periode dokumen IDP.',
        'type'        => 'read',
        'capabilities' => 'local/myidpebi:reset_idp',
        'ajax'        => true,
    ],
];

// Jika Anda ingin mendefinisikan layanan khusus (service) terpusat
$services = [
    'IDP EBI Web Service' => [
        'functions' => [
            'local_myidpebi_get_global_analytics',
            'local_myidpebi_get_all_user_idps',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ]
];