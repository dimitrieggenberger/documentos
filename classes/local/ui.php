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
        $tosign = count(units::signable_docids((int)$USER->id));
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
                . html_writer::div(s(!empty($signer['substitutefor']) ? get_string_manager()->get_string('pdfsubstitute',
                    'local_documents', $signer['substitutefor'], 'es') : $signer['title']), 'local-documents-sheet-muted')
                . html_writer::div(s(trim(($signer['email'] ?? '') . ' ' . ($signer['phone'] ?? ''))), 'local-documents-sheet-muted')
                . html_writer::div(($composed['signaturemode'] ?? '') === documents::SIGN_HANDWRITTEN
                    ? get_string('signhandwritten', 'local_documents') : (!empty($signer['approvedon'])
                    ? get_string('approvedon', 'local_documents', userdate((int)$signer['approvedon'],
                        get_string('strftimedatetimeshort', 'langconfig')))
                    : get_string('signaturepending', 'local_documents')), 'local-documents-sheet-approval'),
                'local-documents-sheet-signer');
        }
        $companions = '';
        if (!empty($composed['companions'])) {
            $companions = html_writer::div(html_writer::div(get_string_manager()->get_string('pdfcompanions',
                'local_documents', null, 'es'), 'fw-bold') . html_writer::alist(array_map(static fn($item) => s($item['line']),
                $composed['companions']), [], 'ol'), 'local-documents-sheet-attachments');
        }
        $attachments = $companions;
        if (!empty($composed['attachments'])) {
            $items = array_map(static fn($a) => s($a['name']) . ' · ' . display_size($a['size']), $composed['attachments']);
            $attachments .= html_writer::div(html_writer::div(get_string('attachments', 'local_documents'), 'fw-bold')
                . html_writer::alist($items, [], 'ol'), 'local-documents-sheet-attachments');
        }
        return html_writer::div($sheet . ($signers !== '' ? html_writer::div($signers, 'local-documents-sheet-signers') : '')
            . $attachments, 'local-documents-sheet');
    }

    /**
     * The file on a draft: the documents that go with it, and how to add one (docs/documents-plan.md 14).
     *
     * @param array $bundle from documents::get()
     * @param array[] $items from companions::resolve()
     * @param moodle_url $url the draft's page
     * @return string
     */
    public static function companions_card(array $bundle, array $items, moodle_url $url): string {
        global $DB;
        $doc = $bundle['doc'];
        $form = static function(string $action, string $inner, string $class = 'mt-2') use ($url): string {
            return html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',
                'value' => sesskey()]) . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
                'value' => $action]) . $inner, ['method' => 'post', 'action' => $url->out(false), 'class' => $class]);
        };
        $delivery = static function(string $prefix) : string {
            return html_writer::div(html_writer::select([companions::WITH => get_string('companionwith', 'local_documents'),
                companions::FAMILY => get_string('companionfamily', 'local_documents')], 'delivery', companions::WITH, false,
                ['class' => 'form-select form-select-sm', 'id' => $prefix . '-delivery', 'aria-label' =>
                get_string('companiondelivery', 'local_documents')]), 'col-sm-6')
                . html_writer::div(html_writer::empty_tag('input', ['type' => 'date', 'name' => 'deliveredon',
                'class' => 'form-control form-control-sm', 'id' => $prefix . '-date', 'title' =>
                get_string('companiondeliveredon', 'local_documents'), 'aria-label' =>
                get_string('companiondeliveredon', 'local_documents')]), 'col-sm-6');
        };

        $html = html_writer::tag('h3', get_string('companions', 'local_documents'), ['class' => 'h5']);
        $html .= html_writer::tag('p', get_string('companionsintro', 'local_documents'), ['class' => 'small text-muted']);

        // What is in the file now.
        if ($items) {
            $rows = '';
            foreach (array_values($items) as $i => $item) {
                $badge = $item['kind'] === 'external' || $item['ready'] ? '' : ' ' . html_writer::span(
                    get_string($item['status'] === 'missing' ? 'companiongone' : 'status_' . $item['status'], 'local_documents'),
                    'badge badge-warning bg-warning text-dark');
                $open = $item['docid'] > 0 ? ' ' . html_writer::link(new moodle_url($item['ready'] ? '/local/documents/view.php'
                    : '/local/documents/edit.php', ['id' => $item['docid']]), get_string($item['ready'] ? 'open'
                    : 'opendraft', 'local_documents'), ['class' => 'small']) : '';
                $remove = $form('companionremove', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'index',
                    'value' => $i]) . html_writer::tag('button', get_string('remove'), ['type' => 'submit',
                    'class' => 'btn btn-link btn-sm p-0']), 'd-inline ms-2');
                $rows .= html_writer::tag('li', s($item['line']) . $badge . $open . $remove, ['class' => 'mb-1']);
            }
            $html .= html_writer::tag('ol', $rows, ['class' => 'local-documents-companions small']);
        } else {
            $html .= html_writer::tag('p', get_string('companionsempty', 'local_documents'), ['class' => 'small']);
        }

        // Prepare what is missing: a draft for the same person, put in the file.
        $linkedtypes = [];
        foreach ($items as $item) {
            if ($item['docid'] > 0) {
                $linkedtypes[] = (int)$DB->get_field('local_documents_doc', 'typeid', ['id' => $item['docid']]);
            }
        }
        $prepare = '';
        foreach (companions::suggested($bundle['type']) as $type) {
            $years = $type->source === 'grades' ? sources::school_years((int)$doc->userid) : [];
            if (!$years && in_array((int)$type->id, $linkedtypes, true)) {
                continue;
            }
            $inner = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'typeid', 'value' => $type->id]);
            if ($years) {
                $inner .= html_writer::select($years, 'year', array_key_first($years), false,
                    ['class' => 'form-select form-select-sm d-inline-block w-auto me-1', 'aria-label' =>
                    get_string('schoolyear', 'local_documents')]);
            }
            $inner .= html_writer::tag('button', get_string('companionprepare', 'local_documents', s($type->name)),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']);
            $prepare .= $form('companionprepare', $inner);
        }
        if ($prepare !== '') {
            $html .= html_writer::tag('h4', get_string('companionpreparehdr', 'local_documents'), ['class' => 'h6 mt-3'])
                . $prepare;
        }

        // An issued document of the same person.
        $candidates = companions::candidates($doc);
        if ($candidates) {
            $options = [];
            foreach ($candidates as $candidate) {
                $options[$candidate->id] = $candidate->typename . ' — ' . numbering::display((string)$candidate->number);
            }
            $html .= html_writer::tag('h4', get_string('companionaddhdr', 'local_documents'), ['class' => 'h6 mt-3'])
                . $form('companionadd', html_writer::div(html_writer::div(html_writer::select($options, 'companion', '', false,
                    ['class' => 'form-select form-select-sm', 'aria-label' => get_string('companionaddhdr',
                    'local_documents')]), 'col-12') . $delivery('ld-add'), 'row g-2')
                    . html_writer::tag('button', get_string('add'), ['type' => 'submit',
                    'class' => 'btn btn-sm btn-outline-secondary mt-2']));
        }

        // A document issued on paper before the platform.
        $html .= html_writer::tag('details', html_writer::tag('summary', get_string('companionexternalhdr', 'local_documents'),
            ['class' => 'h6 mt-3'])
            . $form('companionexternal', html_writer::div(
                html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'title', 'required' => 'required',
                    'class' => 'form-control form-control-sm', 'placeholder' => get_string('companionexternaltitle',
                    'local_documents'), 'aria-label' => get_string('companionexternaltitle', 'local_documents')]), 'col-sm-8')
                . html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'number',
                    'class' => 'form-control form-control-sm', 'placeholder' => get_string('number', 'local_documents'),
                    'aria-label' => get_string('number', 'local_documents')]), 'col-sm-4')
                . $delivery('ld-ext'), 'row g-2')
                . html_writer::tag('button', get_string('add'), ['type' => 'submit',
                    'class' => 'btn btn-sm btn-outline-secondary mt-2'])));

        return html_writer::div($html, 'local-documents-card local-documents-companions-card mt-4 p-3');
    }
}
