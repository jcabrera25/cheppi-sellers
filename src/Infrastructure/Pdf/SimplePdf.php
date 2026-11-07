<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\Pdf;

/**
 * Tiny PDF 1.4 writer: A4 pages, Helvetica (regular/bold), text, lines and filled rectangles.
 * Enough for statements and receipts without shipping a PDF library. Text is converted to
 * Windows-1252, so Spanish accents and ñ work with the built-in fonts. Units are points, origin top-left.
 */
final class SimplePdf
{
    public const WIDTH = 595.28;
    public const HEIGHT = 841.89;

    /** Helvetica advance widths (1/1000 em) for the characters statements use most. */
    private const WIDTHS = [
        ' ' => 278, '.' => 278, ',' => 278, '/' => 278, '-' => 333, ':' => 278, '#' => 556, '%' => 889, '(' => 333, ')' => 333,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556, '8' => 556, '9' => 556,
        'S' => 667, 'i' => 222, 'l' => 222, 'j' => 222, 't' => 278, 'f' => 278, 'r' => 333, 'I' => 278, 'm' => 833, 'w' => 722,
        'M' => 833, 'W' => 944, 'a' => 556, 'e' => 556, 'o' => 556, 'n' => 556, 'u' => 556, 's' => 500, 'c' => 500, 'v' => 500,
    ];

    /** @var list<string> content streams, one per page */
    private array $pages = [];
    private string $current = '';

    public function __construct(private readonly string $title = '')
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = ' ';
    }

    /** @param array{0: float, 1: float, 2: float} $rgb 0..1 */
    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false, array $rgb = [0.09, 0.07, 0.18], string $align = 'left'): void
    {
        $encoded = self::encode($text);
        if ($align !== 'left') {
            $w = self::textWidth($text, $size, $bold);
            $x = $align === 'right' ? $x - $w : $x - $w / 2;
        }
        $this->current .= sprintf(
            "BT %.3F %.3F %.3F rg /%s %.1F Tf %.2F %.2F Td (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2], $bold ? 'F2' : 'F1', $size, $x, self::HEIGHT - $y, $encoded
        );
    }

    /** @param array{0: float, 1: float, 2: float} $rgb */
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->current .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, self::HEIGHT - $y - $h, $w, $h);
    }

    /** @param array{0: float, 1: float, 2: float} $rgb */
    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.85, 0.84, 0.9], float $width = 0.6): void
    {
        $this->current .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $rgb[0], $rgb[1], $rgb[2], $width, $x1, self::HEIGHT - $y1, $x2, self::HEIGHT - $y2);
    }

    public static function textWidth(string $text, float $size, bool $bold = false): float
    {
        $units = 0;
        foreach (mb_str_split($text) as $char) {
            $units += self::WIDTHS[$char] ?? 556;
        }
        return $units * $size / 1000 * ($bold ? 1.04 : 1.0);
    }

    /** Cuts text so it fits $maxWidth, adding an ellipsis. */
    public static function fit(string $text, float $maxWidth, float $size): string
    {
        if (self::textWidth($text, $size) <= $maxWidth) {
            return $text;
        }
        while ($text !== '' && self::textWidth($text . '…', $size) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }
        return $text . '…';
    }

    public function output(): string
    {
        $pages = $this->pages;
        $pages[] = $this->current;
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $n = 5;
        $pageObjects = [];
        foreach ($pages as $content) {
            $pageId = $n++;
            $contentId = $n++;
            $kids[] = "{$pageId} 0 R";
            $pageObjects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::WIDTH, self::HEIGHT, $contentId);
            $stream = gzcompress($content, 6);
            $pageObjects[$contentId] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($stream), $stream);
        }
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($kids));
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects += $pageObjects;
        $infoId = $n;
        $objects[$infoId] = sprintf('<< /Title (%s) /Producer (Cheppi Sellers) /CreationDate (D:%s) >>', self::encode($this->title), gmdate('YmdHis'));
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objects) + 1);
        foreach ($objects as $id => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF", count($objects) + 1, $infoId, $xref);
        return $pdf;
    }

    private static function encode(string $text): string
    {
        $text = strtr($text, ['…' => '...', '−' => '-', '·' => '-', '“' => '"', '”' => '"', '‘' => "'", '’' => "'"]);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        $text = $converted !== false ? $converted : (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
