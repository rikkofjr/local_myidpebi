<?php
defined('MOODLE_INTERNAL') || die();

function local_myidpebi_get_atasan_username($userid) {
    global $DB;
    $fieldid = $DB->get_field('user_info_field', 'id', ['shortname' => 'atasan_langsung']);
    if (!$fieldid) return '';
    $data = $DB->get_field('user_info_data', 'data', ['userid' => $userid, 'fieldid' => $fieldid]);
    return $data ? trim($data) : '';
}