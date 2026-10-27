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
 * The series catalogue (Tabla de Retención Documental) and the correlatives in use.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\config;
use local_documents\local\numbering;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:managetemplates', $context);

$url = new moodle_url('/local/documents/series.php');
$action = optional_param('action', '', PARAM_ALPHA);

if ($action !== '' && confirm_sesskey()) {
    if ($action === 'add') {
        $code = core_text::strtoupper(required_param('code', PARAM_ALPHANUM));
        $name = trim(required_param('name', PARAM_TEXT));
        if ($code === '' || $name === '' || $DB->record_exists('local_documents_series', ['code' => $code])) {
            redirect($url, get_string('seriesinvalid', 'local_documents'), null, \core\output\notification::NOTIFY_ERROR);
        }
        $DB->insert_record('local_documents_series', (object)[
            'code' => $code, 'name' => $name,
            'trdcode' => trim(optional_param('trdcode', '', PARAM_TEXT)),
            'parentcode' => core_text::strtoupper(optional_param('parentcode', '', PARAM_ALPHANUM)),
            'docvalue' => optional_param('docvalue', 'administrativo', PARAM_ALPHA),
            'keepactive' => max(0, optional_param('keepactive', 5, PARAM_INT)),
            'keepcentral' => max(0, optional_param('keepcentral', 10, PARAM_INT)),
            'permanent' => optional_param('permanent', 0, PARAM_BOOL) ? 1 : 0,
            'enabled' => 1, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        redirect($url, get_string('seriesadded', 'local_documents', $code), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if ($action === 'toggle') {
        $series = $DB->get_record('local_documents_series', ['id' => required_param('seriesid', PARAM_INT)], '*', MUST_EXIST);
        $DB->set_field('local_documents_series', 'enabled', $series->enabled ? 0 : 1, ['id' => $series->id]);
        redirect($url);
    }
    if ($action === 'counter') {
        $seriesid = required_param('seriesid', PARAM_INT);
        $DB->get_record('local_documents_series', ['id' => $seriesid], 'id', MUST_EXIST);
        $unit = core_text::strtoupper(required_param('unit', PARAM_ALPHANUM));
        if (!array_key_exists($unit, config::units())) {
            throw new moodle_exception('invalidunit', 'local_documents');
        }
        try {
            numbering::set_last($seriesid, required_param('year', PARAM_INT), $unit, max(0, required_param('last', PARAM_INT)));
        } catch (moodle_exception $e) {
            redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
        }
        redirect($url, get_string('countersaved', 'local_documents'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

ui::setup($url, get_string('seriesandnumbers', 'local_documents'), 'series');
echo ui::header(get_string('seriesandnumbers', 'local_documents'));

$school = config::school();
echo html_writer::tag('p', get_string('numberformat', 'local_documents', s(numbering::display(numbering::format('CONSES', 22,
    $school['sace'] ?: 'SACE', (int)date('Y'), array_key_first(config::units()), $school['jornada'])))),
    ['class' => 'text-muted']);

// Correlatives.
echo html_writer::tag('h3', get_string('counters', 'local_documents'), ['class' => 'h5 mt-3']);
$counters = $DB->get_records_sql('SELECT c.*, s.code FROM {local_documents_counter} c
                                    JOIN {local_documents_series} s ON s.id = c.seriesid
                                ORDER BY c.year DESC, s.code, c.unitcode');
if ($counters) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [get_string('series', 'local_documents'), get_string('unit', 'local_documents'),
        get_string('year', 'local_documents'), get_string('lastused', 'local_documents'),
        get_string('nextnumber', 'local_documents')];
    foreach ($counters as $c) {
        $table->data[] = [html_writer::tag('code', s($c->code)), s($c->unitcode), (int)$c->year,
            numbering::pad((int)$c->lastvalue), numbering::pad((int)$c->lastvalue + 1)];
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('nocounters', 'local_documents'), ['class' => 'text-muted']);
}

$seriesoptions = [];
$allseries = $DB->get_records('local_documents_series', null, 'code');
foreach ($allseries as $s) {
    $seriesoptions[$s->id] = $s->code;
}
$unitoptions = array_combine(array_keys(config::units()), array_keys(config::units()));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
    'class' => 'd-flex flex-wrap gap-2 align-items-end mb-4 local-documents-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'counter']);
foreach ([
    ['seriesid', get_string('series', 'local_documents'), html_writer::select($seriesoptions, 'seriesid', '', false,
        ['id' => 'ld-c-seriesid', 'class' => 'form-select custom-select'])],
    ['unit', get_string('unit', 'local_documents'), html_writer::select($unitoptions, 'unit', '', false,
        ['id' => 'ld-c-unit', 'class' => 'form-select custom-select'])],
    ['year', get_string('year', 'local_documents'), html_writer::empty_tag('input', ['type' => 'number', 'name' => 'year',
        'id' => 'ld-c-year', 'value' => date('Y'), 'class' => 'form-control', 'min' => 2000, 'max' => 2100])],
    ['last', get_string('lastused', 'local_documents'), html_writer::empty_tag('input', ['type' => 'number',
        'name' => 'last', 'id' => 'ld-c-last', 'value' => 0, 'class' => 'form-control', 'min' => 0])],
] as [$name, $label, $control]) {
    echo html_writer::div(html_writer::label($label, 'ld-c-' . $name, true, ['class' => 'form-label small mb-1']) . $control);
}
echo html_writer::div(html_writer::tag('button', get_string('setcounter', 'local_documents'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']));
echo html_writer::end_tag('form');
echo html_writer::tag('p', get_string('setcounter_help', 'local_documents'), ['class' => 'small text-muted']);

// Catalogue.
echo html_writer::tag('h3', get_string('seriescatalogue', 'local_documents'), ['class' => 'h5 mt-4']);
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('seriescode', 'local_documents'), get_string('seriesname', 'local_documents'),
    get_string('trdcode', 'local_documents'), get_string('docvalue', 'local_documents'),
    get_string('retention', 'local_documents'), ''];
foreach ($allseries as $s) {
    $toggle = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'toggle'])
        . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'seriesid', 'value' => $s->id])
        . html_writer::tag('button', get_string($s->enabled ? 'disable' : 'enable'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary'])
        . html_writer::end_tag('form');
    $table->data[] = [
        html_writer::tag('code', s($s->code)) . ($s->enabled ? '' : ' ' . html_writer::span(
            get_string('disabled', 'local_documents'), 'badge badge-secondary bg-secondary')),
        ($s->parentcode ? html_writer::span(s($s->parentcode) . ' › ', 'text-muted') : '') . s($s->name),
        s($s->trdcode),
        s(core_text::strtoupper($s->docvalue)),
        get_string('retentionshort', 'local_documents', (object)['active' => (int)$s->keepactive,
            'central' => (int)$s->keepcentral, 'permanent' => $s->permanent
                ? get_string('retentionpermanent', 'local_documents') : '']),
        $toggle,
    ];
}
echo html_writer::table($table);

// Add a series.
echo html_writer::tag('h3', get_string('addseries', 'local_documents'), ['class' => 'h5 mt-4']);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
    'class' => 'd-flex flex-wrap gap-2 align-items-end local-documents-filters']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
foreach ([
    ['code', get_string('seriescode', 'local_documents'), 'text', '', true],
    ['name', get_string('seriesname', 'local_documents'), 'text', '', true],
    ['trdcode', get_string('trdcode', 'local_documents'), 'text', '', false],
    ['parentcode', get_string('parentcode', 'local_documents'), 'text', '', false],
    ['keepactive', get_string('keepactive', 'local_documents'), 'number', '5', false],
    ['keepcentral', get_string('keepcentral', 'local_documents'), 'number', '10', false],
] as [$name, $label, $type, $value, $required]) {
    $attributes = ['type' => $type, 'name' => $name, 'id' => 'ld-s-' . $name, 'value' => $value, 'class' => 'form-control'];
    if ($required) {
        $attributes['required'] = 'required';
    }
    echo html_writer::div(html_writer::label($label, 'ld-s-' . $name, true, ['class' => 'form-label small mb-1'])
        . html_writer::empty_tag('input', $attributes));
}
echo html_writer::div(html_writer::label(get_string('docvalue', 'local_documents'), 'ld-s-docvalue', true,
    ['class' => 'form-label small mb-1']) . html_writer::select(['administrativo' => 'ADMINISTRATIVO', 'legal' => 'LEGAL',
    'tecnico' => 'TÉCNICO', 'fiscal' => 'FISCAL'], 'docvalue', 'administrativo', false,
    ['id' => 'ld-s-docvalue', 'class' => 'form-select custom-select']));
echo html_writer::div(html_writer::checkbox('permanent', 1, true, get_string('retentionpermanent', 'local_documents'),
    ['id' => 'ld-s-permanent']), 'pb-2');
echo html_writer::div(html_writer::tag('button', get_string('addseries', 'local_documents'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']));
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
