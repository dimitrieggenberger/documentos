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
 * The letter-size sheet: letterhead, number block, QR code and footer band.
 *
 * Every measure of the layout is a constant here, so matching the Figma
 * frame is a change in one place.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class letter_pdf extends \pdf {

    /** @var float Letter, in millimetres. */
    const PAGE_W = 215.9;
    /** @var float */
    const PAGE_H = 279.4;
    /** @var float Side margins. */
    const MARGIN_X = 22;
    /** @var float Where the text starts under the letterhead. */
    const BODY_TOP = 42;
    /** @var float Room kept free for the footer (QR and band). */
    const FOOTER_H = 46;
    /** @var float Letterhead logo height. */
    const LOGO_H = 20;
    /** @var float QR code side. */
    const QR = 24;
    /** @var float Footer band height. */
    const BAND_H = 11;
    /** @var string Body font: FreeSans carries every Spanish character. */
    const FONT = 'freesans';

    /** @var array What the header and footer print, set by pdf::render(). */
    public $sheet = [];

    /**
     * Letterhead, watermark and the draft mark, on every page.
     *
     * @return void
     */
    public function Header() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $sheet = $this->sheet;
        $color = self::rgb($sheet['school']['brandcolor']);

        if (!empty($sheet['images']['watermark'])) {
            $this->SetAlpha(0.07);
            $this->Image($sheet['images']['watermark'], (self::PAGE_W - 120) / 2, 85, 120, 0, '', '', '', false, 300,
                '', false, false, 0, false, false, false);
            $this->SetAlpha(1);
        }
        if (!empty($sheet['images']['logo'])) {
            $this->Image($sheet['images']['logo'], self::MARGIN_X, 11, 0, self::LOGO_H, '', '', '', false, 300, '',
                false, false, 0, false, false, false);
        }
        if (!empty($sheet['images']['logo2'])) {
            $this->Image($sheet['images']['logo2'], self::MARGIN_X, 11, 0, self::LOGO_H, '', '', '', false, 300, 'R',
                false, false, 0, false, false, false);
        }

        $inner = self::PAGE_W - 2 * self::MARGIN_X - 2 * 30;
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->SetFont(self::FONT, 'B', 13);
        $this->SetXY(self::MARGIN_X + 30, 13);
        $this->MultiCell($inner, 6, \core_text::strtoupper($sheet['school']['name']), 0, 'C', false, 1);
        if ($sheet['school']['headerlines'] !== '') {
            $this->SetTextColor(73, 80, 87);
            $this->SetFont(self::FONT, '', 8.5);
            $this->SetX(self::MARGIN_X + 30);
            $this->MultiCell($inner, 4, $sheet['school']['headerlines'], 0, 'C', false, 1);
        }
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        $this->SetLineWidth(0.6);
        $this->Line(self::MARGIN_X, 35, self::PAGE_W - self::MARGIN_X, 35);

        if (empty($sheet['final'])) {
            $this->SetAlpha(0.12);
            $this->SetTextColor(161, 39, 52);
            $this->SetFont(self::FONT, 'B', 72);
            $this->StartTransform();
            $this->Rotate(35, self::PAGE_W / 2, self::PAGE_H / 2);
            $this->Text(28, self::PAGE_H / 2 - 12, $sheet['strings']['draft']);
            $this->StopTransform();
            $this->SetAlpha(1);
        }
        $this->SetTextColor(22, 33, 44);
    }

    /**
     * QR code, number, verification line and the footer band.
     *
     * @return void
     */
    public function Footer() { // phpcs:ignore moodle.NamingConventions.ValidFunctionName.LowercaseMethod
        $sheet = $this->sheet;
        $color = self::rgb($sheet['school']['brandcolor']);
        $top = self::PAGE_H - self::BAND_H - self::QR - 8;

        $textx = self::MARGIN_X;
        if (!empty($sheet['final'])) {
            $this->write2DBarcode($sheet['verifyurl'], 'QRCODE,M', self::MARGIN_X, $top, self::QR, self::QR,
                ['border' => false, 'padding' => 0, 'fgcolor' => [0, 0, 0], 'bgcolor' => false], 'N');
            $textx = self::MARGIN_X + self::QR + 4;
        }
        $width = self::PAGE_W - self::MARGIN_X - $textx;
        $this->SetTextColor(22, 33, 44);
        $this->SetXY($textx, $top + 1);
        $this->SetFont(self::FONT, 'B', 8);
        $this->MultiCell($width, 4, $sheet['strings']['numberline'], 0, 'L', false, 1, $textx);
        $this->SetFont(self::FONT, '', 7.5);
        foreach ($sheet['strings']['footerlines'] as $line) {
            $this->MultiCell($width, 3.6, $line, 0, 'L', false, 1, $textx);
        }
        $this->SetTextColor(107, 114, 128);
        $this->MultiCell($width, 3.6, sprintf($sheet['strings']['page'], $this->getAliasNumPage(),
            $this->getAliasNbPages()), 0, 'L', false, 1, $textx);

        $this->SetFillColor($color[0], $color[1], $color[2]);
        $this->Rect(0, self::PAGE_H - self::BAND_H, self::PAGE_W, self::BAND_H, 'F');
        if ($sheet['school']['footertext'] !== '') {
            $this->SetTextColor(255, 255, 255);
            $this->SetFont(self::FONT, '', 7.5);
            $this->SetXY(self::MARGIN_X, self::PAGE_H - self::BAND_H + 1.5);
            $this->MultiCell(self::PAGE_W - 2 * self::MARGIN_X, 3.6, $sheet['school']['footertext'], 0, 'C', false, 1);
        }
    }

    /**
     * "#003f5c" as [0, 63, 92].
     *
     * @param string $hex
     * @return int[]
     */
    public static function rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
