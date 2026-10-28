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
 * Public verification page on this site, used when no website page is set.
 *
 * Same answer as verify_api.php, as a page. No login: whoever holds the paper
 * must be able to check it.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');

use local_documents\local\config;
use local_documents\local\verify;

$code = optional_param('c', '', PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/documents/verify.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype('local-documents-area');
$PAGE->set_title(get_string('verifytitle', 'local_documents'));
header('X-Robots-Tag: noindex');

$result = null;
if ($code !== '') {
    $result = verify::allow((string)getremoteaddr()) ? verify::lookup($code, (string)getremoteaddr())
        : ['status' => 'ratelimited'];
}

echo $OUTPUT->header();
echo html_writer::start_div('local-documents-verify');
echo html_writer::tag('h1', get_string('verifytitle', 'local_documents'), ['class' => 'h3']);
echo html_writer::tag('p', s(config::school()['name']), ['class' => 'text-muted']);

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'd-flex gap-2 my-3']);
echo html_writer::label(get_string('verifycode', 'local_documents'), 'ld-code', false, ['class' => 'visually-hidden sr-only']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'c', 'id' => 'ld-code', 'value' => $code,
    'class' => 'form-control', 'placeholder' => 'XXXX-XXXX-XXXX', 'autocomplete' => 'off', 'maxlength' => 20]);
echo html_writer::tag('button', get_string('verifybutton', 'local_documents'), ['type' => 'submit',
    'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

if ($result !== null) {
    $status = $result['status'];
    $class = ['valid' => 'success', 'expired' => 'warning', 'superseded' => 'warning', 'recalled' => 'danger'][$status]
        ?? 'secondary';
    $html = html_writer::tag('h2', get_string('verify_' . $status, 'local_documents'), ['class' => 'h4']);
    if (isset($result['title'])) {
        $rows = [
            [get_string('documenttype', 'local_documents'), $result['title']],
            [get_string('number', 'local_documents'), $result['number'] . ($result['version'] > 1
                ? ' (' . get_string('version', 'local_documents') . ' ' . $result['version'] . ')' : '')],
            [get_string('holder', 'local_documents'), $result['holder']],
            [get_string('holderidnumber', 'local_documents'), $result['holderid']],
            [get_string('subjectline', 'local_documents'), $result['subject']],
            [get_string('issuedate', 'local_documents'), $result['issued']],
            [get_string('validuntil', 'local_documents'), $result['validuntil']],
            [get_string('issuedbyschool', 'local_documents'), $result['school'] . ' · ' . $result['unit']],
            [get_string('signedby', 'local_documents'), implode('; ', $result['signers'])],
        ];
        if ($status === 'recalled') {
            $rows[] = [get_string('recalledon', 'local_documents'), $result['recalled']];
            $rows[] = [get_string('recallnotepublic', 'local_documents'), $result['recallnote']];
        }
        if ($status === 'superseded' && !empty($result['replacedby'])) {
            $rows[] = [get_string('replacedby', 'local_documents'), get_string('replacedbyvalue', 'local_documents',
                (object)['version' => $result['replacedby'], 'date' => $result['replacedon']])];
        }
        $dl = '';
        foreach ($rows as [$label, $value]) {
            if ((string)$value !== '') {
                $dl .= html_writer::tag('dt', s($label)) . html_writer::tag('dd', s($value));
            }
        }
        if (!empty($result['attachments'])) {
            $items = array_map(static fn($a) => s($a['name']) . ' · ' . display_size($a['size'])
                . html_writer::div('SHA-256 ' . html_writer::tag('code', s($a['sha256'])), 'small text-break'),
                $result['attachments']);
            $dl .= html_writer::tag('dt', get_string('attachments', 'local_documents'))
                . html_writer::tag('dd', html_writer::alist($items, [], 'ol'));
        }
        $html .= html_writer::tag('dl', $dl, ['class' => 'local-documents-verify-facts'])
            . html_writer::tag('p', get_string('verifysha', 'local_documents') . ' '
                . html_writer::tag('code', s($result['sha256']), ['class' => 'text-break']), ['class' => 'small']);
    }
    echo html_writer::div($html, 'alert alert-' . $class . ' local-documents-verify-result');
}
echo html_writer::tag('p', get_string('verifyprivacy', 'local_documents'), ['class' => 'small text-muted']);
echo html_writer::end_div();
echo $OUTPUT->footer();
