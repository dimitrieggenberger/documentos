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
 * The documents that accompany another one, as a file: a constancia de traslado goes with the
 * constancia de conducta, the solvencia and the certificaciones de estudios (docs/documents-plan.md 14).
 *
 * A companion is one of:
 * - a document of the same person issued on the platform, that goes with the file (in the original
 *   when it is signed by hand, as its verifiable PDF when it is signed electronically) or that was
 *   handed to the family before;
 * - a draft prepared from the file, which must be issued before the file is;
 * - a document issued on paper before the platform, named by its title and number.
 *
 * They are kept in the document's data (`companions`), so they are part of what is signed, and
 * frozen with it at issue. The sheet prints them as an index under the signatures.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class companions {

    /** @var string Goes with the file. */
    const WITH = 'with';
    /** @var string Handed to the family before. */
    const FAMILY = 'family';

    /**
     * The document types a type suggests as its companions, in the order set.
     *
     * @param \stdClass $type
     * @return \stdClass[] type records
     */
    public static function suggested(\stdClass $type): array {
        global $DB;
        $names = self::shortnames((string)($type->companions ?? ''));
        if (!$names) {
            return [];
        }
        $records = $DB->get_records_list('local_documents_type', 'shortname', $names);
        $byname = [];
        foreach ($records as $record) {
            if ($record->enabled) {
                $byname[$record->shortname] = $record;
            }
        }
        return array_values(array_filter(array_map(static fn($name) => $byname[$name] ?? null, $names)));
    }

    /**
     * "constancia_conducta, constancia_solvencia" as a list.
     *
     * @param string $value
     * @return string[]
     */
    public static function shortnames(string $value): array {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), 'strlen')));
    }

    /**
     * Whether a document works as a file: its type suggests companions, or it already has some.
     *
     * @param array $bundle from documents::get()
     * @return bool
     */
    public static function enabled(array $bundle): bool {
        return self::suggested($bundle['type']) !== [] || !empty($bundle['data']['companions']);
    }

    /**
     * The companions as stored on a document.
     *
     * @param array $data the document's data
     * @return array[]
     */
    public static function stored(array $data): array {
        return array_values(array_filter((array)($data['companions'] ?? []), 'is_array'));
    }

    /**
     * Each companion worked out for the screen and the sheet.
     *
     * @param array $data the document's data
     * @return array[] kind, docid, title, number, code, status, signaturemode, delivery, deliveredon, ready, line
     */
    public static function resolve(array $data): array {
        global $DB;
        $result = [];
        foreach (self::stored($data) as $entry) {
            $delivery = ($entry['delivery'] ?? self::WITH) === self::FAMILY ? self::FAMILY : self::WITH;
            $deliveredon = (int)($entry['deliveredon'] ?? 0);
            if (($entry['kind'] ?? '') === 'external') {
                $item = ['kind' => 'external', 'docid' => 0, 'title' => trim((string)($entry['title'] ?? '')),
                    'number' => trim((string)($entry['number'] ?? '')), 'code' => '', 'status' => 'paper',
                    'signaturemode' => documents::SIGN_HANDWRITTEN, 'delivery' => $delivery, 'deliveredon' => $deliveredon,
                    'ready' => true];
            } else {
                $doc = $DB->get_record('local_documents_doc', ['id' => (int)($entry['docid'] ?? 0)]);
                $type = $doc ? $DB->get_record('local_documents_type', ['id' => $doc->typeid]) : null;
                $issued = $doc && $doc->status !== documents::STATUS_DRAFT;
                $item = ['kind' => 'doc', 'docid' => $doc ? (int)$doc->id : 0,
                    'title' => $doc ? self::title($doc, $type) : '',
                    'number' => $doc ? numbering::display((string)$doc->number) : '',
                    'code' => $issued ? numbering::format_code((string)$doc->verifycode) : '',
                    'status' => $doc ? (string)$doc->status : 'missing',
                    'signaturemode' => $type ? documents::signature_mode($type) : documents::SIGN_ELECTRONIC,
                    'delivery' => $delivery, 'deliveredon' => $deliveredon,
                    'ready' => $doc && in_array($doc->status, [documents::STATUS_ISSUED, documents::STATUS_SUPERSEDED], true)];
            }
            $item['line'] = self::line($item);
            $result[] = $item;
        }
        return $result;
    }

    /**
     * What the sheet prints of one companion, in Spanish.
     *
     * @param array $item from resolve()
     * @return string
     */
    public static function line(array $item): string {
        $sm = get_string_manager();
        if ($item['kind'] === 'external') {
            $head = $item['title'] . ($item['number'] !== '' ? ' — N.º ' . $item['number'] : '') . ' · '
                . $sm->get_string('pdfcompanionpaper', 'local_documents', null, 'es');
        } else if (!$item['ready']) {
            return $item['title'] . ' · ' . $sm->get_string('pdfcompanionpending', 'local_documents', null, 'es');
        } else {
            $head = $item['title'] . ' — ' . $item['number'];
        }
        if ($item['delivery'] === self::FAMILY) {
            $how = $sm->get_string('pdfcompanionfamily', 'local_documents', $item['deliveredon'] > 0
                ? spanish::date_long($item['deliveredon']) : '—', 'es');
        } else if ($item['signaturemode'] === documents::SIGN_HANDWRITTEN) {
            $how = $sm->get_string('pdfcompanionoriginal', 'local_documents', null, 'es');
        } else {
            $how = $sm->get_string('pdfcompanionpdf', 'local_documents', $item['code'], 'es');
        }
        return $head . ' · ' . $how;
    }

    /**
     * A document's title as printed, with the school year for a certificación of one year.
     *
     * @param \stdClass $doc
     * @param \stdClass|null $type
     * @return string
     */
    private static function title(\stdClass $doc, ?\stdClass $type): string {
        $data = documents::data($doc);
        $values = $data['frozen']['values'] ?? [];
        $title = templates::plain((string)$doc->title, $values);
        $year = trim((string)($values['student.yearlabel'] ?? ''));
        if ($year === '' && $type && $type->source === 'grades' && !empty($data['year'])) {
            $year = (string)$data['year'];
        }
        return $type && $type->source === 'grades' && $year !== '' && !str_contains($title, $year)
            ? $title . ' ' . $year : $title;
    }

    /**
     * Why a file cannot go out yet: a companion still a draft, waiting for signatures, gone or recalled.
     *
     * @param array $data the document's data
     * @return string[]
     */
    public static function problems(array $data): array {
        $problems = [];
        foreach (self::resolve($data) as $item) {
            if ($item['kind'] === 'external') {
                continue;
            }
            if ($item['status'] === 'missing') {
                $problems[] = get_string('companionmissing', 'local_documents');
            } else if ($item['status'] === documents::STATUS_RECALLED) {
                $problems[] = get_string('companionrecalled', 'local_documents', s($item['title']));
            } else if (!$item['ready']) {
                $problems[] = get_string('companionnotissued', 'local_documents', s($item['title']));
            }
        }
        return $problems;
    }

    /**
     * The issued documents of the same person that are not in the file yet.
     *
     * @param \stdClass $doc
     * @return \stdClass[] id => document with typename
     */
    public static function candidates(\stdClass $doc): array {
        global $DB;
        if ((int)$doc->userid <= 0) {
            return [];
        }
        $linked = array_map(static fn($e) => (int)($e['docid'] ?? 0), self::stored(documents::data($doc)));
        $records = $DB->get_records_sql("SELECT d.*, t.name AS typename
                                           FROM {local_documents_doc} d
                                           JOIN {local_documents_type} t ON t.id = d.typeid
                                          WHERE d.userid = ? AND d.id <> ? AND d.status = ?
                                       ORDER BY d.issuedate DESC, d.id DESC",
            [$doc->userid, $doc->id, documents::STATUS_ISSUED]);
        return array_filter($records, static fn($record) => !in_array((int)$record->id, $linked, true));
    }

    /**
     * Put an issued document of the same person in the file.
     *
     * @param int $id the file (a draft)
     * @param int $companionid
     * @param string $delivery WITH or FAMILY
     * @param int $deliveredon when it was handed to the family
     * @param int $userid who does it
     * @return void
     */
    public static function add(int $id, int $companionid, string $delivery, int $deliveredon, int $userid): void {
        $doc = self::draft($id);
        if (!isset(self::candidates($doc)[$companionid])) {
            throw new \moodle_exception('companionnotallowed', 'local_documents');
        }
        self::store($doc, ['kind' => 'doc', 'docid' => $companionid,
            'delivery' => $delivery === self::FAMILY ? self::FAMILY : self::WITH,
            'deliveredon' => $delivery === self::FAMILY ? $deliveredon : 0], $userid);
    }

    /**
     * Name a document issued on paper before the platform, handed to the family or going with the file.
     *
     * @param int $id the file (a draft)
     * @param string $title
     * @param string $number
     * @param string $delivery
     * @param int $deliveredon
     * @param int $userid
     * @return void
     */
    public static function add_external(int $id, string $title, string $number, string $delivery, int $deliveredon,
            int $userid): void {
        $doc = self::draft($id);
        $title = \core_text::substr(trim($title), 0, 200);
        if ($title === '') {
            throw new \moodle_exception('companiontitlerequired', 'local_documents');
        }
        self::store($doc, ['kind' => 'external', 'title' => $title, 'number' => \core_text::substr(trim($number), 0, 100),
            'delivery' => $delivery === self::FAMILY ? self::FAMILY : self::WITH,
            'deliveredon' => $delivery === self::FAMILY ? $deliveredon : 0], $userid);
    }

    /**
     * Prepare a companion from the file: a new draft of the type for the same person, put in the file.
     *
     * @param int $id the file (a draft)
     * @param int $typeid
     * @param string $year the school year, for a certificación of one year ('' for the current one)
     * @param int $userid
     * @return int the new draft
     */
    public static function prepare(int $id, int $typeid, string $year, int $userid): int {
        $doc = self::draft($id);
        $bundle = documents::get($id);
        if (!in_array($typeid, array_map(static fn($t) => (int)$t->id, self::suggested($bundle['type'])), true)) {
            throw new \moodle_exception('companionnotallowed', 'local_documents');
        }
        $newid = documents::create_draft($typeid, (int)$doc->userid, $userid);
        if ($year !== '') {
            documents::update_draft($newid, ['year' => $year], $userid);
        }
        self::store(self::draft($id), ['kind' => 'doc', 'docid' => $newid, 'delivery' => self::WITH, 'deliveredon' => 0],
            $userid);
        return $newid;
    }

    /**
     * Take one companion out of the file (a prepared draft stays a draft of its own).
     *
     * @param int $id the file (a draft)
     * @param int $index its place in the list
     * @param int $userid
     * @return void
     */
    public static function remove(int $id, int $index, int $userid): void {
        global $DB;
        $doc = self::draft($id);
        $data = documents::data($doc);
        $list = self::stored($data);
        if (!isset($list[$index])) {
            return;
        }
        array_splice($list, $index, 1);
        $data['companions'] = $list;
        $DB->update_record('local_documents_doc', (object)['id' => $id, 'data' => json_encode($data), 'timemodified' => time()]);
        documents::log($id, 'companionremoved', $userid);
    }

    /**
     * The file, which must still be a draft.
     *
     * @param int $id
     * @return \stdClass
     */
    private static function draft(int $id): \stdClass {
        global $DB;
        $doc = $DB->get_record('local_documents_doc', ['id' => $id], '*', MUST_EXIST);
        if ($doc->status !== documents::STATUS_DRAFT) {
            throw new \moodle_exception('notadraft', 'local_documents');
        }
        return $doc;
    }

    /**
     * Add one entry to the file's list.
     *
     * @param \stdClass $doc
     * @param array $entry
     * @param int $userid
     * @return void
     */
    private static function store(\stdClass $doc, array $entry, int $userid): void {
        global $DB;
        $data = documents::data($doc);
        $data['companions'] = array_merge(self::stored($data), [$entry]);
        $DB->update_record('local_documents_doc', (object)['id' => $doc->id, 'data' => json_encode($data),
            'timemodified' => time()]);
        documents::log((int)$doc->id, 'companionadded', $userid);
    }
}
