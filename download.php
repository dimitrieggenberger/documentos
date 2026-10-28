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
 * The PDF of a document (the stored file once issued, a marked preview before) or one of its attachments.
 *
 * Staff with the view capability may open any; the head of a unit whose
 * signature is asked for may open the document they are to sign.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\documents;
use local_documents\local\pdf;
use local_documents\local\slips;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);

$id = required_param('id', PARAM_INT);
$fileid = optional_param('file', 0, PARAM_INT);
$slipid = optional_param('slip', 0, PARAM_INT);
$scan = optional_param('scan', 0, PARAM_BOOL);
$bundle = documents::get($id);
$doc = $bundle['doc'];

$signer = $doc->status === documents::STATUS_PENDING && documents::my_approvals($id, (int)$USER->id);
if (!$signer) {
    require_capability('local/documents:view', $context);
}

// One student's own PDF of an authorisation (letter and slip), or the slip the family returned, scanned.
if ($slipid) {
    $slip = $DB->get_record('local_documents_slip', ['id' => $slipid, 'docid' => $id], '*', MUST_EXIST);
    $file = $scan ? slips::scan($slip) : slips::pdf($slip);
    if (!$file) {
        throw new moodle_exception('filemissing', 'local_documents');
    }
    documents::log($id, 'downloaded', (int)$USER->id, $slip->holdername);
    \core\session\manager::write_close();
    send_stored_file($file, 0, 0, false);
}

if ($fileid) {
    $file = documents::attachment_file($doc, $fileid);
    if (!$file) {
        throw new moodle_exception('filemissing', 'local_documents');
    }
    documents::log($id, 'downloaded', (int)$USER->id, $file->get_filename());
    \core\session\manager::write_close();
    send_stored_file($file, 0, 0, true);
}

if (in_array($doc->status, [documents::STATUS_DRAFT, documents::STATUS_PENDING], true)) {
    if (!$signer) {
        require_capability('local/documents:issue', $context);
    }
    $content = pdf::render($bundle, false);
    documents::log($id, 'previewed', (int)$USER->id);
    send_file($content, documents::filename($doc), 0, 0, true, false, 'application/pdf');
}

$file = documents::file($doc);
if (!$file) {
    throw new moodle_exception('filemissing', 'local_documents');
}
documents::log($id, 'downloaded', (int)$USER->id);
\core\session\manager::write_close();
send_stored_file($file, 0, 0, false, ['filename' => documents::filename($doc)]);
