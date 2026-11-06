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
 * the header, the pale seal behind the page; in the footer the verification
 * QR and lines on the left and europaschule.eu on the right.
 *
 * Every measure is in points and comes from the Figma frame "Letter"
 * (EuropaSchule · Design Sandbox, node 109:508): US Letter 612 × 792 pt,
 * margins 1.5 cm top and bottom (42.5 pt) and 3 cm left and right (85 pt),
 * Poppins throughout. Matching a later change of the frame is a change of
 * these constants. The footer and the signed badge follow the Figma
 * components Documento/Pie and Documento/Sello de firma electrónica
 * ("A · Insignia (elegida)"), chosen on 2026-11-05.
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
    /** @var float Space under the header in an authorisation, so the letter and its slip share one page. */
    const AFTER_HEADER_COMPACT = 36;
    /** @var float The watermark: 380 pt, centred, its top 229 pt down. */
    const WATERMARK = 380;
    /** @var float */
    const WATERMARK_TOP = 229;
    /** @var float Opacity of the watermark (5 %). */
    const WATERMARK_ALPHA = 0.05;
    /** @var float europaschule.eu, bottom right. */
    const URL_W = 92;
    /** @var float */
    const URL_H = 15.935;
    /** @var float The verification QR code, bottom left. */
    const QR = 40;
    /** @var float Gap between the QR, the verification lines and europaschule.eu. */
    const FOOTER_GAP = 8;
    /** @var float The signed badge: its height (a pill, fully rounded). */
    const BADGE_H = 13.5;
    /** @var float The badge's shield icon. */
    const BADGE_ICON = 7.5;
    /** @var float Room left of the icon. */
    const BADGE_PAD_L = 5;
    /** @var float Room right of the text. */
    const BADGE_PAD_R = 7;
    /** @var float Gap between the icon and the text. */
    const BADGE_GAP = 4;
    /** @var float The badge's text size. */
    const BADGE_FONT = 5.75;
    /** @var int[] The badge's hairline outline, #ccd1d6. */
    const BADGE_RULE = [204, 209, 214];
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
     * The verification QR at the bottom left, the verification lines and the page beside it,
     * europaschule.eu at the bottom right.
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $sheet = $this->sheet;
        $bottom = self::PAGE_H - self::MARGIN_Y;
        $left = self::MARGIN_X;
        $right = self::PAGE_W - self::MARGIN_X;

        if (!empty($sheet['images']['url'])) {
            $this->Image($sheet['images']['url'], $right - self::URL_W, $bottom - self::URL_H, self::URL_W, self::URL_H, '',
                '', '', false, 300, '', false, false, 0, false, false, false);
        }

        if (!empty($sheet['final'])) {
            $this->write2DBarcode($sheet['verifyurl'], 'QRCODE,M', $left, $bottom - self::QR, self::QR, self::QR,
                ['border' => false, 'padding' => 0, 'fgcolor' => self::TEXT, 'bgcolor' => false], 'N');
            $left += self::QR + self::FOOTER_GAP;
        }
        $lines = $sheet['strings']['footerlines'];
        $lines[] = sprintf($sheet['strings']['page'], $this->getAliasNumPage(), $this->getAliasNbPages());
        $width = $right - self::URL_W - 2 * self::FOOTER_GAP - $left;
        $this->SetTextColor(...self::MUTED);
        $this->SetFont(self::FONT, '', 6);
        $this->setCellHeightRatio(1.25);
        $y = $bottom - count($lines) * 7.5;
        foreach ($lines as $i => $line) {
            $this->SetFont(self::FONT, $i === 0 && !empty($sheet['final']) ? 'B' : '', 6);
            $this->SetXY($left, $y);
            $this->Cell($width, 7.5, $line, 0, 0, 'L', false, '', 1);
            $y += 7.5;
        }
        $this->SetTextColor(...self::TEXT);
    }

    /**
     * The signed badge: a light pill with a hairline outline, the green shield and one line,
     * "Firmado electrónicamente · 28.10.2026, 10:42 h", centred between $from and $to.
     *
     * Green only on the icon, so it does not compete with the logo or the text; the words carry
     * the meaning in black and white. The badge proves nothing by itself: the QR and the code do.
     *
     * @param float $x left edge
     * @param float $from where the space above it starts (the end of the body)
     * @param float $to where the space under it ends (the signer's name)
     * @param string $label "Firmado electrónicamente"
     * @param string $when the date and time of the signature
     * @return void
     */
    public function signed_badge(float $x, float $from, float $to, string $label, string $when): void {
        $padding = $this->getCellPaddings();
        $this->setCellPaddings(0, 0, 0, 0);
        $this->SetFont(self::FONT, '', self::BADGE_FONT);
        $rest = ' · ' . $when;
        $labelw = $this->GetStringWidth($label);
        $restw = $this->GetStringWidth($rest);
        $width = self::BADGE_PAD_L + self::BADGE_ICON + self::BADGE_GAP + $labelw + $restw + self::BADGE_PAD_R;
        $y = ($from + $to - self::BADGE_H) / 2;

        $this->SetDrawColor(...self::BADGE_RULE);
        $this->SetLineWidth(0.5);
        $this->RoundedRect($x, $y, $width, self::BADGE_H, self::BADGE_H / 2, '1111', 'D');
        $icon = dirname(__DIR__, 2) . '/pix/brand/signed.svg';
        if (is_readable($icon)) {
            $this->ImageSVG($icon, $x + self::BADGE_PAD_L, $y + (self::BADGE_H - self::BADGE_ICON) / 2, self::BADGE_ICON,
                self::BADGE_ICON);
        }
        $this->SetXY($x + self::BADGE_PAD_L + self::BADGE_ICON + self::BADGE_GAP, $y);
        $this->SetTextColor(...self::TEXT);
        $this->Cell($labelw, self::BADGE_H, $label, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $this->SetTextColor(...self::MUTED);
        $this->Cell($restw, self::BADGE_H, $rest, 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $this->SetTextColor(...self::TEXT);
        $this->setCellPaddings($padding['L'], $padding['T'], $padding['R'], $padding['B']);
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
