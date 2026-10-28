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
 * The consent slips (colillas) of an authorisation: one per student, each with its own code, its own
 * PDF (letter and slip, to send to that family) and the family's answer (docs/documents-plan.md 15).
 *
 * A type has a slip when its template has consent text. A type about one student gets that student's
 * slip with the draft; a type about a group gets the students the issuer picks on the draft, by
 * course or group of a course, or one by one.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slips {

    /** @var string No answer recorded yet. */
    const PENDING = 'pending';
    /** @var string The family authorises. */
    const YES = 'yes';
    /** @var string The family does not authorise. */
    const NO = 'no';
    /** @var string File area of each student's PDF. */
    const PDFAREA = 'slippdf';
    /** @var string File area of the returned, signed slip as scanned. */
    const SCANAREA = 'slipscan';

    /**
     * Whether a template version has a consent slip.
     *
     * @param \stdClass $tplver
     * @return bool
     */
    public static function enabled(\stdClass $tplver): bool {
        return trim((string)($tplver->consent ?? '')) !== '';
    }

    /**
     * The slips of a document, in order.
     *
     * @param int $docid
     * @return \stdClass[]
     */
    public static function list(int $docid): array {
        global $DB;
        return array_values($DB->get_records('local_documents_slip', ['docid' => $docid], 'sortorder, holdername, id'));
    }

    /**
     * One student on the document: their slip, created once.
     *
     * @param int $docid
     * @param int $userid
     * @return bool true when added now
     */
    public static function add_student(int $docid, int $userid): bool {
        global $DB;
        if ($userid <= 0 || $DB->record_exists('local_documents_slip', ['docid' => $docid, 'userid' => $userid])) {
            return false;
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted) {
            return false;
        }
        $DB->insert_record('local_documents_slip', (object)(['docid' => $docid, 'userid' => $userid,
            'verifycode' => numbering::new_code(), 'sortorder' => 0, 'response' => self::PENDING]
            + self::holder($userid)));
        return true;
    }

    /**
     * Name, identity number and grade of a student, as the slip prints them.
     *
     * @param int $userid
     * @return array{holdername: string, holderidnumber: string, gradename: string}
     */
    public static function holder(int $userid): array {
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $grade = '';
        if (sources::has_grades()) {
            $record = sources::student_record($userid, '');
            $grade = $record ? spanish::grade_name((string)$record['level']) : '';
        }
        return ['holdername' => fullname($user),
            'holderidnumber' => trim((string)config::profile_value($userid, 'fieldidnumber', 'idnumber')),
            'gradename' => $grade];
    }

    /**
     * The students of a course, or of one group of it: users enrolled with a student role.
     *
     * @param int $courseid
     * @param int $groupid 0 for the whole course
     * @return int[] user ids
     */
    public static function course_students(int $courseid, int $groupid = 0): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $roleids = array_keys(get_archetype_roles('student'));
        $ids = [];
        foreach ($roleids as $roleid) {
            foreach (get_role_users($roleid, $context, false, 'u.id, u.lastname, u.firstname', 'u.lastname, u.firstname')
                    as $user) {
                $ids[(int)$user->id] = (int)$user->id;
            }
        }
        if ($groupid > 0) {
            $members = array_map('intval', array_keys(groups_get_members($groupid, 'u.id')));
            $ids = array_intersect($ids, $members);
        }
        return array_values($ids);
    }

    /**
     * The courses and groups to pick students from: "c:ID" and "g:ID" => label, by course name.
     *
     * @return array<string, string>
     */
    public static function course_options(): array {
        global $DB;
        $options = [];
        foreach ($DB->get_records_select('course', 'id <> ? AND visible = 1', [SITEID], 'fullname', 'id, fullname')
                as $course) {
            $options['c:' . $course->id] = format_string($course->fullname);
            foreach (groups_get_all_groups((int)$course->id, 0, 0, 'g.id, g.name') as $group) {
                $options['g:' . $group->id] = format_string($course->fullname) . ' › ' . format_string($group->name);
            }
        }
        return $options;
    }

    /**
     * Add the students of a course or group ("c:ID" or "g:ID").
     *
     * @param int $docid
     * @param string $pick
     * @return int how many were added
     */
    public static function add_pick(int $docid, string $pick): int {
        global $DB;
        $ids = [];
        if (preg_match('/^c:(\d+)$/', $pick, $m)) {
            $ids = self::course_students((int)$m[1]);
        } else if (preg_match('/^g:(\d+)$/', $pick, $m)) {
            $group = $DB->get_record('groups', ['id' => (int)$m[1]], 'id, courseid');
            $ids = $group ? self::course_students((int)$group->courseid, (int)$group->id) : [];
        }
        $added = 0;
        foreach ($ids as $userid) {
            $added += self::add_student($docid, $userid) ? 1 : 0;
        }
        return $added;
    }

    /**
     * Take a student off a draft.
     *
     * @param int $docid
     * @param int $slipid
     * @return void
     */
    public static function remove(int $docid, int $slipid): void {
        global $DB;
        $DB->delete_records('local_documents_slip', ['id' => $slipid, 'docid' => $docid]);
    }

    /**
     * Before issuing: each slip with the student's name, number and grade as they are today, in order.
     *
     * @param int $docid
     * @return void
     */
    public static function refresh(int $docid): void {
        global $DB;
        $slips = self::list($docid);
        usort($slips, static fn($a, $b) => strcmp(\core_text::strtolower((string)$a->gradename . ' ' . $a->holdername),
            \core_text::strtolower((string)$b->gradename . ' ' . $b->holdername)));
        foreach ($slips as $i => $slip) {
            $DB->update_record('local_documents_slip', (object)(['id' => $slip->id, 'sortorder' => $i]
                + self::holder((int)$slip->userid)));
        }
    }

    /**
     * Keep each student's own PDF of the issued document, and its fingerprint.
     *
     * @param \stdClass $slip
     * @param string $content the PDF
     * @param string $number the document's number, for the file name
     * @return void
     */
    public static function store_pdf(\stdClass $slip, string $content, string $number): void {
        global $DB;
        $fs = get_file_storage();
        $context = \context_system::instance();
        $fs->delete_area_files($context->id, 'local_documents', self::PDFAREA, (int)$slip->id);
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'local_documents',
            'filearea' => self::PDFAREA, 'itemid' => (int)$slip->id, 'filepath' => '/',
            'filename' => clean_filename($number . ' ' . $slip->holdername . '.pdf')], $content);
        $DB->set_field('local_documents_slip', 'pdfhash', hash('sha256', $content), ['id' => $slip->id]);
    }

    /**
     * A student's own PDF, when the document is issued.
     *
     * @param \stdClass $slip
     * @return \stored_file|null
     */
    public static function pdf(\stdClass $slip): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_documents', self::PDFAREA,
            (int)$slip->id, 'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * The returned slip as scanned, if any.
     *
     * @param \stdClass $slip
     * @return \stored_file|null
     */
    public static function scan(\stdClass $slip): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_documents', self::SCANAREA,
            (int)$slip->id, 'id', false);
        return $files ? reset($files) : null;
    }

    /**
     * Record what the family answered on the slip they returned.
     *
     * @param int $slipid
     * @param string $response YES, NO or PENDING
     * @param int $date the date they wrote
     * @param string $note
     * @param int $userid who records it
     * @return void
     */
    public static function respond(int $slipid, string $response, int $date, string $note, int $userid): void {
        global $DB;
        $slip = $DB->get_record('local_documents_slip', ['id' => $slipid], '*', MUST_EXIST);
        $doc = $DB->get_record('local_documents_doc', ['id' => $slip->docid], 'id, status', MUST_EXIST);
        if (!in_array($doc->status, [documents::STATUS_ISSUED, documents::STATUS_SUPERSEDED, documents::STATUS_RECALLED],
                true)) {
            throw new \moodle_exception('notissued', 'local_documents');
        }
        $response = in_array($response, [self::YES, self::NO], true) ? $response : self::PENDING;
        $DB->update_record('local_documents_slip', (object)['id' => $slipid, 'response' => $response,
            'responsedate' => $response === self::PENDING ? 0 : $date, 'note' => trim($note),
            'recordedby' => $userid, 'timerecorded' => time()]);
        documents::log((int)$slip->docid, 'response', $userid, $slip->holdername . ': ' . $response);
    }

    /**
     * How many answered what.
     *
     * @param \stdClass[] $slips
     * @return array{yes: int, no: int, pending: int}
     */
    public static function counts(array $slips): array {
        $counts = [self::YES => 0, self::NO => 0, self::PENDING => 0];
        foreach ($slips as $slip) {
            $counts[$slip->response] = ($counts[$slip->response] ?? 0) + 1;
        }
        return $counts;
    }

    /**
     * A correction keeps the same students, with new slips: the families answer the corrected letter.
     *
     * @param int $fromid
     * @param int $toid
     * @return void
     */
    public static function copy(int $fromid, int $toid): void {
        foreach (self::list($fromid) as $slip) {
            self::add_student($toid, (int)$slip->userid);
        }
    }

    /**
     * Remove every slip of a draft, with its files.
     *
     * @param int $docid
     * @return void
     */
    public static function delete_all(int $docid): void {
        global $DB;
        $fs = get_file_storage();
        $context = \context_system::instance();
        foreach (self::list($docid) as $slip) {
            $fs->delete_area_files($context->id, 'local_documents', self::PDFAREA, (int)$slip->id);
            $fs->delete_area_files($context->id, 'local_documents', self::SCANAREA, (int)$slip->id);
        }
        $DB->delete_records('local_documents_slip', ['docid' => $docid]);
    }
}
