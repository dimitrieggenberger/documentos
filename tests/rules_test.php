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

use local_documents\local\numbering;
use local_documents\local\spanish;
use local_documents\local\templates;
use local_documents\local\verify;

/**
 * The pure rules: numbers, codes, Spanish wording, template markup.
 *
 * @package   local_documents
 * @category  test
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_documents\local\numbering
 * @covers    \local_documents\local\spanish
 * @covers    \local_documents\local\templates
 */
final class rules_test extends \advanced_testcase {

    public function test_number_follows_the_instructivo(): void {
        $this->assertSame('CERTRAD-075-050100012M02-2026-DCE-JM',
            numbering::format('certrad', 75, '050100012m02', 2026, 'dce', 'jm'));
        $this->assertSame('OF-1000-180100002B10-2026-DCE-JM', numbering::format('OF', 1000, '180100002B10', 2026, 'DCE', 'JM'));
        $this->assertSame('CERTRAD – 075 – 050100012M02 – 2026 – DCE – JM',
            numbering::display('CERTRAD-075-050100012M02-2026-DCE-JM'));
        $this->assertSame('007', numbering::pad(7));
    }

    public function test_codes_read_back_as_typed(): void {
        $this->assertSame('K7QM3XRT9WPA', numbering::normalize_code('k7qm-3xrt 9wpa'));
        $this->assertSame('K7QM3XRT90PA', numbering::normalize_code('K7QM-3XRT-9OPA'), 'O is read as zero');
        $this->assertSame('', numbering::normalize_code('K7QM-3XRT'), 'too short');
        $this->assertSame('', numbering::normalize_code('K7QM-3XRT-9WPU'), 'U is not in the alphabet');
        $this->assertSame('K7QM-3XRT-9WPA', numbering::format_code('K7QM3XRT9WPA'));
    }

    public function test_spanish_wording(): void {
        $this->assertSame('veintisiete', spanish::number(27));
        $this->assertSame('dos mil veintiséis', spanish::number(2026));
        $this->assertSame('treinta y uno', spanish::number(31));
        $this->assertSame('ciento uno', spanish::number(101));
        $this->assertSame('veintiún mil', spanish::number(21000));
        $this->assertSame('Décimo Grado', spanish::grade_name('10G'));
        $this->assertSame('', spanish::grade_name('G'));
        $this->assertSame('3 años y 2 meses', spanish::years_between(make_timestamp(2023, 8, 1), make_timestamp(2026, 10, 5)));
    }

    public function test_dates_in_words(): void {
        $this->resetAfterTest();
        set_config('timezone', 'America/Tegucigalpa');
        $time = make_timestamp(2026, 10, 27, 12, 0, 0, 'America/Tegucigalpa');
        $this->assertSame('27 de octubre de 2026', spanish::date_long($time));
        $this->assertSame('a los veintisiete días del mes de octubre del año dos mil veintiséis', spanish::date_words($time));
        $this->assertStringStartsWith('al primer día',
            spanish::date_words(make_timestamp(2026, 3, 1, 12, 0, 0, 'America/Tegucigalpa')));
    }

    public function test_markup_escapes_values_and_keeps_its_own_tags(): void {
        $html = templates::render("= **HACE CONSTAR:**\n\nQue {{holder.name}} cursa{{f.x| en |.}}\n\n{{tabla.notas}}",
            ['holder.name' => '<b>Ana</b> & Co', 'f.x' => ''], ['tabla.notas' => '<table></table>']);
        $this->assertStringContainsString('<p style="text-align:center;"><b>HACE CONSTAR:</b></p>', $html);
        $this->assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt; &amp; Co', $html);
        $this->assertStringNotContainsString(' en ', $html, 'an empty conditional prints nothing');
        $this->assertStringContainsString('<table></table>', $html);
        $this->assertSame(['f.y'], templates::missing('{{f.x|a}} {{f.y}} {{tabla.notas}}', [], ['tabla.notas']));
    }

    public function test_fields_specification(): void {
        $fields = templates::parse_fields("conducta | Conducta | select | Muy buena | Excelente;Muy buena | required\n"
            . "# a comment\nasunto | Asunto | text | | required\nBad Key | x");
        $this->assertSame(['conducta', 'asunto'], array_keys($fields));
        $this->assertSame(['Excelente', 'Muy buena'], $fields['conducta']['options']);
        $this->assertTrue($fields['asunto']['required']);
        $this->resetAfterTest();
        $this->assertCount(1, templates::validate_fields("ok | Ok\nBad Key | x"));
    }

    public function test_identity_number_masked(): void {
        $this->assertSame('••••-••••-•1237', verify::mask('0501-2010-01237'));
        $this->assertSame('', verify::mask(''));
    }

    public function test_diff(): void {
        $this->assertSame([[' ', 'a'], ['-', 'b'], ['+', 'c']], templates::diff("a\nb", "a\nc"));
    }
}
