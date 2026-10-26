<?php

namespace App\Services;

class ProgressPdfChartService
{
    /**
     * Generate canonical SVG trend chart widgets for PDF reports from a dossier.
     *
     * @param  array<string, mixed>  $dossier
     * @return array{svgTotalTrend: string, svgSectionTrend: string}
     */
    public function forDossier(array $dossier): array
    {
        $history = $dossier['history'] ?? [];

        return [
            'svgTotalTrend' => $this->renderDossierLineChartSvg($history, [
                'total' => ['label' => 'Total SAT', 'color' => '#2563eb', 'values' => array_column($history, 'total')],
                'rolling' => ['label' => '3-Test Avg', 'color' => '#0891b2', 'values' => array_column($history, 'rollingAverage')],
            ], 400, 1600, 546, 140),
            'svgSectionTrend' => $this->renderDossierLineChartSvg($history, [
                'rw' => ['label' => 'RW Section', 'color' => '#0284c7', 'values' => array_column($history, 'rw')],
                'math' => ['label' => 'Math Section', 'color' => '#c2410c', 'values' => array_column($history, 'math')],
            ], 200, 800, 268, 125),
        ];
    }

    /**
     * Compact multi-series line chart used by the longitudinal dossier.
     * Series values must use a shared scale (total/rolling or RW/Math).
     *
     * @param list<array{shortDate:string}> $history
     * @param array<string, array{label:string,color:string,values:list<int>}> $series
     */
    public function renderDossierLineChartSvg(array $history, array $series, int $min, int $max, int $width = 560, int $height = 155): string
    {
        if ($history === [] || $series === []) {
            return $this->renderEmptyChartSvg($width, $height, 'No comparable score history available.');
        }

        $isCompact = $width < 350;
        $left = $isCompact ? 28 : 34;
        $right = $isCompact ? 10 : 14;
        $top = $isCompact ? 18 : 22;
        $bottom = $isCompact ? 18 : 24;
        $plotW = $width - $left - $right;
        $plotH = $height - $top - $bottom;
        $count = count($history);
        $range = max(1, $max - $min);
        $ticks = $isCompact ? 3 : 4;

        $svg = [sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="font-family:\'DejaVu Sans\',Arial,sans-serif;">', $width, $height, $width, $height)];
        $svg[] = sprintf('<rect x="0.5" y="0.5" width="%d" height="%d" rx="6" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1"/>', $width - 1, $height - 1);

        for ($i = 0; $i <= $ticks; $i++) {
            $value = $min + (($max - $min) * $i / $ticks);
            $y = $top + $plotH - (($value - $min) / $range * $plotH);
            $svg[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="2,2"/>', $left, $y, $width - $right, $y);
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="%.1f" fill="#94a3b8" text-anchor="end">%d</text>', $left - 3, $y + 2.5, $isCompact ? 6.0 : 7.0, round($value));
        }

        $lineWidth = $isCompact ? '1.8' : '2.2';
        $circleR = $isCompact ? '2.2' : '2.7';

        foreach ($series as $seriesData) {
            $points = [];
            foreach ($seriesData['values'] as $index => $value) {
                $x = $count === 1 ? $left + ($plotW / 2) : $left + ($index / ($count - 1) * $plotW);
                $y = $top + $plotH - (($value - $min) / $range * $plotH);
                $points[] = sprintf('%.1f,%.1f', $x, $y);
            }
            if (count($points) > 1) {
                $svg[] = sprintf('<polyline points="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round"/>', implode(' ', $points), $seriesData['color'], $lineWidth);
            }
            foreach ($points as $point) {
                [$x, $y] = array_map('floatval', explode(',', $point));
                $svg[] = sprintf('<circle cx="%.1f" cy="%.1f" r="%s" fill="%s" stroke="#fff" stroke-width="1"/>', $x, $y, $circleR, $seriesData['color']);
            }
        }

        // X-axis date labels (with decimation in compact mode to avoid overlapping)
        $step = ($isCompact && $count > 5) ? (int) ceil($count / 4) : 1;
        $renderedIndices = [];
        for ($i = 0; $i < $count; $i += $step) {
            $renderedIndices[] = $i;
        }
        if (! in_array($count - 1, $renderedIndices, true)) {
            // Drop penultimate if too close
            if (($count - 1 - end($renderedIndices)) < ($step / 2)) {
                array_pop($renderedIndices);
            }
            $renderedIndices[] = $count - 1;
        }

        foreach ($renderedIndices as $index) {
            if (! isset($history[$index])) {
                continue;
            }
            $item = $history[$index];
            $x = $count === 1 ? $left + ($plotW / 2) : $left + ($index / ($count - 1) * $plotW);
            $svg[] = sprintf('<text x="%.1f" y="%d" font-size="%.1f" fill="#64748b" text-anchor="middle">%s</text>', $x, $height - 5, $isCompact ? 6.0 : 7.0, htmlspecialchars($item['shortDate']));
        }

        // Legends
        $legendX = $isCompact ? 28 : 44;
        $legendY = $isCompact ? 10 : 12;
        foreach ($series as $seriesData) {
            $svg[] = sprintf('<rect x="%d" y="%d" width="%d" height="3" rx="1" fill="%s"/>', $legendX, $legendY - 3, $isCompact ? 6 : 7, $seriesData['color']);
            $svg[] = sprintf('<text x="%d" y="%d" font-size="%.1f" font-weight="600" fill="#475569">%s</text>', $legendX + ($isCompact ? 8 : 10), $legendY, $isCompact ? 6.2 : 7.0, htmlspecialchars($seriesData['label']));
            $legendX += ($isCompact ? 10 : 16) + strlen($seriesData['label']) * ($isCompact ? 3.8 : 4.0);
        }
        $svg[] = '</svg>';

        return sprintf(
            '<img src="data:image/svg+xml;base64,%s" width="%d" height="%d" style="display:block;width:%dpx;height:%dpx" alt="Score trend chart">',
            base64_encode(implode("\n", $svg)),
            $width,
            $height,
            $width,
            $height,
        );
    }

    private function renderEmptyChartSvg(int $width, int $height, string $message): string
    {
        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:#f8fafc; font-family:\'DejaVu Sans\', Arial, sans-serif;">'
            . '<rect x="0.5" y="0.5" width="%d" height="%d" rx="6" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1"/>'
            . '<text x="%d" y="%d" font-size="8.5" fill="#94a3b8" text-anchor="middle" font-style="italic">%s</text>'
            . '</svg>',
            $width,
            $height,
            $width,
            $height,
            $width - 1,
            $height - 1,
            $width / 2,
            $height / 2,
            htmlspecialchars($message)
        );
    }
}
