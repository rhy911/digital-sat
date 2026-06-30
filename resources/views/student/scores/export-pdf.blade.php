<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Score Report - {{ $userTest->test->title }}</title>
    <style>
        @page { margin: 28px; }
        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }
        h1, h2, h3, p { margin: 0; }
        h1 { color: #0a2d6e; font-size: 24px; margin-bottom: 4px; }
        h2 { color: #0a2d6e; font-size: 15px; margin: 22px 0 10px; }
        table { border-collapse: collapse; width: 100%; }
        th {
            background: #1e293b;
            color: #ffffff;
            font-size: 9px;
            letter-spacing: .04em;
            padding: 7px 6px;
            text-align: left;
            text-transform: uppercase;
        }
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 6px;
            vertical-align: top;
        }
        .muted { color: #64748b; }
        .hero {
            background: #e6f1fa;
            border-left: 6px solid #0077c8;
            margin-top: 18px;
            padding: 16px 18px;
        }
        .score {
            color: #0a2d6e;
            font-size: 38px;
            font-weight: 800;
            line-height: 1;
        }
        .score-label {
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            margin-top: 6px;
        }
        .disclosure {
            color: #475569;
            margin-top: 8px;
        }
        .stats {
            margin-top: 14px;
            width: 100%;
        }
        .stat {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            padding: 10px;
            text-align: center;
            width: 33.333%;
        }
        .stat strong {
            color: #0a2d6e;
            display: block;
            font-size: 20px;
        }
        .badge {
            border-radius: 10px;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
        }
        .correct { background: #d1fae5; color: #065f46; }
        .wrong { background: #fee2e2; color: #991b1b; }
        .omitted { background: #f1f5f9; color: #475569; }
        .section-pill {
            background: #dbeafe;
            border-radius: 10px;
            color: #1e40af;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
        }
        .section-pill.math {
            background: #ede9fe;
            color: #5b21b6;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 10px;
            margin-top: 24px;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Score Report</h1>
    <p class="muted">
        {{ $userTest->test->title }}
        |
        {{ $userTest->completed_at ? $userTest->completed_at->format('F j, Y') : 'In progress' }}
        |
        {{ $userTest->user?->email }}
    </p>

    <section class="hero">
        @if ($isScaledSatResult)
            <div class="score">{{ $userTest->total_score }} <span style="font-size:16px;color:#64748b;">/ 1600</span></div>
            <p class="score-label">
                {{ $userTest->score_estimate_kind === 'adaptive_irt_provisional' ? 'Provisional IRT estimate' : 'Estimated practice score' }}
            </p>
            <p class="disclosure">
                @if($userTest->score_estimate_kind === 'adaptive_irt_provisional')
                    Estimated range {{ $userTest->total_score_lower }}-{{ $userTest->total_score_upper }}. Item parameters and scaled mapping remain provisional.
                @elseif($userTest->score_estimate_kind === 'normal_generic')
                    Route-neutral estimate using built-in conversion {{ $userTest->score_conversion_version }}. A form-specific table may improve accuracy.
                @else
                    Route-neutral estimate using approved conversion v{{ $userTest->scoreConversionSet?->version ?? 'legacy' }}.
                @endif
                Not an official College Board score.
            </p>
        @else
            <div class="score">{{ $accuracyPercent }}%</div>
            <p class="score-label">Practice performance, not a calibrated SAT score.</p>
            <p class="disclosure">{{ $correct }} of {{ $totalQ }} scored questions correct.</p>
        @endif
    </section>

    <table class="stats">
        <tr>
            <td class="stat"><strong>{{ $totalQ }}</strong>Total Questions</td>
            <td class="stat"><strong>{{ $correct }}</strong>Correct</td>
            <td class="stat"><strong>{{ $wrong + $omitted }}</strong>Incorrect / Omitted</td>
        </tr>
    </table>

    <h2>Knowledge &amp; Skills</h2>
    @if (count($domainSummaries))
        <table>
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Domain</th>
                    <th>Correct</th>
                    <th>Performance</th>
                    <th>Section Share</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($domainSummaries as $domain)
                    <tr>
                        <td>{{ $domain['section'] }}</td>
                        <td>{{ $domain['domain'] }}</td>
                        <td>{{ $domain['correct'] }} / {{ $domain['total'] }} ({{ $domain['percentCorrect'] }}%)</td>
                        <td>{{ $domain['performance'] }}</td>
                        <td>{{ $domain['coveragePercent'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No scored domains available.</p>
    @endif

    <h2>Question Review</h2>
    @if (count($allAnswers))
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Section</th>
                    <th>Result</th>
                    <th>Domain</th>
                    <th>Your Answer</th>
                    <th>Correct Answer</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($allAnswers as $row)
                    <tr>
                        <td>{{ $row['idx'] }}</td>
                        <td>
                            <span class="section-pill {{ $row['sectionType'] === 'math' ? 'math' : '' }}">
                                {{ $row['sectionName'] }}@if (!empty($row['moduleNumber'])) M{{ $row['moduleNumber'] }}@endif
                            </span>
                        </td>
                        <td>
                            @if ($row['statusKey'] === 'correct')
                                <span class="badge correct">Correct</span>
                            @elseif ($row['statusKey'] === 'wrong')
                                <span class="badge wrong">Incorrect</span>
                            @else
                                <span class="badge omitted">Omitted</span>
                            @endif
                        </td>
                        <td>{{ $row['domainLabel'] }}</td>
                        <td>{{ $row['answer']->selected_answer ?: 'Omitted' }}</td>
                        <td>{{ $row['correctAnswer'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No question results available.</p>
    @endif

    <p class="footer">Generated by Digital SAT. Report reflects saved practice-test results at export time.</p>
</body>
</html>
