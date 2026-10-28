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
 * Signatures: the documents waiting for mine, and one document to approve or send back.
 *
 * Open to the head of a unit whose signature was asked for, whether or not
 * they may issue documents themselves: the approval is theirs alone.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\documents;
use local_documents\local\templates;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$url = new moodle_url('/local/documents/sign.php', $id ? ['id' => $id] : []);

if ($id && $action !== '' && confirm_sesskey()) {
    try {
        if ($action === 'approve') {
            $issued = documents::approve($id, (int)$USER->id);
            redirect(new moodle_url('/local/documents/sign.php'), $issued
                ? get_string('approvedissued', 'local_documents', documents::get($id)['doc']->number)
                : get_string('approvedwaiting', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        if ($action === 'reject') {
            $note = trim(required_param('note', PARAM_TEXT));
            if ($note === '') {
                redirect($url, get_string('rejectnoterequired', 'local_documents'), null,
                    \core\output\notification::NOTIFY_ERROR);
            }
            documents::reject($id, (int)$USER->id, $note);
            redirect(new moodle_url('/local/documents/sign.php'), get_string('rejected', 'local_documents'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$title = get_string('tosign', 'local_documents');
ui::setup($url, $title, 'sign');
echo ui::header($title);

// The queue: what waits for me first (Working Screens 1).
if (!$id) {
    $docs = $DB->get_records_sql("SELECT DISTINCT d.*, t.name AS typename
                                    FROM {local_documents_doc} d
                                    JOIN {local_documents_type} t ON t.id = d.typeid
                                    JOIN {local_documents_approval} a ON a.docid = d.id
                                   WHERE a.userid = ? AND a.status = 'pending' AND d.status = ?
                                ORDER BY d.timemodified ASC", [$USER->id, documents::STATUS_PENDING]);
    if (!$docs) {
        echo $OUTPUT->notification(get_string('nothingtosignnow', 'local_documents'),
            \core\output\notification::NOTIFY_SUCCESS, false);
    }
    echo html_writer::start_div('local-documents-tasks');
    foreach ($docs as $d) {
        $data = documents::data($d);
        $sender = core_user::get_user((int)($data['submittedby'] ?? $d->createdby));
        echo html_writer::div(
            html_writer::div(html_writer::div(s($d->typename), 'local-documents-task-title')
                . html_writer::div(s(($d->holdername ?: '—') . ' · ' . get_string('sentby', 'local_documents',
                    $sender ? fullname($sender) : '—') . ' · ' . userdate((int)$d->timemodified,
                    get_string('strftimedatetimeshort', 'langconfig'))), 'local-documents-task-meta'))
            . html_writer::link(new moodle_url('/local/documents/sign.php', ['id' => $d->id]),
                get_string('review', 'local_documents'), ['class' => 'btn btn-primary']),
            'local-documents-task');
    }
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$bundle = documents::get($id);
$doc = $bundle['doc'];
$mine = $DB->get_records('local_documents_approval', ['docid' => $id, 'userid' => $USER->id, 'status' => 'pending']);
if (!$mine) {
    require_capability('local/documents:view', $context);
}
$composed = documents::compose($bundle);

if ($doc->status !== documents::STATUS_PENDING) {
    echo $OUTPUT->notification(get_string('nolongerpending', 'local_documents'), \core\output\notification::NOTIFY_INFO,
        false);
}

echo html_writer::start_div('local-documents-draft row');
echo html_writer::start_div('col-lg-5');

// The state of every signature.
$rows = [];
foreach ($DB->get_records('local_documents_approval', ['docid' => $id], 'sortorder, id') as $approval) {
    $unit = $DB->get_record('local_documents_unit', ['id' => $approval->unitid]);
    $rows[] = [s($unit ? $unit->code . ' — ' . $unit->name : '—'), ui::user((int)$approval->userid),
        $approval->status === 'approved'
            ? html_writer::span(get_string('approvedon', 'local_documents', userdate((int)$approval->timedecided,
                get_string('strftimedatetimeshort', 'langconfig'))), 'badge badge-success bg-success')
            : html_writer::span(get_string('signaturepending', 'local_documents'), 'badge badge-warning bg-warning text-dark')];
}
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('unit', 'local_documents'), get_string('signer', 'local_documents', ''),
    get_string('status', 'local_documents')];
$table->data = $rows;
echo html_writer::tag('h3', get_string('signatures', 'local_documents'), ['class' => 'h5']);
echo html_writer::table($table);

// What the signer reads before signing: the PDF, the attachments, and any change to the template's text.
$links = [html_writer::link(new moodle_url('/local/documents/download.php', ['id' => $id]),
    get_string('previewpdf', 'local_documents'), ['class' => 'btn btn-outline-secondary', 'target' => '_blank'])];
echo html_writer::div(implode(' ', $links), 'mb-3');
if ($composed['attachments']) {
    echo html_writer::tag('h3', get_string('attachments', 'local_documents'), ['class' => 'h5']);
    echo html_writer::alist(array_map(static fn($a) => html_writer::link(new moodle_url('/local/documents/download.php',
        ['id' => $id, 'file' => $a['fileid']]), s($a['name'])) . ' · ' . display_size($a['size']),
        $composed['attachments']), [], 'ol');
}
$body = (string)($bundle['data']['body'] ?? $bundle['tplver']->body);
if (trim($body) !== trim((string)$bundle['tplver']->body)) {
    echo $OUTPUT->notification(get_string('bodychanged', 'local_documents'), \core\output\notification::NOTIFY_WARNING,
        false);
    $out = '';
    foreach (templates::diff((string)$bundle['tplver']->body, $body) as [$mark, $line]) {
        $class = $mark === '+' ? 'local-documents-diff-add' : ($mark === '-' ? 'local-documents-diff-del' : '');
        $out .= html_writer::div(s($mark . ' ' . $line), $class);
    }
    echo html_writer::div($out, 'local-documents-diff mb-3');
}

if ($mine && $doc->status === documents::STATUS_PENDING) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'approve']);
    echo html_writer::tag('p', get_string('approveexplain', 'local_documents'), ['class' => 'small text-muted']);
    echo html_writer::tag('button', get_string('approve', 'local_documents'), ['type' => 'submit',
        'class' => 'btn btn-success', 'id' => 'ld-approve']);
    echo html_writer::end_tag('form');

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reject']);
    echo html_writer::label(get_string('rejectnote', 'local_documents'), 'ld-note', true, ['class' => 'form-label']);
    echo html_writer::tag('textarea', '', ['name' => 'note', 'id' => 'ld-note', 'rows' => 3, 'class' => 'form-control mb-2',
        'required' => 'required']);
    echo html_writer::tag('button', get_string('reject', 'local_documents'), ['type' => 'submit',
        'class' => 'btn btn-outline-danger', 'id' => 'ld-reject']);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();

echo html_writer::start_div('col-lg-7');
echo ui::sheet($bundle, $composed);
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
