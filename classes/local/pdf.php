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
 * Write a document as a letter-size PDF.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf {

    /**
     * The PDF of a document: a draft preview (marked BORRADOR, no number, no QR) or the final one.
     *
     * @param array $bundle from documents::get()
     * @param bool $final
     * @return string the PDF bytes
     */
    public static function render(array $bundle, bool $final): string {
        $doc = $bundle['doc'];
        $composed = documents::compose($bundle);
        $school = $composed['school'];
        $strings = self::strings($doc, $composed, $final);

        $pdf = new letter_pdf('P', 'mm', 'LETTER', true, 'UTF-8');
        $pdf->sheet = [
            'final' => $final,
            'school' => $school,
            'images' => self::images(),
            'verifyurl' => config::verify_url((string)$doc->verifycode),
            'strings' => $strings,
        ];
        $pdf->SetCreator('local_documents');
        $pdf->SetAuthor($school['name']);
        $pdf->SetTitle(trim($doc->title . ' ' . ($final ? $doc->number : '')));
        $pdf->SetSubject($doc->holdername ?? '');
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(letter_pdf::MARGIN_X, letter_pdf::BODY_TOP, letter_pdf::MARGIN_X);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(letter_pdf::FOOTER_H);
        $pdf->SetAutoPageBreak(true, letter_pdf::FOOTER_H);
        $pdf->setCellHeightRatio(1.45);
        $pdf->AddPage();

        $textwidth = letter_pdf::PAGE_W - 2 * letter_pdf::MARGIN_X;
        // Number block, top right, as the instructivo shows it on the sheet.
        $pdf->SetFont(letter_pdf::FONT, 'B', 10);
        $pdf->SetTextColor(22, 33, 44);
        $pdf->MultiCell($textwidth, 5, $final ? numbering::display((string)$doc->number) : $strings['nonumber'],
            0, 'R', false, 1);
        $pdf->SetFont(letter_pdf::FONT, '', 9);
        $pdf->MultiCell($textwidth, 4.5, \core_text::strtoupper($school['name']), 0, 'R', false, 1);
        if ((int)$doc->docversion > 1) {
            $pdf->SetFont(letter_pdf::FONT, 'I', 8.5);
            $pdf->SetTextColor(107, 114, 128);
            $pdf->MultiCell($textwidth, 4.5, $strings['versionline'], 0, 'R', false, 1);
            $pdf->SetTextColor(22, 33, 44);
        }

        if ($bundle['type']->subject === 'none') {
            $pdf->Ln(4);
            $pdf->SetFont(letter_pdf::FONT, '', 10.5);
            $pdf->MultiCell($textwidth, 5, $school['city'] . ', ' . $composed['values']['date.long'], 0, 'R', false, 1);
        }

        $pdf->Ln(8);
        $pdf->SetFont(letter_pdf::FONT, 'B', 15);
        $pdf->MultiCell($textwidth, 7, $doc->title, 0, 'C', false, 1);
        $pdf->Ln(6);

        $pdf->SetFont(letter_pdf::FONT, '', 11.5);
        $pdf->writeHTML($composed['bodyhtml'], true, false, true, false, '');

        self::signatures($pdf, $composed['signers'], $textwidth);

        return $pdf->Output('', 'S');
    }

    /**
     * The signature blocks, side by side when there are two.
     *
     * @param letter_pdf $pdf
     * @param array $signers
     * @param float $textwidth
     * @return void
     */
    private static function signatures(letter_pdf $pdf, array $signers, float $textwidth): void {
        // Room to sign above the line, then name and title: kept together on one page.
        $gap = 20;
        if ($pdf->GetY() + $gap + 14 > letter_pdf::PAGE_H - letter_pdf::FOOTER_H) {
            $pdf->AddPage();
        }
        $y = $pdf->GetY() + $gap;
        $count = max(1, count($signers));
        $column = $textwidth / $count;
        $linewidth = min(70, $column - 10);
        foreach (array_values($signers) as $i => $signer) {
            $x = letter_pdf::MARGIN_X + $i * $column + ($column - $linewidth) / 2;
            $pdf->SetDrawColor(22, 33, 44);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($x, $y, $x + $linewidth, $y);
            $pdf->SetXY($x - 5, $y + 1.5);
            $pdf->SetFont(letter_pdf::FONT, 'B', 10.5);
            $pdf->MultiCell($linewidth + 10, 5, (string)$signer['name'], 0, 'C', false, 1, $x - 5);
            $pdf->SetFont(letter_pdf::FONT, '', 9.5);
            $pdf->MultiCell($linewidth + 10, 4.5, (string)$signer['title'], 0, 'C', false, 1, $x - 5);
        }
    }

    /**
     * The footer's wording, in Spanish like the document.
     *
     * @param \stdClass $doc
     * @param array $composed
     * @param bool $final
     * @return array
     */
    private static function strings(\stdClass $doc, array $composed, bool $final): array {
        $code = numbering::format_code((string)$doc->verifycode);
        $sm = get_string_manager();
        $lines = [];
        if ($final) {
            $lines[] = $sm->get_string('pdfcode', 'local_documents', $code, 'es');
            $lines[] = $sm->get_string('pdfverify', 'local_documents', config::verify_label(), 'es');
            if ((int)$doc->validuntil > 0) {
                $lines[] = $sm->get_string('pdfvaliduntil', 'local_documents', spanish::date_long((int)$doc->validuntil),
                    'es');
            }
        } else {
            $lines[] = $sm->get_string('pdfdraftnote', 'local_documents', null, 'es');
        }
        $previous = (int)($composed['previousdate'] ?? 0);
        return [
            'draft' => $sm->get_string('pdfdraft', 'local_documents', null, 'es'),
            'nonumber' => $sm->get_string('pdfnonumber', 'local_documents', null, 'es'),
            'numberline' => $final ? $sm->get_string('pdfnumber', 'local_documents', numbering::display((string)$doc->number),
                'es') : $sm->get_string('pdfnonumber', 'local_documents', null, 'es'),
            'footerlines' => $lines,
            'page' => $sm->get_string('pdfpage', 'local_documents', null, 'es'),
            'versionline' => $sm->get_string('pdfversion', 'local_documents', (object)['version' => (int)$doc->docversion,
                'date' => $previous > 0 ? spanish::date_long($previous) : '—'], 'es'),
        ];
    }

    /**
     * The letterhead images from the settings, as local paths.
     *
     * @return array<string, string>
     */
    private static function images(): array {
        global $CFG;
        $fs = get_file_storage();
        $context = \context_system::instance();
        $images = [];
        foreach (['logo', 'logo2', 'watermark'] as $area) {
            $files = $fs->get_area_files($context->id, 'local_documents', $area, 0, 'sortorder, id', false);
            $file = $files ? reset($files) : null;
            if (!$file || !in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                continue;
            }
            $path = make_request_directory() . '/' . $area . ($file->get_mimetype() === 'image/png' ? '.png' : '.jpg');
            $file->copy_content_to($path);
            $images[$area] = $path;
        }
        return $images;
    }
}
