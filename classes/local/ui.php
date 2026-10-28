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

namespace local_documents\local;

use html_writer;
use moodle_url;

/**
 * Page shell and small rendering helpers shared by the plugin's pages.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {

    /**
     * Set up a staff page of the documents area.
     *
     * @param moodle_url $url
     * @param string $title
     * @param string $tab registry, new, templates, series
     * @return void
     */
    public static function setup(moodle_url $url, string $title, string $tab): void {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url($url);
        $PAGE->set_pagelayout('standard');
        $PAGE->set_pagetype('local-documents-area');
        $PAGE->set_title($title);
        $PAGE->set_heading(get_string('pluginname', 'local_documents'));
        $PAGE->navbar->add(get_string('pluginname', 'local_documents'), new moodle_url('/local/documents/index.php'));
        $PAGE->navbar->add($title);
        $PAGE->set_secondary_navigation(false);
        self::$tab = $tab;
    }

    /** @var string The current tab, for tabs(). */
    private static $tab = '';

    /**
     * The area's tabs, the ones this user may open.
     *
     * @return string
     */
    public static function tabs(): string {
        global $OUTPUT;
        $context = \context_system::instance();
        $tabs = [];
        if (has_capability('local/documents:view', $context)) {
            $tabs[] = new \tabobject('registry', new moodle_url('/local/documents/index.php'),
                get_string('registry', 'local_documents'));
        }
        if (has_capability('local/documents:issue', $context)) {
            $tabs[] = new \tabobject('new', new moodle_url('/local/documents/create.php'),
                get_string('newdocument', 'local_documents'));
        }
        global $DB, $USER;
        $tosign = $DB->count_records_sql("SELECT COUNT(DISTINCT a.docid) FROM {local_documents_approval} a
                                            JOIN {local_documents_doc} d ON d.id = a.docid
                                           WHERE a.userid = ? AND a.status = 'pending' AND d.status = ?",
            [$USER->id, documents::STATUS_PENDING]);
        if ($tosign > 0 || units::is_signer((int)$USER->id)) {
            $tabs[] = new \tabobject('sign', new moodle_url('/local/documents/sign.php'),
                get_string('tosign', 'local_documents') . ($tosign > 0 ? ' (' . $tosign . ')' : ''));
        }
        if (has_capability('local/documents:managetemplates', $context)) {
            $tabs[] = new \tabobject('templates', new moodle_url('/local/documents/templates.php'),
                get_string('templates', 'local_documents'));
            $tabs[] = new \tabobject('series', new moodle_url('/local/documents/series.php'),
                get_string('seriesandnumbers', 'local_documents'));
        }
        if (has_capability('local/documents:manageunits', $context)) {
            $tabs[] = new \tabobject('units', new moodle_url('/local/documents/units.php'),
                get_string('units', 'local_documents'));
        }
        if (has_capability('moodle/site:config', $context)) {
            $tabs[] = new \tabobject('settings', new moodle_url('/admin/settings.php', ['section' => 'local_documents']),
                get_string('settings', 'local_documents'));
        }
        return $OUTPUT->tabtree($tabs, self::$tab);
    }

    /**
     * The page header: title, tabs, and a warning while the school is not set up.
     *
     * @param string $title
     * @return string
     */
    public static function header(string $title): string {
        global $OUTPUT;
        $html = $OUTPUT->header() . $OUTPUT->heading($title, 2) . self::tabs();
        if ($missing = config::missing()) {
            $names = array_map(static fn($name) => get_string($name, 'local_documents'), $missing);
            $link = has_capability('moodle/site:config', \context_system::instance())
                ? ' ' . html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'local_documents']),
                    get_string('opensettings', 'local_documents')) : '';
            $html .= $OUTPUT->notification(get_string('settingsmissing', 'local_documents', implode(', ', $names)) . $link,
                \core\output\notification::NOTIFY_WARNING, false);
        }
        return $html;
    }

    /**
     * A status badge.
     *
     * @param string $status
     * @param bool $expired an issued document past its validity
     * @return string
     */
    public static function status(string $status, bool $expired = false): string {
        $classes = [
            documents::STATUS_DRAFT => 'badge badge-secondary bg-secondary',
            documents::STATUS_PENDING => 'badge badge-info bg-info text-dark',
            documents::STATUS_ISSUED => 'badge badge-success bg-success',
            documents::STATUS_SUPERSEDED => 'badge badge-warning bg-warning text-dark',
            documents::STATUS_RECALLED => 'badge badge-danger bg-danger',
        ];
        if ($status === documents::STATUS_ISSUED && $expired) {
            return html_writer::span(get_string('status_expired', 'local_documents'),
                'badge badge-light bg-light text-dark border');
        }
        return html_writer::span(get_string('status_' . $status, 'local_documents'), $classes[$status] ?? 'badge');
    }

    /**
     * A date as the staff pages show it.
     *
     * @param int $time
     * @return string
     */
    public static function date(int $time): string {
        return $time > 0 ? userdate($time, get_string('strftimedate', 'langconfig'))
            : html_writer::span('—', 'text-muted');
    }

    /**
     * A user's name, or a muted dash.
     *
     * @param int $userid
     * @return string
     */
    public static function user(int $userid): string {
        if ($userid <= 0) {
            return html_writer::span('—', 'text-muted');
        }
        $user = \core_user::get_user($userid);
        return $user ? s(fullname($user)) : html_writer::span('—', 'text-muted');
    }

    /**
     * The name of a subject kind: student, staff, person, none.
     *
     * @param string $subject
     * @return string
     */
    public static function subject(string $subject): string {
        return get_string('subject_' . $subject, 'local_documents');
    }

    /**
     * The sheet as it stands, in the order of the PDF (pdf::render) at screen size:
     * header, recipient, title, body, signatures with their state, attachments.
     *
     * @param array $bundle from documents::get()
     * @param array $composed from documents::compose()
     * @return string
     */
    public static function sheet(array $bundle, array $composed): string {
        global $OUTPUT;
        $school = $composed['school'];
        $unit = $composed['unit'] ?? config::unit((string)$bundle['doc']->unitcode);
        $values = $composed['values'];
        $headerblock = static function(string $bold, array $lines): string {
            $html = html_writer::div(s($bold), 'fw-bold');
            foreach (array_filter(array_map('trim', $lines), 'strlen') as $line) {
                $html .= html_writer::div(s($line));
            }
            return html_writer::div($html, 'local-documents-sheet-headblock');
        };
        $sheet = html_writer::div(
            html_writer::empty_tag('img', ['src' => $OUTPUT->image_url('brand/seal-documents', 'local_documents'), 'alt' => '',
                'class' => 'local-documents-sheet-seal'])
            . $headerblock($school['name'], [$school['legalname']])
            . $headerblock($unit['name'], [$unit['email'], $unit['phone']]),
            'local-documents-sheet-header');
        if ($bundle['type']->subject === 'none' && trim((string)($values['f.destinatario'] ?? '')) !== '') {
            $sheet .= html_writer::div(html_writer::div(s($values['f.destinatario']), 'fw-bold')
                . implode('', array_map(static fn($line) => html_writer::div(s($line)), array_filter([$values['f.cargo'] ?? '',
                    $values['f.institucion'] ?? '', $values['f.correo'] ?? ''], static fn($line) => trim((string)$line) !== ''))),
                'local-documents-sheet-recipient');
        }
        $sheet .= html_writer::div(s(templates::plain((string)$bundle['doc']->title, $values)), 'local-documents-sheet-title')
            . html_writer::div($composed['bodyhtml'], 'local-documents-sheet-body');
        $signers = '';
        foreach ($composed['signers'] as $signer) {
            $signers .= html_writer::div(html_writer::div(s($signer['name']) ?: '&nbsp;', 'fw-bold')
                . html_writer::div(s($signer['title']), 'local-documents-sheet-muted')
                . html_writer::div(s(trim(($signer['email'] ?? '') . ' ' . ($signer['phone'] ?? ''))), 'local-documents-sheet-muted')
                . html_writer::div(!empty($signer['approvedon'])
                    ? get_string('approvedon', 'local_documents', userdate((int)$signer['approvedon'],
                        get_string('strftimedatetimeshort', 'langconfig')))
                    : get_string('signaturepending', 'local_documents'), 'local-documents-sheet-approval'),
                'local-documents-sheet-signer');
        }
        $attachments = '';
        if (!empty($composed['attachments'])) {
            $items = array_map(static fn($a) => s($a['name']) . ' · ' . display_size($a['size']), $composed['attachments']);
            $attachments = html_writer::div(html_writer::div(get_string('attachments', 'local_documents'), 'fw-bold')
                . html_writer::alist($items, [], 'ol'), 'local-documents-sheet-attachments');
        }
        return html_writer::div($sheet . ($signers !== '' ? html_writer::div($signers, 'local-documents-sheet-signers') : '')
            . $attachments, 'local-documents-sheet');
    }
}
