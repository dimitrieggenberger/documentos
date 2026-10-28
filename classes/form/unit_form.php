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

/**
 * One issuing unit: its code and name, the Moodle user who signs for it, and what it may issue.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unit_form extends \moodleform {

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'code', get_string('unitcode', 'local_documents'), ['size' => 10, 'maxlength' => 20]);
        $mform->setType('code', PARAM_ALPHANUM);
        $mform->addRule('code', null, 'required', null, 'client');
        $mform->addHelpButton('code', 'unitcode', 'local_documents');
        $mform->addElement('text', 'name', get_string('unitname', 'local_documents'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text', 'email', get_string('unitemail', 'local_documents'), ['size' => 40]);
        $mform->setType('email', PARAM_EMAIL);
        $mform->addElement('text', 'phone', get_string('unitphone', 'local_documents'), ['size' => 30]);
        $mform->setType('phone', PARAM_TEXT);

        $mform->addElement('header', 'headhdr', get_string('unithead', 'local_documents'));
        $mform->setExpanded('headhdr');
        $mform->addElement('autocomplete', 'userid', get_string('unituser', 'local_documents'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'valuehtmlcallback' => static function($value) {
                $user = \core_user::get_user((int)$value);
                return $user ? s(fullname($user) . ' · ' . $user->email) : '';
            },
        ]);
        $mform->addRule('userid', null, 'required', null, 'client');
        $mform->addHelpButton('userid', 'unituser', 'local_documents');
        $mform->addElement('text', 'signername', get_string('signername', 'local_documents'), ['size' => 40]);
        $mform->setType('signername', PARAM_TEXT);
        $mform->addHelpButton('signername', 'signername', 'local_documents');
        $mform->addElement('text', 'signertitle', get_string('signertitle', 'local_documents'), ['size' => 40]);
        $mform->setType('signertitle', PARAM_TEXT);

        $mform->addElement('header', 'substitutehdr', get_string('substitution', 'local_documents'));
        $mform->addElement('static', 'substitutionintro', '', get_string('substitutionintro', 'local_documents'));
        $mform->addElement('autocomplete', 'substituteid', get_string('substitute', 'local_documents'), [], [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => false,
            'noselectionstring' => get_string('nosubstitute', 'local_documents'),
            'valuehtmlcallback' => static function($value) {
                $user = (int)$value > 0 ? \core_user::get_user((int)$value) : null;
                return $user ? s(fullname($user) . ' · ' . $user->email) : '';
            },
        ]);
        $mform->addHelpButton('substituteid', 'substitute', 'local_documents');
        $mform->addElement('date_selector', 'substitutefrom', get_string('substitutefrom', 'local_documents'),
            ['optional' => true]);
        $mform->addElement('date_selector', 'substituteuntil', get_string('substituteuntil', 'local_documents'),
            ['optional' => true]);
        $mform->addHelpButton('substituteuntil', 'substituteuntil', 'local_documents');
        if (!empty($this->_customdata['substitute'])) {
            $mform->setExpanded('substitutehdr');
        }

        $mform->addElement('header', 'typeshdr', get_string('unittypes', 'local_documents'));
        $mform->setExpanded('typeshdr');
        $types = [];
        foreach ($DB->get_records_sql('SELECT t.id, t.name, s.code FROM {local_documents_type} t
                                         JOIN {local_documents_series} s ON s.id = t.seriesid
                                     ORDER BY t.sortorder, t.name') as $type) {
            $types[$type->id] = $type->code . ' · ' . $type->name;
        }
        $mform->addElement('autocomplete', 'typeids', get_string('unittypes', 'local_documents'), $types,
            ['multiple' => true, 'noselectionstring' => get_string('unitnotypes', 'local_documents')]);
        $mform->addHelpButton('typeids', 'unittypes', 'local_documents');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_documents'));
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * A code of capitals and digits, used by no other unit; a head that is a real user; a substitute who is
     * someone else, for a period that ends after it starts.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        $code = \core_text::strtoupper(trim((string)$data['code']));
        if (!preg_match('/^[A-Z0-9]{1,20}$/', $code)) {
            $errors['code'] = get_string('unitcodeinvalid', 'local_documents');
        } else if ($DB->record_exists_select('local_documents_unit', 'code = ? AND id <> ?', [$code, (int)$data['id']])) {
            $errors['code'] = get_string('unitcodetaken', 'local_documents');
        }
        if (empty($data['userid']) || !$DB->record_exists('user', ['id' => (int)$data['userid'], 'deleted' => 0])) {
            $errors['userid'] = get_string('unituserrequired', 'local_documents');
        }
        $substitute = (int)($data['substituteid'] ?? 0);
        if ($substitute > 0) {
            if ($substitute === (int)($data['userid'] ?? 0)) {
                $errors['substituteid'] = get_string('substituteishead', 'local_documents');
            } else if (!$DB->record_exists('user', ['id' => $substitute, 'deleted' => 0])) {
                $errors['substituteid'] = get_string('unituserrequired', 'local_documents');
            }
        }
        $from = (int)($data['substitutefrom'] ?? 0);
        $until = (int)($data['substituteuntil'] ?? 0);
        if ($from > 0 && $until > 0 && $until < $from) {
            $errors['substituteuntil'] = get_string('substituteuntilbefore', 'local_documents');
        }
        return $errors;
    }
}
