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

namespace local_documents;

use local_documents\local\documents;
use local_documents\local\verify;

/**
 * The life of a document against the database: numbering, versions, recall.
 *
 * @package   local_documents
 * @category  test
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_documents\local\documents
 */
final class documents_test extends \advanced_testcase {

    /**
     * A school set up, and a letter type to issue (no person needed).
     *
     * @return int the type id
     */
    private function setup_school(): int {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        foreach (['schoolname' => 'Escuela de Prueba', 'sacecode' => '050100001M01', 'jornada' => 'JM'] as $name => $value) {
            set_config($name, $value, 'local_documents');
        }
        return (int)$DB->get_field('local_documents_type', 'id', ['shortname' => 'memorandum']);
    }

    /**
     * A filled-in memorandum draft.
     *
     * @param int $typeid
     * @return int
     */
    private function draft(int $typeid): int {
        global $USER;
        $id = documents::create_draft($typeid, 0, (int)$USER->id);
        documents::update_draft($id, ['fields' => ['destinatario' => 'Personal docente', 'de' => 'Dirección',
            'asunto' => 'Reunión', 'texto' => 'Reunión el lunes.']], (int)$USER->id);
        return $id;
    }

    public function test_numbers_run_per_series_and_year(): void {
        global $USER;
        $typeid = $this->setup_school();
        $a = $this->draft($typeid);
        $b = $this->draft($typeid);
        documents::issue($a, (int)$USER->id);
        documents::issue($b, (int)$USER->id);
        $year = (int)date('Y');
        $this->assertSame("MM-001-050100001M01-$year-DCE-JM", documents::get($a)['doc']->number);
        $this->assertSame("MM-002-050100001M01-$year-DCE-JM", documents::get($b)['doc']->number);
        $doc = documents::get($a)['doc'];
        $this->assertSame(hash('sha256', documents::file($doc)->get_content()), $doc->contenthash);
    }

    public function test_draft_with_missing_values_is_not_issued(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = documents::create_draft($typeid, 0, (int)$USER->id);
        $this->expectException(\moodle_exception::class);
        documents::issue($id, (int)$USER->id);
    }

    public function test_correction_keeps_the_number_and_supersedes(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = $this->draft($typeid);
        documents::issue($id, (int)$USER->id);
        $first = documents::get($id)['doc'];
        $second = documents::revise($id, (int)$USER->id);
        $this->assertSame('valid', verify::lookup($first->verifycode)['status'], 'valid until the correction is issued');
        documents::issue($second, (int)$USER->id);
        $this->assertSame($first->number, documents::get($second)['doc']->number);
        $this->assertSame(2, (int)documents::get($second)['doc']->docversion);
        $answer = verify::lookup($first->verifycode);
        $this->assertSame('superseded', $answer['status']);
        $this->assertSame(2, $answer['replacedby']);
    }

    public function test_recall_is_public_and_the_number_is_not_reused(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = $this->draft($typeid);
        documents::issue($id, (int)$USER->id);
        documents::recall($id, 'Error', 'Anulado.', (int)$USER->id);
        $doc = documents::get($id)['doc'];
        $answer = verify::lookup($doc->verifycode);
        $this->assertSame('recalled', $answer['status']);
        $this->assertSame('Anulado.', $answer['recallnote']);
        $next = $this->draft($typeid);
        documents::issue($next, (int)$USER->id);
        $this->assertSame(2, (int)documents::get($next)['doc']->correlative);
    }

    public function test_a_draft_is_never_verified(): void {
        global $USER;
        $typeid = $this->setup_school();
        $doc = documents::get($this->draft($typeid))['doc'];
        $this->assertSame('notfound', verify::lookup($doc->verifycode)['status']);
    }

    public function test_counter_cannot_go_below_an_issued_number(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = $this->draft($typeid);
        documents::issue($id, (int)$USER->id);
        $doc = documents::get($id)['doc'];
        \local_documents\local\numbering::set_last((int)$doc->seriesid, (int)$doc->year, 'DCE', 40);
        $next = $this->draft($typeid);
        documents::issue($next, (int)$USER->id);
        $this->assertSame(41, (int)documents::get($next)['doc']->correlative);
        $this->expectException(\moodle_exception::class);
        \local_documents\local\numbering::set_last((int)$doc->seriesid, (int)$doc->year, 'DCE', 0);
    }

    /**
     * An oficio draft (signed by DCE), filled in.
     *
     * @param int $creatorid
     * @return int
     */
    private function oficio(int $creatorid): int {
        global $DB;
        $typeid = (int)$DB->get_field('local_documents_type', 'id', ['shortname' => 'oficio']);
        $id = documents::create_draft($typeid, 0, $creatorid);
        documents::update_draft($id, ['fields' => ['destinatario' => 'Sra. Prueba', 'asunto' => 'Prueba',
            'texto' => 'Texto.']], $creatorid);
        return $id;
    }

    /**
     * Link a unit to a head.
     *
     * @param string $code
     * @param int $userid
     * @return void
     */
    private function head(string $code, int $userid): void {
        global $DB;
        $unit = \local_documents\local\units::by_code($code);
        $DB->set_field('local_documents_unit', 'userid', $userid, ['id' => $unit->id]);
    }

    public function test_a_document_without_signatures_is_issued_at_once(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = $this->draft($typeid);
        $this->assertTrue(documents::submit($id, (int)$USER->id));
        $this->assertSame(documents::STATUS_ISSUED, documents::get($id)['doc']->status);
    }

    public function test_the_signer_approves_and_the_last_signature_issues(): void {
        $this->setup_school();
        $secretary = $this->getDataGenerator()->create_user();
        $director = $this->getDataGenerator()->create_user();
        $this->head('DCE', (int)$director->id);
        $sink = $this->redirectMessages();
        $id = $this->oficio((int)$secretary->id);
        $this->assertFalse(documents::submit($id, (int)$secretary->id));
        $this->assertSame(documents::STATUS_PENDING, documents::get($id)['doc']->status);
        $this->assertSame('', (string)documents::get($id)['doc']->number, 'no number before the last signature');
        $this->assertCount(1, $sink->get_messages(), 'the director is asked');
        try {
            documents::approve($id, (int)$secretary->id);
            $this->fail('only the head signs');
        } catch (\moodle_exception $e) {
            $this->assertSame('nothingtosign', $e->errorcode);
        }
        $this->assertTrue(documents::approve($id, (int)$director->id));
        $doc = documents::get($id)['doc'];
        $this->assertSame(documents::STATUS_ISSUED, $doc->status);
        $this->assertSame((int)$secretary->id, (int)$doc->issuedby);
        $frozen = documents::data($doc)['frozen'];
        $this->assertGreaterThan(0, $frozen['signers'][0]['approvedon']);
        $this->assertStringContainsString('aprobado el', verify::lookup($doc->verifycode)['signers'][0]);
    }

    public function test_the_sole_signer_issues_alone(): void {
        $this->setup_school();
        $director = $this->getDataGenerator()->create_user();
        $this->head('DCE', (int)$director->id);
        $id = $this->oficio((int)$director->id);
        $this->assertTrue(documents::submit($id, (int)$director->id));
        $this->assertSame(documents::STATUS_ISSUED, documents::get($id)['doc']->status);
    }

    public function test_sent_back_opens_the_draft_again(): void {
        $this->setup_school();
        $secretary = $this->getDataGenerator()->create_user();
        $director = $this->getDataGenerator()->create_user();
        $this->head('DCE', (int)$director->id);
        $this->redirectMessages();
        $id = $this->oficio((int)$secretary->id);
        documents::submit($id, (int)$secretary->id);
        documents::reject($id, (int)$director->id, 'Falta el saludo.');
        $this->assertSame(documents::STATUS_DRAFT, documents::get($id)['doc']->status);
        documents::update_draft($id, ['fields' => ['saludo' => 'Estimada señora:']], (int)$secretary->id);
        $this->assertFalse(documents::submit($id, (int)$secretary->id), 'asked again');
    }

    public function test_a_unit_without_head_blocks_sending(): void {
        $this->setup_school();
        $this->head('DCE', 0);
        $secretary = $this->getDataGenerator()->create_user();
        $id = $this->oficio((int)$secretary->id);
        $this->expectException(\moodle_exception::class);
        documents::submit($id, (int)$secretary->id);
    }

    public function test_attachments_are_part_of_the_verifiable_document(): void {
        global $USER;
        $typeid = $this->setup_school();
        $id = $this->draft($typeid);
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_documents', 'filearea' => documents::ATTACHMENTS, 'itemid' => $id, 'filepath' => '/',
            'filename' => 'lista.png'], 'contenido de prueba');
        documents::submit($id, (int)$USER->id);
        $doc = documents::get($id)['doc'];
        $answer = verify::lookup($doc->verifycode);
        $this->assertSame('lista.png', $answer['attachments'][0]['name']);
        $this->assertSame(hash('sha256', 'contenido de prueba'), $answer['attachments'][0]['sha256']);
        $pdf = documents::file($doc)->get_content();
        $this->assertStringContainsString('/EmbeddedFile', $pdf, 'the attachment travels inside the PDF');
    }
}
