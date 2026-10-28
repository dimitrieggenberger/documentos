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
 * One document: its state, versions and history; download, correct, recall.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\form\recall_form;
use local_documents\local\config;
use local_documents\local\documents;
use local_documents\local\numbering;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:view', $context);

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$bundle = documents::get($id);
$doc = $bundle['doc'];
$url = new moodle_url('/local/documents/view.php', ['id' => $id]);
$canissue = has_capability('local/documents:issue', $context);
$canrecall = has_capability('local/documents:recall', $context);

if ($action === 'withdraw' && confirm_sesskey()) {
    require_capability('local/documents:issue', $context);
    documents::withdraw($id, (int)$USER->id);
    redirect(new moodle_url('/local/documents/edit.php', ['id' => $id]), get_string('withdrawn', 'local_documents'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'revise' && confirm_sesskey()) {
    require_capability('local/documents:issue', $context);
    redirect(new moodle_url('/local/documents/edit.php', ['id' => documents::revise($id, (int)$USER->id)]));
}

$recallform = null;
if ($action === 'recall' && $doc->status === documents::STATUS_ISSUED) {
    require_capability('local/documents:recall', $context);
    $recallform = new recall_form($url);
    $recallform->set_data(['id' => $id]);
    if ($recallform->is_cancelled()) {
        redirect($url);
    }
    if ($data = $recallform->get_data()) {
        documents::recall($id, (string)$data->reason, (string)$data->note, (int)$USER->id);
        redirect($url, get_string('recalled', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$title = $doc->number !== '' ? $doc->number : get_string('draftof', 'local_documents', $bundle['type']->name);
ui::setup($url, $title, 'registry');
echo ui::header(s($doc->title));

if ($recallform) {
    $recallform->display();
    echo $OUTPUT->footer();
    exit;
}

$expired = (int)$doc->validuntil > 0 && (int)$doc->validuntil < time();
$rows = [
    [get_string('status', 'local_documents'), ui::status($doc->status, $expired)],
    [get_string('number', 'local_documents'), $doc->number !== '' ? html_writer::tag('strong', s($doc->number),
        ['class' => 'local-documents-number']) : html_writer::span(get_string('nonumberyet', 'local_documents'), 'text-muted')],
    [get_string('version', 'local_documents'), (int)$doc->docversion],
    [get_string('documenttype', 'local_documents'), s($bundle['type']->name) . ' · '
        . html_writer::span(s($bundle['series']->code . ' (' . $bundle['series']->trdcode . ')'), 'text-muted')],
    [get_string('holder', 'local_documents'), ($doc->holdername ? s($doc->holdername) : '—')
        . ($doc->holderidnumber ? html_writer::span(' · ' . s($doc->holderidnumber), 'text-muted') : '')],
    [get_string('unit', 'local_documents'), s($doc->unitcode . ' — ' . (config::units()[$doc->unitcode] ?? ''))],
];
if ($doc->status !== documents::STATUS_DRAFT) {
    $verify = config::verify_url((string)$doc->verifycode);
    $rows[] = [get_string('issuedate', 'local_documents'), ui::date((int)$doc->issuedate)];
    $rows[] = [get_string('issuedby', 'local_documents'), ui::user((int)$doc->issuedby) . ' · '
        . userdate((int)$doc->timeissued, get_string('strftimedatetimeshort', 'langconfig'))];
    if ((int)$doc->validuntil > 0) {
        $rows[] = [get_string('validuntil', 'local_documents'), ui::date((int)$doc->validuntil)];
    }
    $rows[] = [get_string('verifycode', 'local_documents'), html_writer::tag('code',
        numbering::format_code((string)$doc->verifycode)) . ' · ' . html_writer::link($verify,
        get_string('openverification', 'local_documents'), ['target' => '_blank', 'rel' => 'noopener'])];
    $rows[] = [get_string('sha256', 'local_documents'), html_writer::tag('code', s($doc->contenthash),
        ['class' => 'small text-break'])];
    $rows[] = [get_string('retention', 'local_documents'), get_string('retentionvalue', 'local_documents', (object)[
        'active' => (int)$bundle['series']->keepactive, 'central' => (int)$bundle['series']->keepcentral,
        'until' => (int)$doc->year + (int)$bundle['series']->keepactive + (int)$bundle['series']->keepcentral,
        'permanent' => $bundle['series']->permanent ? get_string('retentionpermanent', 'local_documents') : ''])];
}
if ($doc->status === documents::STATUS_RECALLED) {
    $rows[] = [get_string('recalledon', 'local_documents'), ui::date((int)$doc->timerecalled) . ' · '
        . ui::user((int)$doc->recalledby)];
    $rows[] = [get_string('recallreason', 'local_documents'), s($doc->recallreason)];
    $rows[] = [get_string('recallnote', 'local_documents'), $doc->recallnote !== '' ? s($doc->recallnote)
        : html_writer::span('—', 'text-muted')];
}
$table = new html_table();
$table->attributes['class'] = 'generaltable local-documents-record';
foreach ($rows as [$label, $value]) {
    $head = new html_table_cell($label);
    $head->header = true;
    $head->scope = 'row';
    $table->data[] = new html_table_row([$head, new html_table_cell((string)$value)]);
}

// Actions.
$actions = [];
if ($doc->status === documents::STATUS_DRAFT && $canissue) {
    $actions[] = html_writer::link(new moodle_url('/local/documents/edit.php', ['id' => $id]),
        get_string('continuedraft', 'local_documents'), ['class' => 'btn btn-primary']);
} else if ($doc->status === documents::STATUS_PENDING) {
    if (documents::my_approvals($id, (int)$USER->id)) {
        $actions[] = html_writer::link(new moodle_url('/local/documents/sign.php', ['id' => $id]),
            get_string('review', 'local_documents'), ['class' => 'btn btn-primary']);
    }
    $actions[] = html_writer::link(new moodle_url('/local/documents/download.php', ['id' => $id]),
        get_string('previewpdf', 'local_documents'), ['class' => 'btn btn-outline-secondary']);
    if ($canissue) {
        $withdraw = new single_button(new moodle_url($url, ['action' => 'withdraw']),
            get_string('withdraw', 'local_documents'), 'post', single_button::BUTTON_SECONDARY);
        $withdraw->add_confirm_action(get_string('withdrawconfirm', 'local_documents'));
        $actions[] = $OUTPUT->render($withdraw);
    }
} else if ($doc->status !== documents::STATUS_DRAFT) {
    $actions[] = html_writer::link(new moodle_url('/local/documents/download.php', ['id' => $id]),
        get_string('downloadpdf', 'local_documents'), ['class' => 'btn btn-primary']);
}
if ($doc->status === documents::STATUS_ISSUED && $canissue) {
    $revise = new single_button(new moodle_url($url, ['action' => 'revise']), get_string('revise', 'local_documents'),
        'post', single_button::BUTTON_SECONDARY);
    $revise->add_confirm_action(get_string('reviseconfirm', 'local_documents'));
    $actions[] = $OUTPUT->render($revise);
}
if ($doc->status === documents::STATUS_ISSUED && $canrecall) {
    $actions[] = html_writer::link(new moodle_url($url, ['action' => 'recall']), get_string('recall', 'local_documents'),
        ['class' => 'btn btn-outline-danger']);
}

echo html_writer::div(implode(' ', $actions), 'd-flex flex-wrap gap-2 mb-3 local-documents-actions');
echo html_writer::div(html_writer::table($table), 'local-documents-card');

// Signatures and attachments.
$composed = documents::compose($bundle);
if ($composed['signers']) {
    $st = new html_table();
    $st->attributes['class'] = 'generaltable';
    $st->head = [get_string('unit', 'local_documents'), get_string('signer', 'local_documents', ''),
        get_string('status', 'local_documents'), get_string('signatureevidence', 'local_documents')];
    $evidence = [];
    foreach ($DB->get_records('local_documents_approval', ['docid' => $id, 'status' => 'approved']) as $approval) {
        $evidence[(int)$approval->unitid] = $approval;
    }
    foreach ($composed['signers'] as $signer) {
        $proof = $evidence[(int)$signer['unitid']] ?? null;
        $st->data[] = [s($signer['unitcode'] . ' — ' . $signer['unitname']), s(trim($signer['name'] . ', '
            . (!empty($signer['substitutefor']) ? get_string('substitutefor', 'local_documents', $signer['substitutefor'])
                : $signer['title']), ', ')), !empty($signer['approvedon'])
            ? html_writer::span(get_string('approvedon', 'local_documents', userdate((int)$signer['approvedon'],
                get_string('strftimedatetimeshort', 'langconfig'))), 'badge badge-success bg-success')
            : html_writer::span(get_string(($composed['signaturemode'] ?? '') === documents::SIGN_HANDWRITTEN
                ? 'signhandwritten' : 'signaturepending', 'local_documents'), 'badge badge-warning bg-warning text-dark'),
            $proof && (string)$proof->contenthash !== '' ? html_writer::div(get_string('evidenceip', 'local_documents',
                s((string)$proof->ipaddress ?: '—')), 'small') . html_writer::div(get_string('evidencehash', 'local_documents',
                html_writer::tag('code', s(substr((string)$proof->contenthash, 0, 16)) . '…',
                ['title' => s((string)$proof->contenthash)])), 'small text-muted') : ''];
    }
    echo html_writer::tag('h3', get_string('signatures', 'local_documents'), ['class' => 'h5 mt-4']);
    echo html_writer::table($st);
}
if (!empty($composed['companions'])) {
    echo html_writer::tag('h3', get_string('companions', 'local_documents'), ['class' => 'h5 mt-4']);
    echo html_writer::alist(array_map(static fn($item) => s($item['line']) . ($item['docid'] > 0 ? ' '
        . html_writer::link(new moodle_url('/local/documents/view.php', ['id' => $item['docid']]),
            get_string('open', 'local_documents'), ['class' => 'small']) : ''), $composed['companions']), [], 'ol');
}
if ($composed['attachments']) {
    echo html_writer::tag('h3', get_string('attachments', 'local_documents'), ['class' => 'h5 mt-4']);
    echo html_writer::alist(array_map(static fn($a) => html_writer::link(new moodle_url('/local/documents/download.php',
        ['id' => $id, 'file' => $a['fileid']]), s($a['name'])) . ' · ' . display_size($a['size'])
        . html_writer::div('SHA-256 ' . html_writer::tag('code', s($a['sha256'])), 'small text-muted text-break'),
        $composed['attachments']), [], 'ol');
}

// Versions.
$versions = documents::versions((int)$doc->rootid);
if (count($versions) > 1) {
    echo html_writer::tag('h3', get_string('versions', 'local_documents'), ['class' => 'h5 mt-4']);
    $vt = new html_table();
    $vt->attributes['class'] = 'generaltable';
    $vt->head = [get_string('version', 'local_documents'), get_string('status', 'local_documents'),
        get_string('issuedate', 'local_documents'), get_string('verifycode', 'local_documents'), ''];
    foreach ($versions as $v) {
        $vt->data[] = [
            'v' . (int)$v->docversion . ((int)$v->id === $id ? ' ' . get_string('thisversion', 'local_documents') : ''),
            ui::status($v->status),
            ui::date($v->status === documents::STATUS_DRAFT ? 0 : (int)$v->issuedate),
            $v->status === documents::STATUS_DRAFT ? '—' : html_writer::tag('code', numbering::format_code($v->verifycode)),
            (int)$v->id === $id ? '' : html_writer::link(new moodle_url('/local/documents/view.php', ['id' => $v->id]),
                get_string('open', 'local_documents')),
        ];
    }
    echo html_writer::table($vt);
}

// History.
echo html_writer::tag('h3', get_string('history', 'local_documents'), ['class' => 'h5 mt-4']);
[$insql, $inparams] = $DB->get_in_or_equal(array_map(static fn($v) => (int)$v->id, $versions));
$logs = $DB->get_records_select('local_documents_log', "docid $insql", $inparams, 'timecreated DESC, id DESC', '*', 0, 200);
$lt = new html_table();
$lt->attributes['class'] = 'generaltable local-documents-log';
$lt->head = [get_string('when', 'local_documents'), get_string('action', 'local_documents'),
    get_string('version', 'local_documents'), get_string('who', 'local_documents'), get_string('details', 'local_documents')];
$versionof = [];
foreach ($versions as $v) {
    $versionof[(int)$v->id] = 'v' . (int)$v->docversion;
}
foreach ($logs as $log) {
    $lt->data[] = [
        userdate((int)$log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        get_string('log_' . $log->action, 'local_documents'),
        $versionof[(int)$log->docid] ?? '',
        $log->userid ? ui::user((int)$log->userid) : get_string('public', 'local_documents'),
        $log->action === 'verified' ? '' : s($log->info),
    ];
}
echo html_writer::table($lt);

echo $OUTPUT->footer();
