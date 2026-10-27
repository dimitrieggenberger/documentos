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
 * Edit one document type and its template; see and compare its versions.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\form\template_form;
use local_documents\local\templates;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
require_capability('local/documents:managetemplates', $context);

$id = optional_param('id', 0, PARAM_INT);
$compare = optional_param('compare', 0, PARAM_INT);
$url = new moodle_url('/local/documents/template_edit.php', $id ? ['id' => $id] : []);
$type = $id ? $DB->get_record('local_documents_type', ['id' => $id], '*', MUST_EXIST) : null;
$current = $type && $type->currentversion ? $DB->get_record('local_documents_tplver', ['id' => $type->currentversion]) : null;

$form = new template_form($url, ['isnew' => !$type, 'current' => $current]);
if ($type) {
    $form->set_data([
        'id' => $type->id, 'name' => $type->name, 'seriesid' => $type->seriesid, 'subject' => $type->subject,
        'source' => $type->source, 'unitcode' => $type->unitcode, 'validitydays' => $type->validitydays,
        'enabled' => $type->enabled, 'title' => $current->title ?? '', 'body' => $current->body ?? '',
        'fields' => $current->fields ?? '', 'signatories' => $current->signatories ?? 1,
        'allowbodyedit' => $current->allowbodyedit ?? 1,
    ]);
} else {
    $form->set_data(['enabled' => 1, 'allowbodyedit' => 1, 'signatories' => 1, 'validitydays' => 0]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/documents/templates.php'));
}
if ($data = $form->get_data()) {
    $now = time();
    $transaction = $DB->start_delegated_transaction();
    if (!$type) {
        $base = core_text::substr(trim(preg_replace('/[^a-z0-9]+/', '_', core_text::strtolower(
            core_text::specialtoascii((string)$data->name))), '_'), 0, 40) ?: 'documento';
        $shortname = $base;
        for ($i = 2; $DB->record_exists('local_documents_type', ['shortname' => $shortname]); $i++) {
            $shortname = $base . '_' . $i;
        }
        $typeid = (int)$DB->insert_record('local_documents_type', (object)[
            'shortname' => $shortname, 'name' => trim($data->name), 'seriesid' => (int)$data->seriesid,
            'subject' => $data->subject, 'source' => $data->source, 'unitcode' => $data->unitcode,
            'validitydays' => max(0, (int)$data->validitydays), 'currentversion' => 0, 'enabled' => (int)$data->enabled,
            'sortorder' => 1000, 'timecreated' => $now, 'timemodified' => $now,
        ]);
    } else {
        $typeid = (int)$type->id;
        $DB->update_record('local_documents_type', (object)[
            'id' => $typeid, 'name' => trim($data->name), 'seriesid' => (int)$data->seriesid, 'source' => $data->source,
            'unitcode' => $data->unitcode, 'validitydays' => max(0, (int)$data->validitydays),
            'enabled' => (int)$data->enabled, 'timemodified' => $now,
        ]);
    }
    if (!$current || template_form::content_changed($current, (array)$data)) {
        templates::save_version($typeid, (array)$data, (int)$USER->id);
    }
    $transaction->allow_commit();
    redirect(new moodle_url('/local/documents/template_edit.php', ['id' => $typeid]),
        get_string('templatesaved', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$title = $type ? $type->name : get_string('newtype', 'local_documents');
ui::setup($url, $title, 'templates');
echo ui::header(s($title));

if ($type && $compare) {
    $new = $DB->get_record('local_documents_tplver', ['typeid' => $type->id, 'version' => $compare], '*', MUST_EXIST);
    $old = $DB->get_record('local_documents_tplver', ['typeid' => $type->id, 'version' => $compare - 1]);
    echo html_writer::tag('h3', get_string('changesin', 'local_documents', $compare), ['class' => 'h5']);
    echo html_writer::tag('p', s($new->changenote), ['class' => 'text-muted']);
    foreach (['title' => 'printedtitle', 'body' => 'body', 'fields' => 'fields'] as $field => $label) {
        $lines = templates::diff($old ? (string)$old->$field : '', (string)$new->$field);
        if (!array_filter($lines, static fn($l) => $l[0] !== ' ')) {
            continue;
        }
        echo html_writer::tag('h4', get_string($label, 'local_documents'), ['class' => 'h6 mt-3']);
        $out = '';
        foreach ($lines as [$mark, $line]) {
            $class = $mark === '+' ? 'local-documents-diff-add' : ($mark === '-' ? 'local-documents-diff-del' : '');
            $out .= html_writer::div(s($mark . ' ' . $line), $class);
        }
        echo html_writer::div($out, 'local-documents-diff');
    }
    echo html_writer::div(html_writer::link($url, get_string('back')), 'mt-3');
    echo $OUTPUT->footer();
    exit;
}

$form->display();

if ($type) {
    echo html_writer::tag('details', html_writer::tag('summary', get_string('placeholders', 'local_documents'))
        . html_writer::alist(array_map(static fn($k, $v) => html_writer::tag('code', '{{' . s($k) . '}}') . ' — ' . s($v),
            array_keys(templates::known_placeholders()), templates::known_placeholders()), ['class' => 'small mt-2'])
        . html_writer::tag('p', get_string('placeholdersfields', 'local_documents'), ['class' => 'small text-muted']),
        ['class' => 'mt-3']);

    echo html_writer::tag('h3', get_string('templatehistory', 'local_documents'), ['class' => 'h5 mt-4']);
    $versions = $DB->get_records_sql('SELECT v.*, (SELECT COUNT(1) FROM {local_documents_doc} d
                                                    WHERE d.tplverid = v.id AND d.status <> :draft) AS used
                                        FROM {local_documents_tplver} v WHERE v.typeid = :typeid
                                    ORDER BY v.version DESC', ['typeid' => $type->id, 'draft' => 'draft']);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('version', 'local_documents'), get_string('when', 'local_documents'),
        get_string('who', 'local_documents'), get_string('changenote', 'local_documents'),
        get_string('issuedcount', 'local_documents'), ''];
    foreach ($versions as $v) {
        $table->data[] = [
            'v' . (int)$v->version . ((int)$v->id === (int)$type->currentversion
                ? ' ' . html_writer::span(get_string('current', 'local_documents'), 'badge badge-success bg-success') : ''),
            userdate((int)$v->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            ui::user((int)$v->createdby),
            s($v->changenote),
            (int)$v->used,
            html_writer::link(new moodle_url($url, ['compare' => $v->version]), get_string('showchanges', 'local_documents')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
