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
    const JORNADAS = ['JM' => 'Jornada Matutina', 'JV' => 'Jornada Vespertina', 'JN' => 'Jornada Nocturna',
        'JD' => 'Jornada Doble', 'JE' => 'Jornada Extendida'];

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

    /**
     * The school as documents print it.
     *
     * @return array{name: string, legalname: string, sace: string, city: string, department: string,
     *     jornada: string, jornadalabel: string}
     */
    public static function school(): array {
        $jornada = \core_text::strtoupper(self::get('jornada', 'JM'));
        return [
            'name' => self::get('schoolname', format_string(get_site()->fullname)),
            'legalname' => self::get('legalname'),
            'sace' => \core_text::strtoupper(self::get('sacecode')),
            'city' => self::get('city', 'San Pedro Sula'),
            'department' => self::get('department', 'Cortés'),
            'jornada' => $jornada,
            'jornadalabel' => self::JORNADAS[$jornada] ?? $jornada,
        ];
    }

    /**
     * The issuing units (5th part of the number): code => name.
     *
     * @return array<string, string>
     */
    public static function units(): array {
        return array_map(static fn($unit) => $unit['name'], self::unit_details());
    }

    /**
     * One issuing unit as the header prints it: its name, official e-mail and phone.
     *
     * @param string $code
     * @return array{code: string, name: string, email: string, phone: string}
     */
    public static function unit(string $code): array {
        $units = self::unit_details();
        return $units[$code] ?? ['code' => $code, 'name' => $code, 'email' => '', 'phone' => ''];
    }

    /**
     * Every issuing unit: one "CODE | Name | e-mail | phone" per line.
     *
     * @return array<string, array{code: string, name: string, email: string, phone: string}>
     */
    private static function unit_details(): array {
        $raw = self::get('units', "DCE | Dirección del Centro Educativo\nSDCE | Subdirección del Centro Educativo\n"
            . "SCE | Secretaría del Centro Educativo");
        $units = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $code = \core_text::strtoupper($parts[0]);
            if (preg_match('/^[A-Z0-9]{1,20}$/', $code)) {
                $units[$code] = ['code' => $code, 'name' => ($parts[1] ?? '') !== '' ? $parts[1] : $code,
                    'email' => $parts[2] ?? '', 'phone' => $parts[3] ?? ''];
            }
        }
        return $units ?: ['DCE' => ['code' => 'DCE', 'name' => 'Dirección del Centro Educativo', 'email' => '',
            'phone' => '']];
    }

    /**
     * Who may sign: one "Name | Position | e-mail | phone" per line.
     *
     * @return array<int, array{name: string, title: string, email: string, phone: string}>
     */
    public static function signatories(): array {
        $list = [];
        foreach (preg_split('/\R/', self::get('signatories')) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if ($parts[0] !== '') {
                $list[] = ['name' => $parts[0], 'title' => $parts[1] ?? '', 'email' => $parts[2] ?? '',
                    'phone' => $parts[3] ?? ''];
            }
        }
        return $list;
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
        foreach (['schoolname', 'sacecode', 'jornada'] as $name) {
            if (self::get($name) === '') {
                $missing[] = $name;
            }
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
