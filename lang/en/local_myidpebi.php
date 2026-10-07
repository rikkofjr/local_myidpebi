<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'My IDP EBI';
$string['help_jp'] = 'Lihat keterangan aktivitas pembelajaran';
$string['help_jp_help'] = 'Lihat detail keterangan aktivitas pembelajaran pada kamus aktivitas pembelajaran';
$string['help_pembimbing'] = 'Informasi Pembimbing';
$string['help_pembimbing_help'] = 'Secara default kolom ini akan otomatis terisi oleh NIK Atasan Langsung Anda dari sistem. Namun, jika dalam program IDP ini Anda dibimbing oleh Coach atau Mentor lain di luar departemen, Anda dapat mengubah isi kolom ini dengan NIK Pembimbing tersebut.';

// Status kalimat
$string['myidpebi:actiontype_status0'] = 'draft';
$string['myidpebi:badge_status0'] = 'Draft / Pengajuan';
$string['myidpebi:desc_status0'] = 'Menunggu persetujuan rencana IDP dari Atasan Langsung.';

$string['myidpebi:actiontype_status1'] = 'approve_atasan';
$string['myidpebi:badge_status1'] = 'Disetujui / Berjalan';
$string['myidpebi:desc_status1'] = 'Disetujui Oleh Atasan.';

$string['myidpebi:actiontype_status2'] = 'verifikasi_atasan';
$string['myidpebi:badge_status2'] = 'Diverifikasi Atasan';
$string['myidpebi:desc_status2'] = 'Diverifikasi Oleh Atasan.';

$string['myidpebi:actiontype_status3'] = 'verifikasi_koordinator';
$string['myidpebi:badge_status3'] = 'Diverifikasi Koordinator IDP';
$string['myidpebi:desc_status3'] = 'Diverifikasi Koordinator IDP.';


$string['myidpebi:actiontype_reset_idp'] = 'reset_admin';
$string['myidpebi:reset_idp'] = 'Reset IDP kestatus awal.';