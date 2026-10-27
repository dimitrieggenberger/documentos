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
 * Public verification as JSON, for the school's website (wordpress/es-verificacion).
 *
 * The one page of this plugin without a login, on purpose: whoever holds the
 * paper must be able to check it. It answers only for issued documents and
 * only with what the sheet itself prints (see \local_documents\local\verify).
 * With a token set in the settings, only a caller that sends it is answered,
 * and that caller may pass on the visitor's address for the rate limit.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_documents\local\config;
use local_documents\local\verify;

$code = optional_param('c', '', PARAM_ALPHANUMEXT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$token = config::get('verifytoken');
$sent = (string)($_SERVER['HTTP_X_VERIFY_TOKEN'] ?? '');
$trusted = $token !== '' && $sent !== '' && hash_equals($token, $sent);
if ($token !== '' && !$trusted) {
    http_response_code(403);
    echo json_encode(['status' => 'forbidden']);
    exit;
}

$client = getremoteaddr();
if ($trusted && !empty($_SERVER['HTTP_X_VERIFY_CLIENT'])) {
    $forwarded = clean_param((string)$_SERVER['HTTP_X_VERIFY_CLIENT'], PARAM_HOST);
    $client = $forwarded !== '' ? 'wp:' . $forwarded : $client;
}
if (!verify::allow((string)$client)) {
    http_response_code(429);
    echo json_encode(['status' => 'ratelimited']);
    exit;
}

echo json_encode(verify::lookup($code, (string)$client), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
