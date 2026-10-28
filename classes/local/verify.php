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
 * The public answer to "is this document genuine?".
 *
 * Asked by anyone who holds the paper: the QR code or the code printed under
 * it is the key, so the answer carries only what is already printed on the
 * sheet (title, number, holder, date, signers' unit) plus its state, with the
 * identity number masked. No grades, no PDF.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verify {

    /** @var int Look-ups one address may make per window. */
    const LIMIT = 30;
    /** @var int The window, in seconds. */
    const WINDOW = 900;

    /**
     * Whether one address may ask once more; counts the request.
     *
     * @param string $client
     * @return bool
     */
    public static function allow(string $client): bool {
        $cache = \cache::make('local_documents', 'verifythrottle');
        $key = sha1($client);
        $now = time();
        $entry = $cache->get($key);
        if (!is_array($entry) || $entry[0] < $now - self::WINDOW) {
            $entry = [$now, 0];
        }
        $entry[1]++;
        $cache->set($key, $entry);
        return $entry[1] <= self::LIMIT;
    }

    /**
     * Look a code up.
     *
     * @param string $input the code as typed or read from the QR
     * @param string $client the asking address, for the document's log
     * @return array{status: string, ...}
     */
    public static function lookup(string $input, string $client = ''): array {
        global $DB;
        $code = numbering::normalize_code($input);
        if ($code === '') {
            return ['status' => 'invalid'];
        }
        $doc = $DB->get_record('local_documents_doc', ['verifycode' => $code]);
        // A consent slip of an authorisation has its own code: it answers for the letter it belongs to.
        $slip = $doc ? null : $DB->get_record('local_documents_slip', ['verifycode' => $code]);
        if ($slip) {
            $doc = $DB->get_record('local_documents_doc', ['id' => $slip->docid]);
        }
        if (!$doc || in_array($doc->status, [documents::STATUS_DRAFT, documents::STATUS_PENDING], true)) {
            return ['status' => 'notfound'];
        }
        $data = documents::data($doc);
        $frozen = $data['frozen'] ?? [];
        $now = time();

        $status = 'valid';
        if ($doc->status === documents::STATUS_RECALLED) {
            $status = 'recalled';
        } else if ($doc->status === documents::STATUS_SUPERSEDED) {
            $status = 'superseded';
        } else if ((int)$doc->validuntil > 0 && $now > (int)$doc->validuntil) {
            $status = 'expired';
        }

        $result = [
            'status' => $status,
            'code' => numbering::format_code($code),
            'title' => (string)$doc->title,
            'number' => numbering::display((string)$doc->number),
            'version' => (int)$doc->docversion,
            'holder' => (string)$doc->holdername,
            'holderid' => self::mask((string)$doc->holderidnumber),
            'subject' => (string)($frozen['values']['f.asunto'] ?? ''),
            'issued' => spanish::date_long((int)$doc->issuedate),
            'issueddate' => date('Y-m-d', (int)$doc->issuedate),
            'validuntil' => (int)$doc->validuntil > 0 ? spanish::date_long((int)$doc->validuntil) : '',
            'school' => (string)($frozen['school']['officialname'] ?? $frozen['school']['name'] ?? config::school()['name']),
            'unit' => (string)($frozen['unit']['name'] ?? $frozen['units'][$doc->unitcode] ?? $doc->unitcode),
            'signers' => array_values(array_filter(array_map(static function($s) {
                $line = trim(($s['name'] ?? '') . ', ' . (!empty($s['substitutefor'])
                    ? get_string_manager()->get_string('verifysubstitute', 'local_documents', $s['substitutefor'], 'es')
                    : ($s['title'] ?? '')), ', ');
                if ($line !== '' && !empty($s['approvedon'])) {
                    $line .= ' (' . get_string_manager()->get_string('verifyapprovedon', 'local_documents',
                        spanish::date_time((int)$s['approvedon']), 'es') . ')';
                }
                return $line;
            }, $frozen['signers'] ?? []))),
            'attachments' => array_map(static fn($a) => ['name' => (string)$a['name'], 'size' => (int)$a['size'],
                'sha256' => (string)$a['sha256']], array_values($frozen['attachments'] ?? [])),
            'sha256' => (string)$doc->contenthash,
            'companions' => self::companions($frozen['companions'] ?? []),
            'signaturemode' => (string)($frozen['signaturemode'] ?? documents::SIGN_ELECTRONIC),
            // Signed by hand: only the printed original with its signature and seal is valid.
            // Electronically signed: the law, and what the platform keeps of each signature.
            'legal' => ($frozen['signaturemode'] ?? '') === documents::SIGN_HANDWRITTEN
                ? get_string_manager()->get_string('verifyhandwritten', 'local_documents', null, 'es')
                : (!empty($frozen['signers']) && !empty($frozen['signaturelaw'])
                ? trim($frozen['signaturelaw'] . ' ' . get_string_manager()->get_string('verifyevidence', 'local_documents',
                    null, 'es')) : ''),
        ];
        if ($slip) {
            // The student's own copy: their name as the slip prints it, and the fingerprint of their PDF.
            $result['slip'] = true;
            $result['holder'] = (string)$slip->holdername;
            $result['holderid'] = self::mask((string)$slip->holderidnumber);
            $result['sha256'] = (string)$slip->pdfhash;
            $result['title'] = get_string_manager()->get_string('verifysliptitle', 'local_documents', $result['title'], 'es');
        } else if ($DB->record_exists('local_documents_slip', ['docid' => $doc->id])) {
            $result['recipients'] = $DB->count_records('local_documents_slip', ['docid' => $doc->id]);
        }
        if ($status === 'recalled') {
            $result['recalled'] = spanish::date_long((int)$doc->timerecalled);
            $result['recallnote'] = (string)$doc->recallnote;
        }
        if ($status === 'superseded' && $doc->supersededby) {
            $newer = $DB->get_record('local_documents_doc', ['id' => $doc->supersededby], 'id, docversion, issuedate');
            if ($newer) {
                $result['replacedby'] = (int)$newer->docversion;
                $result['replacedon'] = spanish::date_long((int)$newer->issuedate);
            }
        }
        documents::log((int)$doc->id, 'verified', 0, \core_text::substr($client, 0, 100));
        return $result;
    }

    /**
     * The documents that go with this one, as its index prints them, each with what has become of it since:
     * recalled, or replaced by a corrected version.
     *
     * @param array[] $companions frozen with the document
     * @return string[]
     */
    private static function companions(array $companions): array {
        global $DB;
        $sm = get_string_manager();
        $lines = [];
        foreach ($companions as $item) {
            $line = (string)($item['line'] ?? '');
            if (!empty($item['docid']) && ($doc = $DB->get_record('local_documents_doc', ['id' => (int)$item['docid']],
                    'id, status'))) {
                if ($doc->status === documents::STATUS_RECALLED) {
                    $line .= ' (' . $sm->get_string('verifycompanionrecalled', 'local_documents', null, 'es') . ')';
                } else if ($doc->status === documents::STATUS_SUPERSEDED) {
                    $line .= ' (' . $sm->get_string('verifycompanionsuperseded', 'local_documents', null, 'es') . ')';
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * "0501-2010-01234" as "••••-••••-•1234": enough to match the paper, not to copy it.
     *
     * @param string $value
     * @return string
     */
    public static function mask(string $value): string {
        $value = trim($value);
        $count = preg_match_all('/[A-Za-z0-9]/', $value);
        if ($count === 0) {
            return '';
        }
        $keep = min(4, max(0, $count - 2));
        $seen = 0;
        $out = '';
        foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (preg_match('/[A-Za-z0-9]/', $char)) {
                $seen++;
                $out .= $seen > $count - $keep ? $char : '•';
            } else {
                $out .= $char;
            }
        }
        return $out;
    }
}
