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
    return true;
}
