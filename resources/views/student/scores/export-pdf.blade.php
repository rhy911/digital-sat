<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Score Report - {{ $userTest->test->title }}</title>
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
            font-size: 20px;
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
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .hero-table { width: 100%; border-collapse: collapse; }
        .score-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 16px;
            text-align: center;
        }
        .score-main {
            color: #0f172a;
            font-size: 36px;
            font-weight: bold;
            line-height: 1;
        }
        .score-max {
            color: #64748b;
            font-size: 14px;
            font-weight: normal;
        }
        .score-subtitle {
            color: #475569;
            font-size: 10px;
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
            color: #475569;
            font-size: 10px;
            line-height: 1.45;
            padding-left: 16px;
        }

        /* STATS SUMMARY GRID */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .stat-card {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
            width: 25%;
        }
        .stat-val {
            color: #0f172a;
            font-size: 16px;
            font-weight: bold;
            display: block;
        }
        .stat-lbl {
            color: #64748b;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* SECTION HEADINGS */
        .section-title {
            color: #0f172a;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 18px 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #0f172a;
        }

        /* TABLES */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.data-table th {
            background: #1e293b;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            text-align: left;
            text-transform: uppercase;
        }
        table.data-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-correct { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-wrong { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge-omitted { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-easy { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-medium { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-hard { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .pill-sec {
            background: #dbeafe;
            color: #1e40af;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .pill-sec.math {
            background: #f3e8ff;
            color: #6b21a8;
        }

        /* PROGRESS METER IN TABLE */
        .meter-container {
            width: 100%;
            background: #e2e8f0;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 3px;
        }
        .meter-bar {
            height: 6px;
            border-radius: 3px;
        }
        .meter-green { background: #16a34a; }
        .meter-amber { background: #d97706; }
        .meter-red { background: #dc2626; }

        /* FOOTER */
        .page-footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 9px;
            margin-top: 24px;
            padding-top: 8px;
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
                    <div class="brand-title">Digital SAT &middot; Official Score Report</div>
                    <div class="main-title">{{ $userTest->test->title }}</div>
                    <div class="meta-line">
                        Student: <strong>{{ $userTest->user?->name ?? 'Student' }}</strong> ({{ $userTest->user?->email }}) &nbsp;&bull;&nbsp;
                        Completed: <strong>{{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y · g:i A') : 'In progress' }}</strong>
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="badge-header">
                        Calibrated 3PL IRT Model<br>
                        Form ID: {{ $userTest->test->id }}
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
                        @if ($userTest->attempt_type === 'section')
                            <div class="score-main">
                                {{ $userTest->section_type === 'reading_writing' ? ($userTest->score_reading_writing ?? '—') : ($userTest->score_math ?? '—') }}
                                <span class="score-max">/ 800</span>
                            </div>
                            <div class="score-subtitle">
                                {{ $userTest->section_type === 'reading_writing' ? 'Reading & Writing Score' : 'Math Score' }}
                            </div>
                        @elseif ($isScaledSatResult)
                            <div class="score-main">
                                {{ $userTest->total_score ?? '—' }}
                                <span class="score-max">/ 1600</span>
                            </div>
                            <div class="score-subtitle">Total SAT Practice Score</div>
                        @else
                            <div class="score-main">
                                {{ $correct }}
                                <span class="score-max">/ {{ $totalQ }}</span>
                            </div>
                            <div class="score-subtitle">Raw Correct Answers</div>
                        @endif
                    </div>
                </td>
                <td style="vertical-align: middle;">
                    <div class="disclosure-box">
                        @if ($userTest->attempt_type === 'section')
                            <p style="font-weight: bold; color: #0f172a; font-size: 11px; margin-bottom: 2px;">
                                Section Performance Estimate
                            </p>
                            <p>
                                @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                                    Adaptive IRT calculation based on your section routing.
                                    Likely score range: <strong class="score-range">{{ $userTest->section_type === 'reading_writing' ? ($userTest->score_reading_writing_lower . ' – ' . $userTest->score_reading_writing_upper) : ($userTest->score_math_lower . ' – ' . $userTest->score_math_upper) }}</strong>.
                                @else
                                    Estimated score based on answered questions in this section.
                                @endif
                            </p>
                        @elseif ($isScaledSatResult)
                            <p style="font-weight: bold; color: #0f172a; font-size: 11px; margin-bottom: 2px;">
                                Scale Score & Range Estimate
                            </p>
                            <p>
                                @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                                    Theta MLE 3PL IRT model score. Likely score range: <strong class="score-range">{{ $userTest->total_score_lower }} – {{ $userTest->total_score_upper }}</strong>.
                                @elseif($userTest->score_estimate_kind === 'normal_generic')
                                    Converted with standard digital SAT scoring table (v{{ $userTest->score_conversion_version }}).
                                @else
                                    Converted with official test form scoring table (v{{ $userTest->scoreConversionSet?->version ?? '1.0' }}).
                                @endif
                            </p>
                            @if($userTest->score_reading_writing && $userTest->score_math)
                                <p style="margin-top: 4px; font-weight: bold;">
                                    Reading &amp; Writing: <span style="color:#0a2d6e;">{{ $userTest->score_reading_writing }}</span> &nbsp;|&nbsp;
                                    Math: <span style="color:#0a2d6e;">{{ $userTest->score_math }}</span>
                                </p>
                            @endif
                        @else
                            <p style="font-weight: bold; color: #0f172a; font-size: 11px; margin-bottom: 2px;">
                                Raw Practice Summary
                            </p>
                            <p>{{ $correct }} of {{ $totalQ }} scored questions answered correctly.</p>
                        @endif
                        <p style="color: #94a3b8; font-size: 9px; margin-top: 4px; font-style: italic;">
                            Note: Practice estimates simulate official Bluebook scoring guidelines.
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
                <span class="stat-lbl">Total Questions</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #16a34a;">{{ $correct }}</span>
                <span class="stat-lbl">Correct Answers</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #dc2626;">{{ $wrong + $omitted }}</span>
                <span class="stat-lbl">Incorrect / Omitted</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #2563eb;">{{ $totalTimeFormatted }}</span>
                <span class="stat-lbl">Time Tracked ({{ $avgSeconds }}s/q)</span>
            </td>
        </tr>
    </table>

    <!-- KNOWLEDGE & SKILLS BREAKDOWN -->
    <div class="section-title">Knowledge &amp; Skill Performance</div>
    @if (count($domainSummaries))
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 18%;">Section</th>
                    <th style="width: 32%;">Domain</th>
                    <th style="width: 18%;">Correct / Total</th>
                    <th style="width: 20%;">Accuracy Meter</th>
                    <th style="width: 12%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domainSummaries as $domain)
                    @php
                        $pct = $domain['percentCorrect'];
                        $meterColor = $pct >= 75 ? 'meter-green' : ($pct >= 50 ? 'meter-amber' : 'meter-red');
                    @endphp
                    <tr>
                        <td><strong>{{ $domain['section'] }}</strong></td>
                        <td>{{ $domain['domain'] }}</td>
                        <td><strong>{{ $domain['correct'] }} / {{ $domain['total'] }}</strong> ({{ $pct }}%)</td>
                        <td>
                            <div style="font-weight: bold; font-size: 9px; margin-bottom: 2px;">{{ $pct }}%</div>
                            <div class="meter-container">
                                <div class="meter-bar {{ $meterColor }}" style="width: {{ $pct }}%;"></div>
                            </div>
                        </td>
                        <td>
                            @if($pct >= 75)
                                <span class="badge badge-correct">Mastered</span>
                            @elseif($pct >= 50)
                                <span class="badge" style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;">On Track</span>
                            @else
                                <span class="badge badge-wrong">Needs Focus</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #64748b; font-style: italic; margin-bottom: 16px;">No domain breakdown available for this attempt.</p>
    @endif

    <!-- QUESTION REVIEW TABLE -->
    <div class="section-title">Question-by-Question Detailed Review &amp; Time Tracked</div>
    @if (count($allAnswers))
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 16%;">Section &amp; Module</th>
                    <th style="width: 11%;">Result</th>
                    <th style="width: 30%;">Skill Domain</th>
                    <th style="width: 10%;">Difficulty</th>
                    <th style="width: 10%;">Time Spent</th>
                    <th style="width: 9%;">Your Answer</th>
                    <th style="width: 9%;">Correct</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($allAnswers as $row)
                    @php
                        $sec = $row['timeSpent'] ?? 0;
                        $m = floor($sec / 60);
                        $s = $sec % 60;
                        $rowTimeFormatted = $m > 0 ? "{$m}m {$s}s" : "{$sec}s";
                    @endphp
                    <tr style="page-break-inside: avoid;">
                        <td><strong>{{ $row['idx'] }}</strong></td>
                        <td>
                            <span class="pill-sec {{ $row['sectionType'] === 'math' ? 'math' : '' }}">
                                {{ $row['sectionName'] }}@if (!empty($row['moduleNumber'])) M{{ $row['moduleNumber'] }}@endif
                            </span>
                        </td>
                        <td>
                            @if ($row['statusKey'] === 'correct')
                                <span class="badge badge-correct">Correct</span>
                            @elseif ($row['statusKey'] === 'wrong')
                                <span class="badge badge-wrong">Incorrect</span>
                            @else
                                <span class="badge badge-omitted">Omitted</span>
                            @endif
                        </td>
                        <td>{{ $row['domainLabel'] }}</td>
                        <td>
                            @if(strtolower($row['difficulty'] ?? '') === 'easy')
                                <span class="badge badge-easy">Easy</span>
                            @elseif(strtolower($row['difficulty'] ?? '') === 'medium')
                                <span class="badge badge-medium">Medium</span>
                            @elseif(strtolower($row['difficulty'] ?? '') === 'hard')
                                <span class="badge badge-hard">Hard</span>
                            @else
                                <span style="color:#94a3b8;">—</span>
                            @endif
                        </td>
                        <td><strong>{{ $rowTimeFormatted }}</strong></td>
                        <td><strong>{{ $row['answer']->selected_answer ?: 'Omitted' }}</strong></td>
                        <td><strong style="color:#15803d;">{{ $row['correctAnswer'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color: #64748b; font-style: italic;">No question details recorded.</p>
    @endif

    <!-- PAGE FOOTER -->
    <div class="page-footer">
        <table>
            <tr>
                <td>Digital SAT Online Testing Platform &middot; Student Performance &amp; Time Tracking Report</td>
                <td style="text-align: right;">Generated on {{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
