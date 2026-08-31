<?php

namespace App\Services;

class ProgressPdfChartService
{
    /**
     * Render a vector SVG Score Trajectory Line Chart for DomPDF.
     *
     * @param array{labels: list<string>, total: list<int|null>, rw: list<int|null>, math: list<int|null>, points: list<array>, targetScore: int|null} $trendData
     */
    public function renderScoreTrendSvg(array $trendData, int $width = 560, int $height = 135): string
    {
        $points = $trendData['points'] ?? [];
        if (empty($points)) {
            return $this->renderEmptyChartSvg($width, $height, 'No scored attempts available for trend line.');
        }

        $padLeft = 38;
        $padRight = 24;
        $padTop = 24;
        $padBottom = 22;

        $plotW = $width - $padLeft - $padRight;
        $plotH = $height - $padTop - $padBottom;

        $minScore = 400;
        $maxScore = 1600;
        $scoreRange = $maxScore - $minScore;

        $count = count($points);
        $stepX = $count > 1 ? $plotW / ($count - 1) : $plotW / 2;

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:#ffffff; font-family:\'DejaVu Sans\', Arial, sans-serif;">', $width, $height, $width, $height);

        // Chart Card Border & Subtle Background
        $svg[] = sprintf('<rect x="0.5" y="0.5" width="%d" height="%d" rx="6" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1"/>', $width - 1, $height - 1);

        // Horizontal Grid Lines & Score Ticks
        $ticks = [600, 800, 1000, 1200, 1400, 1600];
        foreach ($ticks as $tick) {
            $y = $padTop + $plotH - (($tick - $minScore) / $scoreRange * $plotH);
            $svg[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3,3"/>', $padLeft, $y, $width - $padRight, $y);
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="7.5" fill="#94a3b8" text-anchor="end" dominant-baseline="middle">%d</text>', $padLeft - 5, $y, $tick);
        }

        // Target Score Dashed Line
        $target = $trendData['targetScore'] ?? null;
        if ($target && $target >= $minScore && $target <= $maxScore) {
            $targetY = $padTop + $plotH - (($target - $minScore) / $scoreRange * $plotH);
            $svg[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#6366f1" stroke-width="1.5" stroke-dasharray="5,4"/>', $padLeft, $targetY, $width - $padRight, $targetY);
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="7.5" font-weight="bold" fill="#6366f1" text-anchor="end">Goal: %d</text>', $width - $padRight, $targetY - 4, $target);
        }

        // Plot Coordinates
        $coords = [];
        foreach ($points as $idx => $pt) {
            $x = $count > 1 ? $padLeft + ($idx * $stepX) : $padLeft + ($plotW / 2);
            $score = $pt['total'] ?? null;
            $y = $score !== null ? $padTop + $plotH - (($score - $minScore) / $scoreRange * $plotH) : null;
            $coords[] = [
                'x' => $x,
                'y' => $y,
                'score' => $score,
                'date' => $pt['date'] ?? ('#' . ($idx + 1)),
                'rw' => $pt['rw'] ?? null,
                'math' => $pt['math'] ?? null,
            ];
        }

        // Polyline Points for Total Score
        $validPoints = array_filter($coords, fn ($c) => $c['y'] !== null);
        if (count($validPoints) > 1) {
            $polyPoints = implode(' ', array_map(fn ($c) => sprintf('%.1f,%.1f', $c['x'], $c['y']), $validPoints));
            $svg[] = sprintf('<polyline points="%s" fill="none" stroke="#3A52EE" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>', $polyPoints);
        }

        // Render Data Points, Pills & X-Axis Labels
        foreach ($coords as $pt) {
            $x = $pt['x'];
            $y = $pt['y'];

            // X-Axis Date
            $shortDate = $this->formatShortDate($pt['date']);
            $svg[] = sprintf('<text x="%.1f" y="%d" font-size="8" font-weight="600" fill="#475569" text-anchor="middle">%s</text>', $x, $height - 7, htmlspecialchars($shortDate));

            if ($y === null) {
                continue;
            }

            // Score Marker Circle
            $svg[] = sprintf('<circle cx="%.1f" cy="%.1f" r="4" fill="#3A52EE" stroke="#ffffff" stroke-width="2"/>', $x, $y);

            // Score Pill Badge
            $pillW = 32;
            $pillH = 14;
            $pillX = $x - ($pillW / 2);
            $pillY = max(4, $y - 18);

            $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%d" height="%d" rx="3" fill="#0f172a"/>', $pillX, $pillY, $pillW, $pillH);
            $svg[] = sprintf('<text x="%.1f" y="%.1f" font-size="8" font-weight="bold" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">%d</text>', $x, $pillY + ($pillH / 2), $pt['score']);
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Render a vector SVG 4-Axis Radar Chart for DomPDF.
     *
     * @param array{labels: list<string>, data: list<int>} $radarDomainData
     */
    public function renderRadarSvg(array $radarDomainData, string $title, string $brandColor = '#3A52EE', int $size = 265): string
    {
        $labels = $radarDomainData['labels'] ?? [];
        $data = $radarDomainData['data'] ?? [];

        if (empty($labels) || count($labels) < 4) {
            return $this->renderEmptyChartSvg($size, 180, 'No radar data available.');
        }

        $width = $size;
        $height = 185;
        $cx = $width / 2;
        $cy = 100;
        $r = 58;

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:#ffffff; font-family:\'DejaVu Sans\', Arial, sans-serif;">', $width, $height, $width, $height);

        // Chart Card Background
        $svg[] = sprintf('<rect x="0.5" y="0.5" width="%d" height="%d" rx="6" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1"/>', $width - 1, $height - 1);

        // Title Banner
        $svg[] = sprintf('<text x="12" y="16" font-size="8.5" font-weight="bold" fill="#0f172a" text-transform="uppercase" letter-spacing="0.5">%s</text>', htmlspecialchars($title));

        // Concentric Web Polygons (25%, 50%, 75%, 100%)
        $rings = [0.25, 0.50, 0.75, 1.0];
        foreach ($rings as $ringRatio) {
            $ringR = $r * $ringRatio;
            $ringPoints = [
                sprintf('%.1f,%.1f', $cx, $cy - $ringR), // Top
                sprintf('%.1f,%.1f', $cx + $ringR, $cy), // Right
                sprintf('%.1f,%.1f', $cx, $cy + $ringR), // Bottom
                sprintf('%.1f,%.1f', $cx - $ringR, $cy), // Left
            ];
            $svg[] = sprintf('<polygon points="%s" fill="none" stroke="#e2e8f0" stroke-width="1"/>', implode(' ', $ringPoints));
        }

        // 4 Axis Spokes
        $svg[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#cbd5e1" stroke-width="1"/>', $cx, $cy, $cx, $cy - $r);
        $svg[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#cbd5e1" stroke-width="1"/>', $cx, $cy, $cx + $r, $cy);
        $svg[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#cbd5e1" stroke-width="1"/>', $cx, $cy, $cx, $cy + $r);
        $svg[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#cbd5e1" stroke-width="1"/>', $cx, $cy, $cx - $r, $cy);

        // Data Polygon Coordinates (Top, Right, Bottom, Left)
        $valTop = min(100, max(0, $data[0] ?? 0));
        $valRight = min(100, max(0, $data[1] ?? 0));
        $valBottom = min(100, max(0, $data[2] ?? 0));
        $valLeft = min(100, max(0, $data[3] ?? 0));

        $polyCoords = [
            [$cx, $cy - ($r * ($valTop / 100))],
            [$cx + ($r * ($valRight / 100)), $cy],
            [$cx, $cy + ($r * ($valBottom / 100))],
            [$cx - ($r * ($valLeft / 100)), $cy],
        ];
        $polyPointsStr = implode(' ', array_map(fn ($c) => sprintf('%.1f,%.1f', $c[0], $c[1]), $polyCoords));

        // Fill Polygon & Stroke
        $svg[] = sprintf('<polygon points="%s" fill="rgba(58, 82, 238, 0.22)" stroke="%s" stroke-width="2"/>', $polyPointsStr, $brandColor);

        // Vertices markers & Label Pills
        $positions = [
            ['coord' => $polyCoords[0], 'label' => $labels[0] ?? '', 'pct' => $valTop, 'tx' => $cx, 'ty' => $cy - $r - 8, 'anchor' => 'middle'],
            ['coord' => $polyCoords[1], 'label' => $labels[1] ?? '', 'pct' => $valRight, 'tx' => $cx + $r + 6, 'ty' => $cy + 3, 'anchor' => 'start'],
            ['coord' => $polyCoords[2], 'label' => $labels[2] ?? '', 'pct' => $valBottom, 'tx' => $cx, 'ty' => $cy + $r + 12, 'anchor' => 'middle'],
            ['coord' => $polyCoords[3], 'label' => $labels[3] ?? '', 'pct' => $valLeft, 'tx' => $cx - $r - 6, 'ty' => $cy + 3, 'anchor' => 'end'],
        ];

        foreach ($positions as $pos) {
            $c = $pos['coord'];
            $svg[] = sprintf('<circle cx="%.1f" cy="%.1f" r="3.5" fill="%s" stroke="#ffffff" stroke-width="1.5"/>', $c[0], $c[1], $brandColor);

            // Domain Name & Pct
            $shortName = $this->abbreviateDomainName($pos['label']);
            $text = sprintf('%s (%d%%)', $shortName, $pos['pct']);
            $svg[] = sprintf('<text x="%.1f" y="%.1f" font-size="7" font-weight="600" fill="#334155" text-anchor="%s">%s</text>', $pos['tx'], $pos['ty'], $pos['anchor'], htmlspecialchars($text));
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Render a vector SVG Stacked Bar Chart for question response times.
     *
     * @param array{labels: list<string>, correct: list<int>, incorrect: list<int>, totalQuestions: int} $histogramData
     */
    public function renderHistogramSvg(array $histogramData, int $width = 265, int $height = 110): string
    {
        $labels = $histogramData['labels'] ?? [];
        $correct = $histogramData['correct'] ?? [];
        $incorrect = $histogramData['incorrect'] ?? [];

        if (empty($labels)) {
            return $this->renderEmptyChartSvg($width, $height, 'No timing data recorded.');
        }

        $padLeft = 24;
        $padRight = 12;
        $padTop = 20;
        $padBottom = 20;

        $plotW = $width - $padLeft - $padRight;
        $plotH = $height - $padTop - $padBottom;

        $maxVal = 1;
        foreach ($labels as $i => $lbl) {
            $sum = ($correct[$i] ?? 0) + ($incorrect[$i] ?? 0);
            if ($sum > $maxVal) {
                $maxVal = $sum;
            }
        }

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" style="background:#ffffff; font-family:\'DejaVu Sans\', Arial, sans-serif;">', $width, $height, $width, $height);
        $svg[] = sprintf('<rect x="0.5" y="0.5" width="%d" height="%d" rx="6" fill="#f8fafc" stroke="#cbd5e1" stroke-width="1"/>', $width - 1, $height - 1);
        $svg[] = '<text x="10" y="14" font-size="8" font-weight="bold" fill="#0f172a" text-transform="uppercase" letter-spacing="0.5">Time Distribution</text>';

        $numBars = count($labels);
        $barSlot = $plotW / max(1, $numBars);
        $barW = min(22, $barSlot * 0.65);

        foreach ($labels as $i => $lbl) {
            $corCount = $correct[$i] ?? 0;
            $incCount = $incorrect[$i] ?? 0;
            $totalCount = $corCount + $incCount;

            $slotCenter = $padLeft + ($i * $barSlot) + ($barSlot / 2);
            $barX = $slotCenter - ($barW / 2);

            $totalH = ($totalCount / $maxVal) * $plotH;
            $corH = $totalCount > 0 ? ($corCount / $maxVal) * $plotH : 0;
            $incH = $totalCount > 0 ? ($incCount / $maxVal) * $plotH : 0;

            $baseY = $padTop + $plotH;

            if ($incH > 0) {
                $incY = $baseY - $incH;
                $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#f43f5e" rx="2"/>', $barX, $incY, $barW, $incH);
            }

            if ($corH > 0) {
                $corY = $baseY - $incH - $corH;
                $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#10b981" rx="2"/>', $barX, $corY, $barW, $corH);
            }

            if ($totalCount > 0) {
                $textY = $baseY - $totalH - 3;
                $svg[] = sprintf('<text x="%.1f" y="%.1f" font-size="7" font-weight="bold" fill="#475569" text-anchor="middle">%d</text>', $slotCenter, $textY, $totalCount);
            }

            $svg[] = sprintf('<text x="%.1f" y="%d" font-size="6.5" fill="#64748b" text-anchor="middle">%s</text>', $slotCenter, $height - 6, htmlspecialchars($lbl));
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
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
            if (! empty($renderedIndices) && ($count - 1 - end($renderedIndices)) < ($step / 2)) {
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

    private function formatShortDate(string $date): string
    {
        $time = strtotime($date);
        if ($time !== false) {
            return date('M j', $time);
        }

        return $date;
    }

    private function abbreviateDomainName(string $name): string
    {
        $map = [
            'Craft and Structure' => 'Craft & Struct',
            'Information and Ideas' => 'Info & Ideas',
            'Standard English Conventions' => 'Conventions',
            'Expression of Ideas' => 'Expression',
            'Algebra' => 'Algebra',
            'Advanced Math' => 'Adv Math',
            'Problem-Solving and Data Analysis' => 'Problem-Solving',
            'Geometry and Trigonometry' => 'Geo & Trig',
        ];

        return $map[$name] ?? $name;
    }
}
