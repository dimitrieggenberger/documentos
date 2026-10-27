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
        foreach (['schoolname' => 'Escuela de Prueba', 'sacecode' => '050100001M01', 'jornada' => 'JM',
                'signatories' => 'Ana Uno | Directora'] as $name => $value) {
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
}
