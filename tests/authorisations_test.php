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
use local_documents\local\slips;
use local_documents\local\units;
use local_documents\local\verify;

/**
 * Authorisations for families: a letter and a consent slip per student (docs/documents-plan.md 15).
 *
 * @package   local_documents
 * @category  test
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_documents\local\slips
 */
final class authorisations_test extends \advanced_testcase {

    /** @var \stdClass the head of DCE, who issues */
    private $director;

    /**
     * A school whose director issues, and a type id by short name.
     *
     * @param string $shortname
     * @return int
     */
    private function setup_type(string $shortname): int {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('sacecode', '050100001M01', 'local_documents');
        $this->director = $this->getDataGenerator()->create_user();
        $DB->set_field('local_documents_unit', 'userid', $this->director->id, ['code' => 'DCE']);
        $this->setUser($this->director);
        return (int)$DB->get_field('local_documents_type', 'id', ['shortname' => $shortname]);
    }

    /**
     * A filled-in excursion draft.
     *
     * @param int $typeid
     * @return int
     */
    private function excursion(int $typeid): int {
        $id = documents::create_draft($typeid, 0, (int)$this->director->id);
        documents::update_draft($id, ['fields' => ['actividad' => 'Visita al Museo', 'lugar' => 'Museo de Antropología',
            'fecha' => (string)mktime(12, 0, 0, 11, 20, 2026), 'horario' => 'de 7:30 a 13:00',
            'transporte' => 'bus del centro', 'devolver_a' => 'su docente guía',
            'devolver_antes' => (string)mktime(12, 0, 0, 11, 14, 2026)]], (int)$this->director->id);
        return $id;
    }

    public function test_the_authorisations_are_issued_by_every_unit_and_have_a_slip(): void {
        global $DB;
        $this->setup_type('autorizacion_salida');
        foreach (['autorizacion_salida', 'autorizacion_imagen', 'autorizacion_salud', 'autorizacion_expediente',
                'autorizacion_extracurricular'] as $shortname) {
            $type = $DB->get_record('local_documents_type', ['shortname' => $shortname], '*', MUST_EXIST);
            $this->assertSame('AUTOR', $DB->get_field('local_documents_series', 'code', ['id' => $type->seriesid]));
            $this->assertCount(5, units::for_type((int)$type->id), $shortname . ': every unit');
            $this->assertSame([units::ISSUER], units::signer_slots((int)$type->id));
            $this->assertTrue(slips::enabled($DB->get_record('local_documents_tplver', ['id' => $type->currentversion])));
        }
    }

    public function test_a_group_authorisation_gives_each_student_a_slip_a_code_and_a_pdf(): void {
        global $DB;
        $typeid = $this->setup_type('autorizacion_salida');
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $students = [];
        foreach (['Ana', 'Beto', 'Carla'] as $i => $name) {
            $students[$i] = $this->getDataGenerator()->create_user(['firstname' => $name, 'lastname' => 'Prueba']);
            $this->getDataGenerator()->enrol_user($students[$i]->id, $course->id, 'student');
        }
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $students[0]->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $students[1]->id]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        $id = $this->excursion($typeid);
        $this->assertNotEmpty(array_filter(documents::problems(documents::get($id)),
            static fn($p) => str_contains($p, get_string('needsrecipients', 'local_documents'))), 'nobody yet');
        $this->assertSame(2, slips::add_pick($id, 'g:' . $group->id), 'the group, not the teacher');
        $this->assertSame(1, slips::add_pick($id, 'c:' . $course->id), 'the rest of the course, once each');
        $this->assertCount(3, slips::list($id));

        $this->assertTrue(documents::submit($id, (int)$this->director->id), 'the head of the issuing unit issues alone');
        $doc = documents::get($id)['doc'];
        $this->assertStringContainsString('AUTOR-001-', $doc->number);
        $content = documents::file($doc)->get_content();
        $this->assertSame(3, preg_match_all('#/Type\s*/Page[^s]#', $content), 'a letter and slip per student');

        foreach (slips::list($id) as $slip) {
            $pdf = slips::pdf($slip);
            $this->assertNotNull($pdf);
            $this->assertSame(hash('sha256', $pdf->get_content()), $slip->pdfhash);
            $answer = verify::lookup($slip->verifycode);
            $this->assertSame('valid', $answer['status']);
            $this->assertTrue($answer['slip']);
            $this->assertSame($slip->holdername, $answer['holder']);
            $this->assertSame($slip->pdfhash, $answer['sha256']);
            $this->assertStringContainsString('Colilla de autorización', $answer['title']);
        }
        $this->assertSame(3, verify::lookup($doc->verifycode)['recipients']);
    }

    public function test_answers_are_recorded_once_issued_and_counted(): void {
        $typeid = $this->setup_type('autorizacion_salida');
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $id = $this->excursion($typeid);
        slips::add_student($id, (int)$a->id);
        slips::add_student($id, (int)$b->id);
        $first = slips::list($id)[0];
        try {
            slips::respond((int)$first->id, slips::YES, time(), '', (int)$this->director->id);
            $this->fail('no answer before the letter is issued');
        } catch (\moodle_exception $e) {
            $this->assertSame('notissued', $e->errorcode);
        }
        documents::submit($id, (int)$this->director->id);
        slips::respond((int)$first->id, slips::YES, mktime(12, 0, 0, 11, 10, 2026), 'Alergia al maní.',
            (int)$this->director->id);
        $this->assertSame([slips::YES => 1, slips::NO => 0, slips::PENDING => 1], slips::counts(slips::list($id)));
        $this->assertArrayNotHasKey('response', verify::lookup($first->verifycode), 'the answer is never public');
    }

    public function test_a_correction_asks_the_same_students_again(): void {
        $typeid = $this->setup_type('autorizacion_salida');
        $a = $this->getDataGenerator()->create_user();
        $id = $this->excursion($typeid);
        slips::add_student($id, (int)$a->id);
        documents::submit($id, (int)$this->director->id);
        $old = slips::list($id)[0];
        slips::respond((int)$old->id, slips::NO, time(), '', (int)$this->director->id);
        $newid = documents::revise($id, (int)$this->director->id);
        $new = slips::list($newid)[0];
        $this->assertSame((int)$a->id, (int)$new->userid);
        $this->assertNotSame($old->verifycode, $new->verifycode);
        $this->assertSame(slips::PENDING, $new->response);
    }

    public function test_sharing_the_file_is_asked_for_one_student(): void {
        $typeid = $this->setup_type('autorizacion_expediente');
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Prueba']);
        $id = documents::create_draft($typeid, (int)$student->id, (int)$this->director->id);
        $list = slips::list($id);
        $this->assertCount(1, $list, 'the student\'s own slip, with the draft');
        $this->assertSame((int)$student->id, (int)$list[0]->userid);
    }
}
