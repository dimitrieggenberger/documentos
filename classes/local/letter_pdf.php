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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * The letter-size sheet of the brand manual: seal, school and department in
 * the header, the pale seal behind the page, europaschule.eu and the page in
 * the footer.
 *
 * Every measure is in points and comes from the Figma frame "Letter"
 * (EuropaSchule · Design Sandbox, node 109:508): US Letter 612 × 792 pt,
 * margins 1.5 cm top and bottom (42.5 pt) and 3 cm left and right (85 pt),
 * Poppins throughout. Matching a later change of the frame is a change of
 * these constants.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class letter_pdf extends \pdf {

    /** @var float US Letter. */
    const PAGE_W = 612;
    /** @var float */
    const PAGE_H = 792;
    /** @var float 3 cm. */
    const MARGIN_X = 85;
    /** @var float 1.5 cm. */
    const MARGIN_Y = 42.5;
    /** @var float The header's seal. */
    const SEAL = 66;
    /** @var float Gap between the header's items. */
    const HEADER_GAP = 24;
    /** @var float The header's vertical rules. */
    const RULE_H = 32;
    /** @var float The school column of the header. */
    const SCHOOL_W = 140;
    /** @var float Space under the header, before place and date. */
    const AFTER_HEADER = 110;
    /** @var float Space after place and date. */
    const AFTER_DATE = 28;
    /** @var float Space after the recipient, before the title. */
    const AFTER_RECIPIENT = 52;
    /** @var float Space before the title when there is no recipient. */
    const BEFORE_TITLE = 40;
    /** @var float Space after the title. */
    const AFTER_TITLE = 16;
    /** @var float Space between paragraphs of the body. */
    const PARAGRAPH = 10;
    /** @var float Space before the signature block. */
    const BEFORE_SIGNATURE = 8;
    /** @var float The room left to sign by hand, above the name. */
    const SIGN_H = 34;
    /** @var float A signer's column. */
    const SIGNER_W = 150;
    /** @var float Gap between signers and the stamp. */
    const SIGNER_GAP = 28;
    /** @var float The stamp beside the signatures. */
    const STAMP = 78;
    /** @var float Space before the attachment line. */
    const BEFORE_ATTACHMENT = 36;
    /** @var float The watermark: 380 pt, centred, its top 229 pt down. */
    const WATERMARK = 380;
    /** @var float */
    const WATERMARK_TOP = 229;
    /** @var float Opacity of the watermark (5 %). */
    const WATERMARK_ALPHA = 0.05;
    /** @var float europaschule.eu, bottom left. */
    const URL_W = 92;
    /** @var float */
    const URL_H = 15.935;
    /** @var float The verification QR code, bottom right. */
    const QR = 40;
    /** @var float The footer's height: the body stops above it. */
    const FOOTER_H = 52;
    /** @var string The brand font, converted for TCPDF in fonts/. */
    const FONT = 'poppins';
    /** @var int[] Body text, #1d2125. */
    const TEXT = [29, 33, 37];
    /** @var int[] Secondary text, #6a737b. */
    const MUTED = [106, 115, 123];
    /** @var int[] Rules and table borders, #dee2e6. */
    const RULE = [222, 226, 230];

    /** @var array What the header and footer print, set by pdf::render(). */
    public $sheet = [];

    /**
     * Register Poppins from the plugin's fonts folder.
     *
     * @return void
     */
    public function add_brand_fonts(): void {
        $dir = dirname(__DIR__, 2) . '/fonts/';
        $this->AddFont(self::FONT, '', $dir . 'poppins.php');
        $this->AddFont(self::FONT, 'B', $dir . 'poppinsb.php');
        $this->AddFont(self::FONT, 'I', $dir . 'poppinsi.php');
    }

    /**
     * Where the body starts on every page: under the header.
     *
     * @return float
     */
    public static function body_top(): float {
        return self::MARGIN_Y + self::SEAL + self::HEADER_GAP;
    }

    /**
     * The width text may take.
     *
     * @return float
     */
    public static function text_width(): float {
        return self::PAGE_W - 2 * self::MARGIN_X;
    }

    /**
     * Watermark, header and the draft mark, on every page.
     *
     * @return void
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $sheet = $this->sheet;
        $images = $sheet['images'];

        if (!empty($images['watermark'])) {
            $this->SetAlpha(self::WATERMARK_ALPHA);
            $this->Image($images['watermark'], (self::PAGE_W - self::WATERMARK) / 2, self::WATERMARK_TOP,
                self::WATERMARK, self::WATERMARK, '', '', '', false, 300, '', false, false, 0, false, false, false);
            $this->SetAlpha(1);
        }

        $x = self::MARGIN_X;
        $y = self::MARGIN_Y;
        if (!empty($images['seal'])) {
            $this->Image($images['seal'], $x, $y, self::SEAL, self::SEAL, '', '', '', false, 300, '', false, false, 0,
                false, false, false);
        }
        $x += self::SEAL + self::HEADER_GAP;
        $this->rule($x, $y);
        $x += 0.75 + self::HEADER_GAP;
        $this->header_block($x, $y, self::SCHOOL_W, $sheet['school']['name'], [$sheet['school']['legalname']]);
        $x += self::SCHOOL_W + self::HEADER_GAP;
        $this->rule($x, $y);
        $x += 0.75 + self::HEADER_GAP;
        $unit = $sheet['unit'];
        $this->header_block($x, $y, self::PAGE_W - self::MARGIN_X - $x, $unit['name'], [$unit['email'], $unit['phone']]);

        if (empty($sheet['final'])) {
            $this->SetAlpha(0.08);
            $this->SetTextColor(161, 39, 52);
            $this->SetFont(self::FONT, 'B', 96);
            $this->StartTransform();
            $this->Rotate(35, self::PAGE_W / 2, self::PAGE_H / 2);
            $this->Text(70, self::PAGE_H / 2 - 40, $sheet['strings']['draft']);
            $this->StopTransform();
            $this->SetAlpha(1);
        }
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * europaschule.eu at the bottom left; the verification and the page at the bottom right.
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $sheet = $this->sheet;
        $bottom = self::PAGE_H - self::MARGIN_Y;

        if (!empty($sheet['images']['url'])) {
            $this->Image($sheet['images']['url'], self::MARGIN_X, $bottom - self::URL_H, self::URL_W, self::URL_H, '', '',
                '', false, 300, '', false, false, 0, false, false, false);
        }

        $right = self::PAGE_W - self::MARGIN_X;
        if (!empty($sheet['final'])) {
            $this->write2DBarcode($sheet['verifyurl'], 'QRCODE,M', $right - self::QR, $bottom - self::QR, self::QR, self::QR,
                ['border' => false, 'padding' => 0, 'fgcolor' => self::TEXT, 'bgcolor' => false], 'N');
            $right -= self::QR + 8;
        }
        $lines = $sheet['strings']['footerlines'];
        $lines[] = sprintf($sheet['strings']['page'], $this->getAliasNumPage(), $this->getAliasNbPages());
        $width = 260;
        $this->SetTextColor(...self::MUTED);
        $this->SetFont(self::FONT, '', 6);
        $this->setCellHeightRatio(1.25);
        $y = $bottom - count($lines) * 7.5;
        foreach ($lines as $i => $line) {
            $this->SetFont(self::FONT, $i === 0 && !empty($sheet['final']) ? 'B' : '', 6);
            $this->SetXY($right - $width, $y);
            $this->Cell($width, 7.5, $line, 0, 0, 'R', false, '', 1);
            $y += 7.5;
        }
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * One vertical rule of the header.
     *
     * @param float $x
     * @param float $y
     * @return void
     */
    private function rule(float $x, float $y): void {
        $this->SetFillColor(...self::RULE);
        $this->Rect($x, $y, 0.75, self::RULE_H, 'F');
    }

    /**
     * A header column: a bold line, then regular ones (Poppins 6 pt on 7.5).
     *
     * @param float $x
     * @param float $y
     * @param float $width
     * @param string $bold
     * @param string[] $lines
     * @return void
     */
    private function header_block(float $x, float $y, float $width, string $bold, array $lines): void {
        $this->SetTextColor(...self::MUTED);
        $this->SetFont(self::FONT, 'B', 6);
        $this->SetXY($x, $y);
        $this->Cell($width, 7.5, $bold, 0, 2, 'L', false, '', 1);
        $this->SetFont(self::FONT, '', 6);
        foreach (array_filter(array_map('trim', $lines), 'strlen') as $line) {
            $this->SetX($x);
            $this->Cell($width, 7.5, $line, 0, 2, 'L', false, '', 1);
        }
    }
}
