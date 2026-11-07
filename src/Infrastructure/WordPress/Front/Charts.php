<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Infrastructure\WordPress\Front;

/**
 * Small charts rendered on the server as inline SVG: no chart library, no JavaScript, instant paint.
 * Colours come from the Cheppi CSS tokens so they follow the brand.
 */
final class Charts
{
    private const PALETTE = ['var(--ch-violet)', 'var(--ch-lime)', 'var(--ch-night)', '#a68bff', '#e3f7a0', 'var(--ch-text-3)'];

    /**
     * Vertical bars, one per day.
     *
     * @param list<array{date: string, cents: int}> $rows
     */
    public static function salesBars(array $rows): string
    {
        $w = 640;
        $h = 180;
        $pad = 24;
        $n = max(1, count($rows));
        $max = max(1, ...array_map(static fn (array $r): int => $r['cents'], $rows));
        $slot = ($w - $pad) / $n;
        $barW = max(6, $slot * 0.56);
        $total = array_sum(array_map(static fn (array $r): int => $r['cents'], $rows));

        $bars = '';
        foreach ($rows as $i => $row) {
            $x = $pad / 2 + $i * $slot + ($slot - $barW) / 2;
            $barH = $row['cents'] > 0 ? max(4, ($h - 40) * $row['cents'] / $max) : 4;
            $y = $h - 24 - $barH;
            $isLast = $i === $n - 1;
            $fill = $row['cents'] === $max && $row['cents'] > 0 ? 'var(--ch-lime)' : ($row['cents'] > 0 ? 'var(--ch-violet)' : 'var(--ch-border)');
            $label = esc_attr(sprintf('%s: S/ %s', wp_date('j M', strtotime($row['date'])), number_format($row['cents'] / 100, 2)));
            $bars .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="6" fill="%s"><title>%s</title></rect>',
                $x, $y, $barW, $barH, $fill, $label
            );
            if ($i % 2 === 0 || $isLast) {
                $bars .= sprintf(
                    '<text x="%.1f" y="%d" text-anchor="middle" font-size="11" fill="var(--ch-text-3)">%s</text>',
                    $x + $barW / 2, $h - 6, esc_html(wp_date('j', strtotime($row['date'])))
                );
            }
        }
        $summary = $total > 0
            ? sprintf(__('Ventas de los últimos %1$d días: S/ %2$s', 'cheppi-sellers'), $n, number_format($total / 100, 2))
            : sprintf(__('Sin ventas en los últimos %d días', 'cheppi-sellers'), $n);

        return sprintf('<p class="ch-chart__total">%s</p>', esc_html($summary)) . sprintf(
            '<svg class="ch-chart" viewBox="0 0 %1$d %2$d" role="img" aria-label="%3$s"><line x1="%4$d" x2="%5$d" y1="%6$d" y2="%6$d" stroke="var(--ch-border)"/>%7$s</svg>',
            $w, $h, esc_attr($summary), $pad / 2, $w - $pad / 2, $h - 24, $bars
        );
    }

    /**
     * Ranked horizontal bars (HTML + CSS widths; reads well on phones and with screen readers).
     *
     * @param list<array{name: string, cents: int, qty: int}> $rows
     */
    public static function ranking(array $rows): string
    {
        $max = max(1, ...array_map(static fn (array $r): int => $r['cents'], $rows ?: [['cents' => 1]]));
        $out = '<ol class="ch-rank">';
        foreach ($rows as $i => $row) {
            $out .= sprintf(
                '<li><span class="ch-rank__name">%s</span><span class="ch-rank__value">S/ %s · %s</span><span class="ch-rank__bar"><span style="width:%d%%;background:%s"></span></span></li>',
                esc_html($row['name']),
                esc_html(number_format($row['cents'] / 100, 2)),
                esc_html(sprintf(_n('%d und.', '%d und.', $row['qty'], 'cheppi-sellers'), $row['qty'])),
                (int) max(4, round($row['cents'] / $max * 100)),
                $i === 0 ? 'var(--ch-lime)' : 'var(--ch-violet)'
            );
        }
        return $out . '</ol>';
    }

    /**
     * Donut with legend.
     *
     * @param list<array{name: string, count: int}> $rows
     */
    public static function donut(array $rows, string $centerLabel): string
    {
        $total = array_sum(array_map(static fn (array $r): int => $r['count'], $rows));
        $r = 52;
        $c = 2 * M_PI * $r;
        $offset = 0.0;
        $arcs = '';
        $legend = '';
        foreach ($rows as $i => $row) {
            $color = self::PALETTE[$i % count(self::PALETTE)];
            $len = $total > 0 ? $c * $row['count'] / $total : 0;
            $arcs .= sprintf(
                '<circle r="%d" cx="70" cy="70" fill="none" stroke="%s" stroke-width="18" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 70 70)"><title>%s</title></circle>',
                $r, $color, max(0, $len - 2), $c, -$offset, esc_html($row['name'] . ': ' . $row['count'])
            );
            $offset += $len;
            $legend .= sprintf(
                '<li><span class="ch-dot" style="background:%s"></span><span>%s</span><strong>%d</strong></li>',
                $color, esc_html($row['name']), $row['count']
            );
        }
        if ($total === 0) {
            $arcs = sprintf('<circle r="%d" cx="70" cy="70" fill="none" stroke="var(--ch-border)" stroke-width="18"/>', $r);
        }
        return sprintf(
            '<div class="ch-donut"><svg viewBox="0 0 140 140" role="img" aria-label="%1$s">%2$s<text x="70" y="68" text-anchor="middle" class="ch-donut__n">%3$d</text><text x="70" y="86" text-anchor="middle" class="ch-donut__l">%4$s</text></svg><ul class="ch-legend">%5$s</ul></div>',
            esc_attr(sprintf('%d %s', $total, $centerLabel)), $arcs, $total, esc_html($centerLabel), $legend
        );
    }
}
