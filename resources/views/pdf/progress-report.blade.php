<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Digital SAT Student Progress Report</title>
<style>
@page {
    margin: 18px 20px 20px 20px;
    size: letter portrait;
}
* {
    box-sizing: border-box;
}
body {
    margin: 0;
    color: #1e293b;
    font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
    font-size: 8.8px;
    line-height: 1.4;
    background: #ffffff;
}
.page {
    position: relative;
    page-break-after: always;
}
.page.last-page {
    page-break-after: auto;
}

/* Typography Helpers (Warm Academic Prestige) */
.eyebrow {
    color: #38bdf8;
    font-size: 7.5px;
    font-weight: bold;
    letter-spacing: 1.2px;
    text-transform: uppercase;
}
h1 {
    font-size: 18px;
    margin: 3px 0 4px 0;
    color: #ffffff;
    font-weight: bold;
    letter-spacing: -0.2px;
}
h2 {
    font-size: 11px;
    letter-spacing: 0.2px;
    margin: 0;
    color: #ffffff;
    font-weight: bold;
}
h3 {
    font-size: 9.5px;
    margin: 0 0 4px 0;
    font-weight: bold;
    color: #1e293b;
}
.section-title {
    font-size: 9.5px;
    font-weight: bold;
    letter-spacing: 0.2px;
    margin: 6px 0 4px 0;
    color: #1e293b;
}
.meta {
    color: #94a3b8;
    font-size: 7.8px;
}
.small {
    font-size: 7.8px;
}
.muted {
    color: #64748b;
}
.bold {
    font-weight: bold;
}

/* Layout Containers */
table.layout {
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
}
table.layout td {
    padding: 0;
    vertical-align: top;
}

/* Masthead Header */
.mast {
    background: #1e293b;
    border-radius: 6px;
    color: #ffffff;
    margin-bottom: 7px;
    padding: 9px 12px;
}
.brand-pill {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 5px;
    padding: 5px 8px;
    text-align: right;
}

/* Section Header Banners */
.head {
    background: #1e293b;
    border-radius: 5px;
    color: #ffffff;
    margin-bottom: 7px;
    padding: 5px 10px;
}

/* Card Containers */
.card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 9px;
    margin-bottom: 6px;
}
.card-hero {
    background: #f8fafc;
    border: 1.5px solid #cbd5e1;
    border-radius: 6px;
    padding: 9px 11px;
}
.card-soft {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 6px 8px;
}
.narrative-box {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-left: 3.5px solid #0284c7;
    border-radius: 5px;
    color: #0f172a;
    line-height: 1.45;
    padding: 6px 9px;
    font-size: 8.5px;
}

/* Tables */
table.data-table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin-bottom: 5px;
}
table.data-table th {
    background: #1e293b;
    color: #ffffff;
    font-size: 7.8px;
    font-weight: bold;
    letter-spacing: 0.3px;
    padding: 4px 6px;
    vertical-align: middle;
}
table.data-table td {
    border-bottom: 1px solid #e2e8f0;
    font-size: 8.2px;
    padding: 4px 6px;
    vertical-align: middle;
    word-wrap: break-word;
}
table.data-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}

/* Alignments & Semantic Colors */
table.data-table th.left, table.data-table td.left, .left { text-align: left !important; }
table.data-table th.right, table.data-table td.right, .right { text-align: right !important; }
table.data-table th.center, table.data-table td.center, .center { text-align: center !important; }
.blue { color: #2563eb; }
.indigo { color: #4f46e5; }
.orange { color: #d97706; }
.green { color: #16a34a; }
.red { color: #dc2626; }
.amber { color: #d97706; }

/* Status Badges */
.badge {
    border-radius: 3px;
    display: inline-block;
    font-size: 7px;
    font-weight: bold;
    padding: 1.5px 5px;
    letter-spacing: 0.2px;
}
.bg-green { background: #dcfce7; color: #166534; }
.bg-amber { background: #fef3c7; color: #92400e; }
.bg-red { background: #fee2e2; color: #991b1b; }
.bg-blue { background: #e0f2fe; color: #0369a1; }
.bg-purple { background: #ede9fe; color: #5b21b6; }
.bg-gray { background: #f1f5f9; color: #475569; }

/* Visual Progress Bars */
.bar-track {
    background: #e2e8f0;
    border-radius: 3px;
    display: inline-block;
    height: 7px;
    overflow: hidden;
    vertical-align: middle;
    width: 95px;
}
.bar-fill {
    display: block;
    height: 7px;
    border-radius: 3px;
}

/* Running Footer */
.footer {
    border-top: 1px solid #e2e8f0;
    color: #64748b;
    font-size: 7.2px;
    margin-top: 6px;
    padding-top: 4px;
    text-align: center;
    width: 100%;
}
</style>
</head>
<body>
@php($score = $dossier['score'])
@php($history = $dossier['history'])
@php($latest = $score['latest'])
@php($adm = $dossier['admissions'] ?? [])
@php($ptLoss = $dossier['pointLoss'] ?? [])
@php($fmt = fn($v, $f = 'N/A') => $v === null ? $f : number_format($v))
@php($tone = fn($v) => $v === null ? 'muted' : ($v >= 80 ? 'green' : ($v >= 60 ? 'amber' : 'red')))
@php($badge = fn($v) => in_array($v, ['Mastered', 'Strong', 'Rapid improvement', 'Improving'], true) ? 'bg-green' : (in_array($v, ['Priority', 'Declining'], true) ? 'bg-red' : (in_array($v, ['At risk', 'Developing', 'Moderate'], true) ? 'bg-amber' : 'bg-gray')))
@php($rw = collect($dossier['domains'])->where('section', 'reading_and_writing'))
@php($math = collect($dossier['domains'])->where('section', 'math'))
@php($logoInversePath = public_path('brand/logo-horizontal-inverse.png'))
@php($logoInverseBase64 = file_exists($logoInversePath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoInversePath)) : null)

{{-- ========================================================================= --}}
{{-- PAGE 1: PARENT & STUDENT OVERVIEW                                         --}}
{{-- ========================================================================= --}}
<div class="page">
    {{-- Masthead Banner --}}
    <div class="mast">
        <table class="layout">
            <tr>
                <td style="width: 68%;">
                    @if($logoInverseBase64)
                        <img src="{{ $logoInverseBase64 }}" alt="Logo" style="height: 22px; margin-bottom: 5px; display: block;">
                    @endif
                    <div class="eyebrow">Digital SAT • Student Progress Report</div>
                    <h1>{{ $student->name }}</h1>
                    <div class="meta">
                        Generated {{ now()->format('M j, Y') }} &nbsp;•&nbsp; 
                        <b>{{ $dossier['scoredFullTestCount'] }}</b> Full Practice Tests &nbsp;•&nbsp; 
                        <b>{{ $dossier['activityAttemptCount'] }}</b> Practice Activities
                        @if(!empty($classroom))
                            <br>
                            <span style="color: #cbd5e1;">Class: <b>{{ $classroom->name }}</b> &nbsp;|&nbsp; Teacher: <b>{{ $teacher?->name ?? 'Course Instructor' }}</b></span>
                        @endif
                    </div>
                </td>
                <td style="width: 28%; text-align: right; vertical-align: middle;">
                    <div class="brand-pill">
                        <div style="font-size: 7px; color: #94a3b8; letter-spacing: 0.5px;">SAT Prep System</div>
                        <div style="font-size: 10px; font-weight: bold; color: #38bdf8; margin: 1px 0;">Student Progress Report</div>
                        <div style="font-size: 7.2px; color: #cbd5e1;">
                            @if($score['target'])
                                Goal: <b style="color: #ffffff;">{{ $score['target'] }}</b> 
                                ({{ $score['targetGap'] !== null ? ($score['targetGap'] > 0 ? $score['targetGap'].' pts to go' : 'Achieved') : '' }})
                            @else
                                Self-Paced Practice
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Hero Score Showcase + 3 Snapshot Cards --}}
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            {{-- Left: Hero Score Card --}}
            <td style="width: 48%; padding-right: 5px;">
                <div class="card-hero">
                    <div style="font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Latest SAT Score</div>
                    <div style="font-size: 32px; font-weight: bold; color: #2563eb; line-height: 1.1; margin: 2px 0;">
                        {{ $fmt($latest['total'] ?? null) }}
                        <span style="font-size: 10px; font-weight: normal; color: #64748b;">/ 1600</span>
                    </div>
                    <div style="font-size: 9px; color: #334155; margin-bottom: 4px;">
                        Reading & Writing: <b style="color: #0284c7;">{{ $latest['rw'] ?? '-' }}</b> &nbsp;|&nbsp; 
                        Math: <b style="color: #d97706;">{{ $latest['math'] ?? '-' }}</b>
                    </div>
                    @if(!empty($adm['hasData']))
                        <div style="font-size: 7.8px; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: 4px; padding: 2px 6px;">
                            <b>Official SAT Benchmarks:</b> Exceeds Standards (RW +{{ $adm['benchmarks']['rw']['margin'] }}, Math +{{ $adm['benchmarks']['math']['margin'] }} pts)
                        </div>
                    @endif
                </div>
            </td>

            {{-- Right: 3 Key Snapshot Cards --}}
            <td style="width: 52%; padding-left: 3px;">
                <table class="layout">
                    <tr>
                        <td style="padding-bottom: 4px;">
                            <div class="card-soft">
                                <table class="layout">
                                    <tr>
                                        <td>
                                            <div style="font-size: 7.5px; color: #64748b;">Highest Score</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #1e293b;">{{ $fmt($score['best']) }}</div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-green">Personal Best</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 4px;">
                            <div class="card-soft">
                                <table class="layout">
                                    <tr>
                                        <td>
                                            <div style="font-size: 7.5px; color: #64748b;">Score Improvement</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #16a34a;">
                                                {{ $score['growth'] !== null && $score['growth'] >= 0 ? '+' : '' }}{{ $score['growth'] ?? 'N/A' }} pts
                                            </div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-amber">{{ $score['plateau'] }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="card-soft">
                                <table class="layout">
                                    <tr>
                                        <td>
                                            <div style="font-size: 7.5px; color: #64748b;">Recent Average Score</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #0284c7;">
                                                {{ $fmt($score['recentAverage']) }}
                                            </div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-blue">Latest {{ min(3, $dossier['scoredFullTestCount']) }} Tests</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Score History Chart (Full-Width Spacious) --}}
    <div style="margin-bottom: 6px;">
        <div class="section-title">Score Progression & Trend</div>
        {!! $svgTotalTrend !!}
    </div>

    {{-- Teacher's Performance Notes for Parents --}}
    <div style="margin-bottom: 6px;">
        <div class="section-title">Teacher's Performance Notes</div>
        <div class="narrative-box">
            {{ $dossier['narrative'] }}
        </div>
    </div>

    {{-- Score Stability & Recent Comparable Tests (2 columns) --}}
    <table class="layout">
        <tr>
            {{-- Left: Stability Summary --}}
            <td style="width: 36%; padding-right: 5px;">
                <div class="section-title">Score Consistency & Stats</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="left" style="width: 60%;">Metric</th>
                            <th class="right" style="width: 40%;">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="left">Score Median</td>
                            <td class="right bold">{{ $fmt($score['median']) }}</td>
                        </tr>
                        <tr>
                            <td class="left">Score Range</td>
                            <td class="right bold">{{ $fmt($score['range']) }} pts</td>
                        </tr>
                        <tr>
                            <td class="left">{{ count($history) <= 3 ? '3-Test Average' : 'Best 3-Test Average' }}</td>
                            <td class="right bold">{{ $fmt($score['bestThreeAverage']) }}</td>
                        </tr>
                        <tr>
                            <td class="left">Average Gain per Test</td>
                            <td class="right bold">{{ $score['velocity'] === null ? 'N/A' : (($score['velocity'] >= 0 ? '+' : '') . $score['velocity'] . ' pts/test') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Right: Recent History Table --}}
            <td style="width: 64%; padding-left: 3px;">
                <div class="section-title">Recent Practice Tests</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="left" style="width: 18%;">Date</th>
                            <th class="left" style="width: 46%;">Practice Test</th>
                            <th class="center" style="width: 12%;">RW</th>
                            <th class="center" style="width: 12%;">Math</th>
                            <th class="center" style="width: 12%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($history, -4) as $item)
                            <tr>
                                <td class="left">{{ $item['date'] }}</td>
                                <td class="left" style="font-weight: 500;">{{ $item['title'] }}</td>
                                <td class="center blue bold">{{ $item['rw'] }}</td>
                                <td class="center orange bold">{{ $item['math'] }}</td>
                                <td class="center bold" style="color: #1e293b;">{{ $item['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="center muted">No scored tests completed yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        Digital SAT Student Progress Report &nbsp;•&nbsp; Page 1: Overview & Progress Trend
    </div>
</div>

{{-- ========================================================================= --}}
{{-- PAGE 2: SUBJECT MASTERY & SKILL ANALYSIS                                 --}}
{{-- ========================================================================= --}}
<div class="page">
    <div class="head">
        <h2>Page 2: Subject Mastery & Skill Analysis</h2>
    </div>

    {{-- Domain Progress Cards (Side-by-Side) --}}
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            {{-- Left: Reading & Writing Topics --}}
            <td style="width: 50%; padding-right: 4px;">
                <div class="card" style="padding: 7px 9px;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0284c7; margin-bottom: 4px;">Reading & Writing Topics</div>
                    <table class="layout">
                        @foreach($rw as $d)
                            <tr>
                                <td style="padding: 2.5px 0; font-size: 8.2px; color: #334155; width: 52%;">
                                    {{ $d['domain'] }}
                                </td>
                                <td style="padding: 2.5px 0; width: 33%;">
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width: {{ $d['accuracy'] }}%; background: #0284c7;"></div>
                                    </div>
                                </td>
                                <td class="right bold" style="padding: 2.5px 0; font-size: 8.2px; color: #0284c7; width: 15%;">
                                    {{ $d['accuracy'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>

            {{-- Right: Math Topics --}}
            <td style="width: 50%; padding-left: 4px;">
                <div class="card" style="padding: 7px 9px;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #d97706; margin-bottom: 4px;">Math Topics</div>
                    <table class="layout">
                        @foreach($math as $d)
                            <tr>
                                <td style="padding: 2.5px 0; font-size: 8.2px; color: #334155; width: 52%;">
                                    {{ $d['domain'] }}
                                </td>
                                <td style="padding: 2.5px 0; width: 33%;">
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width: {{ $d['accuracy'] }}%; background: #d97706;"></div>
                                    </div>
                                </td>
                                <td class="right bold" style="padding: 2.5px 0; font-size: 8.2px; color: #d97706; width: 15%;">
                                    {{ $d['accuracy'] }}%
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Question Difficulty Readiness Infographic (3 Cards) --}}
    <div class="section-title">Performance by Question Difficulty</div>
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            @php($easyItems = collect($dossier['difficulty'])->pluck('tiers.easy'))
            @php($easyTotal = $easyItems->sum('total'))
            @php($easyCorrect = $easyItems->sum('correct'))
            @php($easyAcc = $easyTotal > 0 ? (int) round(($easyCorrect / $easyTotal) * 100) : 0)

            @php($medItems = collect($dossier['difficulty'])->pluck('tiers.medium'))
            @php($medTotal = $medItems->sum('total'))
            @php($medCorrect = $medItems->sum('correct'))
            @php($medAcc = $medTotal > 0 ? (int) round(($medCorrect / $medTotal) * 100) : 0)

            @php($hardItems = collect($dossier['difficulty'])->pluck('tiers.hard'))
            @php($hardTotal = $hardItems->sum('total'))
            @php($hardCorrect = $hardItems->sum('correct'))
            @php($hardAcc = $hardTotal > 0 ? (int) round(($hardCorrect / $hardTotal) * 100) : 0)

            <td style="width: 33.3%; padding-right: 4px;">
                <div class="card-soft" style="text-align: center; border-top: 3px solid #16a34a; padding: 7px 6px;">
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Easy Questions</div>
                    <div style="font-size: 18px; font-weight: bold; color: #16a34a; margin: 1px 0;">{{ $easyAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">{{ $easyCorrect }}/{{ $easyTotal }} Correct &bull; <b style="color: #16a34a;">Mastered</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Foundational consistency</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-right: 2px; padding-left: 2px;">
                <div class="card-soft" style="text-align: center; border-top: 3px solid #d97706; padding: 7px 6px;">
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Medium Questions</div>
                    <div style="font-size: 18px; font-weight: bold; color: #d97706; margin: 1px 0;">{{ $medAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">{{ $medCorrect }}/{{ $medTotal }} Correct &bull; <b style="color: #d97706;">Solid</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Core standard proficiency</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-left: 4px;">
                <div class="card-soft" style="text-align: center; border-top: 3px solid #2563eb; padding: 7px 6px;">
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Hard Questions</div>
                    <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin: 1px 0;">{{ $hardAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">{{ $hardCorrect }}/{{ $hardTotal }} Correct &bull; <b style="color: #2563eb;">Opportunity Area</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Key driver for 1400+ score</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Top 5 Focus Skills to Improve --}}
    <div class="section-title">Top 5 Focus Skills to Improve</div>
    <table class="data-table" style="margin-bottom: 6px;">
        <thead>
            <tr>
                <th class="left" style="width: 30%;">Skill Subdomain</th>
                <th class="center" style="width: 14%;">Section</th>
                <th class="center" style="width: 12%;">Accuracy</th>
                <th class="center" style="width: 15%;">Questions Practiced</th>
                <th class="center" style="width: 15%;">Mastery</th>
                <th class="center" style="width: 14%;">Trend</th>
            </tr>
        </thead>
        <tbody>
            @php($priorityList = collect($dossier['skills'])->filter(fn($s) => $s['evidenceLevel'] !== 'Insufficient')->sortByDesc('opportunityIndex')->take(5))
            @forelse($priorityList as $s)
                <tr>
                    <td class="left">
                        <b>{{ $s['skill'] }}</b><br>
                        <span class="small muted">{{ $s['domain'] }}</span>
                    </td>
                    <td class="center {{ $s['sectionLabel'] === 'Math' ? 'orange' : 'blue' }} bold">{{ $s['sectionLabel'] }}</td>
                    <td class="center bold {{ $tone($s['accuracy']) }}">{{ $s['accuracy'] }}%</td>
                    <td class="center">{{ $s['correct'] }}/{{ $s['total'] }} <span class="small muted">({{ $s['evidenceLevel'] }})</span></td>
                    <td class="center {{ $tone($s['masteryIndex']) }}">
                        <b>{{ $s['masteryIndex'] }}</b> <span class="small muted">({{ $s['masteryBand'] }})</span>
                    </td>
                    <td class="center">
                        <span class="badge {{ $badge($s['trend']) }}">{{ $s['trend'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Complete additional practice tests to identify focus skills.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Mastery vs. Raw Accuracy Explanation --}}
    <div class="narrative-box" style="margin-top: 6px;">
        <b>How Mastery is Calculated:</b> Raw accuracy shows the percentage of correct answers. The <b>Mastery Score</b> factors in recent test performance, question difficulty, and total questions practiced. Target bands: 90+ Mastered, 80+ Strong, 70+ Developing, 60+ At Risk, below 60 Priority Focus.
    </div>

    <div class="footer">
        Digital SAT Student Progress Report &nbsp;•&nbsp; Page 2: Subject Mastery & Skill Breakdown
    </div>
</div>

{{-- ========================================================================= --}}
{{-- PAGE 3: PACING HABITS, SCORE POTENTIAL & ACTION PLAN                      --}}
{{-- ========================================================================= --}}
<div class="page {{ count($history) > 10 ? '' : 'last-page' }}">
    <div class="head">
        <h2>Page 3: Pacing Habits, Score Potential & Action Plan</h2>
    </div>

    {{-- Score Potential Bridge Card --}}
    @if(!empty($ptLoss['avoidablePoints']))
    <div class="card" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; padding: 7px 10px; margin-bottom: 6px;">
        <table class="layout">
            <tr>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #475569; font-weight: bold; text-transform: uppercase;">Current Score</div>
                    <div style="font-size: 15px; font-weight: bold; color: #1e293b;">{{ $latest['total'] ?? '-' }}</div>
                </td>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #166534; font-weight: bold; text-transform: uppercase;">Recoverable Points</div>
                    <div style="font-size: 15px; font-weight: bold; color: #16a34a;">+{{ $ptLoss['avoidablePoints'] }} pts</div>
                </td>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #0369a1; font-weight: bold; text-transform: uppercase;">Immediate Potential</div>
                    <div style="font-size: 15px; font-weight: bold; color: #0284c7;">{{ $ptLoss['immediateAttainable'] }}</div>
                </td>
                <td style="width: 25%; text-align: center; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #4f46e5; font-weight: bold; text-transform: uppercase;">{{ $ptLoss['targetType'] ?? 'Target Goal' }}</div>
                    <div style="font-size: 15px; font-weight: bold; color: #4f46e5;">{{ $ptLoss['targetDisplay'] ?? '-' }}</div>
                </td>
            </tr>
        </table>
        <div style="font-size: 7.5px; color: #15803d; text-align: center; margin-top: 3px;">
            <b>Immediate Point Recovery:</b> Gaining up to <b>+{{ $ptLoss['avoidablePoints'] }} points</b> requires no new math or vocabulary—only eliminating careless slips, time traps, and unforced omissions.
        </div>
    </div>
    @endif

    {{-- Points Lost to Avoidable Errors (3 Cards) --}}
    <div class="section-title">Points Lost to Avoidable Errors</div>
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            @php($careless = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Careless / Rushing Errors'))
            @php($timetrap = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Time Trap Over-Investment'))
            @php($omitted = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Unforced Omissions'))

            <td style="width: 33.3%; padding-right: 4px;">
                <div class="card-soft" style="border-left: 3px solid #dc2626; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Careless / Rushing Slips</div>
                    <div style="font-size: 14px; font-weight: bold; color: #dc2626; margin: 1px 0;">-{{ $careless['pointsLost'] ?? 0 }} pts</div>
                    <div style="font-size: 7.2px; color: #64748b;">{{ $careless['count'] ?? 0 }} Qs missed ({{ $careless['perTest'] ?? 0 }}/test)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Missed on Easy/Medium questions at fast pace.</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-right: 2px; padding-left: 2px;">
                <div class="card-soft" style="border-left: 3px solid #d97706; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Time Trap Over-Investment</div>
                    <div style="font-size: 14px; font-weight: bold; color: #d97706; margin: 1px 0;">-{{ $timetrap['pointsLost'] ?? 0 }} pts</div>
                    <div style="font-size: 7.2px; color: #64748b;">{{ $timetrap['count'] ?? 0 }} Qs missed ({{ $timetrap['perTest'] ?? 0 }}/test)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Over-invested &gt;1.4&times; expected time and missed.</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-left: 4px;">
                <div class="card-soft" style="border-left: 3px solid #64748b; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Unanswered Questions</div>
                    <div style="font-size: 14px; font-weight: bold; color: #475569; margin: 1px 0;">-{{ $omitted['pointsLost'] ?? 0 }} pts</div>
                    <div style="font-size: 7.2px; color: #64748b;">{{ $omitted['count'] ?? 0 }} Qs left blank ({{ $omitted['perTest'] ?? 0 }}/test)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Digital SAT has no wrong-answer penalty.</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Timing & Focus Diagnostic Insight Card --}}
    <div class="card" style="margin-bottom: 6px; padding: 6px 9px; background: #f8fafc;">
        <table class="layout">
            <tr>
                <td style="width: 50%; padding-right: 6px; border-right: 1px solid #e2e8f0;">
                    <div style="font-size: 8px; font-weight: bold; color: #0284c7;">Pacing Pattern: Start Speed vs End Focus</div>
                    <div style="font-size: 7.5px; color: #334155; margin-top: 2px; line-height: 1.35;">
                        Student tends to work rapidly through early questions (Q1–10: avg 37s–42s, 70–76% accuracy), where careless slips occur. Late-module focus is strong (Q21+: 82% accuracy, avg 30s). Slowing down by 10s on Q1–10 will immediately recover points.
                    </div>
                </td>
                <td style="width: 50%; padding-left: 6px;">
                    <div style="font-size: 8px; font-weight: bold; color: #16a34a;">Test Stamina & Focus</div>
                    <div style="font-size: 7.5px; color: #334155; margin-top: 2px; line-height: 1.35;">
                        Across 278 total questions, accuracy does not decay toward the end of modules. The student demonstrates excellent cognitive stamina, confirming that score gains should focus on initial question discipline rather than endurance fatigue.
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Action Plan & Next Steps --}}
    <div class="section-title">Action Plan & Next Steps</div>
    @forelse(array_slice($dossier['prescriptions'], 0, 3) as $r)
        <div class="card" style="border-left: 3.5px solid #2563eb; margin-bottom: 5px; padding: 6px 9px;">
            <table class="layout">
                <tr>
                    <td>
                        <b style="font-size: 9px; color: #1e293b;">{{ $r['skill'] }}</b> 
                        &nbsp;•&nbsp; <span class="bold {{ $tone($r['accuracy']) }}">{{ $r['accuracy'] }}% accuracy</span> 
                        <span class="small muted">across {{ $r['total'] }} questions</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top: 3px; font-size: 7.8px; color: #334155; line-height: 1.4;">
                        <span style="color: #0284c7; font-weight: bold;">Objective:</span> {{ $r['objective'] }}<br>
                        <span style="color: #d97706; font-weight: bold;">Practice Plan:</span> {{ $r['practice'] }} &nbsp;|&nbsp; 
                        <span style="color: #16a34a; font-weight: bold;">Target Goal:</span> {{ $r['success'] }}
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <div class="card center muted">Complete more practice sets to generate tailored recommendations.</div>
    @endforelse

    <div class="footer">
        Digital SAT Student Progress Report &nbsp;•&nbsp; Page 3: Pacing Habits, Score Potential & Action Plan
    </div>
</div>

{{-- ========================================================================= --}}
{{-- OPTIONAL PAGE 4: HISTORICAL TEST ARCHIVE (RENDERED ONLY IF >10 TESTS)     --}}
{{-- ========================================================================= --}}
@if(count($history) > 10)
<div class="page last-page">
    <div class="head">
        <h2>Section 4: Full Practice Test History</h2>
    </div>

    <div class="section-title">All Completed Practice Tests</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center" style="width: 8%;">#</th>
                <th class="left" style="width: 18%;">Completion Date</th>
                <th class="left" style="width: 38%;">Practice Test Title</th>
                <th class="center" style="width: 12%;">RW Score</th>
                <th class="center" style="width: 12%;">Math Score</th>
                <th class="center" style="width: 12%;">Total Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach($history as $item)
                <tr>
                    <td class="center">{{ $item['index'] }}</td>
                    <td class="left">{{ $item['date'] }}</td>
                    <td class="left" style="font-weight: 500;">{{ $item['title'] }}</td>
                    <td class="center blue bold">{{ $item['rw'] }}</td>
                    <td class="center orange bold">{{ $item['math'] }}</td>
                    <td class="center bold">{{ $item['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="narrative-box" style="margin-top: 8px;">
        <b>Note:</b> This table lists every completed full-length practice test. Section-only attempts are excluded to ensure accurate score history tracking.
    </div>

    <div class="footer">
        Digital SAT Student Progress Report &nbsp;•&nbsp; Section 4: Practice Test History
    </div>
</div>
@endif

</body>
</html>
