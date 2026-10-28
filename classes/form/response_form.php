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

use local_documents\local\slips;

/**
 * What a family answered on the consent slip they returned: authorises or not, the date they wrote,
 * a note, and the signed slip as scanned.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_form extends \moodleform {

    /**
     * The scan: one PDF or picture.
     *
     * @return array
     */
    public static function scan_options(): array {
        return ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 10 * 1024 * 1024,
            'accepted_types' => ['.pdf', '.png', '.jpg', '.jpeg']];
    }

    /**
     * Define the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'slip');
        $mform->setType('slip', PARAM_INT);
        $choices = [];
        foreach ([slips::YES, slips::NO, slips::PENDING] as $value) {
            $choices[] = $mform->createElement('radio', 'response', '', get_string('response_' . $value, 'local_documents'),
                $value);
        }
        $mform->addGroup($choices, 'responsegroup', get_string('response', 'local_documents'), '<br>', false);
        $mform->setDefault('response', slips::PENDING);
        $mform->addElement('date_selector', 'responsedate', get_string('responsedate', 'local_documents'));
        $mform->addHelpButton('responsedate', 'responsedate', 'local_documents');
        $mform->hideIf('responsedate', 'response', 'eq', slips::PENDING);
        $mform->addElement('textarea', 'note', get_string('responsenote', 'local_documents'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addElement('filemanager', 'scan', get_string('responsescan', 'local_documents'), null, self::scan_options());
        $mform->addHelpButton('scan', 'responsescan', 'local_documents');
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
