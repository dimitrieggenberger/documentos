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
 * Record what a family answered on the consent slip they returned (docs/documents-plan.md 15).
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\form\response_form;
use local_documents\local\documents;
use local_documents\local\numbering;
use local_documents\local\slips;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:issue', $context);

$slipid = required_param('slip', PARAM_INT);
$slip = $DB->get_record('local_documents_slip', ['id' => $slipid], '*', MUST_EXIST);
$doc = documents::get((int)$slip->docid)['doc'];
$viewurl = new moodle_url('/local/documents/view.php', ['id' => $doc->id], 'slips');
$url = new moodle_url('/local/documents/response.php', ['slip' => $slipid]);
if (in_array($doc->status, [documents::STATUS_DRAFT, documents::STATUS_PENDING], true)) {
    redirect($viewurl);
}
$title = get_string('recordresponse', 'local_documents');
ui::setup($url, $title, 'registry');

$form = new response_form($url);
$draftitemid = file_get_submitted_draft_itemid('scan');
file_prepare_draft_area($draftitemid, $context->id, 'local_documents', slips::SCANAREA, $slipid,
    response_form::scan_options());
$form->set_data(['slip' => $slipid, 'response' => $slip->response, 'responsedate' => (int)$slip->responsedate ?: time(),
    'note' => (string)$slip->note, 'scan' => $draftitemid]);

if ($form->is_cancelled()) {
    redirect($viewurl);
}
if ($data = $form->get_data()) {
    slips::respond($slipid, (string)$data->response, (int)($data->responsedate ?? 0), (string)$data->note, (int)$USER->id);
    file_save_draft_area_files($data->scan, $context->id, 'local_documents', slips::SCANAREA, $slipid,
        response_form::scan_options());
    redirect($viewurl, get_string('responsesaved', 'local_documents', s($slip->holdername)), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo ui::header($title);
echo html_writer::tag('p', html_writer::tag('strong', s($slip->holdername)) . ' · ' . s(numbering::display((string)$doc->number))
    . ' · ' . html_writer::tag('code', s(numbering::format_code((string)$slip->verifycode))));
$form->display();
echo $OUTPUT->footer();
