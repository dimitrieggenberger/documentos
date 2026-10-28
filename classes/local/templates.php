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
 * Template versions and the small markup documents are written in.
 *
 * A template is never edited in place: every save adds a version with its
 * change note, and an issued document keeps the version it was made from.
 *
 * Markup, kept small so the letterhead stays the school's and not the
 * issuer's: blank lines separate paragraphs, a single line break stays one,
 * **text** is bold, *text* is italic, a paragraph starting with "= " is
 * centred, a paragraph whose lines all start with "- " is a bulleted list. Placeholders
 * are {{key}}; {{key|before|after}} prints the text around the value only when
 * the value is not empty. {{tabla.notas}} and {{tabla.asistencia}} on a
 * paragraph of their own become the tables.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class templates {

    /** @var string[] Field types the issuer form knows. */
    const FIELD_TYPES = ['text', 'textarea', 'select', 'date'];

    /**
     * Add a template version and make it the type's current one.
     *
     * @param int $typeid
     * @param array $data title, body, fields, allowbodyedit, changenote
     * @param int $userid
     * @return int the new version's id
     */
    public static function save_version(int $typeid, array $data, int $userid): int {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $last = (int)$DB->get_field_sql('SELECT MAX(version) FROM {local_documents_tplver} WHERE typeid = ?', [$typeid]);
        $id = (int)$DB->insert_record('local_documents_tplver', (object)[
            'typeid' => $typeid,
            'version' => $last + 1,
            'title' => trim((string)$data['title']),
            'body' => str_replace("\r\n", "\n", (string)$data['body']),
            'fields' => str_replace("\r\n", "\n", trim((string)($data['fields'] ?? ''))),
            'signatories' => 0,
            'allowbodyedit' => empty($data['allowbodyedit']) ? 0 : 1,
            'changenote' => trim((string)($data['changenote'] ?? '')),
            'createdby' => $userid,
            'timecreated' => time(),
        ]);
        $DB->update_record('local_documents_type', (object)['id' => $typeid, 'currentversion' => $id,
            'timemodified' => time()]);
        $transaction->allow_commit();
        return $id;
    }

    /**
     * The fields a template asks the issuer for.
     *
     * One line per field: key | Label | type | default | options or "required" | "required".
     * Options of a select are separated by ";".
     *
     * @param string $spec
     * @return array<string, array{key: string, label: string, type: string, default: string, options: string[], required: bool}>
     */
    public static function parse_fields(string $spec): array {
        $fields = [];
        foreach (preg_split('/\R/', $spec) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $key = strtolower((string)$parts[0]);
            if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', $key)) {
                continue;
            }
            $type = in_array($parts[2] ?? '', self::FIELD_TYPES, true) ? $parts[2] : 'text';
            $field = ['key' => $key, 'label' => ($parts[1] ?? '') !== '' ? $parts[1] : $key, 'type' => $type,
                'default' => (string)($parts[3] ?? ''), 'options' => [], 'required' => false];
            foreach (array_slice($parts, 4) as $extra) {
                if (strtolower($extra) === 'required') {
                    $field['required'] = true;
                } else if ($extra !== '') {
                    $field['options'] = array_values(array_filter(array_map('trim', explode(';', $extra)), 'strlen'));
                }
            }
            $fields[$key] = $field;
        }
        return $fields;
    }

    /**
     * Check a field specification, for the template form.
     *
     * @param string $spec
     * @return string[] problems, one per line that is not understood
     */
    public static function validate_fields(string $spec): array {
        $problems = [];
        foreach (preg_split('/\R/', $spec) as $i => $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (!preg_match('/^[a-z][a-z0-9_]{0,29}$/', strtolower($parts[0]))) {
                $problems[] = get_string('fieldbadkey', 'local_documents', $i + 1);
            } else if (isset($parts[2]) && $parts[2] !== '' && !in_array($parts[2], self::FIELD_TYPES, true)) {
                $problems[] = get_string('fieldbadtype', 'local_documents', $i + 1);
            }
        }
        return $problems;
    }

    /**
     * Turn a body into HTML for the PDF.
     *
     * Values are escaped; only the markup above and the generated tables
     * produce tags.
     *
     * @param string $body
     * @param array<string, string> $values placeholder key => plain value
     * @param array<string, string> $tables placeholder key => trusted HTML
     * @return string
     */
    public static function render(string $body, array $values, array $tables = []): string {
        $body = str_replace("\r\n", "\n", $body);
        $tablehtml = [];
        $text = self::substitute($body, $values, static function(string $key) use ($tables, &$tablehtml): ?string {
            if (!isset($tables[$key])) {
                return null;
            }
            $tablehtml[] = $tables[$key];
            return "\x01" . (count($tablehtml) - 1) . "\x01";
        });

        $html = '';
        foreach (preg_split('/\n{2,}/', trim($text)) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (preg_match('/^\x01(\d+)\x01$/', $paragraph, $m)) {
                $html .= $tablehtml[(int)$m[1]];
                continue;
            }
            $align = 'justify';
            if (strpos($paragraph, '= ') === 0) {
                $align = 'center';
                $paragraph = substr($paragraph, 2);
            }
            // Tables in running text are dropped: they only belong on a line of their own.
            $paragraph = self::inline(preg_replace('/\x01\d+\x01/', '', $paragraph));
            $lines = preg_split('/\n/', $paragraph);
            if (count(array_filter($lines, static fn($line) => strpos(ltrim($line), '- ') === 0)) === count($lines)) {
                $items = array_map(static fn($line) => '<li>' . substr(ltrim($line), 2) . '</li>', $lines);
                $html .= '<ul>' . implode('', $items) . '</ul>';
                continue;
            }
            $html .= '<p style="text-align:' . $align . ';">' . nl2br($paragraph, false) . '</p>';
        }
        return $html;
    }

    /**
     * Bold (**text**) and italic (*text*) inside an already escaped line.
     *
     * @param string $text
     * @return string
     */
    private static function inline(string $text): string {
        $text = preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', $text);
        return preg_replace('/(?<![*\w])\*(?!\s)([^*\n]+?)(?<!\s)\*(?![*\w])/u', '<i>$1</i>', $text);
    }

    /**
     * Replace the placeholders of a text; every piece of text that is not a table comes out escaped.
     *
     * @param string $text
     * @param array<string, string> $values
     * @param callable|null $special key => replacement or null, called first (tables)
     * @return string
     */
    public static function substitute(string $text, array $values, ?callable $special = null): string {
        $out = '';
        $offset = 0;
        if (preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*(?:\|([^|}]*))?(?:\|([^}]*))?\}\}/i', $text, $matches,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $out .= s(substr($text, $offset, $m[0][1] - $offset));
                $offset = $m[0][1] + strlen($m[0][0]);
                $key = strtolower($m[1][0]);
                $replacement = $special ? $special($key) : null;
                if ($replacement !== null) {
                    $out .= $replacement;
                    continue;
                }
                $value = trim((string)($values[$key] ?? ''));
                if (isset($m[2]) && $m[2][1] >= 0) {
                    $out .= $value === '' ? '' : s($m[2][0] . $value . ($m[3][0] ?? ''));
                } else {
                    $out .= $value === '' ? '<span style="color:#a12734;">[' . s($key) . ']</span>' : s($value);
                }
            }
        }
        return $out . s(substr($text, $offset));
    }

    /**
     * A line with placeholders as plain text (a title), nothing escaped: TCPDF prints it as text.
     *
     * @param string $text
     * @param array<string, string> $values
     * @return string
     */
    public static function plain(string $text, array $values): string {
        return trim(html_entity_decode(strip_tags(self::substitute($text, $values)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * The placeholders a body uses that have no value, so the issuer is told before issuing.
     *
     * Conditional placeholders ({{key|...}}) are allowed to be empty.
     *
     * @param string $body
     * @param array<string, string> $values
     * @param string[] $tables keys that are tables
     * @return string[]
     */
    public static function missing(string $body, array $values, array $tables = []): array {
        $missing = [];
        if (preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*(\|[^}]*)?\}\}/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $key = strtolower($m[1]);
                if (!empty($m[2]) || in_array($key, $tables, true)) {
                    continue;
                }
                if (trim((string)($values[$key] ?? '')) === '') {
                    $missing[$key] = $key;
                }
            }
        }
        return array_values($missing);
    }

    /**
     * The placeholders every template can use, for the editor's help.
     *
     * @return array<string, string> key => what it prints
     */
    public static function known_placeholders(): array {
        $keys = ['school.name', 'school.officialname', 'school.legalname', 'school.sace', 'school.city', 'school.department', 'school.jornada',
            'unit.name', 'date.long', 'date.words', 'holder.name', 'holder.idnumber', 'g.el', 'g.al', 'g.del', 'g.o',
            'student.gradename', 'student.level', 'student.yearlabel', 'student.lastquarter', 'student.birthdate',
            'staff.position', 'staff.startdate', 'staff.years', 'staff.courses',
            'tabla.notas', 'tabla.asistencia', 'signer.name', 'signer.title'];
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = get_string('ph_' . str_replace('.', '_', $key), 'local_documents');
        }
        return $result;
    }

    /**
     * The data attributes that give a text field its toolbar (editor.js): the labels and the placeholders.
     *
     * @param \stdClass|null $tplver the template version, for its own fields
     * @param bool $withplaceholders false for a field's own text, where placeholders are not filled in
     * @return array
     */
    public static function editor_attributes(?\stdClass $tplver, bool $withplaceholders = true): array {
        global $PAGE;
        static $loaded = false;
        if (!$loaded) {
            $PAGE->requires->js(new \moodle_url('/local/documents/editor.js'));
            $loaded = true;
        }
        $placeholders = $withplaceholders ? self::known_placeholders() : [];
        if ($tplver && $withplaceholders) {
            foreach (self::parse_fields((string)$tplver->fields) as $key => $field) {
                $placeholders['f.' . $key] = $field['label'];
            }
        }
        $labels = [];
        foreach (['toolbar', 'bold', 'italic', 'center', 'list', 'insert'] as $key) {
            $labels[$key] = get_string('editor' . $key, 'local_documents');
        }
        return ['data-placeholders' => json_encode((object)$placeholders, JSON_UNESCAPED_UNICODE),
            'data-labels' => json_encode($labels, JSON_UNESCAPED_UNICODE)];
    }

    /**
     * A line diff of two texts, for the template history.
     *
     * @param string $old
     * @param string $new
     * @return array<int, array{0: string, 1: string}> [' '|'-'|'+', line]
     */
    public static function diff(string $old, string $new): array {
        $a = preg_split('/\R/', $old);
        $b = preg_split('/\R/', $new);
        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $result = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $result[] = [' ', $a[$i]];
                $i++;
                $j++;
            } else if ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $result[] = ['-', $a[$i++]];
            } else {
                $result[] = ['+', $b[$j++]];
            }
        }
        for (; $i < $n; $i++) {
            $result[] = ['-', $a[$i]];
        }
        for (; $j < $m; $j++) {
            $result[] = ['+', $b[$j]];
        }
        return $result;
    }
}
