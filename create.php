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
 * Start a document: pick what, then who it is about.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\documents;
use local_documents\local\ui;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/documents:issue', $context);

$typeid = optional_param('type', 0, PARAM_INT);
$userid = optional_param('user', 0, PARAM_INT);
$q = trim(optional_param('q', '', PARAM_TEXT));

$url = new moodle_url('/local/documents/create.php', array_filter(['type' => $typeid, 'q' => $q]));
$type = $typeid ? $DB->get_record('local_documents_type', ['id' => $typeid, 'enabled' => 1]) : null;

// Create the draft: a person picked, or a document about no platform user.
if ($type && optional_param('start', 0, PARAM_BOOL)) {
    require_sesskey();
    if (in_array($type->subject, ['student', 'staff'], true)) {
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id', MUST_EXIST);
        $userid = (int)$user->id;
    }
    $id = documents::create_draft((int)$type->id, $userid, (int)$USER->id);
    redirect(new moodle_url('/local/documents/edit.php', ['id' => $id]));
}

ui::setup($url, get_string('newdocument', 'local_documents'), 'new');
echo ui::header(get_string('newdocument', 'local_documents'));

if (!$type) {
    $types = $DB->get_records_sql('SELECT t.*, s.code AS seriescode FROM {local_documents_type} t
                                     JOIN {local_documents_series} s ON s.id = t.seriesid
                                    WHERE t.enabled = 1 AND t.currentversion > 0
                                 ORDER BY t.sortorder, t.name');
    $groups = ['student' => [], 'staff' => [], 'person' => [], 'none' => []];
    foreach ($types as $t) {
        $groups[$t->subject][] = $t;
    }
    echo html_writer::tag('p', get_string('pickdocument', 'local_documents'), ['class' => 'text-muted']);
    foreach ($groups as $subject => $list) {
        if (!$list) {
            continue;
        }
        echo html_writer::tag('h3', get_string('group_' . $subject, 'local_documents'), ['class' => 'h5 mt-4']);
        echo html_writer::start_div('local-documents-typegrid');
        foreach ($list as $t) {
            $link = new moodle_url('/local/documents/create.php', ['type' => $t->id]);
            echo html_writer::link($link, html_writer::span(s($t->seriescode), 'local-documents-typecode')
                . html_writer::span(s($t->name), 'local-documents-typename'), ['class' => 'local-documents-typecard']);
        }
        echo html_writer::end_div();
    }
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', html_writer::tag('strong', s($type->name)) . ' · '
    . html_writer::link(new moodle_url('/local/documents/create.php'), get_string('changedocument', 'local_documents')));

if (!in_array($type->subject, ['student', 'staff'], true)) {
    echo html_writer::tag('p', get_string($type->subject === 'person' ? 'startperson' : 'startletter', 'local_documents'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out_omit_querystring()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1]);
    echo html_writer::tag('button', get_string('startdraft', 'local_documents'), ['type' => 'submit',
        'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

// Who it is about.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out_omit_querystring(),
    'class' => 'd-flex gap-2 mb-3', 'role' => 'search']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type->id]);
echo html_writer::label(get_string($type->subject === 'student' ? 'findstudent' : 'findstaff', 'local_documents'),
    'ld-person', false, ['class' => 'visually-hidden sr-only']);
echo html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'id' => 'ld-person', 'value' => $q,
    'class' => 'form-control', 'autofocus' => 'autofocus',
    'placeholder' => get_string($type->subject === 'student' ? 'findstudent' : 'findstaff', 'local_documents')]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

if (core_text::strlen($q) >= 2) {
    $like = '%' . $DB->sql_like_escape($q) . '%';
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $users = $DB->get_records_sql("SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                                          u.middlename, u.alternatename, u.email, u.idnumber
                                     FROM {user} u
                                    WHERE u.deleted = 0 AND u.confirmed = 1 AND u.id > 2
                                      AND (" . $DB->sql_like($fullname, ':q1', false, false) . '
                                           OR ' . $DB->sql_like('u.idnumber', ':q2', false) . '
                                           OR ' . $DB->sql_like('u.email', ':q3', false) . ')
                                 ORDER BY u.lastname, u.firstname',
        ['q1' => $like, 'q2' => $like, 'q3' => $like], 0, 30);
    if (!$users) {
        echo $OUTPUT->notification(get_string('nobodyfound', 'local_documents'), \core\output\notification::NOTIFY_INFO,
            false);
    }
    echo html_writer::start_tag('ul', ['class' => 'list-group local-documents-people']);
    foreach ($users as $user) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out_omit_querystring(),
                'class' => 'ms-auto ml-auto'])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'user', 'value' => $user->id])
            . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'start', 'value' => 1])
            . html_writer::tag('button', get_string('startdraft', 'local_documents'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-primary'])
            . html_writer::end_tag('form');
        $courses = count(enrol_get_users_courses($user->id, true, 'id'));
        echo html_writer::tag('li', html_writer::div(html_writer::tag('strong', s(fullname($user)))
            . html_writer::div(s(trim($user->idnumber . ' · ' . get_string('coursecount', 'local_documents', $courses),
                ' ·')), 'small text-muted')) . $form, ['class' => 'list-group-item d-flex align-items-center gap-3']);
    }
    echo html_writer::end_tag('ul');
}

echo $OUTPUT->footer();
