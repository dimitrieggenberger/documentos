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
 * Upgrade steps for local_documents.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_documents_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2026102800) {
        // The brand manual's letter (Figma "Letter"): sentence-case titles, the recipient as a block
        // of its own, an attachment line. Only templates nobody edited get the new version.
        \local_documents\local\catalog::refresh_untouched(
            'Diseño de carta del manual de marca: título en tipo oración, destinatario como bloque propio, adjunto.');
        // The old letterhead's settings: replaced by the seal, the stamp and the footer logo.
        foreach (['headerlines', 'footertext', 'brandcolor', 'logo2'] as $name) {
            unset_config($name, 'local_documents');
        }
        $fs = get_file_storage();
        $fs->delete_area_files(context_system::instance()->id, 'local_documents', 'logo2');
        upgrade_plugin_savepoint(true, 2026102800, 'local', 'documents');
    }
    if ($oldversion < 2026102900) {
        // Issuing units become records an administrator manages: each linked to the Moodle user who
        // signs for it, with the document types it may issue; types say whose signatures they require;
        // every signature asked for is an approval.
        $table = new xmldb_table('local_documents_unit');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('phone', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('signername', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('signertitle', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('code', XMLDB_INDEX_UNIQUE, ['code']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_documents_unittype');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('unitid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('unitid', XMLDB_KEY_FOREIGN, ['unitid'], 'local_documents_unit', ['id']);
        $table->add_key('typeid', XMLDB_KEY_FOREIGN, ['typeid'], 'local_documents_type', ['id']);
        $table->add_index('unittype', XMLDB_INDEX_UNIQUE, ['unitid', 'typeid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_documents_signer');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('typeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unitid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('typeid', XMLDB_KEY_FOREIGN, ['typeid'], 'local_documents_type', ['id']);
        $table->add_key('unitid', XMLDB_KEY_FOREIGN, ['unitid'], 'local_documents_unit', ['id']);
        $table->add_index('typeunit', XMLDB_INDEX_UNIQUE, ['typeid', 'unitid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_documents_approval');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('docid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unitid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('timerequested', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('docid', XMLDB_KEY_FOREIGN, ['docid'], 'local_documents_doc', ['id']);
        $table->add_index('userstatus', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        \local_documents\local\units::install_defaults((string)get_config('local_documents', 'units'));
        unset_config('units', 'local_documents');
        unset_config('signatories', 'local_documents');
        // The official name in the texts; constancias and certificaciones keep the template's wording.
        \local_documents\local\catalog::refresh_untouched('Nombre oficial del centro en el texto; firmas por unidad '
            . 'emisora; los adjuntos se suben como archivos.');
        upgrade_plugin_savepoint(true, 2026102900, 'local', 'documents');
    }
    if ($oldversion < 2026103000) {
        // Substitutes: someone signs in substitution of a unit's head for a while.
        $table = new xmldb_table('local_documents_unit');
        foreach (['substituteid', 'substitutefrom', 'substituteuntil'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        $table = new xmldb_table('local_documents_approval');
        $field = new xmldb_field('onbehalfof', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // Circulars and memorandums: any unit issues them, signed by the issuing unit's head;
        // Gerencia General and Gerencia Académica join the units.
        \local_documents\local\units::open_letters_to_all();
        // The memorandum's "De:" is the issuing unit, whichever it is.
        \local_documents\local\catalog::refresh_untouched('Circular y memorándum firmados por la unidad emisora; '
            . '«De:» es la unidad emisora.');
        upgrade_plugin_savepoint(true, 2026103000, 'local', 'documents');
    }
    return true;
}
