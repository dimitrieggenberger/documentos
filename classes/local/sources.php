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
 * Where a document's text comes from: the values its placeholders print.
 *
 * Grades, level and attendance are read from local_grades through its
 * documents bridge (behind class_exists), so a certificate prints what the
 * school published and nothing else. Without local_grades the student lines
 * stay empty and the issuer is told before issuing.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sources {

    /** @var string[] Placeholders that print a table. */
    const TABLES = ['tabla.notas', 'tabla.asistencia'];

    /**
     * Whether published grades can be read.
     *
     * @return bool
     */
    public static function has_grades(): bool {
        return class_exists('\local_grades\local\documents_bridge');
    }

    /**
     * What a new draft starts with for one person.
     *
     * @param \stdClass $type
     * @param int $userid
     * @return array{holdername: string, holderidnumber: string, gender: string, year: string}
     */
    public static function prefill(\stdClass $type, int $userid): array {
        $result = ['holdername' => '', 'holderidnumber' => '', 'gender' => '', 'year' => ''];
        if ($userid > 0) {
            $user = \core_user::get_user($userid, '*', MUST_EXIST);
            $result['holdername'] = fullname($user);
            $result['holderidnumber'] = trim((string)config::profile_value($userid, 'fieldidnumber', 'idnumber'));
            $gender = \core_text::strtoupper(trim((string)config::profile_value($userid, 'fieldgender')));
            if ($gender !== '') {
                $result['gender'] = in_array($gender[0], ['F', 'M', 'W'], true) ? ($gender[0] === 'M' ? 'M' : 'F') : '';
                if (in_array($gender, ['FEMENINO', 'MUJER', 'FEMALE', 'WEIBLICH'], true)) {
                    $result['gender'] = 'F';
                }
            }
        }
        if ($type->subject === 'student' && $userid > 0 && self::has_grades()) {
            $result['year'] = \local_grades\local\documents_bridge::school_years($userid)['current'];
        }
        return $result;
    }

    /**
     * The school years a student can be certified for: key => label.
     *
     * @param int $userid
     * @return array<string, string>
     */
    public static function school_years(int $userid): array {
        if ($userid <= 0 || !self::has_grades()) {
            return [];
        }
        $years = \local_grades\local\documents_bridge::school_years($userid);
        $options = $years['years'];
        if ($years['current'] !== '' && !isset($options[$years['current']])) {
            $options = [$years['current'] => $years['current']] + $options;
        }
        return $options;
    }

    /**
     * The student's published record, in the documents' language.
     *
     * @param int $userid
     * @param string $year
     * @return array|null null without local_grades
     */
    public static function student_record(int $userid, string $year): ?array {
        if ($userid <= 0 || !self::has_grades()) {
            return null;
        }
        $previous = force_current_language('es');
        try {
            $record = \local_grades\local\documents_bridge::student_record($userid, $year);
            $record['lastquarterlabel'] = $record['lastquarter'] > 0
                ? get_string('quarter' . $record['lastquarter'], 'local_grades') : '';
        } finally {
            force_current_language($previous);
        }
        return $record;
    }

    /**
     * Every placeholder value of a document, and its tables.
     *
     * @param \stdClass $doc the document record
     * @param \stdClass $type
     * @param \stdClass $tplver
     * @param array $data the document's data
     * @return array{values: array<string, string>, tables: array<string, string>, record: ?array, warnings: string[]}
     */
    public static function collect(\stdClass $doc, \stdClass $type, \stdClass $tplver, array $data): array {
        $school = config::school();
        $issuedate = (int)($data['issuedate'] ?? time());
        $values = [
            'school.name' => $school['name'],
            'school.sace' => $school['sace'],
            'school.city' => $school['city'],
            'school.department' => $school['department'],
            'school.jornada' => $school['jornadalabel'],
            'date.long' => spanish::date_long($issuedate),
            'date.words' => spanish::date_words($issuedate),
            'holder.name' => trim((string)$doc->holdername),
            'holder.idnumber' => trim((string)$doc->holderidnumber),
        ];
        $female = ($data['gender'] ?? '') === 'F';
        $values += [
            'g.el' => $female ? 'la' : 'el',
            'g.al' => $female ? 'a la' : 'al',
            'g.del' => $female ? 'de la' : 'del',
            'g.o' => $female ? 'a' : 'o',
        ];

        $signers = self::signers($data, (int)$tplver->signatories);
        $values['signer.name'] = $signers[0]['name'] ?? '';
        $values['signer.title'] = $signers[0]['title'] ?? '';

        foreach (templates::parse_fields((string)$tplver->fields) as $key => $field) {
            $value = trim((string)($data['fields'][$key] ?? ''));
            if ($field['type'] === 'date' && ctype_digit($value) && (int)$value > 0) {
                $value = spanish::date_long((int)$value);
            }
            $values['f.' . $key] = $value;
        }

        $tables = [];
        $warnings = [];
        $record = null;
        $userid = (int)$doc->userid;
        if ($type->subject === 'student' && $userid > 0) {
            $record = self::student_record($userid, (string)($data['year'] ?? ''));
            if ($record === null) {
                $warnings[] = get_string('warnnogrades', 'local_documents');
            } else {
                $values['student.level'] = $record['level'];
                $values['student.gradename'] = spanish::grade_name($record['level']);
                $values['student.yearlabel'] = $record['yearlabel'];
                $values['student.lastquarter'] = $record['lastquarterlabel'];
                if ($record['hold'] !== null) {
                    $warnings[] = get_string('warnhold', 'local_documents', $record['hold']['label']);
                }
                if ($type->source === 'grades') {
                    $tables['tabla.notas'] = self::grades_table($record);
                    $tables['tabla.asistencia'] = self::attendance_table($record);
                }
            }
            $birth = config::profile_value($userid, 'fieldbirthdate');
            $values['student.birthdate'] = is_numeric($birth) && (int)$birth > 0 ? spanish::date_long((int)$birth)
                : trim((string)$birth);
        }
        if ($type->subject === 'staff' && $userid > 0) {
            $values['staff.position'] = trim((string)config::profile_value($userid, 'fieldposition'));
            $start = config::profile_value($userid, 'fieldstartdate');
            $startts = is_numeric($start) ? (int)$start : (strtotime((string)$start) ?: 0);
            $values['staff.startdate'] = $startts > 0 ? spanish::date_long($startts) : '';
            $values['staff.years'] = spanish::years_between($startts, $issuedate);
            $values['staff.courses'] = self::taught_courses($userid);
        }
        return ['values' => $values, 'tables' => $tables, 'record' => $record, 'warnings' => $warnings];
    }

    /**
     * The people signing a document, as chosen on the draft.
     *
     * @param array $data
     * @param int $count signature blocks of the template
     * @return array<int, array{name: string, title: string}>
     */
    public static function signers(array $data, int $count): array {
        $list = config::signatories();
        $chosen = $data['signers'] ?? [];
        $signers = [];
        for ($i = 0; $i < max(1, $count); $i++) {
            $index = isset($chosen[$i]) ? (int)$chosen[$i] : $i;
            if (isset($data['signerssnapshot'][$i])) {
                $signers[] = $data['signerssnapshot'][$i];
            } else if (isset($list[$index])) {
                $signers[] = $list[$index];
            } else {
                $signers[] = ['name' => '', 'title' => ''];
            }
        }
        return $signers;
    }

    /**
     * "Matemáticas, Química y Física": the courses a teacher teaches now.
     *
     * @param int $userid
     * @return string
     */
    public static function taught_courses(int $userid): string {
        $names = [];
        foreach (enrol_get_users_courses($userid, true, 'id, fullname') as $course) {
            $context = \context_course::instance($course->id);
            if (!has_capability('moodle/grade:edit', $context, $userid)) {
                continue;
            }
            $name = self::has_grades() ? \local_grades\local\documents_bridge::course_name((string)$course->fullname)
                : (string)$course->fullname;
            $names[$name] = format_string($name);
        }
        $names = array_values($names);
        if (count($names) <= 1) {
            return (string)($names[0] ?? '');
        }
        $last = array_pop($names);
        return implode(', ', $names) . ' y ' . $last;
    }

    /**
     * A grade as the gradebook shows it, without the percent sign: 87, 86.5.
     *
     * @param float|null $value
     * @return string
     */
    public static function grade(?float $value): string {
        if ($value === null) {
            return '—';
        }
        $rounded = round($value, 1);
        return floor($rounded) == $rounded ? (string)(int)$rounded : number_format($rounded, 1, '.', '');
    }

    /**
     * The published grades as a table for the PDF.
     *
     * @param array $record
     * @return string trusted HTML
     */
    public static function grades_table(array $record): string {
        if (empty($record['courses'])) {
            return '<p style="text-align:center;"><i>' . s(get_string_manager()->get_string('nogradespublished',
                'local_documents', null, 'es')) . '</i></p>';
        }
        $head = '#eef2f6';
        $html = '<table border="0.3" cellpadding="3" style="border-color:#9aa5b1;font-size:9.5pt;">'
            . '<thead><tr style="background-color:' . $head . ';font-weight:bold;">'
            . '<th width="46%">Asignatura</th>';
        foreach (['I', 'II', 'III', 'IV'] as $roman) {
            $html .= '<th width="10%" align="center">' . $roman . '</th>';
        }
        $html .= '<th width="14%" align="center">Final</th></tr></thead><tbody>';
        foreach ($record['courses'] as $course) {
            $html .= '<tr><td width="46%">' . s($course['name']) . '</td>';
            for ($i = 1; $i <= 4; $i++) {
                $cell = !empty($course['held'][$i]) ? 'Pend.' : self::grade($course['quarters'][$i]);
                $html .= '<td width="10%" align="center">' . s($cell) . '</td>';
            }
            $html .= '<td width="14%" align="center"><b>' . s(self::grade($course['total'])) . '</b></td></tr>';
        }
        return $html . '</tbody></table>';
    }

    /**
     * The report card's attendance lines as a table, or nothing.
     *
     * @param array $record
     * @return string trusted HTML
     */
    public static function attendance_table(array $record): string {
        if (empty($record['attendance'])) {
            return '';
        }
        $html = '<table border="0.3" cellpadding="3" style="border-color:#9aa5b1;font-size:9.5pt;">'
            . '<thead><tr style="background-color:#eef2f6;font-weight:bold;"><th width="46%">Inasistencias (días)</th>';
        foreach (['I', 'II', 'III', 'IV'] as $roman) {
            $html .= '<th width="10%" align="center">' . $roman . '</th>';
        }
        $html .= '<th width="14%" align="center">Año</th></tr></thead><tbody>';
        foreach ($record['attendance'] as $row) {
            $html .= '<tr><td width="46%">' . s($row['label']) . '</td>';
            foreach (array_values($row['values']) as $i => $value) {
                $html .= '<td width="' . ($i < 4 ? '10' : '14') . '%" align="center">' . s((string)$value) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }
}
