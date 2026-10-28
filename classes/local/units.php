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
 * Issuing units: who issues which documents, and who signs for whom.
 *
 * A unit is the authority of the fifth part of the number (DCE Dirección,
 * SCE Secretaría...). It is always linked to one Moodle user, its head, who
 * signs the documents that require the unit's signature. An administrator
 * creates and edits units, says which document types each may issue, and the
 * template catalogue says which units' signatures each type requires.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class units {

    /**
     * Every unit, in their order.
     *
     * @param bool $enabledonly
     * @return \stdClass[] keyed by id
     */
    public static function all(bool $enabledonly = false): array {
        global $DB;
        return $DB->get_records('local_documents_unit', $enabledonly ? ['enabled' => 1] : null, 'sortorder, code');
    }

    /**
     * One unit by its code, or null.
     *
     * @param string $code
     * @return \stdClass|null
     */
    public static function by_code(string $code): ?\stdClass {
        global $DB;
        return $DB->get_record('local_documents_unit', ['code' => \core_text::strtoupper(trim($code))]) ?: null;
    }

    /**
     * The enabled units that may issue a document type.
     *
     * @param int $typeid
     * @return \stdClass[] keyed by id
     */
    public static function for_type(int $typeid): array {
        global $DB;
        return $DB->get_records_sql('SELECT u.* FROM {local_documents_unit} u
                                       JOIN {local_documents_unittype} ut ON ut.unitid = u.id
                                      WHERE ut.typeid = ? AND u.enabled = 1
                                   ORDER BY u.sortorder, u.code', [$typeid]);
    }

    /**
     * The units whose signature a document type requires, in order; empty when it goes out unsigned.
     *
     * @param int $typeid
     * @return \stdClass[] in signing order
     */
    public static function signers_for_type(int $typeid): array {
        global $DB;
        return array_values($DB->get_records_sql('SELECT u.*, s.sortorder AS signerorder
                                                    FROM {local_documents_signer} s
                                                    JOIN {local_documents_unit} u ON u.id = s.unitid
                                                   WHERE s.typeid = ?
                                                ORDER BY s.sortorder, s.id', [$typeid]));
    }

    /**
     * The type ids a unit may issue.
     *
     * @param int $unitid
     * @return int[]
     */
    public static function type_ids(int $unitid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_documents_unittype', 'typeid', 'unitid = ?', [$unitid]));
    }

    /**
     * Set the units whose signature a type requires, in this order.
     *
     * @param int $typeid
     * @param int[] $unitids
     * @return void
     */
    public static function set_signers(int $typeid, array $unitids): void {
        global $DB;
        $DB->delete_records('local_documents_signer', ['typeid' => $typeid]);
        $order = 0;
        foreach (array_unique(array_map('intval', $unitids)) as $unitid) {
            if ($unitid > 0) {
                $DB->insert_record('local_documents_signer', (object)['typeid' => $typeid, 'unitid' => $unitid,
                    'sortorder' => $order++]);
            }
        }
    }

    /**
     * Create or update a unit and the types it may issue.
     *
     * A new head takes over the signatures still waiting on the unit.
     *
     * @param \stdClass $data id (0 for new), code, name, email, phone, userid, signername, signertitle, enabled, typeids
     * @return int the unit's id
     */
    public static function save(\stdClass $data): int {
        global $DB;
        $now = time();
        $record = (object)[
            'code' => \core_text::strtoupper(trim((string)$data->code)),
            'name' => trim((string)$data->name),
            'email' => trim((string)($data->email ?? '')),
            'phone' => trim((string)($data->phone ?? '')),
            'userid' => (int)$data->userid,
            'signername' => trim((string)($data->signername ?? '')),
            'signertitle' => trim((string)($data->signertitle ?? '')),
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_documents_unit', $record);
            $DB->set_field_select('local_documents_approval', 'userid', $record->userid, 'unitid = ? AND status = ?',
                [$record->id, 'pending']);
        } else {
            $record->sortorder = (int)$DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) + 10
                                                            FROM {local_documents_unit}');
            $record->timecreated = $now;
            $record->id = (int)$DB->insert_record('local_documents_unit', $record);
        }
        if (isset($data->typeids)) {
            $DB->delete_records('local_documents_unittype', ['unitid' => $record->id]);
            foreach (array_unique(array_map('intval', (array)$data->typeids)) as $typeid) {
                if ($typeid > 0) {
                    $DB->insert_record('local_documents_unittype', (object)['unitid' => $record->id, 'typeid' => $typeid]);
                }
            }
        }
        $transaction->allow_commit();
        return (int)$record->id;
    }

    /**
     * A unit as the header and the signature block print it.
     *
     * @param \stdClass|null $unit
     * @return array{id: int, code: string, name: string, email: string, phone: string, userid: int,
     *     signername: string, signertitle: string}
     */
    public static function details(?\stdClass $unit): array {
        if (!$unit) {
            return ['id' => 0, 'code' => '', 'name' => '', 'email' => '', 'phone' => '', 'userid' => 0, 'signername' => '',
                'signertitle' => ''];
        }
        $name = trim((string)$unit->signername);
        if ($name === '' && (int)$unit->userid > 0) {
            $user = \core_user::get_user((int)$unit->userid);
            $name = $user ? fullname($user) : '';
        }
        return ['id' => (int)$unit->id, 'code' => (string)$unit->code, 'name' => (string)$unit->name,
            'email' => (string)$unit->email, 'phone' => (string)$unit->phone, 'userid' => (int)$unit->userid,
            'signername' => $name, 'signertitle' => (string)$unit->signertitle];
    }

    /**
     * Whether a user heads any unit (and so may have something to sign).
     *
     * @param int $userid
     * @return bool
     */
    public static function is_signer(int $userid): bool {
        global $DB;
        return $userid > 0 && ($DB->record_exists('local_documents_unit', ['userid' => $userid])
            || $DB->record_exists('local_documents_approval', ['userid' => $userid, 'status' => 'pending']));
    }

    /**
     * Create the units of the old "units" setting ("CODE | Name | e-mail | phone" per line), once.
     *
     * Every unit may issue every type at first, and each type requires the
     * signatures the catalogue suggests for it; an administrator then links each
     * unit to its head and narrows what it issues.
     *
     * @param string $legacy the old setting, '' for the three default units
     * @return void
     */
    public static function install_defaults(string $legacy = ''): void {
        global $DB;
        if ($DB->record_exists('local_documents_unit', [])) {
            return;
        }
        $lines = trim($legacy) !== '' ? preg_split('/\R/', $legacy) : [
            'DCE | Dirección del Centro Educativo', 'SDCE | Subdirección del Centro Educativo',
            'SCE | Secretaría del Centro Educativo'];
        $types = $DB->get_records_menu('local_documents_type', null, '', 'shortname, id');
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line));
            $code = \core_text::strtoupper($parts[0]);
            if (!preg_match('/^[A-Z0-9]{1,20}$/', $code) || self::by_code($code)) {
                continue;
            }
            self::save((object)['code' => $code, 'name' => ($parts[1] ?? '') !== '' ? $parts[1] : $code,
                'email' => $parts[2] ?? '', 'phone' => $parts[3] ?? '', 'userid' => 0, 'enabled' => 1,
                'typeids' => array_values($types)]);
        }
        foreach (catalog::types() as $shortname => $type) {
            if (!isset($types[$shortname])) {
                continue;
            }
            $unitids = [];
            foreach ($type['signers'] ?? [] as $code) {
                if ($unit = self::by_code($code)) {
                    $unitids[] = (int)$unit->id;
                }
            }
            if (!$DB->record_exists('local_documents_signer', ['typeid' => $types[$shortname]])) {
                self::set_signers((int)$types[$shortname], $unitids);
            }
        }
    }
}
