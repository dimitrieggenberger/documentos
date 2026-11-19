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

/**
 * The fonts converted for TCPDF (docs/documents-plan.md 6).
 *
 * @package   local_documents
 * @category  test
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class fonts_test extends \basic_testcase {

    /**
     * The widths of one converted font.
     *
     * @param string $file
     * @return array<int, int>
     */
    private function widths(string $file): array {
        $cw = [];
        include(dirname(__DIR__) . '/fonts/' . $file);
        return $cw;
    }

    public function test_the_space_is_as_wide_in_every_style(): void {
        // TCPDF draws every space of a justified line with one width, taken from the line's first font: a narrower
        // space in another style makes the next word run into it. Converting the fonts again must keep this.
        $regular = $this->widths('poppins.php');
        foreach (['poppinsb.php', 'poppinsi.php'] as $file) {
            $cw = $this->widths($file);
            foreach ([32, 160] as $char) {
                $this->assertSame($regular[$char], $cw[$char], "$file, character $char");
            }
        }
    }
}
