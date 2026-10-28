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

namespace local_documents\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use local_documents\local\config;
use local_documents\local\documents;
use local_documents\local\sources;
use local_documents\local\templates;
use local_documents\local\units;

/**
 * The draft of one document: who, the template's fields, the wording, the signers.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class draft_form extends \moodleform {

    /**
     * Define the form from the document's template version.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $bundle = $this->_customdata['bundle'];
        $years = $this->_customdata['years'];
        $doc = $bundle['doc'];
        $type = $bundle['type'];
        $tplver = $bundle['tplver'];

        $mform->addElement('hidden', 'id', $doc->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'whohdr', get_string($type->subject === 'none' ? 'letterhdr' : 'whohdr',
            'local_documents'));
        if ($type->subject !== 'none') {
            if ((int)$doc->userid === 0 && (int)$doc->docversion === 1) {
                $mform->addElement('text', 'holdername', get_string('holder', 'local_documents'), ['size' => 50]);
                $mform->addRule('holdername', null, 'required', null, 'client');
            } else {
                $mform->addElement('static', 'holdernamestatic', get_string('holder', 'local_documents'),
                    s($doc->holdername));
            }
            $mform->setType('holdername', PARAM_TEXT);
            if ((int)$doc->docversion === 1) {
                $mform->addElement('text', 'holderidnumber', get_string('holderidnumber', 'local_documents'),
                    ['size' => 30]);
                $mform->addHelpButton('holderidnumber', 'holderidnumber', 'local_documents');
            } else {
                $mform->addElement('static', 'holderidstatic', get_string('holderidnumber', 'local_documents'),
                    s($doc->holderidnumber));
            }
            $mform->setType('holderidnumber', PARAM_TEXT);
            $mform->addElement('select', 'gender', get_string('gender', 'local_documents'), [
                '' => get_string('choosedots'), 'M' => get_string('gender_m', 'local_documents'),
                'F' => get_string('gender_f', 'local_documents')]);
            $mform->addRule('gender', null, 'required', null, 'client');
            $mform->addHelpButton('gender', 'gender', 'local_documents');
        }
        if ($type->subject === 'student' && $years) {
            $mform->addElement('select', 'year', get_string('schoolyear', 'local_documents'), $years);
        }

        $fields = templates::parse_fields((string)$tplver->fields);
        if ($fields) {
            $mform->addElement('header', 'fieldshdr', get_string('fieldshdr', 'local_documents'));
            $mform->setExpanded('fieldshdr');
            foreach ($fields as $key => $field) {
                $name = 'f_' . $key;
                switch ($field['type']) {
                    case 'textarea':
                        $mform->addElement('textarea', $name, s($field['label']), ['rows' => 8, 'cols' => 60]
                            + templates::editor_attributes(null, false));
                        break;
                    case 'select':
                        $options = ['' => get_string('choosedots')];
                        foreach ($field['options'] as $option) {
                            $options[$option] = $option;
                        }
                        $mform->addElement('select', $name, s($field['label']), $options);
                        break;
                    case 'date':
                        $mform->addElement('date_selector', $name, s($field['label']), ['optional' => true]);
                        break;
                    default:
                        $mform->addElement('text', $name, s($field['label']), ['size' => 60]);
                }
                $mform->setType($name, PARAM_TEXT);
                if ($field['required']) {
                    $mform->addRule($name, null, 'required', null, 'client');
                }
            }
        }

        $mform->addElement('header', 'signhdr', get_string('signhdr', 'local_documents'));
        $mform->setExpanded('signhdr');
        $units = units::for_type((int)$type->id);
        if ((int)$doc->docversion === 1 && count($units) > 1) {
            $unitoptions = [];
            foreach ($units as $unit) {
                $unitoptions[$unit->code] = $unit->code . ' — ' . $unit->name;
            }
            $mform->addElement('select', 'unitcode', get_string('unit', 'local_documents'), $unitoptions);
            $mform->addHelpButton('unitcode', 'unit', 'local_documents');
        } else {
            $mform->addElement('static', 'unitstatic', get_string('unit', 'local_documents'),
                s($doc->unitcode . ' — ' . (config::units()[$doc->unitcode] ?? '')));
        }
        $signers = sources::signers($doc);
        $lines = [];
        foreach ($signers as $signer) {
            $lines[] = s(trim($signer['name'] . ', ' . (!empty($signer['substitutefor'])
                ? get_string('substitutefor', 'local_documents', $signer['substitutefor']) : $signer['title']), ', ') ?: '—')
                . ' <span class="text-muted">(' . s($signer['unitcode']) . ')</span>';
        }
        $handwritten = documents::signature_mode($bundle['type']) === documents::SIGN_HANDWRITTEN;
        $mform->addElement('static', 'signersstatic', get_string('requiredsignatures', 'local_documents'),
            ($lines ? implode('<br>', $lines) : get_string('nosignaturesneeded', 'local_documents'))
            . ($lines && $handwritten ? \html_writer::div(get_string('signhandwrittennote', 'local_documents'),
                'small text-muted mt-1') : ''));
        $mform->addHelpButton('signersstatic', 'requiredsignatures', 'local_documents');
        $mform->addElement('date_selector', 'issuedate', get_string('issuedate', 'local_documents'));
        $mform->addHelpButton('issuedate', 'issuedate', 'local_documents');

        if (!empty($tplver->allowbodyedit)) {
            $mform->addElement('header', 'bodyhdr', get_string('bodyhdr', 'local_documents'));
            $mform->addElement('textarea', 'body', get_string('body', 'local_documents'),
                ['rows' => 16, 'cols' => 80, 'class' => 'local-documents-bodyfield'] + templates::editor_attributes($tplver));
            $mform->setType('body', PARAM_RAW);
            $mform->addHelpButton('body', 'body', 'local_documents');
        }

        $mform->addElement('header', 'attachmentshdr', get_string('attachments', 'local_documents'));
        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'local_documents'), null,
            self::attachment_options());
        $mform->addHelpButton('attachments', 'attachments', 'local_documents');

        $buttons = [
            $mform->createElement('submit', 'save', get_string('savedraft', 'local_documents')),
            $mform->createElement('submit', 'preview', get_string('previewpdf', 'local_documents')),
            $mform->createElement('submit', 'issue', get_string($this->_customdata['signsalone'] ? 'issue' : 'sendtosign',
                'local_documents'), ['class' => 'btn-success']),
        ];
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * What may be attached: PDF and images, up to ten files of 20 MB.
     *
     * @return array
     */
    public static function attachment_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 10, 'maxbytes' => 20 * 1024 * 1024,
            'accepted_types' => ['.pdf', '.png', '.jpg', '.jpeg']];
    }

    /**
     * The form's values from the document.
     *
     * @param array $bundle
     * @return array
     */
    public static function defaults(array $bundle): array {
        $doc = $bundle['doc'];
        $data = $bundle['data'];
        $values = [
            'id' => $doc->id,
            'holdername' => $doc->holdername,
            'holderidnumber' => $doc->holderidnumber,
            'gender' => $data['gender'] ?? '',
            'year' => $data['year'] ?? '',
            'unitcode' => $doc->unitcode,
            'issuedate' => (int)($data['issuedate'] ?? time()),
            'body' => $data['body'] ?? $bundle['tplver']->body,
        ];
        foreach (templates::parse_fields((string)$bundle['tplver']->fields) as $key => $field) {
            $value = (string)($data['fields'][$key] ?? '');
            $values['f_' . $key] = $field['type'] === 'date' ? (ctype_digit($value) ? (int)$value : 0) : $value;
        }
        return $values;
    }

    /**
     * The submitted values as documents::update_draft() takes them.
     *
     * @param \stdClass $submitted
     * @param array $bundle
     * @return array
     */
    public static function changes(\stdClass $submitted, array $bundle): array {
        $changes = ['fields' => []];
        foreach (['holdername', 'holderidnumber', 'gender', 'year', 'unitcode', 'body'] as $key) {
            if (isset($submitted->$key)) {
                $changes[$key] = (string)$submitted->$key;
            }
        }
        if (isset($submitted->issuedate)) {
            $changes['issuedate'] = (int)$submitted->issuedate;
        }
        foreach (templates::parse_fields((string)$bundle['tplver']->fields) as $key => $field) {
            $name = 'f_' . $key;
            $value = $submitted->$name ?? '';
            $changes['fields'][$key] = $field['type'] === 'date' ? ((int)$value > 0 ? (string)(int)$value : '')
                : trim((string)$value);
        }
        return $changes;
    }
}
