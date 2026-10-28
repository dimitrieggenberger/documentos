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

namespace local_documents\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_documents.
 *
 * Issued documents are official records the school must keep for the periods
 * of the Tabla de Retención Documental, so a deletion request does not erase
 * them, nor the signatures given on them (approvals); it removes only what is
 * not part of the record: drafts and the addresses in the history.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_documents_doc', [
            'userid' => 'privacy:metadata:doc:userid',
            'holdername' => 'privacy:metadata:doc:holdername',
            'holderidnumber' => 'privacy:metadata:doc:holderidnumber',
            'createdby' => 'privacy:metadata:doc:createdby',
            'issuedby' => 'privacy:metadata:doc:issuedby',
        ], 'privacy:metadata:doc');
        $collection->add_database_table('local_documents_log', [
            'userid' => 'privacy:metadata:log:userid',
            'ip' => 'privacy:metadata:log:ip',
        ], 'privacy:metadata:log');
        $collection->add_database_table('local_documents_approval', [
            'userid' => 'privacy:metadata:approval:userid',
            'timedecided' => 'privacy:metadata:approval:timedecided',
            'ipaddress' => 'privacy:metadata:approval:ipaddress',
        ], 'privacy:metadata:approval');
        $collection->add_database_table('local_documents_unit', [
            'userid' => 'privacy:metadata:unit:userid',
        ], 'privacy:metadata:unit');
        $collection->link_subsystem('core_message', 'privacy:metadata:messages');
        $collection->add_database_table('local_documents_tplver', [
            'createdby' => 'privacy:metadata:tplver:createdby',
        ], 'privacy:metadata:tplver');
        return $collection;
    }

    /**
     * Everything lives in the system context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists_select('local_documents_doc', 'userid = ? OR createdby = ? OR issuedby = ?',
                [$userid, $userid, $userid]) || $DB->record_exists('local_documents_log', ['userid' => $userid])
                || $DB->record_exists('local_documents_approval', ['userid' => $userid])
                || $DB->record_exists('local_documents_unit', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * The users with data in the system context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach (['userid', 'createdby', 'issuedby', 'recalledby'] as $field) {
            $userlist->add_from_sql($field, "SELECT $field FROM {local_documents_doc} WHERE $field > 0", []);
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_documents_log} WHERE userid > 0', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_documents_approval} WHERE userid > 0', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_documents_unit} WHERE userid > 0', []);
    }

    /**
     * Export the documents about the user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $context = \context_system::instance();
        if (!in_array($context->id, $contextlist->get_contextids())) {
            return;
        }
        $docs = [];
        foreach ($DB->get_records('local_documents_doc', ['userid' => $userid], 'id') as $doc) {
            $docs[] = [
                'title' => $doc->title,
                'number' => $doc->number,
                'version' => (int)$doc->docversion,
                'status' => $doc->status,
                'holdername' => $doc->holdername,
                'holderidnumber' => $doc->holderidnumber,
                'issuedate' => $doc->issuedate ? transform::date($doc->issuedate) : '',
            ];
        }
        if ($docs) {
            writer::with_context($context)->export_data([get_string('pluginname', 'local_documents')],
                (object)['documents' => $docs]);
        }
    }

    /**
     * Remove what is not part of the official record, for everyone.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_documents_doc', ['status' => 'draft']);
        $DB->set_field_select('local_documents_log', 'ip', '', 'ip IS NOT NULL');
    }

    /**
     * Remove what is not part of the official record, for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        $DB->delete_records('local_documents_doc', ['status' => 'draft', 'userid' => $userid]);
        $DB->set_field('local_documents_log', 'ip', '', ['userid' => $userid]);
    }

    /**
     * Remove what is not part of the official record, for some users.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids());
        $DB->delete_records_select('local_documents_doc', "status = 'draft' AND userid $insql", $params);
        $DB->set_field_select('local_documents_log', 'ip', '', "userid $insql", $params);
    }
}
