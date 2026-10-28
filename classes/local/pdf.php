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
     * @param \stdClass[]|null $slips an authorisation's students to print (one letter and slip each); null: all
     *     of them when final, the first one in a preview
     * @return string the PDF bytes
     */
    public static function render(array $bundle, bool $final, ?array $slips = null): string {
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
        // One letter per student with a consent slip at its foot (an authorisation), else one letter.
        $consent = (string)($composed['consenthtml'] ?? '');
        $pages = $consent !== '' ? ($slips ?? ($final ? slips::list((int)$doc->id)
            : array_slice(slips::list((int)$doc->id), 0, 1))) : [];
        foreach ($pages ?: [null] as $i => $slip) {
            $pdf->AddPage();

            $width = letter_pdf::text_width();
            $left = letter_pdf::MARGIN_X;
            $y = letter_pdf::MARGIN_Y + letter_pdf::SEAL
                + ($consent !== '' ? letter_pdf::AFTER_HEADER_COMPACT : letter_pdf::AFTER_HEADER);

            // Place and date on the left (letters), the number on the right, as the instructivo prints it.
            $pdf->SetTextColor(...letter_pdf::TEXT);
            if (in_array($bundle['type']->subject, ['none', 'group'], true)) {
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

            // Signed by hand: lines to sign on, and no printed stamp, so the original carries the real seal.
            $handwritten = ($composed['signaturemode'] ?? '') === documents::SIGN_HANDWRITTEN;
            self::signatures($pdf, $composed['signers'], !empty($pdf->sheet['images']['stamp']) && $final && !$handwritten
                ? $pdf->sheet['images']['stamp'] : '', $handwritten);

            self::companions($pdf, $composed['companions'] ?? []);

            self::attachments($pdf, $bundle['doc'], $i === 0 ? ($composed['attachments'] ?? []) : [], $strings, $final);
            if ($consent !== '') {
                self::colilla($pdf, $doc, $composed, $slip, $final);
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * The signature block: per signer the room to sign, name in bold, position, e-mail and
     * phone; the stamp beside them, centred on the block.
     *
     * @param letter_pdf $pdf
     * @param array $signers
     * @param string $stamp path of the stamp image, '' for none
     * @param bool $handwritten a line to sign on by hand instead of the electronic signature
     * @return void
     */
    private static function signatures(letter_pdf $pdf, array $signers, string $stamp, bool $handwritten = false): void {
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
            // The room to sign by hand carries the electronic signature, at the foot of it: "Firmado
            // electrónicamente" and, under it, the date and time it was given.
            $approved = (int)($signer['approvedon'] ?? 0);
            $pdf->SetTextColor(...letter_pdf::MUTED);
            $pdf->SetFont(letter_pdf::FONT, 'I', 6);
            if ($handwritten) {
                $pdf->SetDrawColor(...letter_pdf::MUTED);
                $pdf->SetLineWidth(0.5);
                $pdf->Line($x, $top + letter_pdf::SIGN_H - 3, $x + letter_pdf::SIGNER_W - 20, $top + letter_pdf::SIGN_H - 3);
            } else if ($approved > 0) {
                $pdf->SetXY($x, $top + letter_pdf::SIGN_H - 16.5);
                $pdf->Cell(letter_pdf::SIGNER_W, 7.5, $sm->get_string('pdfsigned', 'local_documents', null, 'es'), 0, 0, 'L',
                    false, '', 1);
            }
            $pdf->SetXY($x, $top + letter_pdf::SIGN_H - 9);
            $pdf->Cell(letter_pdf::SIGNER_W, 7.5, $handwritten ? '' : ($approved > 0
                ? $sm->get_string('pdfapproved', 'local_documents', spanish::date_time($approved), 'es')
                : $sm->get_string('pdfsignpending', 'local_documents', null, 'es')), 0, 0, 'L', false, '', 1);
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
     * The consent slip (colilla) at the foot of an authorisation, to cut off and return signed: who it is
     * for, AUTORIZO / NO AUTORIZO, what is authorised, and lines for the adult's name, identity number,
     * phone, date and signature; its own code and QR (docs/documents-plan.md 15).
     *
     * @param letter_pdf $pdf
     * @param \stdClass $doc
     * @param array $composed
     * @param \stdClass|null $slip the student; null in a preview without students yet
     * @param bool $final
     * @return void
     */
    private static function colilla(letter_pdf $pdf, \stdClass $doc, array $composed, ?\stdClass $slip, bool $final): void {
        // Drawn where the letter ends when it fits whole there, else at the top of a new page: tried, and undone
        // when it ran past the page.
        $limit = letter_pdf::PAGE_H - letter_pdf::MARGIN_Y - letter_pdf::FOOTER_H;
        $page = $pdf->getPage();
        $pdf->startTransaction();
        $pdf->SetY($pdf->GetY() + 18);
        self::colilla_draw($pdf, $doc, $composed, $slip, $final);
        if ($pdf->getPage() === $page && $pdf->GetY() <= $limit) {
            $pdf->commitTransaction();
            return;
        }
        $pdf->rollbackTransaction(true);
        $pdf->AddPage();
        self::colilla_draw($pdf, $doc, $composed, $slip, $final);
    }

    /**
     * The consent slip itself, from the current position down.
     *
     * @param letter_pdf $pdf
     * @param \stdClass $doc
     * @param array $composed
     * @param \stdClass|null $slip
     * @param bool $final
     * @return void
     */
    private static function colilla_draw(letter_pdf $pdf, \stdClass $doc, array $composed, ?\stdClass $slip,
            bool $final): void {
        $sm = get_string_manager();
        $str = static fn(string $key, $a = null) => $sm->get_string($key, 'local_documents', $a, 'es');
        $left = letter_pdf::MARGIN_X;
        $width = letter_pdf::text_width();

        // The cut line, with what to do with the part below.
        $y = $pdf->GetY();
        $pdf->SetDrawColor(...letter_pdf::MUTED);
        $pdf->SetLineStyle(['width' => 0.5, 'dash' => '3,3', 'color' => letter_pdf::MUTED]);
        $pdf->Line($left - 30, $y, $left + $width + 30, $y);
        $pdf->SetLineStyle(['width' => 0.5, 'dash' => 0, 'color' => letter_pdf::MUTED]);
        $pdf->SetFont(letter_pdf::FONT, 'I', 6);
        $pdf->SetTextColor(...letter_pdf::MUTED);
        $pdf->SetFillColor(255, 255, 255);
        $label = $str('pdfslipcut');
        $labelw = $pdf->GetStringWidth($label) + 12;
        $pdf->SetXY($left + ($width - $labelw) / 2, $y - 4);
        $pdf->Cell($labelw, 8, $label, 0, 0, 'C', true);

        // Title, number and where it goes back.
        $values = $composed['values'];
        $y += 12;
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetFont(letter_pdf::FONT, 'B', 9);
        $pdf->SetXY($left, $y);
        $pdf->Cell($width / 2, 12, $str('pdfsliptitle'), 0, 0, 'L');
        $pdf->SetFont(letter_pdf::FONT, '', 6);
        $pdf->SetTextColor(...letter_pdf::MUTED);
        $pdf->SetXY($left + $width / 2, $y + 2);
        $pdf->Cell($width / 2, 8, $final ? numbering::display((string)$doc->number) : $str('pdfnonumber'), 0, 0, 'R', false,
            '', 1);
        $to = trim((string)($values['f.devolver_a'] ?? ''));
        $by = trim((string)($values['f.devolver_antes'] ?? ''));
        $pdf->SetY($y + 14);
        if ($to !== '') {
            $pdf->SetFont(letter_pdf::FONT, 'I', 7);
            $pdf->MultiCell($width, 9, $str($by !== '' ? 'pdfslipreturnby' : 'pdfslipreturn', (object)['to' => $to, 'by' => $by]),
                0, 'L', false, 1, $left);
        }

        // "Yo, ..., en mi calidad de madre, padre o persona responsable legal de <student>:"
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetFont(letter_pdf::FONT, '', 8);
        $student = $slip ? '<b>' . s($slip->holdername) . '</b>'
            . (trim((string)$slip->holderidnumber) !== '' ? ', ' . s($str('pdfslipid', trim((string)$slip->holderidnumber))) : '')
            . (trim((string)$slip->gradename) !== '' ? ', ' . s($str('pdfslipgrade', trim((string)$slip->gradename))) : '')
            : s($str('pdfslipnostudent'));
        $pdf->SetY($pdf->GetY() + 4);
        // Left-aligned: a form, and TCPDF loses the space after bold text on a justified line.
        $pdf->writeHTML('<p style="text-align:left;">' . s($str('pdfslipadult')) . ' ' . $student . ':</p>', true, false,
            true, false, '');

        // AUTORIZO / NO AUTORIZO, then what is authorised.
        $y = $pdf->GetY() + 4;
        $pdf->SetLineStyle(['width' => 0.75, 'dash' => 0, 'color' => letter_pdf::TEXT]);
        $pdf->SetFont(letter_pdf::FONT, 'B', 8);
        $x = $left;
        foreach (['pdfslipyes', 'pdfslipno'] as $key) {
            $pdf->Rect($x, $y + 1, 8, 8);
            $pdf->SetXY($x + 12, $y);
            $text = $str($key);
            $pdf->Cell($pdf->GetStringWidth($text) + 2, 10, $text, 0, 0, 'L');
            $x += 12 + $pdf->GetStringWidth($text) + 28;
        }
        $pdf->SetFont(letter_pdf::FONT, '', 8);
        $pdf->SetY($y + 14);
        $pdf->writeHTML(str_replace('text-align:justify;', 'text-align:left;', (string)$composed['consenthtml']), true,
            false, true, false, '');

        // The adult's lines, and the slip's own code and QR beside them.
        $y = $pdf->GetY() + 4;
        $qr = 46;
        $lines = $width - $qr - 16;
        $pdf->SetLineStyle(['width' => 0.5, 'dash' => 0, 'color' => letter_pdf::MUTED]);
        $pdf->SetFont(letter_pdf::FONT, '', 7);
        $pdf->SetTextColor(...letter_pdf::MUTED);
        $rows = [[$str('pdfslipname'), 1.0], [$str('pdfslipidnumber'), 0.55, $str('pdfslipphone'), 0.45],
            [$str('pdfslipdate'), 0.35, $str('pdfslipsignature'), 0.65]];
        foreach ($rows as $row) {
            $x = $left;
            $y += 18;
            for ($k = 0; $k < count($row); $k += 2) {
                $w = $lines * $row[$k + 1];
                $pdf->SetXY($x, $y - 9);
                $pdf->Cell($w, 8, $row[$k], 0, 0, 'L', false, '', 1);
                $labelw = min($pdf->GetStringWidth($row[$k]) + 4, $w / 2);
                $pdf->Line($x + $labelw, $y, $x + $w - 8, $y);
                $x += $w;
            }
        }
        if ($slip && $final) {
            $qx = $left + $width - $qr;
            $qy = $y - 3 * 18 + 2;
            $pdf->write2DBarcode(config::verify_url((string)$slip->verifycode), 'QRCODE,M', $qx, $qy, $qr, $qr,
                ['border' => false, 'padding' => 0, 'fgcolor' => letter_pdf::TEXT, 'bgcolor' => false], 'N');
            $pdf->SetFont(letter_pdf::FONT, '', 5.5);
            $pdf->SetXY($qx - 20, $qy + $qr + 2);
            $pdf->Cell($qr + 20, 7, numbering::format_code((string)$slip->verifycode), 0, 0, 'C', false, '', 1);
        }
        $pdf->SetTextColor(...letter_pdf::TEXT);
        $pdf->SetXY($left, $y + 4);
    }

    /**
     * The documents that go with this one, as an index under the signatures (docs/documents-plan.md 14).
     *
     * @param letter_pdf $pdf
     * @param array[] $companions from companions::resolve()
     * @return void
     */
    private static function companions(letter_pdf $pdf, array $companions): void {
        if (!$companions) {
            return;
        }
        $left = letter_pdf::MARGIN_X;
        $width = letter_pdf::text_width();
        $needed = 10 + 12 * count($companions);
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
        $pdf->MultiCell($width, 10, get_string_manager()->get_string('pdfcompanions', 'local_documents', null, 'es'), 0, 'L',
            false, 1, $left);
        $pdf->SetFont(letter_pdf::FONT, '', 7);
        foreach (array_values($companions) as $i => $item) {
            $pdf->SetY($pdf->GetY() + 2);
            $pdf->MultiCell($width, 9, ($i + 1) . '. ' . $item['line'], 0, 'L', false, 1, $left);
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
            if (($composed['signaturemode'] ?? '') === documents::SIGN_HANDWRITTEN) {
                $lines[] = $sm->get_string('pdfhandwritten', 'local_documents', null, 'es');
            } else if (!empty($composed['signaturelaw'])) {
                $lines[] = (string)$composed['signaturelaw'];
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
