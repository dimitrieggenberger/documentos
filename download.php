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
 * The PDF of a document: the stored file once issued, a marked preview while a draft.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_documents\local\documents;
use local_documents\local\pdf;

require_login();
$context = context_system::instance();
require_capability('local/documents:view', $context);

$id = required_param('id', PARAM_INT);
$bundle = documents::get($id);
$doc = $bundle['doc'];

if ($doc->status === documents::STATUS_DRAFT) {
    require_capability('local/documents:issue', $context);
    $content = pdf::render($bundle, false);
    documents::log($id, 'previewed', (int)$USER->id);
    send_file($content, documents::filename($doc), 0, 0, true, false, 'application/pdf');
}

$file = documents::file($doc);
if (!$file) {
    throw new moodle_exception('filemissing', 'local_documents');
}
documents::log($id, 'downloaded', (int)$USER->id);
\core\session\manager::write_close();
send_stored_file($file, 0, 0, false, ['filename' => documents::filename($doc)]);
