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
 * The plugin settings, read in one place.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {

    /** @var string[] Shift codes the instructivo uses, with their printed names. */
    const JORNADAS = ['EX' => 'Jornada Extendida', 'JM' => 'Jornada Matutina', 'JV' => 'Jornada Vespertina',
        'JN' => 'Jornada Nocturna', 'JD' => 'Jornada Doble'];

    /** @var string[] The sheet's images: key => file area of its setting. */
    const IMAGE_AREAS = ['seal' => 'logo', 'watermark' => 'watermark', 'stamp' => 'stamp', 'url' => 'footerlogo'];

    /**
     * One setting, trimmed.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    public static function get(string $name, string $default = ''): string {
        $value = get_config('local_documents', $name);
        return $value === false || $value === null || trim((string)$value) === '' ? $default : trim((string)$value);
    }

    /** @var string[] The school's names and code until the settings say otherwise. */
    const SCHOOL_DEFAULTS = [
        'schoolname' => 'EuropaSchule San Pedro Sula',
        'officialname' => 'Centro Educativo No Gubernamental Trilingüe EuropaSchule',
        'legalname' => 'Educación Helvética SA',
        'sacecode' => '050101072T03',
    ];

    /**
     * The school as documents print it.
     *
     * Its commercial name and legal name go in the letterhead (brand manual);
     * its official name, as registered with the Secretaría de Educación, under
     * the number and in the text of official documents.
     *
     * @return array{name: string, officialname: string, legalname: string, sace: string, city: string,
     *     department: string, jornada: string, jornadalabel: string}
     */
    public static function school(): array {
        $jornada = \core_text::strtoupper(self::get('jornada', 'EX'));
        $name = self::get('schoolname', self::SCHOOL_DEFAULTS['schoolname']);
        return [
            'name' => $name,
            'officialname' => self::get('officialname', self::SCHOOL_DEFAULTS['officialname']),
            'legalname' => self::get('legalname', self::SCHOOL_DEFAULTS['legalname']),
            'sace' => \core_text::strtoupper(self::get('sacecode', self::SCHOOL_DEFAULTS['sacecode'])),
            'city' => self::get('city', 'San Pedro Sula'),
            'department' => self::get('department', 'Cortés'),
            'jornada' => $jornada,
            'jornadalabel' => self::JORNADAS[$jornada] ?? $jornada,
        ];
    }

    /**
     * The enabled issuing units (5th part of the number): code => name.
     *
     * @return array<string, string>
     */
    public static function units(): array {
        $list = [];
        foreach (units::all(true) as $unit) {
            $list[$unit->code] = $unit->name;
        }
        return $list;
    }

    /**
     * One issuing unit as the header prints it: its name, official e-mail and phone.
     *
     * @param string $code
     * @return array{id: int, code: string, name: string, email: string, phone: string, userid: int,
     *     signername: string, signertitle: string}
     */
    public static function unit(string $code): array {
        $details = units::details(units::by_code($code));
        if ($details['code'] === '') {
            $details['code'] = $details['name'] = $code;
        }
        return $details;
    }

    /**
     * The address a QR code opens: the public website's page when set, else this site's own.
     *
     * @param string $code
     * @return string
     */
    public static function verify_url(string $code): string {
        $base = self::get('verifyurl');
        if ($base === '') {
            return (new \moodle_url('/local/documents/verify.php', ['c' => $code]))->out(false);
        }
        return $base . (strpos($base, '?') === false ? '?' : '&') . 'c=' . rawurlencode($code);
    }

    /**
     * The verification page as printed under the QR code, without the scheme.
     *
     * @return string
     */
    public static function verify_label(): string {
        $base = self::get('verifyurl');
        if ($base === '') {
            $base = (new \moodle_url('/local/documents/verify.php'))->out(false);
        }
        return preg_replace('#^https?://#', '', rtrim($base, '/'));
    }

    /**
     * What is missing before anything can be issued.
     *
     * @return string[] setting names
     */
    public static function missing(): array {
        $missing = [];
        if (self::get('jornada', 'EX') === '') {
            $missing[] = 'jornada';
        }
        return $missing;
    }

    /**
     * One profile value of a user: the field named in a setting, or a column of the user table.
     *
     * @param int $userid
     * @param string $setting name of the setting holding the field's short name
     * @param string $default the field used when the setting is empty
     * @return string|int|null
     */
    public static function profile_value(int $userid, string $setting, string $default = '') {
        global $DB, $CFG;
        $field = self::get($setting, $default);
        if ($field === '' || $userid <= 0) {
            return null;
        }
        if (in_array($field, ['idnumber', 'institution', 'department', 'city', 'phone1', 'address'], true)) {
            return $DB->get_field('user', $field, ['id' => $userid]);
        }
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $record = profile_user_record($userid, false);
        return $record->$field ?? null;
    }
}
