<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Mockup harness (never shipped): the [es_verificacion] shortcode's real output for each answer.
 *
 * Runs the WordPress plugin with the few WordPress functions it calls stubbed
 * and the platform's answer invented, and prints one HTML fragment per state as
 * JSON for build.py. Every name and number is fictional.
 *
 * Usage: php states.php > states.json
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('ABSPATH', '/');
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['answer'] = null;
function get_option($key, $default = []) {
    return ['moodle' => 'https://learn.example.invalid', 'token' => 'x'];
}
function untrailingslashit($s) {
    return rtrim($s, '/');
}
function add_shortcode() {
}
function add_filter() {
}
function add_action() {
}
function wp_enqueue_style() {
}
function wp_enqueue_script() {
}
function plugins_url($file) {
    return $file;
}
function get_transient() {
    return false;
}
function set_transient() {
}
function add_query_arg($key, $value, $url) {
    return $url;
}
function sanitize_text_field($s) {
    return trim(strip_tags($s));
}
function wp_unslash($s) {
    return $s;
}
function esc_html($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}
function esc_attr($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES);
}
function esc_url($s) {
    return $s;
}
function get_permalink() {
    return '/verificar/';
}
function is_wp_error($r) {
    return false;
}
function wp_remote_get($url, $args) {
    return ['body' => json_encode($GLOBALS['answer'])];
}
function wp_remote_retrieve_body($r) {
    return $r['body'];
}

require(__DIR__ . '/../../wordpress/es-verificacion/es-verificacion.php');

$document = [
    'code' => '7KQM-3XRT-9WPA', 'title' => 'Constancia de estudio',
    'number' => 'CONSES – 022 – 050100099M01 – 2026 – DCE – JM', 'version' => 1,
    'holder' => 'Valentina Ríos Castro', 'holderid' => '••••-••••-•1274', 'subject' => '',
    'issued' => '27 de octubre de 2026', 'issueddate' => '2026-10-27', 'validuntil' => '25 de enero de 2027',
    'school' => 'EuropaSchule San Pedro Sula', 'unit' => 'Dirección del Centro Educativo',
    'signers' => ['Lic. Rosa Gómez, Directora'],
    'sha256' => '1368c5df645e70f31b98d3297c000d76e6c3492480eb9b0ad9adb8ba88be37b5',
];
$states = [
    'inicio' => null,
    'valido' => ['status' => 'valid'] + $document,
    'vencido' => ['status' => 'expired', 'validuntil' => '25 de enero de 2027'] + $document,
    'sustituido' => ['status' => 'superseded', 'replacedby' => 2, 'replacedon' => '3 de noviembre de 2026'] + $document,
    'anulado' => ['status' => 'recalled', 'recalled' => '30 de octubre de 2026',
        'recallnote' => 'Sustituida por una nueva constancia a solicitud de la familia.'] + $document,
    'noexiste' => ['status' => 'notfound'],
];
$out = [];
foreach ($states as $name => $answer) {
    $GLOBALS['answer'] = $answer;
    $_GET = $answer === null ? [] : ['c' => $name === 'noexiste' ? 'Z9Z9-Z9Z9-Z9Z9' : '7KQM3XRT9WPA'];
    $out[$name] = es_verificacion_shortcode();
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
