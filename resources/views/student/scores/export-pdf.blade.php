<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Practice Score Report - {{ $userTest->test->title }}</title>
    <style>
        @page {
            margin: 20px 24px;
        }
        body {
            color: #0f172a;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        h1, h2, h3, p { margin: 0; padding: 0; }

        /* BRAND HEADER BANNER */
        .report-header {
            background: #0f172a;
            color: #ffffff;
            padding: 16px 20px;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .report-header table { width: 100%; border-collapse: collapse; }
        .brand-title {
            color: #38bdf8;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .main-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.2;
        }
        .meta-line {
            color: #94a3b8;
            font-size: 10px;
            margin-top: 4px;
        }
        .badge-header {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-align: right;
        }

        /* HERO SCORE CONTAINER */
        .hero-container {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 16px;
        }
        .hero-table { width: 100%; border-collapse: collapse; }
        .score-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: center;
        }
        .score-main {
            color: #0f172a;
            font-size: 32px;
            font-weight: bold;
            line-height: 1;
        }
        .score-max {
            color: #64748b;
            font-size: 13px;
            font-weight: normal;
        }
        .score-subtitle {
            color: #475569;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .score-range {
            color: #2563eb;
            font-size: 11px;
            font-weight: bold;
            margin-top: 4px;
        }
        .disclosure-box {
            padding-left: 16px;
            font-size: 10px;
            color: #475569;
            line-height: 1.45;
        }

        /* STATS TABLE */
        .stats-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        .stat-val {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            line-height: 1.1;
        }
        .stat-lbl {
            display: block;
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: bold;
            margin-top: 2px;
        }

        /* SECTION TITLES */
        .section-title {
            color: #0f172a;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .section-desc {
            color: #64748b;
            font-size: 9.5px;
            margin-bottom: 8px;
        }

        /* DATA TABLES */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
            margin-bottom: 14px;
        }
        .data-table th {
            background: #f1f5f9;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1.5px solid #94a3b8;
            color: #334155;
            font-weight: bold;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 5px 8px;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background: #fcfcfd;
        }


        /* PROGRESS METER IN TABLE */
        .meter-container {
            width: 100%;
            background: #e2e8f0;
            height: 5px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 2px;
        }
        .meter-bar {
            height: 5px;
            border-radius: 3px;
            background: #2563eb;
        }

        /* FOOTER */
        .page-footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8.5px;
            margin-top: 16px;
            padding-top: 6px;
        }
        .page-footer table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    @php
        $totalSeconds = collect($allAnswers)->sum('timeSpent');
        $totalMinutes = floor($totalSeconds / 60);
        $remSeconds = $totalSeconds % 60;
        $totalTimeFormatted = $totalMinutes > 0 ? "{$totalMinutes}m {$remSeconds}s" : "{$totalSeconds}s";
        $avgSeconds = count($allAnswers) > 0 ? (int) round($totalSeconds / count($allAnswers)) : 0;
    @endphp

    <!-- BRAND HEADER -->
    <div class="report-header">
        <table>
            <tr>
                <td>
                    <div class="brand-title">Digital SAT &middot; Practice Score Report</div>
                    <div class="main-title">{{ $userTest->test->title }}</div>
                    <div class="meta-line">
                        Student: <strong>{{ $userTest->user?->name ?? 'Student' }}</strong> ({{ $userTest->user?->email }}) &nbsp;&bull;&nbsp;
                        Completed: <strong>{{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y · g:i A') : 'In progress' }}</strong>
                        @if ($userTest->assignment)
                            &nbsp;&bull;&nbsp; Class: <strong>{{ $userTest->assignment->classroom?->name ?: ($userTest->assignment->title ?: 'Assignment') }}</strong>
                        @elseif ($userTest->examSession)
                            &nbsp;&bull;&nbsp; Session: <strong>{{ $userTest->examSession->title ?: 'Exam Session' }}</strong>
                        @else
                            &nbsp;&bull;&nbsp; Mode: <strong>Self-Paced Practice</strong>
                        @endif
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="badge-header">
                        {{ $scorePresentation['basisLabel'] }}<br>
                        Form ID: {{ $userTest->test?->id }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- HERO SCORE CARD -->
    <div class="hero-container">
        <table class="hero-table">
            <tr>
                <td style="width: 32%; vertical-align: middle;">
                    <div class="score-box">
                        <div class="score-main">
                            {{ $scorePresentation['score'] ?? '—' }}
                            <span class="score-max">/ {{ $scorePresentation['maxScore'] }}</span>
                        </div>
                        <div class="score-subtitle">{{ $scorePresentation['contextLabel'] ?? $scorePresentation['sectionLabel'] }}</div>
                        @if ($scorePresentation['hasRange'])
                            <div class="score-range">Range: {{ $scorePresentation['rangeFormatted'] }}</div>
                        @endif
                    </div>
                </td>
                <td style="vertical-align: middle;">
                    <div class="disclosure-box">
                        <p style="font-weight: bold; color: #0f172a; font-size: 10.5px; margin-bottom: 2px;">
                            {{ $scorePresentation['basisLabel'] }}
                        </p>
                        @if (!empty($scorePresentation['scoreDisclosure']))
                            <p style="margin-bottom: 4px;">{{ $scorePresentation['scoreDisclosure'] }}</p>
                        @endif
                        <p>{{ $scorePresentation['calculationDisclosure'] }}</p>

                        @if ($scorePresentation['isScaledSatResult'] && ($scorePresentation['rwScore'] !== null || $scorePresentation['mathScore'] !== null))
                            <p style="margin-top: 4px; font-weight: bold;">
                                @if ($scorePresentation['rwScore'] !== null)
                                    Reading &amp; Writing: <span style="color:#2563eb;">{{ $scorePresentation['rwScore'] }} / 800</span>
                                    @if ($scorePresentation['rwRange']) ({{ $scorePresentation['rwRange'] }}) @endif
                                @endif
                                @if ($scorePresentation['rwScore'] !== null && $scorePresentation['mathScore'] !== null)
                                    &nbsp;|&nbsp;
                                @endif
                                @if ($scorePresentation['mathScore'] !== null)
                                    Math: <span style="color:#059669;">{{ $scorePresentation['mathScore'] }} / 800</span>
                                    @if ($scorePresentation['mathRange']) ({{ $scorePresentation['mathRange'] }}) @endif
                                @endif
                            </p>
                        @endif

                        <p style="color: #64748b; font-size: 9px; margin-top: 4px; font-style: italic;">
                            {{ $scorePresentation['unofficialNotice'] }}
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- STATS SUMMARY CARDS -->
    <table class="stats-table">
        <tr>
            <td class="stat-card">
                <span class="stat-val">{{ $totalQ }}</span>
                <span class="stat-lbl">All Questions ({{ $unscored }} unscored)</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #166534;">{{ $correct }}</span>
                <span class="stat-lbl">Correct Answers</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #991b1b;">{{ $wrong }}</span>
                <span class="stat-lbl">Incorrect</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #475569;">{{ $omitted }}</span>
                <span class="stat-lbl">Omitted</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #2563eb;">{{ $totalTimeFormatted }}</span>
                <span class="stat-lbl">Time Tracked ({{ $avgSeconds }}s/q)</span>
            </td>
        </tr>
    </table>

    <!-- KNOWLEDGE & SKILLS BREAKDOWN -->
    <div class="section-title">Knowledge &amp; Skill Performance</div>
    <div class="section-desc">Performance across measured content domains. Learning diagnostics include unscored questions.</div>
    @if (count($domainSummaries))
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">Section</th>
                    <th style="width: 36%;">Domain</th>
                    <th style="width: 20%;">Correct / Total</th>
                    <th style="width: 22%;">Observed Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domainSummaries as $domain)
                    @php
                        $pct = $domain['percentCorrect'];
                    @endphp
                    <tr>
                        <td><strong>{{ $domain['section'] }}</strong></td>
                        <td>{{ $domain['domain'] }}</td>
                        <td><strong>{{ $domain['correct'] }} / {{ $domain['total'] }}</strong></td>
                        <td>
                            <div style="font-weight: bold; font-size: 9px; margin-bottom: 2px;">{{ $pct }}%</div>
                            <div class="meter-container">
                                <div class="meter-bar" style="width: {{ $pct }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #64748b; font-style: italic; margin-bottom: 16px;">No domain breakdown available for this attempt.</p>
    @endif

    <!-- DIFFICULTY BREAKDOWN -->
    <div class="section-title">Difficulty Breakdown</div>
    @if (count($difficultySummaries))
        <table class="data-table" style="margin-bottom: 16px;">
            <thead>
                <tr>
                    <th style="width: 25%;">Difficulty</th>
                    <th style="width: 25%;">Questions</th>
                    <th style="width: 25%;">Correct</th>
                    <th style="width: 25%;">Accuracy</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($difficultySummaries as $diff)
                    <tr>
                        <td><strong>{{ $diff['label'] }}</strong></td>
                        <td>{{ $diff['total'] }}</td>
                        <td>{{ $diff['correct'] }}</td>
                        <td><strong>{{ $diff['percentCorrect'] }}%</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- PAGE FOOTER -->
    <div class="page-footer">
        <table>
            <tr>
                <td>Digital SAT Practice Platform &middot; Diagnostic Score Summary</td>
                <td style="text-align: right;">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
