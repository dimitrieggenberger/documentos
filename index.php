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
 * The registry: every document the school issued, and the drafts in progress.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\documents;
use local_documents\local\numbering;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
require_capability('local/documents:view', $context);

$q = trim(optional_param('q', '', PARAM_TEXT));
$seriesid = optional_param('series', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$year = optional_param('year', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$perpage = 50;

$statuses = [documents::STATUS_DRAFT, documents::STATUS_ISSUED, documents::STATUS_SUPERSEDED, documents::STATUS_RECALLED];
if (!in_array($status, $statuses, true)) {
    $status = '';
}
$params = ['q' => $q, 'series' => $seriesid, 'status' => $status, 'year' => $year];
$url = new moodle_url('/local/documents/index.php', array_filter($params));

// The registry lists each document once, at its latest version; earlier versions are on its own page.
$where = ['d.status <> :superseded'];
$sqlparams = ['superseded' => documents::STATUS_SUPERSEDED];
if ($status !== '') {
    $where = ['d.status = :status'];
    $sqlparams = ['status' => $status];
}
if ($seriesid > 0) {
    $where[] = 'd.seriesid = :seriesid';
    $sqlparams['seriesid'] = $seriesid;
}
if ($year > 0) {
    $where[] = 'd.year = :year';
    $sqlparams['year'] = $year;
}
if ($q !== '') {
    $where[] = '(' . $DB->sql_like('d.number', ':q1', false) . ' OR ' . $DB->sql_like('d.holdername', ':q2', false)
        . ' OR ' . $DB->sql_like('d.holderidnumber', ':q3', false) . ' OR ' . $DB->sql_like('d.verifycode', ':q4', false)
        . ')';
    $like = '%' . $DB->sql_like_escape($q) . '%';
    $sqlparams += ['q1' => $like, 'q2' => $like, 'q3' => $like,
        'q4' => '%' . $DB->sql_like_escape(numbering::normalize_code($q) ?: $q) . '%'];
}
$wheresql = implode(' AND ', $where);
$fromsql = "FROM {local_documents_doc} d JOIN {local_documents_type} t ON t.id = d.typeid WHERE $wheresql";

if ($download !== '') {
    $rows = $DB->get_recordset_sql("SELECT d.*, t.name AS typename $fromsql ORDER BY d.timeissued DESC, d.id DESC",
        $sqlparams);
    $columns = ['number' => get_string('number', 'local_documents'), 'version' => get_string('version', 'local_documents'),
        'type' => get_string('documenttype', 'local_documents'), 'holder' => get_string('holder', 'local_documents'),
        'holderid' => get_string('holderidnumber', 'local_documents'), 'date' => get_string('issuedate', 'local_documents'),
        'status' => get_string('status', 'local_documents'), 'issuedby' => get_string('issuedby', 'local_documents'),
        'code' => get_string('verifycode', 'local_documents')];
    \core\dataformat::download_data('registro-documentos-' . date('Ymd'), $download, $columns, $rows,
        static function($d) {
            return [
                'number' => $d->number, 'version' => $d->docversion, 'type' => $d->typename, 'holder' => $d->holdername,
                'holderid' => $d->holderidnumber, 'date' => $d->issuedate ? date('Y-m-d', $d->issuedate) : '',
                'status' => get_string('status_' . $d->status, 'local_documents'),
                'issuedby' => $d->issuedby ? fullname(core_user::get_user($d->issuedby)) : '',
                'code' => numbering::format_code($d->verifycode),
            ];
        });
    $rows->close();
    exit;
}

ui::setup($url, get_string('registry', 'local_documents'), 'registry');
echo ui::header(get_string('registry', 'local_documents'));

// Numbers before detail.
$thisyear = (int)date('Y');
$tiles = [
    [get_string('tileissued', 'local_documents', $thisyear), $DB->count_records_select('local_documents_doc',
        'year = ? AND status IN (?, ?)', [$thisyear, documents::STATUS_ISSUED, documents::STATUS_SUPERSEDED])],
    [get_string('tiledrafts', 'local_documents'), $DB->count_records('local_documents_doc',
        ['status' => documents::STATUS_DRAFT])],
    [get_string('tilerecalled', 'local_documents', $thisyear), $DB->count_records('local_documents_doc',
        ['status' => documents::STATUS_RECALLED, 'year' => $thisyear])],
    [get_string('tileverified', 'local_documents'), $DB->count_records_select('local_documents_log',
        'action = ? AND timecreated > ?', ['verified', time() - 30 * DAYSECS])],
];
echo html_writer::start_div('local-documents-tiles');
foreach ($tiles as [$label, $count]) {
    echo html_writer::div(html_writer::div((string)$count, 'local-documents-tile-number')
        . html_writer::div(s($label), 'local-documents-tile-label'), 'local-documents-tile');
}
echo html_writer::end_div();

// Filters.
$seriesoptions = [0 => get_string('allseries', 'local_documents')];
foreach ($DB->get_records_sql('SELECT DISTINCT s.id, s.code, s.name FROM {local_documents_series} s
                                 JOIN {local_documents_type} t ON t.seriesid = s.id ORDER BY s.code') as $s) {
    $seriesoptions[$s->id] = $s->code . ' · ' . $s->name;
}
$statusoptions = ['' => get_string('allcurrent', 'local_documents')];
foreach ($statuses as $s) {
    $statusoptions[$s] = get_string('status_' . $s, 'local_documents');
}
$yearoptions = [0 => get_string('allyears', 'local_documents')];
foreach ($DB->get_fieldset_sql('SELECT DISTINCT year FROM {local_documents_doc} WHERE year > 0 ORDER BY year DESC') as $y) {
    $yearoptions[$y] = $y;
}
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(),
    'class' => 'local-documents-filters d-flex flex-wrap gap-2 align-items-end mb-3']);
echo html_writer::div(html_writer::label(get_string('search'), 'ld-q', true, ['class' => 'form-label small mb-1'])
    . html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'id' => 'ld-q', 'value' => $q,
        'class' => 'form-control', 'placeholder' => get_string('searchplaceholder', 'local_documents')]));
echo html_writer::div(html_writer::label(get_string('series', 'local_documents'), 'ld-series', true,
    ['class' => 'form-label small mb-1']) . html_writer::select($seriesoptions, 'series', $seriesid, false,
    ['id' => 'ld-series', 'class' => 'form-select custom-select']));
echo html_writer::div(html_writer::label(get_string('status', 'local_documents'), 'ld-status', true,
    ['class' => 'form-label small mb-1']) . html_writer::select($statusoptions, 'status', $status, false,
    ['id' => 'ld-status', 'class' => 'form-select custom-select']));
echo html_writer::div(html_writer::label(get_string('year', 'local_documents'), 'ld-year', true,
    ['class' => 'form-label small mb-1']) . html_writer::select($yearoptions, 'year', $year, false,
    ['id' => 'ld-year', 'class' => 'form-select custom-select']));
echo html_writer::div(html_writer::tag('button', get_string('filter', 'local_documents'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']));
echo html_writer::end_tag('form');

$total = $DB->count_records_sql("SELECT COUNT(1) $fromsql", $sqlparams);
$records = $DB->get_records_sql("SELECT d.*, t.name AS typename $fromsql
                                  ORDER BY CASE WHEN d.status = 'draft' THEN 0 ELSE 1 END, d.timeissued DESC, d.id DESC",
    $sqlparams, $page * $perpage, $perpage);

if (!$records) {
    echo $OUTPUT->notification($q !== '' || $seriesid || $status !== '' || $year
        ? get_string('nothingfound', 'local_documents') : get_string('nodocumentsyet', 'local_documents'),
        \core\output\notification::NOTIFY_INFO, false);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-documents-registry';
    $table->head = [get_string('number', 'local_documents'), get_string('documenttype', 'local_documents'),
        get_string('holder', 'local_documents'), get_string('issuedate', 'local_documents'),
        get_string('status', 'local_documents'), ''];
    foreach ($records as $d) {
        $view = new moodle_url('/local/documents/view.php', ['id' => $d->id]);
        $number = $d->number !== '' ? html_writer::link($view, s($d->number), ['class' => 'local-documents-number'])
            : html_writer::link($view, get_string('nonumberyet', 'local_documents'), ['class' => 'text-muted']);
        if ((int)$d->docversion > 1) {
            $number .= ' ' . html_writer::span('v' . (int)$d->docversion, 'badge badge-light bg-light text-dark border');
        }
        $expired = (int)$d->validuntil > 0 && (int)$d->validuntil < time();
        $table->data[] = [
            $number,
            s($d->typename),
            $d->holdername !== '' && $d->holdername !== null ? s($d->holdername) : html_writer::span('—', 'text-muted'),
            ui::date((int)$d->issuedate && $d->status !== documents::STATUS_DRAFT ? (int)$d->issuedate : 0),
            ui::status($d->status, $expired),
            html_writer::link($d->status === documents::STATUS_DRAFT && has_capability('local/documents:issue', $context)
                ? new moodle_url('/local/documents/edit.php', ['id' => $d->id]) : $view,
                $d->status === documents::STATUS_DRAFT ? get_string('continuedraft', 'local_documents')
                    : get_string('open', 'local_documents'), ['class' => 'btn btn-sm btn-outline-secondary']),
        ];
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
    echo $OUTPUT->download_dataformat_selector(get_string('exportregistry', 'local_documents'), $url->out_omit_querystring(),
        'download', $params);
}

echo $OUTPUT->footer();
