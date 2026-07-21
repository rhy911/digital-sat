<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $assignment->title }} — Classroom Assignment Report</title>
    <style>
        @page {
            margin: 18px 24px;
        }
        body {
            color: #0f172a;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        h1, h2, h3, p { margin: 0; padding: 0; }

        /* BRAND HEADER BANNER */
        .report-header {
            background: #0f172a;
            color: #ffffff;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 14px;
        }
        .report-header table { width: 100%; border-collapse: collapse; }
        .brand-title {
            color: #38bdf8;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .main-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.2;
        }
        .meta-line {
            color: #94a3b8;
            font-size: 9.5px;
            margin-top: 3px;
        }
        .badge-header {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-align: right;
        }

        /* STATS SUMMARY CARDS */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
            width: 25%;
        }
        .stat-val {
            color: #0f172a;
            font-size: 18px;
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
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 16px 0 8px;
            padding-bottom: 3px;
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
            font-size: 8.5px;
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
            font-size: 9.5px;
        }
        table.data-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            font-size: 8.5px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-completed { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-progress { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-pending { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .badge-withdrawn { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .badge-easy { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-medium { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-hard { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        /* PROGRESS METER IN TABLE */
        .meter-container {
            width: 100%;
            background: #e2e8f0;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 2px;
        }
        .meter-bar {
            height: 6px;
            border-radius: 3px;
        }
        .meter-red { background: #dc2626; }
        .meter-amber { background: #d97706; }
        .meter-green { background: #16a34a; }

        /* FOOTER */
        .page-footer {
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 8.5px;
            margin-top: 20px;
            padding-top: 6px;
        }
        .page-footer table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    <!-- BRAND HEADER -->
    <div class="report-header">
        <table>
            <tr>
                <td>
                    <div class="brand-title">Digital SAT &middot; Teacher Assignment Report</div>
                    <div class="main-title">{{ $assignment->title }}</div>
                    <div class="meta-line">
                        Classroom: <strong>{{ $assignment->classroom->name }}</strong> &nbsp;&bull;&nbsp;
                        Test Form: <strong>{{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}</strong> &nbsp;&bull;&nbsp;
                        Generated: <strong>{{ now()->format('F j, Y · g:i A') }}</strong>
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div class="badge-header">
                        Class Performance Report<br>
                        Assignment ID: #{{ $assignment->id }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- STATS SUMMARY CARDS -->
    <table class="stats-table">
        <tr>
            <td class="stat-card">
                <span class="stat-val">{{ $report['metrics']['assigned'] }}</span>
                <span class="stat-lbl">Assigned Students</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #16a34a;">{{ $report['metrics']['completed'] }}</span>
                <span class="stat-lbl">Completed Attempts</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #2563eb;">{{ $report['metrics']['in_progress'] }}</span>
                <span class="stat-lbl">In Progress</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #0f172a;">
                    @if ($assignment->assign_type === 'section')
                        {{ $assignment->section_type === 'reading_writing' ? ($report['metrics']['average_rw'] ? $report['metrics']['average_rw'] . ' / 800' : '—') : ($report['metrics']['average_math'] ? $report['metrics']['average_math'] . ' / 800' : '—') }}
                    @else
                        {{ $report['metrics']['average_score'] ? $report['metrics']['average_score'] . ' / 1600' : '—' }}
                    @endif
                </span>
                <span class="stat-lbl">Class Average Score</span>
            </td>
        </tr>
    </table>

    <!-- SECTION 1: STUDENT ROSTER & RESULTS -->
    <div class="section-title">Student Roster &amp; Test Results</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 28%;">Student Name &amp; Email</th>
                <th style="width: 14%;">Attempt Status</th>
                <th style="width: 14%;">Attempts Limit</th>
                <th style="width: 14%;">Best Score</th>
                @if ($assignment->assign_type !== 'section')
                    <th style="width: 15%;">R&amp;W Score</th>
                    <th style="width: 15%;">Math Score</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                @php
                    $isWithdrawn = $row['recipient']->status === 'withdrawn';
                    $statusText = $isWithdrawn
                        ? 'Withdrawn'
                        : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started'));
                    
                    $badgeClass = $isWithdrawn ? 'badge-withdrawn' : ($row['in_progress'] ? 'badge-progress' : ($row['best'] ? 'badge-completed' : 'badge-pending'));
                    
                    $best = $row['best'];
                    $bestScore = $best
                        ? ($assignment->assign_type === 'section'
                            ? ($assignment->section_type === 'reading_writing' ? $best->score_reading_writing : $best->score_math)
                            : $best->total_score)
                        : null;
                @endphp
                <tr style="page-break-inside: avoid;">
                    <td>
                        <strong>{{ $row['recipient']->student->name }}</strong><br>
                        <span style="color: #64748b; font-size: 8.5px;">{{ $row['recipient']->student->email }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                    </td>
                    <td><strong>{{ $row['completed_count'] }}</strong> / {{ $assignment->attempt_limit }}</td>
                    <td>
                        @if($bestScore)
                            <strong style="color: #0f172a; font-size: 11px;">{{ $bestScore }}</strong>
                        @else
                            <span style="color: #94a3b8;">—</span>
                        @endif
                    </td>
                    @if ($assignment->assign_type !== 'section')
                        <td>{{ $best?->score_reading_writing ?? '—' }}</td>
                        <td>{{ $best?->score_math ?? '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $assignment->assign_type === 'section' ? 4 : 6 }}" style="text-align: center; color: #64748b; font-style: italic; padding: 12px;">No recipients assigned yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SECTION 2: ITEM & QUESTION ANALYSIS -->
    @if(!empty($report['questionAnalysis']))
        <div style="page-break-before: always;"></div>

        <div class="section-title">Item &amp; Question Analysis (Class Difficulty Breakdown)</div>
        <p style="color: #64748b; font-size: 9px; margin-bottom: 10px;">
            Questions presented to students ordered by highest incorrect rate descending.
        </p>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%;">Item / Module</th>
                    <th style="width: 36%;">Question Prompt Snippet</th>
                    <th style="width: 18%;">Skill Domain</th>
                    <th style="width: 10%;">Difficulty</th>
                    <th style="width: 8%;">Correct</th>
                    <th style="width: 14%;">Incorrect Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['questionAnalysis'] as $analysis)
                    @php
                        $q = $analysis['question'];
                        $rate = $analysis['incorrect_rate'];
                        $meterColor = $rate >= 50 ? 'meter-red' : ($rate >= 25 ? 'meter-amber' : 'meter-green');
                        $diff = strtolower($q->difficulty ?? 'medium');
                        $diffClass = $diff === 'easy' ? 'badge-easy' : ($diff === 'hard' ? 'badge-hard' : 'badge-medium');
                    @endphp
                    <tr style="page-break-inside: avoid;">
                        <td>
                            <strong>Q{{ $analysis['position'] }}</strong> &middot; <span style="color:#64748b;">{{ $analysis['module_label'] }}</span>
                        </td>
                        <td>
                            <div style="max-height: 2.8em; overflow: hidden; line-height: 1.35; color: #1e293b;">
                                {{ Str::limit(strip_tags($q->stem), 130) }}
                            </div>
                        </td>
                        <td>{{ $q->skill_domain }}</td>
                        <td><span class="badge {{ $diffClass }}">{{ ucfirst($diff) }}</span></td>
                        <td><strong style="color: #16a34a;">{{ $analysis['correct_answer'] }}</strong></td>
                        <td>
                            <div style="font-weight: bold; color: {{ $rate >= 50 ? '#dc2626' : ($rate >= 25 ? '#d97706' : '#16a34a') }};">
                                {{ $rate }}% <span style="font-weight: normal; color: #64748b; font-size: 8.5px;">({{ count($analysis['incorrect_students']) }}/{{ $analysis['total_presented'] }})</span>
                            </div>
                            <div class="meter-container">
                                <div class="meter-bar {{ $meterColor }}" style="width: {{ $rate }}%;"></div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- PAGE FOOTER -->
    <div class="page-footer">
        <table>
            <tr>
                <td>Digital SAT Online Testing Platform &middot; Teacher Assignment Report</td>
                <td style="text-align: right;">Generated for {{ auth()->user()?->name ?? 'Teacher' }} on {{ now()->format('F j, Y \a\t g:i A') }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
