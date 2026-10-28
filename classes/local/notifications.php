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
 * The messages the signature workflow sends.
 *
 * Each is written in the recipient's language and links to the page where they act.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {

    /**
     * Tell a unit's head that a document waits for their signature.
     *
     * @param int $docid
     * @param int $signerid
     * @param int $senderid
     * @return void
     */
    public static function signature_requested(int $docid, int $signerid, int $senderid): void {
        self::send('signaturerequest', $docid, $signerid, $senderid, 'msgsignrequest',
            new \moodle_url('/local/documents/sign.php', ['id' => $docid]));
    }

    /**
     * Tell the sender that the last signature issued the document.
     *
     * @param int $docid
     * @param int $senderid
     * @return void
     */
    public static function issued(int $docid, int $senderid): void {
        self::send('signatureresult', $docid, $senderid, \core_user::get_noreply_user()->id, 'msgissued',
            new \moodle_url('/local/documents/view.php', ['id' => $docid]));
    }

    /**
     * Tell the sender that a signer sent the document back, and why.
     *
     * @param int $docid
     * @param int $senderid
     * @param int $signerid
     * @param string $note
     * @return void
     */
    public static function rejected(int $docid, int $senderid, int $signerid, string $note): void {
        self::send('signatureresult', $docid, $senderid, $signerid, 'msgrejected',
            new \moodle_url('/local/documents/edit.php', ['id' => $docid]), $note);
    }

    /**
     * Send one message.
     *
     * @param string $provider
     * @param int $docid
     * @param int $toid
     * @param int $fromid
     * @param string $key string key: key_subject and key_body
     * @param \moodle_url $url
     * @param string $note
     * @return void
     */
    private static function send(string $provider, int $docid, int $toid, int $fromid, string $key, \moodle_url $url,
            string $note = ''): void {
        global $DB;
        $to = \core_user::get_user($toid);
        $doc = $DB->get_record('local_documents_doc', ['id' => $docid]);
        if (!$to || !$doc || $to->deleted || $toid === $fromid) {
            return;
        }
        $from = \core_user::get_user($fromid) ?: \core_user::get_noreply_user();
        $a = (object)['title' => templates::plain((string)$doc->title, []), 'holder' => (string)($doc->holdername ?: '—'),
            'number' => (string)$doc->number, 'from' => fullname($from), 'note' => $note, 'url' => $url->out(false)];
        $sm = get_string_manager();
        $lang = !empty($to->lang) ? $to->lang : current_language();
        $message = new \core\message\message();
        $message->component = 'local_documents';
        $message->name = $provider;
        $message->userfrom = $from;
        $message->userto = $to;
        $message->subject = $sm->get_string($key . '_subject', 'local_documents', $a, $lang);
        $message->fullmessage = $sm->get_string($key . '_body', 'local_documents', $a, $lang);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $sm->get_string('opendocument', 'local_documents', null, $lang);
        message_send($message);
    }
}
