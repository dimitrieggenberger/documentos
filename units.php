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
 * Issuing units: who signs for each, and which documents each may issue.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\form\unit_form;
use local_documents\local\ui;
use local_documents\local\units;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:manageunits', $context);

$id = optional_param('id', -1, PARAM_INT);
$url = new moodle_url('/local/documents/units.php');

if ($id >= 0) {
    $editurl = new moodle_url($url, ['id' => $id]);
    $unit = $id > 0 ? $DB->get_record('local_documents_unit', ['id' => $id], '*', MUST_EXIST) : null;
    $form = new unit_form($editurl, ['substitute' => $unit && (int)$unit->substituteid > 0]);
    if ($unit) {
        $unit->typeids = units::type_ids($id);
        $unit->userid = (int)$unit->userid ?: '';
        $unit->substituteid = (int)$unit->substituteid ?: '';
        $form->set_data($unit);
    }
    if ($form->is_cancelled()) {
        redirect($url);
    }
    if ($data = $form->get_data()) {
        $data->id = $id;
        $data->typeids = $data->typeids ?? [];
        if (empty($data->substituteid)) {
            $data->substituteid = $data->substitutefrom = $data->substituteuntil = 0;
        }
        units::save($data);
        redirect($url, get_string('unitsaved', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    $title = $id > 0 ? get_string('editunit', 'local_documents') : get_string('newunit', 'local_documents');
    ui::setup($editurl, $title, 'units');
    echo ui::header($title);
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

ui::setup($url, get_string('units', 'local_documents'), 'units');
echo ui::header(get_string('units', 'local_documents'));
echo html_writer::tag('p', get_string('unitsintro', 'local_documents'), ['class' => 'text-muted']);
// Art. 8 of the Ley sobre Firmas Electrónicas: the means of signing under the signer's sole control. A second
// factor on the accounts that sign is what makes a password alone not enough (docs/documents-plan.md 12).
if (!get_config('tool_mfa', 'enabled')) {
    echo $OUTPUT->notification(get_string('mfarecommended', 'local_documents', (new moodle_url('/admin/settings.php',
        ['section' => 'managemfa']))->out()), \core\output\notification::NOTIFY_WARNING);
}
echo html_writer::div(html_writer::link(new moodle_url($url, ['id' => 0]), get_string('newunit', 'local_documents'),
    ['class' => 'btn btn-primary']), 'mb-3');

$typecount = $DB->get_records_sql_menu('SELECT unitid, COUNT(1) FROM {local_documents_unittype} GROUP BY unitid');
$signcount = $DB->get_records_sql_menu('SELECT unitid, COUNT(1) FROM {local_documents_signer} GROUP BY unitid');
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('unitcode', 'local_documents'), get_string('unitname', 'local_documents'),
    get_string('unituser', 'local_documents'), get_string('unittypes', 'local_documents'),
    get_string('signsfor', 'local_documents'), ''];
foreach (units::all() as $unit) {
    $details = units::details($unit);
    $head = (int)$unit->userid > 0
        ? s(trim($details['signername'] . ', ' . $details['signertitle'], ', '))
        : html_writer::span(get_string('unitwithoutheadshort', 'local_documents'), 'badge badge-danger bg-danger');
    if ((int)$unit->substituteid > 0) {
        $now = units::signer_now($unit);
        $period = [];
        if ((int)$unit->substitutefrom > 0) {
            $period[] = get_string('substitutefromshort', 'local_documents', userdate($unit->substitutefrom,
                get_string('strftimedatefullshort', 'langconfig')));
        }
        if ((int)$unit->substituteuntil > 0) {
            $period[] = get_string('substituteuntilshort', 'local_documents', userdate($unit->substituteuntil,
                get_string('strftimedatefullshort', 'langconfig')));
        }
        $head .= html_writer::div(get_string('substitutedby', 'local_documents', ui::user((int)$unit->substituteid))
            . ($period ? ' (' . implode(' ', $period) . ')' : '') . ' ' . ((int)$now['onbehalfof'] > 0
                ? html_writer::span(get_string('substituteactive', 'local_documents'), 'badge badge-warning bg-warning text-dark')
                : html_writer::span(get_string('substituteinactive', 'local_documents'), 'badge badge-light bg-light text-dark')),
            'small mt-1');
    }
    $table->data[] = [
        html_writer::tag('code', s($unit->code)) . ($unit->enabled ? '' : ' ' . html_writer::span(
            get_string('disabled', 'local_documents'), 'badge badge-secondary bg-secondary')),
        s($unit->name) . html_writer::div(s(trim($unit->email . ' · ' . $unit->phone, ' ·')), 'small text-muted'),
        $head,
        (int)($typecount[$unit->id] ?? 0),
        (int)($signcount[$unit->id] ?? 0),
        html_writer::link(new moodle_url($url, ['id' => $unit->id]), get_string('edit'),
            ['class' => 'btn btn-sm btn-outline-secondary']),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
