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
 * Document types and their templates.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:managetemplates', $context);

$url = new moodle_url('/local/documents/templates.php');
ui::setup($url, get_string('templates', 'local_documents'), 'templates');
echo ui::header(get_string('templates', 'local_documents'));

echo html_writer::div(html_writer::link(new moodle_url('/local/documents/template_edit.php'),
    get_string('newtype', 'local_documents'), ['class' => 'btn btn-primary']), 'mb-3');

$types = $DB->get_records_sql('SELECT t.*, s.code AS seriescode, v.version, v.timecreated AS versiontime,
                                      (SELECT COUNT(1) FROM {local_documents_doc} d
                                        WHERE d.typeid = t.id AND d.status <> :draft) AS issuedcount
                                 FROM {local_documents_type} t
                                 JOIN {local_documents_series} s ON s.id = t.seriesid
                            LEFT JOIN {local_documents_tplver} v ON v.id = t.currentversion
                             ORDER BY t.sortorder, t.name', ['draft' => 'draft']);
$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('typename', 'local_documents'), get_string('series', 'local_documents'),
    get_string('subject', 'local_documents'), get_string('templateversion', 'local_documents'),
    get_string('issuedcount', 'local_documents'), ''];
foreach ($types as $t) {
    $name = s($t->name);
    if (!$t->enabled) {
        $name .= ' ' . html_writer::span(get_string('disabled', 'local_documents'), 'badge badge-secondary bg-secondary');
    }
    if ($t->signaturemode === 'handwritten') {
        $name .= ' ' . html_writer::span(get_string('signaturemode_handwrittenshort', 'local_documents'),
            'badge badge-light bg-light text-dark border');
    }
    $table->data[] = [
        $name,
        html_writer::tag('code', s($t->seriescode)) . ' · ' . s($t->unitcode),
        ui::subject($t->subject) . ($t->source === 'grades' ? ' · ' . get_string('source_grades', 'local_documents') : ''),
        $t->version ? 'v' . (int)$t->version . ' · ' . ui::date((int)$t->versiontime) : '—',
        (int)$t->issuedcount,
        html_writer::link(new moodle_url('/local/documents/template_edit.php', ['id' => $t->id]),
            get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
