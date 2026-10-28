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
 * Write a document as a letter-size PDF, in the order of the brand manual's
 * letter: header, place and date (with the number on the right), recipient,
 * title, body, signature block with the stamp, the attachments' index, footer.
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
        $values = $composed['values'];
        $strings = self::strings($doc, $composed, $final);

        $pdf = new letter_pdf('P', 'pt', 'LETTER', true, 'UTF-8');
        $pdf->add_brand_fonts();
        $pdf->sheet = [
            'final' => $final,
            'school' => $school,
            'unit' => $composed['unit'] ?? config::unit((string)$doc->unitcode),
            'images' => self::images(),
            'verifyurl' => config::verify_url((string)$doc->verifycode),
            'strings' => $strings,
        ];
        $title = templates::plain((string)$doc->title, $values);
        $pdf->SetCreator('local_documents');
        $pdf->SetAuthor($school['officialname'] ?? $school['name']);
        $pdf->SetTitle(trim($title . ' ' . ($final ? $doc->number : '')));
        $pdf->SetSubject((string)($doc->holdername ?? ''));
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(letter_pdf::MARGIN_X, letter_pdf::body_top(), letter_pdf::MARGIN_X);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(letter_pdf::FOOTER_H);
        $pdf->SetAutoPageBreak(true, letter_pdf::MARGIN_Y + letter_pdf::FOOTER_H);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellHeightRatio(1.25);
        $pdf->setHtmlVSpace([
            'p' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => letter_pdf::PARAGRAPH, 'n' => 1]],
            'table' => [0 => ['h' => 0, 'n' => 0], 1 => ['h' => letter_pdf::PARAGRAPH, 'n' => 1]],
        ]);
        $pdf->AddPage();

        $width = letter_pdf::text_width();
        $left = letter_pdf::MARGIN_X;
        $y = letter_pdf::MARGIN_Y + letter_pdf::SEAL + letter_pdf::AFTER_HEADER;

        // Place and date on the left (letters), the number on the right, as the instructivo prints it.
        $pdf->SetTextColor(...letter_pdf::TEXT);
        if ($bundle['type']->subject === 'none') {
            $pdf->SetFont(letter_pdf::FONT, '', 8);
            $pdf->SetXY($left, $y);
            $pdf->Cell($width / 2, 10, $school['city'] . ', ' . $values['date.long'], 0, 0, 'L');
        }
        $pdf->SetFont(letter_pdf::FONT, 'B', 8);
        $pdf->SetXY($left + $width / 2, $y);
        $pdf->Cell($width / 2, 10, $final ? numbering::display((string)$doc->number) : $strings['nonumber'], 0, 2, 'R',
            false, '', 1);
        $pdf->SetTextColor(...letter_pdf::MUTED);
        $pdf->SetFont(letter_pdf::FONT, '', 6);
        $pdf->Cell($width / 2, 7.5, \core_text::strtoupper($school['officialname'] ?? $school['name']), 0, 2, 'R', false,
            '', 1);
        if ((int)$doc->docversion > 1) {
            $pdf->SetFont(letter_pdf::FONT, 'I', 6);
            $pdf->Cell($width / 2, 7.5, $strings['versionline'], 0, 2, 'R', false, '', 1);
        }
        $y += 10;

        // The recipient: full name in bold, then position, institution, e-mail.
        $recipient = array_values(array_filter([$values['f.cargo'] ?? '', $values['f.institucion'] ?? '',
            $values['f.correo'] ?? ''], static fn($line) => trim((string)$line) !== ''));
        $hasrecipient = $bundle['type']->subject === 'none' && trim((string)($values['f.destinatario'] ?? '')) !== '';
        if ($hasrecipient) {
            $y += letter_pdf::AFTER_DATE;
            $pdf->SetTextColor(...letter_pdf::TEXT);
            $pdf->SetFont(letter_pdf::FONT, 'B', 8);
            $pdf->SetXY($left, $y);
            $pdf->MultiCell($width, 10, (string)$values['f.destinatario'], 0, 'L', false, 1);
            $pdf->SetFont(letter_pdf::FONT, '', 8);
            foreach ($recipient as $line) {
                $pdf->MultiCell($width, 10, (string)$line, 0, 'L', false, 1, $left);
            }
            $y = $pdf->GetY() + letter_pdf::AFTER_RECIPIENT;
        } else {
            $y = max($y + 7.5 * ((int)$doc->docversion > 1 ? 2 : 1), $pdf->GetY()) + letter_pdf::BEFORE_TITLE;
        }

        // Title (H1, Poppins Bold 14 pt).
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetFont(letter_pdf::FONT, 'B', 14);
        $pdf->setCellHeightRatio(17 / 14);
        $pdf->SetXY($left, $y);
        $pdf->MultiCell($width, 17, $title, 0, 'L', false, 1);
        $pdf->setCellHeightRatio(1.25);
        $pdf->SetY($pdf->GetY() + letter_pdf::AFTER_TITLE);

        // Body (8 pt on 10, justified paragraphs 10 pt apart).
        $pdf->SetFont(letter_pdf::FONT, '', 8);
        $pdf->writeHTML($composed['bodyhtml'], true, false, true, false, '');

        self::signatures($pdf, $composed['signers'], !empty($pdf->sheet['images']['stamp']) && $final
            ? $pdf->sheet['images']['stamp'] : '');

        self::attachments($pdf, $bundle['doc'], $composed['attachments'] ?? [], $strings, $final);

        return $pdf->Output('', 'S');
    }

    /**
     * The signature block: per signer the room to sign, name in bold, position, e-mail and
     * phone; the stamp beside them, centred on the block.
     *
     * @param letter_pdf $pdf
     * @param array $signers
     * @param string $stamp path of the stamp image, '' for none
     * @return void
     */
    private static function signatures(letter_pdf $pdf, array $signers, string $stamp): void {
        $signers = array_values($signers);
        if (!$signers) {
            // A document issued without signatures: no block, no stamp.
            return;
        }
        $sm = get_string_manager();
        $height = letter_pdf::SIGN_H + 20 + 8 + 20;
        $height = max($height, $stamp !== '' ? letter_pdf::STAMP : 0);
        $top = $pdf->GetY() + letter_pdf::BEFORE_SIGNATURE;
        if ($top + $height > letter_pdf::PAGE_H - letter_pdf::MARGIN_Y - letter_pdf::FOOTER_H) {
            $pdf->AddPage();
            $top = letter_pdf::body_top();
        }
        $x = letter_pdf::MARGIN_X;
        $bottom = $top;
        foreach ($signers as $signer) {
            // The room to sign by hand carries the digital approval, at the foot of it.
            $approved = (int)($signer['approvedon'] ?? 0);
            $pdf->SetTextColor(...letter_pdf::MUTED);
            $pdf->SetFont(letter_pdf::FONT, 'I', 6);
            $pdf->SetXY($x, $top + letter_pdf::SIGN_H - 9);
            $pdf->Cell(letter_pdf::SIGNER_W, 7.5, $approved > 0
                ? $sm->get_string('pdfapproved', 'local_documents', spanish::date_long($approved), 'es')
                : $sm->get_string('pdfsignpending', 'local_documents', null, 'es'), 0, 0, 'L', false, '', 1);
            $y = $top + letter_pdf::SIGN_H;
            $pdf->SetXY($x, $y);
            $pdf->SetTextColor(...letter_pdf::TEXT);
            $pdf->SetFont(letter_pdf::FONT, 'B', 8);
            $pdf->Cell(letter_pdf::SIGNER_W, 10, (string)$signer['name'], 0, 2, 'L', false, '', 1);
            $pdf->SetTextColor(...letter_pdf::MUTED);
            $pdf->SetFont(letter_pdf::FONT, '', 8);
            if (!empty($signer['substitutefor'])) {
                // A substitute: "En sustitución de funciones de Lic. Rosa Gómez, Directora", wrapped in the column.
                $pdf->MultiCell(letter_pdf::SIGNER_W, 10, $sm->get_string('pdfsubstitute', 'local_documents',
                    $signer['substitutefor'], 'es'), 0, 'L', false, 1, $x);
            } else if (trim((string)$signer['title']) !== '') {
                $pdf->Cell(letter_pdf::SIGNER_W, 10, (string)$signer['title'], 0, 2, 'L', false, '', 1);
            }
            $contact = array_values(array_filter([(string)($signer['email'] ?? ''), (string)($signer['phone'] ?? '')],
                static fn($line) => trim($line) !== ''));
            if ($contact) {
                $pdf->SetY($pdf->GetY() + 8);
                foreach ($contact as $line) {
                    $pdf->SetX($x);
                    $pdf->Cell(letter_pdf::SIGNER_W, 10, $line, 0, 2, 'L', false, '', 1);
                }
            }
            $bottom = max($bottom, $pdf->GetY());
            $x += letter_pdf::SIGNER_W + letter_pdf::SIGNER_GAP;
        }
        if ($stamp !== '') {
            $middle = ($top + $bottom) / 2;
            $pdf->Image($stamp, $x, $middle - letter_pdf::STAMP / 2, letter_pdf::STAMP, letter_pdf::STAMP, '', '', '', false,
                300, '', false, false, 0, false, false, false);
            $bottom = max($bottom, $middle + letter_pdf::STAMP / 2);
        }
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetXY(letter_pdf::MARGIN_X, $bottom);
    }

    /**
     * The attachments' index at the foot of the document, each file embedded in the PDF.
     *
     * The list carries every file's name, size and SHA-256, so the document
     * vouches for its attachments: the verification page shows the same hashes,
     * and the files travel inside the PDF (the paper clip opens them).
     *
     * @param letter_pdf $pdf
     * @param \stdClass $doc
     * @param array $attachments from documents::attachments()
     * @param array $strings
     * @param bool $final
     * @return void
     */
    private static function attachments(letter_pdf $pdf, \stdClass $doc, array $attachments, array $strings,
            bool $final): void {
        if (!$attachments) {
            return;
        }
        $left = letter_pdf::MARGIN_X;
        $width = letter_pdf::text_width();
        // The frame's 36 pt before the index, tightened to 12 pt rather than leaving it alone on a page.
        $needed = 10 + 20 * count($attachments);
        $limit = letter_pdf::PAGE_H - letter_pdf::MARGIN_Y - letter_pdf::FOOTER_H;
        if ($pdf->GetY() + letter_pdf::BEFORE_ATTACHMENT + $needed <= $limit) {
            $pdf->SetY($pdf->GetY() + letter_pdf::BEFORE_ATTACHMENT);
        } else if ($pdf->GetY() + 12 + $needed <= $limit) {
            $pdf->SetY($pdf->GetY() + 12);
        } else {
            $pdf->AddPage();
        }
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetFont(letter_pdf::FONT, 'B', 8);
        $pdf->MultiCell($width, 10, $strings['attachments'], 0, 'L', false, 1, $left);
        $dir = make_request_directory();
        foreach (array_values($attachments) as $i => $attachment) {
            $y = $pdf->GetY() + 2;
            $pdf->SetXY($left, $y);
            $pdf->SetTextColor(...letter_pdf::TEXT);
            $pdf->SetFont(letter_pdf::FONT, '', 8);
            $pdf->Cell($width - 14, 10, ($i + 1) . '. ' . $attachment['name'] . ' · ' . display_size($attachment['size']),
                0, 2, 'L', false, '', 1);
            $pdf->SetTextColor(...letter_pdf::MUTED);
            $pdf->SetFont(letter_pdf::FONT, '', 6);
            $pdf->Cell($width - 14, 7.5, 'SHA-256 ' . $attachment['sha256'], 0, 2, 'L', false, '', 1);
            $file = documents::attachment_file($doc, (int)$attachment['fileid']);
            if ($final && $file) {
                // One folder per attachment, so the embedded file keeps its own name.
                check_dir_exists($dir . '/' . ($i + 1));
                $path = $dir . '/' . ($i + 1) . '/' . clean_filename($attachment['name']);
                $file->copy_content_to($path);
                $pdf->Annotation($left + $width - 10, $y + 1, 8, 10, $attachment['name'],
                    ['Subtype' => 'FileAttachment', 'Name' => 'Paperclip', 'FS' => $path]);
            }
        }
        $pdf->SetTextColor(...letter_pdf::TEXT);
    }

    /**
     * The sheet's own wording, in Spanish like the document.
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
            $lines[] = $sm->get_string('pdfnumber', 'local_documents', numbering::display((string)$doc->number), 'es');
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
            'draft' => $sm->get_string($doc->status === documents::STATUS_PENDING ? 'pdfpending' : 'pdfdraft',
                'local_documents', null, 'es'),
            'nonumber' => $sm->get_string('pdfnonumber', 'local_documents', null, 'es'),
            'footerlines' => $lines,
            'page' => $sm->get_string('pdfpage', 'local_documents', null, 'es'),
            'attachments' => $sm->get_string('pdfattachments', 'local_documents', null, 'es'),
            'versionline' => $sm->get_string('pdfversion', 'local_documents', (object)['version' => (int)$doc->docversion,
                'date' => $previous > 0 ? spanish::date_long($previous) : '—'], 'es'),
        ];
    }

    /**
     * The brand images: from the settings when uploaded, else the ones shipped in pix/brand.
     *
     * @return array<string, string> seal, watermark, stamp, url => local path
     */
    public static function images(): array {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $shipped = dirname(__DIR__, 2) . '/pix/brand/';
        $defaults = ['seal' => 'seal-documents.png', 'watermark' => 'watermark.png', 'stamp' => 'stamp.png',
            'url' => 'url.png'];
        $images = [];
        foreach (config::IMAGE_AREAS as $key => $area) {
            $files = $fs->get_area_files($context->id, 'local_documents', $area, 0, 'sortorder, id', false);
            $file = $files ? reset($files) : null;
            if ($file && in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                $path = make_request_directory() . '/' . $area . ($file->get_mimetype() === 'image/png' ? '.png' : '.jpg');
                $file->copy_content_to($path);
                $images[$key] = $path;
            } else if (is_readable($shipped . $defaults[$key])) {
                $images[$key] = $shipped . $defaults[$key];
            }
        }
        return $images;
    }
}
