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

    /** @var string[] The units a school starts with: code => name. */
    const DEFAULT_UNITS = [
        'DCE' => 'Dirección del Centro Educativo',
        'SDCE' => 'Subdirección del Centro Educativo',
        'SCE' => 'Secretaría del Centro Educativo',
        'GG' => 'Gerencia General',
        'GA' => 'Gerencia Académica',
    ];

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

    /** @var int The signature slot meaning "the head of whichever unit issues the document". */
    const ISSUER = 0;

    /**
     * The signature slots of a document type, in order: unit ids, ISSUER for the issuing unit.
     *
     * @param int $typeid
     * @return int[]
     */
    public static function signer_slots(int $typeid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('local_documents_signer', 'unitid', 'typeid = ? ORDER BY sortorder, id',
            [$typeid]));
    }

    /**
     * The units whose heads sign a document of a type issued by a unit, in order, each once.
     *
     * The ISSUER slot becomes the issuing unit; empty when the document goes out unsigned.
     *
     * @param int $typeid
     * @param string $unitcode the issuing unit
     * @return \stdClass[]
     */
    public static function required_units(int $typeid, string $unitcode): array {
        global $DB;
        $units = [];
        foreach (self::signer_slots($typeid) as $unitid) {
            $unit = $unitid === self::ISSUER ? self::by_code($unitcode)
                : ($DB->get_record('local_documents_unit', ['id' => $unitid]) ?: null);
            if ($unit && !isset($units[(int)$unit->id])) {
                $units[(int)$unit->id] = $unit;
            }
        }
        return array_values($units);
    }

    /**
     * Who signs for a unit at a moment: its head, or the substitute while a substitution runs.
     *
     * @param \stdClass $unit
     * @param int|null $time
     * @return array{userid: int, onbehalfof: int} onbehalfof is the head when a substitute signs
     */
    public static function signer_now(\stdClass $unit, ?int $time = null): array {
        $time = $time ?? time();
        $substitute = (int)($unit->substituteid ?? 0);
        $from = (int)($unit->substitutefrom ?? 0);
        $until = (int)($unit->substituteuntil ?? 0);
        $active = $substitute > 0 && $substitute !== (int)$unit->userid && ($from === 0 || $time >= $from)
            && ($until === 0 || $time < $until + DAYSECS);
        return $active ? ['userid' => $substitute, 'onbehalfof' => (int)$unit->userid]
            : ['userid' => (int)$unit->userid, 'onbehalfof' => 0];
    }

    /**
     * The documents waiting for a person's signature: as asked of them, or as the one signing for the unit now.
     *
     * @param int $userid
     * @return int[] document ids
     */
    public static function signable_docids(int $userid): array {
        global $DB;
        if ($userid <= 0) {
            return [];
        }
        $ids = [];
        $rows = $DB->get_records_sql("SELECT a.id, a.docid, a.userid, a.unitid
                                        FROM {local_documents_approval} a
                                        JOIN {local_documents_doc} d ON d.id = a.docid
                                       WHERE a.status = 'pending' AND d.status = 'pending'");
        $units = $rows ? $DB->get_records_list('local_documents_unit', 'id', array_unique(array_column($rows, 'unitid')))
            : [];
        foreach ($rows as $row) {
            $unit = $units[$row->unitid] ?? null;
            if ((int)$row->userid === $userid || ($unit && self::signer_now($unit)['userid'] === $userid)) {
                $ids[(int)$row->docid] = (int)$row->docid;
            }
        }
        return array_values($ids);
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
     * @param int[] $unitids unit ids, -1 for the issuing unit, 0 ignored
     * @return void
     */
    public static function set_signers(int $typeid, array $unitids): void {
        global $DB;
        $DB->delete_records('local_documents_signer', ['typeid' => $typeid]);
        $order = 0;
        // In forms 0 is "no signature" and -1 the issuing unit; stored, the issuing unit is ISSUER (0).
        $slots = [];
        foreach (array_map('intval', $unitids) as $unitid) {
            if ($unitid === -1) {
                $slots[self::ISSUER] = self::ISSUER;
            } else if ($unitid > 0) {
                $slots[$unitid] = $unitid;
            }
        }
        foreach ($slots as $unitid) {
            $DB->insert_record('local_documents_signer', (object)['typeid' => $typeid, 'unitid' => $unitid,
                'sortorder' => $order++]);
        }
    }

    /**
     * Create or update a unit and the types it may issue.
     *
     * A new head, or a substitution starting now, takes over the signatures still waiting on the unit.
     *
     * @param \stdClass $data id (0 for new), code, name, email, phone, userid, signername, signertitle,
     *     substituteid, substitutefrom, substituteuntil, enabled, typeids
     * @return int the unit's id
     */
    public static function save(\stdClass $data): int {
        global $DB, $USER;
        $now = time();
        $record = (object)[
            'code' => \core_text::strtoupper(trim((string)$data->code)),
            'name' => trim((string)$data->name),
            'email' => trim((string)($data->email ?? '')),
            'phone' => trim((string)($data->phone ?? '')),
            'userid' => (int)$data->userid,
            'signername' => trim((string)($data->signername ?? '')),
            'signertitle' => trim((string)($data->signertitle ?? '')),
            'substituteid' => (int)($data->substituteid ?? 0),
            'substitutefrom' => (int)($data->substitutefrom ?? 0),
            'substituteuntil' => (int)($data->substituteuntil ?? 0),
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record('local_documents_unit', $record);
            // The signatures still waiting go to whoever signs for the unit now.
            $signer = self::signer_now($record);
            $moved = $DB->get_fieldset_sql("SELECT DISTINCT a.docid FROM {local_documents_approval} a
                                              JOIN {local_documents_doc} d ON d.id = a.docid
                                             WHERE a.unitid = ? AND a.status = 'pending' AND d.status = 'pending'
                                               AND a.userid <> ?", [$record->id, $signer['userid']]);
            $DB->execute("UPDATE {local_documents_approval} SET userid = ?, onbehalfof = ?
                           WHERE unitid = ? AND status = 'pending'", [$signer['userid'], $signer['onbehalfof'], $record->id]);
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
        // Whoever now signs for the unit hears of the documents waiting for them.
        foreach ($moved ?? [] as $docid) {
            if ($signer['userid'] > 0) {
                notifications::signature_requested((int)$docid, $signer['userid'], (int)$USER->id);
            }
        }
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
     * Open circulars, memorandums and authorisations (the catalogue's `everyunit` types) to every unit,
     * signed by the issuing unit's head, and add the Gerencias: decided 2026-10-28 (anyone who heads a
     * unit issues them). Re-runnable.
     *
     * @return void
     */
    public static function open_letters_to_all(): void {
        global $DB;
        foreach (self::DEFAULT_UNITS as $code => $name) {
            if (!self::by_code($code) && $DB->record_exists('local_documents_unit', [])) {
                self::save((object)['code' => $code, 'name' => $name, 'userid' => 0, 'enabled' => 1]);
            }
        }
        foreach (array_keys(array_filter(catalog::types(), static fn($type) => !empty($type['everyunit']))) as $shortname) {
            $typeid = (int)$DB->get_field('local_documents_type', 'id', ['shortname' => $shortname]);
            if (!$typeid) {
                continue;
            }
            foreach (self::all() as $unit) {
                if (!$DB->record_exists('local_documents_unittype', ['unitid' => $unit->id, 'typeid' => $typeid])) {
                    $DB->insert_record('local_documents_unittype', (object)['unitid' => $unit->id, 'typeid' => $typeid]);
                }
            }
            if (!$DB->record_exists('local_documents_signer', ['typeid' => $typeid])) {
                self::set_signers($typeid, [-1]);
            }
        }
    }

    /**
     * Whether a user heads any unit (and so may have something to sign).
     *
     * @param int $userid
     * @return bool
     */
    public static function is_signer(int $userid): bool {
        global $DB;
        return $userid > 0 && ($DB->record_exists_select('local_documents_unit', 'userid = ? OR substituteid = ?',
            [$userid, $userid]) || $DB->record_exists('local_documents_approval', ['userid' => $userid, 'status' => 'pending']));
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
    public static function install_defaults(string $legacy = '', array $created = []): void {
        global $DB;
        if ($DB->record_exists('local_documents_unit', [])) {
            self::open_letters_to_all();
            // Types the catalogue added since: the signatures it gives them, and the unit that issues them.
            $types = $DB->get_records_menu('local_documents_type', null, '', 'shortname, id');
            $catalog = catalog::types();
            foreach ($created as $shortname) {
                if (!isset($types[$shortname], $catalog[$shortname])) {
                    continue;
                }
                if (empty($catalog[$shortname]['everyunit']) && ($unit = self::by_code('DCE'))
                        && !$DB->record_exists('local_documents_unittype', ['unitid' => $unit->id, 'typeid' => $types[$shortname]])) {
                    $DB->insert_record('local_documents_unittype', (object)['unitid' => $unit->id, 'typeid' => $types[$shortname]]);
                }
                if (!$DB->record_exists('local_documents_signer', ['typeid' => $types[$shortname]])) {
                    self::set_signers((int)$types[$shortname], array_values(array_filter(array_map(
                        static fn($code) => $code === 'ISSUER' ? -1 : (int)(self::by_code($code)->id ?? 0),
                        $catalog[$shortname]['signers'] ?? []))));
                }
            }
            return;
        }
        $lines = trim($legacy) !== '' ? preg_split('/\R/', $legacy)
            : array_map(static fn($code, $name) => "$code | $name", array_keys(self::DEFAULT_UNITS), self::DEFAULT_UNITS);
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
                if ($code === 'ISSUER') {
                    $unitids[] = -1;
                } else if ($unit = self::by_code($code)) {
                    $unitids[] = (int)$unit->id;
                }
            }
            if (!$DB->record_exists('local_documents_signer', ['typeid' => $types[$shortname]])) {
                self::set_signers((int)$types[$shortname], $unitids);
            }
        }
    }
}
