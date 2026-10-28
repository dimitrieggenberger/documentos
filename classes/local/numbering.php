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
 * Document numbers (Acuerdo 0583-SE-2018, artículo 17) and verification codes.
 *
 * A school's number has six parts, joined by hyphens:
 * series - correlative - SACE code - year - issuing unit - shift,
 * for example CERTRAD-075-050100012M02-2026-DCE-JM. The correlative runs per
 * series, year and unit and is never reused: a recalled document keeps its
 * number, and a corrected one keeps it too, with a new version.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class numbering {

    /** @var string Crockford base-32: no I, L, O or U, so a code read aloud cannot be misread. */
    const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var int Characters in a verification code (60 bits). */
    const CODE_LENGTH = 12;

    /**
     * The canonical number.
     *
     * @param string $series
     * @param int $correlative
     * @param string $sace
     * @param int $year
     * @param string $unit
     * @param string $jornada
     * @return string
     */
    public static function format(string $series, int $correlative, string $sace, int $year, string $unit,
            string $jornada): string {
        $parts = [$series, self::pad($correlative), $sace, (string)$year, $unit, $jornada];
        $parts = array_map(static fn($p) => \core_text::strtoupper(trim((string)$p)), $parts);
        return implode('-', array_values(array_filter($parts, static fn($p) => $p !== '')));
    }

    /**
     * The number as printed on the sheet: "CERTRAD – 075 – 050100012M02 – 2026 – DCE – JM".
     *
     * @param string $number
     * @return string
     */
    public static function display(string $number): string {
        return str_replace('-', ' – ', $number);
    }

    /**
     * Three digits at least, as in the instructivo (012, 172); more once a series passes 999.
     *
     * @param int $correlative
     * @return string
     */
    public static function pad(int $correlative): string {
        return str_pad((string)max(0, $correlative), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Take the next correlative for a series, year and unit.
     *
     * Must run inside the caller's transaction: if issuing fails afterwards,
     * the counter goes back with it and no number is lost. The lock keeps two
     * secretaries issuing at the same moment from getting the same number.
     *
     * @param int $seriesid
     * @param int $year
     * @param string $unit
     * @return int
     */
    public static function next(int $seriesid, int $year, string $unit): int {
        global $DB;
        $counter = $DB->get_record('local_documents_counter', ['seriesid' => $seriesid, 'year' => $year,
            'unitcode' => $unit]);
        if (!$counter) {
            $counter = (object)['seriesid' => $seriesid, 'year' => $year, 'unitcode' => $unit, 'lastvalue' => 0,
                'timemodified' => time()];
            $counter->id = $DB->insert_record('local_documents_counter', $counter);
        }
        $next = (int)$counter->lastvalue + 1;
        // Never below a number already on a document, even if the counter was set back by hand.
        $issued = (int)$DB->get_field_sql('SELECT MAX(correlative) FROM {local_documents_doc}
                                            WHERE seriesid = ? AND year = ? AND unitcode = ? AND status <> ?',
            [$seriesid, $year, $unit, documents::STATUS_DRAFT]);
        $next = max($next, $issued + 1);
        $DB->update_record('local_documents_counter', (object)['id' => $counter->id, 'lastvalue' => $next,
            'timemodified' => time()]);
        return $next;
    }

    /**
     * Get the numbering lock for a series, year and unit.
     *
     * @param int $seriesid
     * @param int $year
     * @param string $unit
     * @return \core\lock\lock
     */
    public static function lock(int $seriesid, int $year, string $unit): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('local_documents_numbering');
        $lock = $factory->get_lock("{$seriesid}-{$year}-{$unit}", 20);
        if (!$lock) {
            throw new \moodle_exception('numberlockbusy', 'local_documents');
        }
        return $lock;
    }

    /**
     * Set the last correlative used, for a school that numbered on paper before.
     *
     * @param int $seriesid
     * @param int $year
     * @param string $unit
     * @param int $lastvalue
     * @return void
     */
    public static function set_last(int $seriesid, int $year, string $unit, int $lastvalue): void {
        global $DB;
        $lock = self::lock($seriesid, $year, $unit);
        try {
            $issued = (int)$DB->get_field_sql('SELECT MAX(correlative) FROM {local_documents_doc}
                                                WHERE seriesid = ? AND year = ? AND unitcode = ? AND status <> ?',
                [$seriesid, $year, $unit, documents::STATUS_DRAFT]);
            if ($lastvalue < $issued) {
                throw new \moodle_exception('counterbelowissued', 'local_documents', '', self::pad($issued));
            }
            $counter = $DB->get_record('local_documents_counter', ['seriesid' => $seriesid, 'year' => $year,
                'unitcode' => $unit]);
            if ($counter) {
                $DB->update_record('local_documents_counter', (object)['id' => $counter->id, 'lastvalue' => $lastvalue,
                    'timemodified' => time()]);
            } else {
                $DB->insert_record('local_documents_counter', (object)['seriesid' => $seriesid, 'year' => $year,
                    'unitcode' => $unit, 'lastvalue' => $lastvalue, 'timemodified' => time()]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * A new verification code that no document has yet.
     *
     * @return string twelve characters, without hyphens
     */
    public static function new_code(): string {
        global $DB;
        do {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, 31)];
            }
        } while ($DB->record_exists('local_documents_doc', ['verifycode' => $code])
            || $DB->record_exists('local_documents_slip', ['verifycode' => $code]));
        return $code;
    }

    /**
     * A code as someone typed it: case, spaces and hyphens ignored, O read as 0 and I or L as 1.
     *
     * @param string $input
     * @return string '' when it cannot be a code
     */
    public static function normalize_code(string $input): string {
        $code = strtoupper(preg_replace('/[\s\-–.]+/u', '', $input));
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);
        if (strlen($code) !== self::CODE_LENGTH || strspn($code, self::ALPHABET) !== self::CODE_LENGTH) {
            return '';
        }
        return $code;
    }

    /**
     * "K7QM-3XRT-9WPA".
     *
     * @param string $code
     * @return string
     */
    public static function format_code(string $code): string {
        return implode('-', str_split($code, 4));
    }
}
