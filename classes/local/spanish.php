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
 * Spanish wording the documents need: dates in words, grade names.
 *
 * Pure functions, so the wording can be tested without a database.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class spanish {

    /** @var string[] Month names, January first. */
    const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre',
        'octubre', 'noviembre', 'diciembre'];

    /**
     * A whole number from 0 to 999 999 in Spanish words, as a date or a count is written.
     *
     * @param int $n
     * @return string
     */
    public static function number(int $n): string {
        $units = ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
            'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
            'veintisiete', 'veintiocho', 'veintinueve'];
        $tens = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta',
            9 => 'noventa'];
        $hundreds = [1 => 'ciento', 2 => 'doscientos', 3 => 'trescientos', 4 => 'cuatrocientos', 5 => 'quinientos',
            6 => 'seiscientos', 7 => 'setecientos', 8 => 'ochocientos', 9 => 'novecientos'];
        if ($n < 0 || $n > 999999) {
            return (string)$n;
        }
        if ($n < 30) {
            return $units[$n];
        }
        if ($n < 100) {
            $rest = $n % 10;
            return $tens[intdiv($n, 10)] . ($rest ? ' y ' . $units[$rest] : '');
        }
        if ($n < 1000) {
            if ($n === 100) {
                return 'cien';
            }
            $rest = $n % 100;
            return $hundreds[intdiv($n, 100)] . ($rest ? ' ' . self::number($rest) : '');
        }
        $thousands = intdiv($n, 1000);
        $rest = $n % 1000;
        $head = $thousands === 1 ? 'mil'
            : preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], self::number($thousands)) . ' mil';
        return $head . ($rest ? ' ' . self::number($rest) : '');
    }

    /**
     * "27 de octubre de 2026".
     *
     * @param int $time
     * @param string $timezone
     * @return string
     */
    public static function date_long(int $time, string $timezone = '99'): string {
        [$day, $month, $year] = self::parts($time, $timezone);
        return $day . ' de ' . self::MONTHS[$month - 1] . ' de ' . $year;
    }

    /**
     * "a los veintisiete días del mes de octubre del año dos mil veintiséis".
     *
     * @param int $time
     * @param string $timezone
     * @return string
     */
    public static function date_words(int $time, string $timezone = '99'): string {
        [$day, $month, $year] = self::parts($time, $timezone);
        $days = $day === 1 ? 'al primer día' : 'a los ' . self::number($day) . ' días';
        return $days . ' del mes de ' . self::MONTHS[$month - 1] . ' del año ' . self::number($year);
    }

    /**
     * Day, month and year of a moment in the site's time zone.
     *
     * @param int $time
     * @param string $timezone
     * @return int[]
     */
    public static function parts(int $time, string $timezone = '99'): array {
        $date = new \DateTime('@' . $time);
        $date->setTimezone(\core_date::get_user_timezone_object($timezone === '99' ? \core_date::get_server_timezone() : $timezone));
        return [(int)$date->format('j'), (int)$date->format('n'), (int)$date->format('Y')];
    }

    /**
     * The grade a level code names: "10G" is "Décimo Grado".
     *
     * @param string $level
     * @return string '' when the code has no number in it
     */
    public static function grade_name(string $level): string {
        $names = [1 => 'Primer', 2 => 'Segundo', 3 => 'Tercer', 4 => 'Cuarto', 5 => 'Quinto', 6 => 'Sexto',
            7 => 'Séptimo', 8 => 'Octavo', 9 => 'Noveno', 10 => 'Décimo', 11 => 'Undécimo', 12 => 'Duodécimo'];
        if (!preg_match('/^(\d{1,2})/', trim($level), $m) || !isset($names[(int)$m[1]])) {
            return '';
        }
        return $names[(int)$m[1]] . ' Grado';
    }

    /**
     * Whole years between two moments, written out: "3 años", "1 año".
     *
     * @param int $from
     * @param int $to
     * @return string
     */
    public static function years_between(int $from, int $to): string {
        if ($from <= 0 || $to <= $from) {
            return '';
        }
        $a = new \DateTime('@' . $from);
        $b = new \DateTime('@' . $to);
        $years = (int)$a->diff($b)->y;
        $months = (int)$a->diff($b)->m;
        $text = $years === 1 ? '1 año' : $years . ' años';
        if ($months > 0) {
            $text .= ' y ' . ($months === 1 ? '1 mes' : $months . ' meses');
        }
        return $text;
    }
}
