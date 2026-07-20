<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $assignment->title }} — Results</title>
    <style>
        @page { margin: 28px; }
        body {
            color: #1f2937;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            margin: 0;
        }
        h1, h2, p { margin: 0; }
        h1 { color: #0a2d6e; font-size: 22px; margin-bottom: 4px; }
        .muted { color: #64748b; }
        .stats {
            margin-top: 16px;
            width: 100%;
        }
        .stat {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            padding: 10px;
            text-align: center;
            width: 25%;
        }
        .stat strong {
            color: #0a2d6e;
            display: block;
            font-size: 18px;
        }
        .stat span {
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        table { border-collapse: collapse; margin-top: 18px; width: 100%; }
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
    <h1>{{ $assignment->title }}</h1>
    <p class="muted">
        {{ $assignment->classroom->name }} · {{ $assignment->test->title }}
        &nbsp;|&nbsp; Generated {{ now()->format('F j, Y') }}
    </p>

    <table class="stats">
        <tr>
            <td class="stat"><strong>{{ $report['metrics']['assigned'] }}</strong><span>Assigned</span></td>
            <td class="stat"><strong>{{ $report['metrics']['completed'] }}</strong><span>Completed</span></td>
            <td class="stat"><strong>{{ $report['metrics']['in_progress'] }}</strong><span>In progress</span></td>
            <td class="stat">
                <strong>
                    @if ($assignment->assign_type === 'section')
                        {{ $assignment->section_type === 'reading_writing' ? ($report['metrics']['average_rw'] ?? '—') : ($report['metrics']['average_math'] ?? '—') }}
                    @else
                        {{ $report['metrics']['average_score'] ?? '—' }}
                    @endif
                </strong>
                <span>Average</span>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Best score</th>
                @if ($assignment->assign_type !== 'section')
                    <th>R&amp;W Score</th>
                    <th>Math Score</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                @php
                    $status = $row['recipient']->status === 'withdrawn'
                        ? 'Withdrawn'
                        : ($row['in_progress'] ? 'In progress' : ($row['best'] ? ($row['late'] ? 'Completed late' : 'Completed') : 'Not started'));
                    $best = $row['best'];
                    $bestScore = $best
                        ? ($assignment->assign_type === 'section'
                            ? ($assignment->section_type === 'reading_writing' ? $best->score_reading_writing : $best->score_math)
                            : $best->total_score)
                        : null;
                @endphp
                <tr>
                    <td>
                        <strong>{{ $row['recipient']->student->name }}</strong><br>
                        <span class="muted">{{ $row['recipient']->student->email }}</span>
                    </td>
                    <td>{{ $status }}</td>
                    <td>{{ $row['completed_count'] }} / {{ $assignment->attempt_limit }}</td>
                    <td>{{ $bestScore ?? '—' }}</td>
                    @if ($assignment->assign_type !== 'section')
                        <td>{{ $best?->score_reading_writing ?? '—' }}</td>
                        <td>{{ $best?->score_math ?? '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $assignment->assign_type === 'section' ? 4 : 6 }}">No recipients yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Digital SAT · Teacher report · Generated {{ now()->format('F j, Y g:i A') }}</p>
</body>
</html>
