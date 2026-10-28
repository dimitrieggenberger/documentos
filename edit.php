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
 * Work on a draft: fill in, adjust the wording, preview, issue.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\form\draft_form;
use local_documents\local\companions;
use local_documents\local\documents;
use local_documents\local\sources;
use local_documents\local\templates;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:issue', $context);

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$bundle = documents::get($id);
$doc = $bundle['doc'];
$viewurl = new moodle_url('/local/documents/view.php', ['id' => $id]);
if ($doc->status !== documents::STATUS_DRAFT) {
    redirect($viewurl);
}
$url = new moodle_url('/local/documents/edit.php', ['id' => $id]);

if ($action === 'delete' && confirm_sesskey()) {
    documents::delete_draft($id);
    redirect(new moodle_url('/local/documents/index.php'), get_string('draftdeleted', 'local_documents'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
// The file: documents that go with this one (docs/documents-plan.md 14).
if (in_array($action, ['companionadd', 'companionexternal', 'companionprepare', 'companionremove'], true)
        && confirm_sesskey()) {
    $deliveredon = 0;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', optional_param('deliveredon', '', PARAM_RAW_TRIMMED), $m)) {
        $deliveredon = make_timestamp((int)$m[1], (int)$m[2], (int)$m[3], 12);
    }
    $delivery = optional_param('delivery', companions::WITH, PARAM_ALPHA);
    try {
        if ($action === 'companionadd') {
            companions::add($id, required_param('companion', PARAM_INT), $delivery, $deliveredon, (int)$USER->id);
        } else if ($action === 'companionexternal') {
            companions::add_external($id, required_param('title', PARAM_TEXT), optional_param('number', '', PARAM_TEXT),
                $delivery, $deliveredon, (int)$USER->id);
        } else if ($action === 'companionprepare') {
            $newid = companions::prepare($id, required_param('typeid', PARAM_INT), optional_param('year', '', PARAM_TEXT),
                (int)$USER->id);
            redirect($url, get_string('companionprepared', 'local_documents', html_writer::link(
                new moodle_url('/local/documents/edit.php', ['id' => $newid]), get_string('opendraft', 'local_documents'))),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } else {
            companions::remove($id, required_param('index', PARAM_INT), (int)$USER->id);
        }
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url);
}
if ($action === 'reset' && confirm_sesskey()) {
    documents::reset_body($id, (int)$USER->id);
    redirect($url, get_string('bodyreset', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'issue' && optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    try {
        $issued = documents::submit($id, (int)$USER->id);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect($viewurl, $issued ? get_string('issued', 'local_documents', documents::get($id)['doc']->number)
        : get_string('senttosign', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Whether the person at the keyboard can issue it alone: nobody else has to sign on the platform
// (a document signed by hand is issued to be printed, and signed on paper).
$handwritten = documents::signature_mode($bundle['type']) === documents::SIGN_HANDWRITTEN;
$othersigners = $handwritten ? [] : array_filter(sources::signers($doc),
    static fn($signer) => (int)$signer['userid'] !== (int)$USER->id);
$years = $bundle['type']->subject === 'student' ? sources::school_years((int)$doc->userid) : [];
$form = new draft_form($url, ['bundle' => $bundle, 'years' => $years, 'signsalone' => !$othersigners]);
$defaults = draft_form::defaults($bundle);
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $context->id, 'local_documents', documents::ATTACHMENTS, $id,
    draft_form::attachment_options());
$defaults['attachments'] = $draftitemid;
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/documents/index.php'));
}
if ($submitted = $form->get_data()) {
    documents::update_draft($id, draft_form::changes($submitted, $bundle), (int)$USER->id);
    file_save_draft_area_files($submitted->attachments, $context->id, 'local_documents', documents::ATTACHMENTS, $id,
        draft_form::attachment_options());
    if (!empty($submitted->preview)) {
        redirect(new moodle_url('/local/documents/download.php', ['id' => $id, 'preview' => 1]));
    }
    if (!empty($submitted->issue)) {
        redirect(new moodle_url($url, ['action' => 'issue']));
    }
    redirect($url, get_string('draftsaved', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$bundle = documents::get($id);
$composed = documents::compose($bundle);
$title = get_string('draftof', 'local_documents', $bundle['type']->name);
ui::setup($url, $title, 'new');
echo ui::header($title);

if ($action === 'issue') {
    $problems = documents::problems($bundle, $composed);
    if ($problems) {
        foreach ($problems as $problem) {
            echo $OUTPUT->notification(s($problem), \core\output\notification::NOTIFY_ERROR, false);
        }
        echo $OUTPUT->continue_button($url);
    } else {
        $message = html_writer::tag('p', get_string($othersigners ? 'sendconfirm' : 'issueconfirm', 'local_documents',
            (object)['title' => s(templates::plain((string)$doc->title, $composed['values'])),
                'holder' => s($doc->holdername ?: '—')]));
        if ($othersigners) {
            $message .= html_writer::alist(array_map(static fn($signer) => s(trim($signer['name'] . ', '
                . $signer['title'], ', ')), array_values($othersigners)));
            $message .= html_writer::tag('p', get_string('sendconfirmnote', 'local_documents'), ['class' => 'text-muted']);
        } else {
            $message .= html_writer::tag('p', get_string((int)$doc->docversion > 1 ? 'issueconfirmrevision'
                : 'issueconfirmnumber', 'local_documents', (int)$doc->docversion), ['class' => 'text-muted']);
            if ($handwritten) {
                $message .= html_writer::tag('p', get_string('issueconfirmhandwritten', 'local_documents',
                    s(implode('; ', array_map(static fn($signer) => trim($signer['name'] . ', ' . $signer['title'], ', '),
                    sources::signers($doc))) ?: '—')), ['class' => 'alert alert-info']);
            }
        }
        echo $OUTPUT->confirm($message, new moodle_url($url, ['action' => 'issue', 'confirm' => 1, 'sesskey' => sesskey()]),
            $url);
    }
    echo $OUTPUT->footer();
    exit;
}

if ((int)$doc->docversion > 1) {
    echo $OUTPUT->notification(get_string('revisionnotice', 'local_documents', (int)$doc->docversion),
        \core\output\notification::NOTIFY_INFO, false);
}
foreach ($composed['warnings'] as $warning) {
    echo $OUTPUT->notification(s($warning), \core\output\notification::NOTIFY_WARNING, false);
}
if ($composed['missing']) {
    echo $OUTPUT->notification(get_string('placeholdersmissing', 'local_documents', implode(', ', $composed['missing'])),
        \core\output\notification::NOTIFY_WARNING, false);
}

echo html_writer::start_div('local-documents-draft row');
echo html_writer::start_div('col-lg-6');
$form->display();
echo html_writer::start_div('d-flex flex-wrap gap-2 mt-3');
if (!empty($bundle['tplver']->allowbodyedit)) {
    echo $OUTPUT->single_button(new moodle_url($url, ['action' => 'reset']), get_string('resetbody', 'local_documents'),
        'post', ['type' => single_button::BUTTON_SECONDARY]);
}
$delete = new single_button(new moodle_url($url, ['action' => 'delete']), get_string('deletedraft', 'local_documents'),
    'post', single_button::BUTTON_DANGER);
$delete->add_confirm_action(get_string('deletedraftconfirm', 'local_documents'));
echo $OUTPUT->render($delete);
echo html_writer::end_div();
if (companions::enabled($bundle)) {
    echo ui::companions_card($bundle, $composed['companions'], $url);
}
echo html_writer::end_div();

// The sheet as it stands, so wording changes are seen before the PDF.
echo html_writer::start_div('col-lg-6');
echo html_writer::tag('h3', get_string('livepreview', 'local_documents'), ['class' => 'h5']);
echo ui::sheet($bundle, $composed);
echo html_writer::tag('details', html_writer::tag('summary', get_string('placeholders', 'local_documents'))
    . html_writer::alist(array_map(static fn($k, $v) => html_writer::tag('code', '{{' . s($k) . '}}') . ' — ' . s($v),
        array_keys(templates::known_placeholders()), templates::known_placeholders()), ['class' => 'small mt-2']),
    ['class' => 'mt-3']);
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
