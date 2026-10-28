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
use local_documents\local\templates;
use local_documents\local\units;

/**
 * A document type and its template; saving changed wording adds a version.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $isnew = !empty($this->_customdata['isnew']);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'typehdr', get_string('typehdr', 'local_documents'));
        $mform->addElement('text', 'name', get_string('typename', 'local_documents'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $series = [];
        foreach ($DB->get_records('local_documents_series', ['enabled' => 1], 'code') as $s) {
            $series[$s->id] = $s->code . ' · ' . $s->name . ($s->trdcode ? ' (' . $s->trdcode . ')' : '');
        }
        $mform->addElement('select', 'seriesid', get_string('series', 'local_documents'), $series);
        $mform->addHelpButton('seriesid', 'series', 'local_documents');
        $subjects = [];
        foreach (['student', 'staff', 'person', 'none', 'group'] as $subject) {
            $subjects[$subject] = get_string('subject_' . $subject, 'local_documents');
        }
        $mform->addElement('select', 'subject', get_string('subject', 'local_documents'), $subjects);
        if (!$isnew) {
            $mform->freeze('subject');
        }
        $mform->addElement('select', 'source', get_string('source', 'local_documents'), [
            'basic' => get_string('source_basic', 'local_documents'),
            'grades' => get_string('source_grades', 'local_documents')]);
        $mform->addHelpButton('source', 'source', 'local_documents');
        $units = [];
        foreach (config::units() as $code => $label) {
            $units[$code] = $code . ' — ' . $label;
        }
        $mform->addElement('select', 'unitcode', get_string('unit', 'local_documents'), $units);
        $mform->addElement('text', 'validitydays', get_string('validitydays', 'local_documents'), ['size' => 5]);
        $mform->setType('validitydays', PARAM_INT);
        $mform->addHelpButton('validitydays', 'validitydays', 'local_documents');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_documents'));

        $mform->addElement('header', 'signhdr', get_string('requiredsignatures', 'local_documents'));
        $mform->setExpanded('signhdr');
        $unitoptions = [0 => get_string('nosignature', 'local_documents'), -1 => get_string('issuingunit', 'local_documents')];
        foreach (units::all() as $unit) {
            $details = units::details($unit);
            $unitoptions[$unit->id] = $unit->code . ' — ' . $unit->name
                . ($details['signername'] !== '' ? ' (' . $details['signername'] . ')' : '');
        }
        for ($i = 1; $i <= 3; $i++) {
            $mform->addElement('select', 'signer' . $i, get_string('signer', 'local_documents', $i), $unitoptions);
        }
        $mform->addHelpButton('signer1', 'requiredsignatures', 'local_documents');
        $mform->addElement('select', 'signaturemode', get_string('signaturemode', 'local_documents'), [
            'electronic' => get_string('signaturemode_electronic', 'local_documents'),
            'handwritten' => get_string('signaturemode_handwritten', 'local_documents')]);
        $mform->addHelpButton('signaturemode', 'signaturemode', 'local_documents');
        $alltypes = [];
        foreach ($DB->get_records('local_documents_type', null, 'sortorder, name', 'id, shortname, name') as $other) {
            $alltypes[$other->shortname] = $other->name;
        }
        $mform->addElement('autocomplete', 'companions', get_string('companionsuggested', 'local_documents'), $alltypes,
            ['multiple' => true, 'noselectionstring' => get_string('companionsnone', 'local_documents')]);
        $mform->addHelpButton('companions', 'companionsuggested', 'local_documents');
        if (!empty($this->_customdata['issuers'])) {
            $mform->addElement('static', 'issuers', get_string('unitsthatissue', 'local_documents'),
                $this->_customdata['issuers']);
        }

        $mform->addElement('header', 'tplhdr', get_string('tplhdr', 'local_documents'));
        $mform->setExpanded('tplhdr');
        $mform->addElement('text', 'title', get_string('printedtitle', 'local_documents'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('textarea', 'body', get_string('body', 'local_documents'),
            ['rows' => 18, 'cols' => 90, 'class' => 'local-documents-bodyfield']
            + templates::editor_attributes($this->_customdata['current'] ?? null));
        $mform->setType('body', PARAM_RAW);
        $mform->addRule('body', null, 'required', null, 'client');
        $mform->addHelpButton('body', 'body', 'local_documents');
        $mform->addElement('textarea', 'fields', get_string('fields', 'local_documents'),
            ['rows' => 6, 'cols' => 90, 'class' => 'local-documents-bodyfield']);
        $mform->setType('fields', PARAM_RAW);
        $mform->addHelpButton('fields', 'fields', 'local_documents');
        $mform->addElement('textarea', 'consent', get_string('consent', 'local_documents'),
            ['rows' => 5, 'cols' => 90, 'class' => 'local-documents-bodyfield']
            + templates::editor_attributes($this->_customdata['current'] ?? null));
        $mform->setType('consent', PARAM_RAW);
        $mform->addHelpButton('consent', 'consent', 'local_documents');
        $mform->addElement('advcheckbox', 'allowbodyedit', get_string('allowbodyedit', 'local_documents'));
        $mform->addHelpButton('allowbodyedit', 'allowbodyedit', 'local_documents');
        $mform->addElement('textarea', 'changenote', get_string('changenote', 'local_documents'), ['rows' => 2, 'cols' => 90]);
        $mform->setType('changenote', PARAM_TEXT);
        $mform->addHelpButton('changenote', 'changenote', 'local_documents');

        $this->add_action_buttons(true, get_string('savetemplate', 'local_documents'));
    }

    /**
     * A changed template needs a change note; the fields must parse.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $current = $this->_customdata['current'] ?? null;
        $changed = !$current || self::content_changed($current, $data);
        if ($changed && trim((string)($data['changenote'] ?? '')) === '') {
            $errors['changenote'] = get_string('changenoterequired', 'local_documents');
        }
        $signers = array_filter([(int)($data['signer1'] ?? 0), (int)($data['signer2'] ?? 0), (int)($data['signer3'] ?? 0)]);
        if (count($signers) !== count(array_unique($signers))) {
            $errors['signer2'] = get_string('signerrepeated', 'local_documents');
        }
        if ($problems = templates::validate_fields((string)($data['fields'] ?? ''))) {
            $errors['fields'] = implode('<br>', $problems);
        }
        return $errors;
    }

    /**
     * Whether the versioned part of the template differs from the current version.
     *
     * @param \stdClass $current
     * @param array $data
     * @return bool
     */
    public static function content_changed(\stdClass $current, array $data): bool {
        $norm = static fn($text) => trim(str_replace("\r\n", "\n", (string)$text));
        return $norm($current->title) !== $norm($data['title'] ?? '')
            || $norm($current->body) !== $norm($data['body'] ?? '')
            || $norm($current->fields) !== $norm($data['fields'] ?? '')
            || $norm($current->consent ?? '') !== $norm($data['consent'] ?? '')
            || (int)$current->allowbodyedit !== (int)!empty($data['allowbodyedit']);
    }
}
