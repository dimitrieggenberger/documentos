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

/**
 * The life of a document: draft, issued, superseded by a correction, recalled.
 *
 * A draft can be changed and deleted. Issuing gives it its number, freezes
 * its text and values, writes the PDF once and records the PDF's SHA-256;
 * from then on nothing about it changes except its status. A correction is a
 * new version under the same number (the old one reads "superseded" on the
 * verification page), a recall marks it void and says so publicly.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class documents {

    /** @var string */
    const STATUS_DRAFT = 'draft';
    /** @var string */
    const STATUS_ISSUED = 'issued';
    /** @var string */
    const STATUS_SUPERSEDED = 'superseded';
    /** @var string */
    const STATUS_RECALLED = 'recalled';

    /** @var string The file area of issued PDFs. */
    const FILEAREA = 'issued';

    /**
     * One document, with its type, template version and series.
     *
     * @param int $id
     * @return array{doc: \stdClass, type: \stdClass, tplver: \stdClass, series: \stdClass, data: array}
     */
    public static function get(int $id): array {
        global $DB;
        $doc = $DB->get_record('local_documents_doc', ['id' => $id], '*', MUST_EXIST);
        return [
            'doc' => $doc,
            'type' => $DB->get_record('local_documents_type', ['id' => $doc->typeid], '*', MUST_EXIST),
            'tplver' => $DB->get_record('local_documents_tplver', ['id' => $doc->tplverid], '*', MUST_EXIST),
            'series' => $DB->get_record('local_documents_series', ['id' => $doc->seriesid], '*', MUST_EXIST),
            'data' => self::data($doc),
        ];
    }

    /**
     * The decoded data of a document.
     *
     * @param \stdClass $doc
     * @return array
     */
    public static function data(\stdClass $doc): array {
        $data = json_decode((string)$doc->data, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Start a draft of one document type.
     *
     * @param int $typeid
     * @param int $userid the student or staff member, 0 for a typed-in person or a letter
     * @param int $creatorid
     * @return int the draft's id
     */
    public static function create_draft(int $typeid, int $userid, int $creatorid): int {
        global $DB;
        $type = $DB->get_record('local_documents_type', ['id' => $typeid, 'enabled' => 1], '*', MUST_EXIST);
        $tplver = $DB->get_record('local_documents_tplver', ['id' => $type->currentversion], '*', MUST_EXIST);
        $series = $DB->get_record('local_documents_series', ['id' => $type->seriesid], '*', MUST_EXIST);
        if (in_array($type->subject, ['student', 'staff'], true) && $userid <= 0) {
            throw new \moodle_exception('needsperson', 'local_documents');
        }
        if (!in_array($type->subject, ['student', 'staff'], true)) {
            $userid = 0;
        }
        $prefill = sources::prefill($type, $userid);
        $fields = [];
        foreach (templates::parse_fields((string)$tplver->fields) as $key => $field) {
            $fields[$key] = $field['default'];
        }
        $now = time();
        $data = [
            'fields' => $fields,
            'gender' => $prefill['gender'],
            'year' => $prefill['year'],
            'body' => (string)$tplver->body,
            'signers' => [0, 1],
            'issuedate' => $now,
        ];
        $id = (int)$DB->insert_record('local_documents_doc', (object)[
            'typeid' => $type->id, 'tplverid' => $tplver->id, 'seriesid' => $series->id, 'status' => self::STATUS_DRAFT,
            'userid' => $userid, 'holdername' => $prefill['holdername'], 'holderidnumber' => $prefill['holderidnumber'],
            'title' => $tplver->title, 'number' => '', 'seriescode' => $series->code, 'correlative' => 0, 'year' => 0,
            'unitcode' => $type->unitcode, 'rootid' => 0, 'docversion' => 1, 'verifycode' => numbering::new_code(),
            'data' => json_encode($data), 'createdby' => $creatorid, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->set_field('local_documents_doc', 'rootid', $id, ['id' => $id]);
        self::log($id, 'created', $creatorid);
        return $id;
    }

    /**
     * Save what the issuer changed on a draft.
     *
     * @param int $id
     * @param array $changes holdername, holderidnumber, unitcode, gender, year, fields, body, signers, issuedate
     * @param int $userid
     * @return void
     */
    public static function update_draft(int $id, array $changes, int $userid): void {
        global $DB;
        $bundle = self::get($id);
        $doc = $bundle['doc'];
        self::require_draft($doc);
        $data = $bundle['data'];
        foreach (['gender', 'year', 'signers', 'issuedate'] as $key) {
            if (array_key_exists($key, $changes)) {
                $data[$key] = $changes[$key];
            }
        }
        if (isset($changes['fields']) && is_array($changes['fields'])) {
            foreach (templates::parse_fields((string)$bundle['tplver']->fields) as $key => $field) {
                if (array_key_exists($key, $changes['fields'])) {
                    $data['fields'][$key] = (string)$changes['fields'][$key];
                }
            }
        }
        if (array_key_exists('body', $changes) && !empty($bundle['tplver']->allowbodyedit)) {
            $data['body'] = str_replace("\r\n", "\n", (string)$changes['body']);
        }
        $record = (object)['id' => $id, 'data' => json_encode($data), 'timemodified' => time()];
        // Who the document is about is only typed in when it is not a platform user;
        // a correction keeps the person of the version it corrects.
        if ((int)$doc->userid === 0 && (int)$doc->docversion === 1) {
            foreach (['holdername', 'holderidnumber'] as $key) {
                if (array_key_exists($key, $changes)) {
                    $record->$key = trim((string)$changes[$key]);
                }
            }
        } else if ((int)$doc->docversion === 1 && array_key_exists('holderidnumber', $changes)) {
            $record->holderidnumber = trim((string)$changes['holderidnumber']);
        }
        if (isset($changes['unitcode']) && (int)$doc->docversion === 1
                && array_key_exists($changes['unitcode'], config::units())) {
            $record->unitcode = $changes['unitcode'];
        }
        $DB->update_record('local_documents_doc', $record);
        self::log($id, 'edited', $userid);
    }

    /**
     * Read the template again into a draft, dropping the issuer's text changes.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function reset_body(int $id, int $userid): void {
        global $DB;
        $bundle = self::get($id);
        self::require_draft($bundle['doc']);
        $data = $bundle['data'];
        $data['body'] = (string)$bundle['tplver']->body;
        $DB->set_field('local_documents_doc', 'data', json_encode($data), ['id' => $id]);
        self::log($id, 'edited', $userid, 'reset');
    }

    /**
     * Everything the sheet prints, worked out now (a draft) or as frozen at issue.
     *
     * @param array $bundle from get()
     * @return array{bodyhtml: string, values: array, signers: array, school: array, warnings: string[], missing: string[]}
     */
    public static function compose(array $bundle): array {
        $doc = $bundle['doc'];
        $data = $bundle['data'];
        if ($doc->status !== self::STATUS_DRAFT && isset($data['frozen'])) {
            return $data['frozen'] + ['warnings' => [], 'missing' => []];
        }
        $collected = sources::collect($doc, $bundle['type'], $bundle['tplver'], $data);
        $body = (string)($data['body'] ?? $bundle['tplver']->body);
        $missing = templates::missing($body, $collected['values'], sources::TABLES);
        foreach (templates::parse_fields((string)$bundle['tplver']->fields) as $key => $field) {
            if ($field['required'] && trim((string)($data['fields'][$key] ?? '')) === '') {
                $missing[] = 'f.' . $key;
            }
        }
        return [
            'bodyhtml' => templates::render($body, $collected['values'], $collected['tables']),
            'values' => $collected['values'],
            'signers' => sources::signers($data, (int)$bundle['tplver']->signatories),
            'school' => config::school(),
            'warnings' => $collected['warnings'],
            'missing' => array_values(array_unique($missing)),
            'blocked' => $bundle['type']->source === 'grades' && $collected['record'] !== null
                && $collected['record']['hold'] !== null,
        ];
    }

    /**
     * Issue a draft: number, frozen text, the PDF and its hash.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function issue(int $id, int $userid): void {
        global $DB;
        $bundle = self::get($id);
        $doc = $bundle['doc'];
        self::require_draft($doc);
        if ($missing = config::missing()) {
            throw new \moodle_exception('settingsmissing', 'local_documents', '', implode(', ', $missing));
        }
        $composed = self::compose($bundle);
        if (!empty($composed['missing'])) {
            throw new \moodle_exception('placeholdersmissing', 'local_documents', '', implode(', ', $composed['missing']));
        }
        if (!empty($composed['blocked'])) {
            throw new \moodle_exception('blockedbyhold', 'local_documents');
        }
        if ((int)$doc->userid === 0 && in_array($bundle['type']->subject, ['person'], true)
                && trim((string)$doc->holdername) === '') {
            throw new \moodle_exception('needsholdername', 'local_documents');
        }

        $data = $bundle['data'];
        $issuedate = (int)($data['issuedate'] ?? time());
        $previous = null;
        if ((int)$doc->docversion > 1) {
            $previous = $DB->get_record('local_documents_doc', ['rootid' => $doc->rootid,
                'status' => self::STATUS_ISSUED], '*', MUST_EXIST);
            $year = (int)$previous->year;
            $unit = (string)$previous->unitcode;
        } else {
            $year = spanish::parts($issuedate)[2];
            $unit = (string)$doc->unitcode;
        }

        $lock = numbering::lock((int)$doc->seriesid, $year, $unit);
        try {
            $transaction = $DB->start_delegated_transaction();
            $school = config::school();
            if ($previous) {
                $correlative = (int)$previous->correlative;
                $number = (string)$previous->number;
            } else {
                $correlative = numbering::next((int)$doc->seriesid, $year, $unit);
                $number = numbering::format((string)$doc->seriescode, $correlative, $school['sace'], $year, $unit,
                    $school['jornada']);
            }
            $validity = (int)$bundle['type']->validitydays;
            $validuntil = $validity > 0 ? usergetmidnight($issuedate + $validity * DAYSECS, \core_date::get_server_timezone())
                + DAYSECS - 1 : 0;

            $data['frozen'] = [
                'bodyhtml' => $composed['bodyhtml'],
                'values' => $composed['values'],
                'signers' => $composed['signers'],
                'school' => $composed['school'],
                'units' => config::units(),
                'previousissued' => $previous ? (int)$previous->timeissued : 0,
                'previousdate' => $previous ? (int)$previous->issuedate : 0,
            ];
            $data['signerssnapshot'] = $composed['signers'];
            $doc->status = self::STATUS_ISSUED;
            $doc->number = $number;
            $doc->correlative = $correlative;
            $doc->year = $year;
            $doc->unitcode = $unit;
            $doc->sacecode = $school['sace'];
            $doc->jornada = $school['jornada'];
            $doc->bodytext = $composed['bodyhtml'];
            $doc->issuedate = $issuedate;
            $doc->validuntil = $validuntil;
            $doc->issuedby = $userid;
            $doc->timeissued = time();
            $doc->timemodified = time();
            $doc->data = json_encode($data);

            $bundle['doc'] = $doc;
            $bundle['data'] = $data;
            $content = pdf::render($bundle, true);
            $doc->contenthash = hash('sha256', $content);
            $DB->update_record('local_documents_doc', $doc);

            $fs = get_file_storage();
            $context = \context_system::instance();
            $fs->delete_area_files($context->id, 'local_documents', self::FILEAREA, $doc->id);
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'local_documents', 'filearea' => self::FILEAREA,
                'itemid' => $doc->id, 'filepath' => '/', 'filename' => self::filename($doc),
            ], $content);

            if ($previous) {
                $DB->update_record('local_documents_doc', (object)['id' => $previous->id,
                    'status' => self::STATUS_SUPERSEDED, 'supersededby' => $doc->id, 'timemodified' => time()]);
                self::log((int)$previous->id, 'superseded', $userid, 'v' . $doc->docversion);
            }
            self::log((int)$doc->id, 'issued', $userid, $number);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Start a correction of an issued document: a draft of the next version, same number.
     *
     * @param int $id the issued version
     * @param int $userid
     * @return int the new draft's id
     */
    public static function revise(int $id, int $userid): int {
        global $DB;
        $bundle = self::get($id);
        $doc = $bundle['doc'];
        if ($doc->status !== self::STATUS_ISSUED) {
            throw new \moodle_exception('cannotrevise', 'local_documents');
        }
        $open = $DB->get_record('local_documents_doc', ['rootid' => $doc->rootid, 'status' => self::STATUS_DRAFT]);
        if ($open) {
            return (int)$open->id;
        }
        $data = $bundle['data'];
        unset($data['frozen'], $data['signerssnapshot']);
        $data['issuedate'] = time();
        $max = (int)$DB->get_field_sql('SELECT MAX(docversion) FROM {local_documents_doc} WHERE rootid = ?', [$doc->rootid]);
        $now = time();
        $newid = (int)$DB->insert_record('local_documents_doc', (object)[
            'typeid' => $doc->typeid, 'tplverid' => $doc->tplverid, 'seriesid' => $doc->seriesid,
            'status' => self::STATUS_DRAFT, 'userid' => $doc->userid, 'holdername' => $doc->holdername,
            'holderidnumber' => $doc->holderidnumber, 'title' => $doc->title, 'number' => '',
            'seriescode' => $doc->seriescode, 'correlative' => 0, 'year' => 0, 'unitcode' => $doc->unitcode,
            'rootid' => $doc->rootid, 'docversion' => $max + 1, 'verifycode' => numbering::new_code(),
            'data' => json_encode($data), 'createdby' => $userid, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        self::log($newid, 'created', $userid, 'v' . ($max + 1));
        self::log((int)$doc->id, 'revisionstarted', $userid, 'v' . ($max + 1));
        return $newid;
    }

    /**
     * Recall an issued document; the verification page then shows it as void.
     *
     * @param int $id
     * @param string $reason internal
     * @param string $note shown on the verification page, may be empty
     * @param int $userid
     * @return void
     */
    public static function recall(int $id, string $reason, string $note, int $userid): void {
        global $DB;
        $doc = $DB->get_record('local_documents_doc', ['id' => $id], '*', MUST_EXIST);
        if ($doc->status !== self::STATUS_ISSUED) {
            throw new \moodle_exception('cannotrecall', 'local_documents');
        }
        $DB->update_record('local_documents_doc', (object)['id' => $id, 'status' => self::STATUS_RECALLED,
            'recalledby' => $userid, 'timerecalled' => time(), 'recallreason' => trim($reason),
            'recallnote' => trim($note), 'timemodified' => time()]);
        // An open correction of a recalled document has nothing left to correct.
        $DB->delete_records('local_documents_doc', ['rootid' => $doc->rootid, 'status' => self::STATUS_DRAFT]);
        self::log($id, 'recalled', $userid, trim($reason));
    }

    /**
     * Delete a draft (an issued document is never deleted).
     *
     * @param int $id
     * @return void
     */
    public static function delete_draft(int $id): void {
        global $DB;
        $doc = $DB->get_record('local_documents_doc', ['id' => $id], '*', MUST_EXIST);
        self::require_draft($doc);
        $DB->delete_records('local_documents_log', ['docid' => $id]);
        $DB->delete_records('local_documents_doc', ['id' => $id]);
    }

    /**
     * The stored PDF of an issued version.
     *
     * @param \stdClass $doc
     * @return \stored_file|null
     */
    public static function file(\stdClass $doc): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(\context_system::instance()->id, 'local_documents', self::FILEAREA, $doc->id,
            'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * "CONSES-022-050100012M02-2026-DCE-JM-v1.pdf".
     *
     * @param \stdClass $doc
     * @return string
     */
    public static function filename(\stdClass $doc): string {
        $base = $doc->number !== '' ? $doc->number : 'borrador-' . $doc->id;
        return clean_filename($base . '-v' . (int)$doc->docversion . '.pdf');
    }

    /**
     * Every version of a document, first to last.
     *
     * @param int $rootid
     * @return \stdClass[]
     */
    public static function versions(int $rootid): array {
        global $DB;
        return array_values($DB->get_records('local_documents_doc', ['rootid' => $rootid], 'docversion ASC'));
    }

    /**
     * Write one line of a document's history.
     *
     * @param int $docid
     * @param string $action
     * @param int $userid
     * @param string $info
     * @return void
     */
    public static function log(int $docid, string $action, int $userid, string $info = ''): void {
        global $DB;
        $DB->insert_record('local_documents_log', (object)['docid' => $docid, 'action' => $action, 'userid' => $userid,
            'info' => \core_text::substr($info, 0, 1000), 'ip' => \core_text::substr((string)getremoteaddr(), 0, 45),
            'timecreated' => time()]);
    }

    /**
     * Refuse to change anything but a draft.
     *
     * @param \stdClass $doc
     * @return void
     */
    private static function require_draft(\stdClass $doc): void {
        if ($doc->status !== self::STATUS_DRAFT) {
            throw new \moodle_exception('notadraft', 'local_documents');
        }
    }
}
