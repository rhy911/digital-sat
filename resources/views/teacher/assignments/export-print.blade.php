<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $assignment->title }} — Classroom Assignment Performance Report</title>
    <style>
        @page {
            margin: 26pt 28pt 36pt 28pt;
            size: letter landscape;
        }
        * {
            box-sizing: border-box;
        }
        body {
            color: #0f172a;
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.45;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        h1, h2, h3, p { margin: 0; padding: 0; }

        /* HEADER BANNER - INK-FRIENDLY MODERN ACADEMIC */
        .masthead {
            border-bottom: 2pt solid #0f172a;
            padding-bottom: 8pt;
            margin-bottom: 10pt;
        }
        .masthead table { width: 100%; border-collapse: collapse; }
        .masthead-eyebrow {
            color: #0284c7;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1pt;
            text-transform: uppercase;
            margin-bottom: 3pt;
        }
        .masthead-title {
            color: #0f172a;
            font-size: 19pt;
            font-weight: bold;
            line-height: 1.2;
            letter-spacing: -0.2pt;
        }
        .masthead-subtitle {
            color: #475569;
            font-size: 9.5pt;
            margin-top: 3pt;
        }
        .masthead-badge {
            background: #f8fafc;
            border: 1pt solid #cbd5e1;
            border-radius: 4pt;
            padding: 5pt 9pt;
            text-align: right;
            font-size: 8.5pt;
            color: #475569;
            line-height: 1.4;
        }
        .masthead-status {
            display: inline-block;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            padding: 1.5pt 6pt;
            border-radius: 3pt;
            margin-bottom: 2pt;
        }
        .status-published { background: #dcfce7; color: #166534; border: 1pt solid #86efac; }
        .status-closed { background: #fee2e2; color: #991b1b; border: 1pt solid #fca5a5; }

        /* METADATA STRIP */
        .meta-strip {
            background: #f8fafc;
            border: 1pt solid #e2e8f0;
            border-radius: 5pt;
            padding: 7pt 10pt;
            margin-bottom: 12pt;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            vertical-align: top;
            padding: 0 8pt;
            border-right: 1pt solid #e2e8f0;
        }
        .meta-table td:first-child { padding-left: 0; }
        .meta-table td:last-child { border-right: none; padding-right: 0; }
        .meta-label {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.4pt;
            margin-bottom: 2pt;
        }
        .meta-val {
            font-size: 9.5pt;
            font-weight: 600;
            color: #0f172a;
        }
        .instructions-callout {
            border-top: 1pt dashed #cbd5e1;
            margin-top: 6pt;
            padding-top: 5pt;
            font-size: 9pt;
            color: #334155;
            line-height: 1.4;
        }

        /* STATS KPI CARDS */
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12pt;
        }
        .stat-card {
            background: #f8fafc;
            border: 1pt solid #cbd5e1;
            border-radius: 5pt;
            padding: 7pt 10pt;
            text-align: center;
            width: 25%;
        }
        .stat-val {
            color: #0f172a;
            font-size: 20pt;
            font-weight: bold;
            display: block;
            line-height: 1.1;
        }
        .stat-lbl {
            color: #64748b;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            margin-top: 3pt;
            display: block;
        }
        .stat-sub {
            color: #64748b;
            font-size: 8pt;
            margin-top: 2pt;
            display: block;
        }

        /* SECTION HEADINGS */
        .section-heading {
            border-left: 3pt solid #0f172a;
            padding-left: 6pt;
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            color: #0f172a;
            margin: 12pt 0 7pt;
        }

        /* DATA TABLES */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            table-layout: fixed;
        }
        table.data-table thead {
            display: table-header-group;
        }
        table.data-table th {
            background: #f1f5f9;
            color: #1e293b;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 0.4pt;
            padding: 5.5pt 7pt;
            text-align: left;
            text-transform: uppercase;
            border-top: 1pt solid #cbd5e1;
            border-bottom: 1.5pt solid #94a3b8;
        }
        table.data-table td {
            border-bottom: 1pt solid #e2e8f0;
            padding: 5.5pt 7pt;
            vertical-align: middle;
            font-size: 9.5pt;
            word-wrap: break-word;
        }
        table.data-table tr:nth-child(even) td {
            background: #fbfcfe;
        }
        table.data-table tbody tr {
            page-break-inside: avoid;
        }

        /* STATUS BADGES */
        .badge {
            display: inline-block;
            font-size: 8pt;
            font-weight: bold;
            padding: 2pt 5pt;
            border-radius: 3pt;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }
        .badge-completed { background: #dcfce7; color: #15803d; border: 1pt solid #86efac; }
        .badge-late { background: #fffbeb; color: #b45309; border: 1pt solid #fde68a; }
        .badge-progress { background: #eff6ff; color: #1d4ed8; border: 1pt solid #bfdbfe; }
        .badge-pending { background: #f8fafc; color: #64748b; border: 1pt solid #cbd5e1; }
        .badge-withdrawn { background: #fef2f2; color: #b91c1c; border: 1pt solid #fecaca; }

        .badge-easy { background: #ecfdf5; color: #047857; border: 1pt solid #a7f3d0; }
        .badge-medium { background: #fffbeb; color: #b45309; border: 1pt solid #fde68a; }
        .badge-hard { background: #fef2f2; color: #b91c1c; border: 1pt solid #fecaca; }

        .badge-mcq { background: #f1f5f9; color: #334155; border: 1pt solid #cbd5e1; font-size: 7.5pt; }
        .badge-spr { background: #ede9fe; color: #5b21b6; border: 1pt solid #ddd6fe; font-size: 7.5pt; }

        /* PROGRESS METER IN TABLE */
        .meter-container {
            width: 100%;
            background: #e2e8f0;
            height: 5pt;
            border-radius: 2.5pt;
            overflow: hidden;
            margin-top: 2pt;
        }
        .meter-bar {
            height: 5pt;
            border-radius: 2.5pt;
        }
        .meter-red { background: #dc2626; }
        .meter-amber { background: #d97706; }
        .meter-green { background: #16a34a; }

        /* INTERVENTION SUMMARY BOX */
        .diag-banner {
            background: #f8fafc;
            border: 1pt solid #cbd5e1;
            border-left: 3.5pt solid #0284c7;
            border-radius: 5pt;
            padding: 7pt 10pt;
            margin-bottom: 10pt;
        }
        .diag-table {
            width: 100%;
            border-collapse: collapse;
        }
        .diag-table td {
            vertical-align: middle;
            padding: 0 8pt;
            border-right: 1pt solid #e2e8f0;
        }
        .diag-table td:first-child { padding-left: 0; }
        .diag-table td:last-child { border-right: none; padding-right: 0; }
        .diag-num {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            display: inline-block;
            margin-right: 4pt;
        }
        .diag-txt {
            font-size: 8pt;
            color: #64748b;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.4pt;
        }

        .missed-list {
            margin-top: 2pt;
            font-size: 8pt;
            color: #dc2626;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    @php
        $assignedCount = (int) ($report['metrics']['assigned'] ?? 0);
        $completedCount = (int) ($report['metrics']['completed'] ?? 0);
        $inProgressCount = (int) ($report['metrics']['in_progress'] ?? 0);
        $completionRate = $assignedCount > 0 ? (int) round(($completedCount / $assignedCount) * 100) : 0;
        $notStartedCount = max(0, $assignedCount - $completedCount - $inProgressCount);

        // Smart STEM formula cleaner for printed diagnostics
        $cleanStem = function($text) {
            if (!$text) return '—';
            $s = strip_tags($text);
            $replacements = [
                '/\\\\frac\{([^}]+)\}\{([^}]+)\}/' => '$1/$2',
                '/\\\\sqrt\{([^}]+)\}/' => '√($1)',
                '/\\\\sqrt/' => '√',
                '/\\\\cdot/' => '·',
                '/\\\\times/' => '×',
                '/\\\\le/' => '≤',
                '/\\\\ge/' => '≥',
                '/\\\\neq/' => '≠',
                '/\\\\approx/' => '≈',
                '/\\\\pm/' => '±',
                '/\\\\pi/' => 'π',
                '/\\\\theta/' => 'θ',
                '/\\\\degree/' => '°',
                '/\^2/' => '²',
                '/\^3/' => '³',
                '/\^0/' => '⁰',
                '/\^1/' => '¹',
                '/\$([^\$]+)\$/' => '$1',
                '/\\\\\((.*?)\\\\\)/' => '$1',
                '/\\\\\[(.*?)\\\\\]/' => '$1',
                '/\s+/' => ' ',
            ];
            $s = preg_replace(array_keys($replacements), array_values($replacements), $s);
            return trim($s ?? '');
        };
    @endphp

    {{-- ========================================================================= --}}
    {{-- SECTION 1: DOCUMENT HEADER & ADMINISTRATIVE METADATA                     --}}
    {{-- ========================================================================= --}}
    <div class="masthead">
        <table>
            <tr>
                <td>
                    <div class="masthead-eyebrow">Digital SAT Platform &middot; Class Performance Report</div>
                    <div class="masthead-title">{{ $assignment->title }}</div>
                    <div class="masthead-subtitle">
                        Classroom: <strong>{{ $assignment->classroom->name }}</strong> &nbsp;&bull;&nbsp;
                        Test Form: <strong>{{ $assignment->test->title }}{{ $assignment->sectionSuffix() }}</strong> &nbsp;&bull;&nbsp;
                        Teacher: <strong>{{ $assignment->teacher?->name ?? 'Course Instructor' }}</strong>
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top; width: 25%;">
                    <div class="masthead-badge">
                        <span class="masthead-status {{ $assignment->status === 'published' ? 'status-published' : 'status-closed' }}">
                            {{ ucfirst($assignment->status) }}
                        </span><br>
                        Assignment ID: <strong>#{{ $assignment->id }}</strong><br>
                        Printed: <strong>{{ now()->format('M j, Y · g:i A') }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- METADATA STRIP --}}
    <div class="meta-strip">
        <table class="meta-table">
            <tr>
                <td>
                    <div class="meta-label">Available From</div>
                    <div class="meta-val">
                        {{ $assignment->available_at ? $assignment->available_at->format('M j, Y · g:i A') : 'Open immediately' }}
                    </div>
                </td>
                <td>
                    <div class="meta-label">Due Date</div>
                    <div class="meta-val">
                        {{ $assignment->due_at ? $assignment->due_at->format('M j, Y · g:i A') : 'No deadline' }}
                    </div>
                </td>
                <td>
                    <div class="meta-label">Timezone</div>
                    <div class="meta-val">Asia/Ho_Chi_Minh</div>
                </td>
                <td>
                    <div class="meta-label">Attempt Limit</div>
                    <div class="meta-val">{{ $assignment->attempt_limit }} {{ Str::plural('attempt', $assignment->attempt_limit) }}</div>
                </td>
                <td>
                    <div class="meta-label">Clock Mode</div>
                    <div class="meta-val">{{ $assignment->isTimedExam() ? 'Standard Timed' : 'Flexible Clock' }}</div>
                </td>
                <td>
                    <div class="meta-label">Assignment Scope</div>
                    <div class="meta-val">{{ $assignment->isSectionAssignment() ? $assignment->sectionLabel() . ' Only' : 'Full Practice Exam' }}</div>
                </td>
            </tr>
        </table>
        @if(filled($assignment->instructions))
            <div class="instructions-callout">
                <strong>Teacher Instructions:</strong> {{ $assignment->instructions }}
            </div>
        @endif
    </div>

    {{-- SUMMARY STATS CARDS --}}
    <table class="stats-table">
        <tr>
            <td class="stat-card">
                <span class="stat-val">{{ $assignedCount }}</span>
                <span class="stat-lbl">Assigned Students</span>
                <span class="stat-sub">Active class roster</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #16a34a;">{{ $completedCount }}</span>
                <span class="stat-lbl">Completed Attempts</span>
                <span class="stat-sub">{{ $completionRate }}% class completion rate</span>
            </td>
            <td class="stat-card">
                <span class="stat-val" style="color: #2563eb;">{{ $inProgressCount }}</span>
                <span class="stat-lbl">In Progress</span>
                <span class="stat-sub">{{ $notStartedCount }} not yet started</span>
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
                <span class="stat-sub">
                    @if ($assignment->assign_type === 'section')
                        {{ $assignment->sectionLabel() }} Section Average
                    @else
                        R&amp;W: {{ $report['metrics']['average_rw'] ?? '—' }} &bull; Math: {{ $report['metrics']['average_math'] ?? '—' }}
                    @endif
                </span>
            </td>
        </tr>
    </table>

    {{-- ========================================================================= --}}
    {{-- SECTION 1: STUDENT ROSTER & INDIVIDUAL TEST RESULTS                       --}}
    {{-- ========================================================================= --}}
    <div class="section-heading">Student Roster &amp; Test Results ({{ $assignedCount }} Students)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">#</th>
                <th style="width: 25%;">Student Name &amp; Email</th>
                <th style="width: 13%; text-align: center;">Attempt Status</th>
                <th style="width: 11%; text-align: center;">Attempts</th>
                <th style="width: 16%; text-align: center;">Completed At</th>
                <th style="width: 11%; text-align: center;">Best Score</th>
                @if ($assignment->assign_type !== 'section')
                    <th style="width: 10%; text-align: center;">R&amp;W (800)</th>
                    <th style="width: 10%; text-align: center;">Math (800)</th>
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
                    
                    $badgeClass = $isWithdrawn 
                        ? 'badge-withdrawn' 
                        : ($row['in_progress'] ? 'badge-progress' : ($row['best'] ? ($row['late'] ? 'badge-late' : 'badge-completed') : 'badge-pending'));
                    
                    $best = $row['best'];
                    $bestScore = $best
                        ? ($assignment->assign_type === 'section'
                            ? ($assignment->section_type === 'reading_writing' ? $best->score_reading_writing : $best->score_math)
                            : $best->total_score)
                        : null;
                    $maxAllowed = $row['recipient']->maxAllowedAttempts();
                @endphp
                <tr>
                    <td style="text-align: center; color: #64748b; font-size: 8.5pt;">{{ $loop->iteration }}</td>
                    <td>
                        <strong style="color: #0f172a; font-size: 9.5pt;">{{ $row['recipient']->student->name }}</strong><br>
                        <span style="color: #64748b; font-size: 8pt;">{{ $row['recipient']->student->email }}</span>
                    </td>
                    <td style="text-align: center;">
                        <span class="badge {{ $badgeClass }}">{{ $statusText }}</span>
                    </td>
                    <td style="text-align: center; font-size: 9.5pt;">
                        <strong>{{ $row['completed_count'] }}</strong> / {{ $maxAllowed }}
                        @if($row['recipient']->extra_attempts > 0)
                            <br><span style="font-size: 8pt; color: #047857; font-weight: bold;">(+{{ $row['recipient']->extra_attempts }} bonus)</span>
                        @endif
                    </td>
                    <td style="text-align: center; color: #475569; font-size: 8.5pt;">
                        {{ $best?->completed_at ? $best->completed_at->format('M j, Y · g:i A') : '—' }}
                    </td>
                    <td style="text-align: center;">
                        @if($bestScore)
                            <strong style="color: #0f172a; font-size: 11pt;">{{ $bestScore }}</strong>
                        @else
                            <span style="color: #94a3b8;">—</span>
                        @endif
                    </td>
                    @if ($assignment->assign_type !== 'section')
                        <td style="text-align: center; color: #0284c7; font-weight: bold; font-size: 9.5pt;">
                            {{ $best?->score_reading_writing ?? '—' }}
                        </td>
                        <td style="text-align: center; color: #d97706; font-weight: bold; font-size: 9.5pt;">
                            {{ $best?->score_math ?? '—' }}
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $assignment->assign_type === 'section' ? 6 : 8 }}" style="text-align: center; color: #64748b; font-style: italic; padding: 14pt;">
                        No students assigned to this classroom assignment yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ========================================================================= --}}
    {{-- SECTION 2: ITEM & QUESTION ANALYSIS (DEDICATED PAGE)                      --}}
    {{-- ========================================================================= --}}
    @if(!empty($report['questionAnalysis']))
        <div style="page-break-before: always;"></div>

        @php
            $analysisItems = collect($report['questionAnalysis']);
            $totalQuestions = $analysisItems->count();
            $highRiskCount = $analysisItems->where('incorrect_rate', '>=', 50)->count();
            $moderateRiskCount = $analysisItems->whereBetween('incorrect_rate', [25, 49.99])->count();
            $lowRiskCount = $analysisItems->where('incorrect_rate', '<', 25)->count();
            
            // Determine most challenging domain
            $domainBreakdown = $analysisItems->groupBy(fn($i) => $i['question']->skill_domain ?? 'General')
                ->map(fn($group) => round($group->average('incorrect_rate')))
                ->sortDesc();
            $mostChallengingDomain = $domainBreakdown->keys()->first();
            $mostChallengingAvg = $domainBreakdown->first();
        @endphp

        <div class="section-heading">Item &amp; Question Analysis (Class Difficulty Breakdown)</div>

        {{-- STRATEGIC INTERVENTION SUMMARY BANNER --}}
        <div class="diag-banner">
            <table class="diag-table">
                <tr>
                    <td style="width: 25%;">
                        <span class="diag-num">{{ $totalQuestions }}</span>
                        <span class="diag-txt">Questions<br>Analyzed</span>
                    </td>
                    <td style="width: 25%;">
                        <span class="diag-num" style="color: #dc2626;">{{ $highRiskCount }}</span>
                        <span class="diag-txt">High Error Items<br>(&ge; 50% incorrect)</span>
                    </td>
                    <td style="width: 25%;">
                        <span class="diag-num" style="color: #d97706;">{{ $moderateRiskCount }}</span>
                        <span class="diag-txt">Moderate Error Items<br>(25% &ndash; 49% error)</span>
                    </td>
                    <td style="width: 25%;">
                        <div class="meta-label">Highest Error Domain</div>
                        <div style="font-size: 9.5pt; font-weight: bold; color: #0f172a; white-space: nowrap; overflow: hidden;">
                            {{ Str::headline(str_replace('_', ' ', $mostChallengingDomain ?? 'General')) }}
                            @if($mostChallengingAvg !== null)
                                <span style="color: #dc2626; font-size: 8.5pt;">({{ $mostChallengingAvg }}% avg)</span>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Item / Module</th>
                    <th style="width: 6%; text-align: center;">Type</th>
                    <th style="width: 36%;">Question Prompt Snippet</th>
                    <th style="width: 17%;">Skill Domain</th>
                    <th style="width: 8%; text-align: center;">Difficulty</th>
                    <th style="width: 6%; text-align: center;">Key</th>
                    <th style="width: 15%; text-align: right;">Incorrect Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['questionAnalysis'] as $analysis)
                    @php
                        $q = $analysis['question'];
                        $rate = (float) $analysis['incorrect_rate'];
                        $meterColor = $rate >= 50 ? 'meter-red' : ($rate >= 25 ? 'meter-amber' : 'meter-green');
                        $diff = strtolower($q->difficulty ?? 'medium');
                        $diffClass = $diff === 'easy' ? 'badge-easy' : ($diff === 'hard' ? 'badge-hard' : 'badge-medium');
                        $isSpr = ($q->question_type ?? '') === 'spr';
                    @endphp
                    <tr>
                        <td>
                            <strong style="color: #0f172a; font-size: 9.5pt;">Q{{ $analysis['position'] }}</strong><br>
                            <span style="color: #64748b; font-size: 8pt;">{{ $analysis['module_label'] }}</span>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $isSpr ? 'badge-spr' : 'badge-mcq' }}">{{ $isSpr ? 'SPR' : 'MCQ' }}</span>
                        </td>
                        <td>
                            <div style="max-height: 2.8em; overflow: hidden; line-height: 1.4; color: #1e293b; font-size: 9pt;">
                                {{ Str::limit($cleanStem($q->stem), 125) }}
                            </div>
                        </td>
                        <td style="color: #334155; font-size: 8.5pt;">
                            {{ Str::headline(str_replace('_', ' ', $q->skill_domain ?? 'General Domain')) }}
                        </td>
                        <td style="text-align: center;">
                            <span class="badge {{ $diffClass }}">{{ ucfirst($diff) }}</span>
                        </td>
                        <td style="text-align: center;">
                            <strong style="color: #16a34a; font-size: 10pt;">{{ $analysis['correct_answer'] }}</strong>
                        </td>
                        <td style="text-align: right;">
                            <div style="font-weight: bold; color: {{ $rate >= 50 ? '#dc2626' : ($rate >= 25 ? '#d97706' : '#16a34a') }}; font-size: 9.5pt;">
                                {{ round($rate) }}% <span style="font-weight: normal; color: #64748b; font-size: 8pt;">({{ count($analysis['incorrect_students']) }}/{{ $analysis['total_presented'] }})</span>
                            </div>
                            <div class="meter-container">
                                <div class="meter-bar {{ $meterColor }}" style="width: {{ min(100, $rate) }}%;"></div>
                            </div>
                            @if($rate >= 50 && !empty($analysis['incorrect_students']))
                                <div class="missed-list" title="Students who missed or omitted this question">
                                    Missed: {{ collect($analysis['incorrect_students'])->pluck('student.name')->take(4)->implode(', ') }}@if(count($analysis['incorrect_students']) > 4)+{{ count($analysis['incorrect_students']) - 4 }}@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
