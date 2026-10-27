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
 * Recall an issued document: why (kept internal) and what the verification page says.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recall_form extends \moodleform {

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', 'recall');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('static', 'recallwarning', '', get_string('recallwarning', 'local_documents'));
        $mform->addElement('textarea', 'reason', get_string('recallreason', 'local_documents'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $mform->addElement('textarea', 'note', get_string('recallnote', 'local_documents'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addHelpButton('note', 'recallnote', 'local_documents');
        $this->add_action_buttons(true, get_string('recall', 'local_documents'));
    }
}
