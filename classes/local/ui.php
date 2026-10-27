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
        if (has_capability('local/documents:managetemplates', $context)) {
            $tabs[] = new \tabobject('templates', new moodle_url('/local/documents/templates.php'),
                get_string('templates', 'local_documents'));
            $tabs[] = new \tabobject('series', new moodle_url('/local/documents/series.php'),
                get_string('seriesandnumbers', 'local_documents'));
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
}
