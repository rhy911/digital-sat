<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Digital SAT - Báo Cáo Tiến Bộ Học Tập</title>
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
@php($badgeVi = fn($v) => match($v) {
    'Mastered' => 'Thành thạo',
    'Strong' => 'Vững vàng',
    'Developing' => 'Đang phát triển',
    'Priority' => 'Cần ưu tiên',
    'At risk' => 'Cần lưu ý',
    'Improving' => 'Đang cải thiện',
    'Declining' => 'Cần cố gắng',
    default => $v
})
@php($badgeClass = fn($v) => in_array($v, ['Mastered', 'Strong', 'Rapid improvement', 'Improving'], true) ? 'bg-green' : (in_array($v, ['Priority', 'Declining'], true) ? 'bg-red' : (in_array($v, ['At risk', 'Developing', 'Moderate'], true) ? 'bg-amber' : 'bg-gray')))

@php($domainMap = [
    'Craft and Structure' => 'Cấu trúc & Văn phong',
    'Information and Ideas' => 'Thông tin & Ý tưởng',
    'Standard English Conventions' => 'Quy tắc Tiếng Anh Chuẩn',
    'Expression of Ideas' => 'Diễn đạt Ý tưởng',
    'Algebra' => 'Đại số',
    'Advanced Math' => 'Toán Nâng cao',
    'Problem-Solving and Data Analysis' => 'Giải quyết Vấn đề & Phân tích Dữ liệu',
    'Geometry and Trigonometry' => 'Hình học & Lượng giác',
])

@php($rw = collect($dossier['domains'])->where('section', 'reading_and_writing'))
@php($math = collect($dossier['domains'])->where('section', 'math'))
@php($logoInversePath = public_path('brand/logo-horizontal-inverse.png'))
@php($logoInverseBase64 = file_exists($logoInversePath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoInversePath)) : null)

{{-- ========================================================================= --}}
{{-- TRANG 1: TỔNG QUAN HỌC SINH & PHỤ HUYNH                                    --}}
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
                    <div class="eyebrow">DIGITAL SAT • BÁO CÁO TIẾN BỘ HỌC TẬP</div>
                    <h1>{{ $student->name }}</h1>
                    <div class="meta">
                        Ngày xuất: {{ now()->format('d/m/Y') }} &nbsp;•&nbsp; 
                        <b>{{ $dossier['scoredFullTestCount'] }}</b> Bài Thi Thử Đầy Đủ &nbsp;•&nbsp; 
                        <b>{{ $dossier['activityAttemptCount'] }}</b> Hoạt Động Luyện Tập
                        @if(!empty($classroom))
                            <br>
                            <span style="color: #cbd5e1;">Lớp: <b>{{ $classroom->name }}</b> &nbsp;|&nbsp; Giáo viên: <b>{{ $teacher?->name ?? 'Giáo viên hướng dẫn' }}</b></span>
                        @endif
                    </div>
                </td>
                <td style="width: 32%; text-align: right; vertical-align: middle;">
                    <div class="brand-pill">
                        <div style="font-size: 7px; color: #94a3b8; letter-spacing: 0.5px;">Hệ Thống Luyện Thi SAT</div>
                        <div style="font-size: 10px; font-weight: bold; color: #38bdf8; margin: 1px 0;">Báo Cáo Tiến Bộ Học Sinh</div>
                        <div style="font-size: 7.2px; color: #cbd5e1;">
                            @if($score['target'])
                                Mục tiêu: <b style="color: #ffffff;">{{ $score['target'] }}</b> 
                                ({{ $score['targetGap'] !== null ? ($score['targetGap'] > 0 ? 'Còn '.$score['targetGap'].' điểm' : 'Đã đạt') : '' }})
                            @else
                                Luyện tập tự do
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
                    <div style="font-size: 7.5px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Điểm SAT Mới Nhất</div>
                    <div style="font-size: 32px; font-weight: bold; color: #2563eb; line-height: 1.1; margin: 2px 0;">
                        {{ $fmt($latest['total'] ?? null) }}
                        <span style="font-size: 10px; font-weight: normal; color: #64748b;">/ 1600</span>
                    </div>
                    <div style="font-size: 9px; color: #334155; margin-bottom: 4px;">
                        Đọc & Viết: <b style="color: #0284c7;">{{ $latest['rw'] ?? '-' }}</b> &nbsp;|&nbsp; 
                        Toán: <b style="color: #d97706;">{{ $latest['math'] ?? '-' }}</b>
                    </div>
                    @if(!empty($adm['hasData']))
                        <div style="font-size: 7.8px; color: #166534; background: #dcfce7; border: 1px solid #86efac; border-radius: 4px; padding: 2px 6px;">
                            <b>Chuẩn SAT Chính Thức:</b> Vượt chuẩn (Đọc & Viết +{{ $adm['benchmarks']['rw']['margin'] }}, Toán +{{ $adm['benchmarks']['math']['margin'] }} điểm)
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
                                            <div style="font-size: 7.5px; color: #64748b;">Điểm Cao Nhất</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #1e293b;">{{ $fmt($score['best']) }}</div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-green">Kỷ Lục Cá Nhân</span>
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
                                            <div style="font-size: 7.5px; color: #64748b;">Mức Độ Tiến Bộ</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #16a34a;">
                                                {{ $score['growth'] !== null && $score['growth'] >= 0 ? '+' : '' }}{{ $score['growth'] ?? 'N/A' }} điểm
                                            </div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-amber">{{ $score['plateau'] == 'Stable trajectory' ? 'Tiến bộ ổn định' : $score['plateau'] }}</span>
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
                                            <div style="font-size: 7.5px; color: #64748b;">Điểm Trung Bình Gần Đây</div>
                                            <div style="font-size: 14px; font-weight: bold; color: #0284c7;">
                                                {{ $fmt($score['recentAverage']) }}
                                            </div>
                                        </td>
                                        <td class="right" style="vertical-align: middle;">
                                            <span class="badge bg-blue">{{ min(3, $dossier['scoredFullTestCount']) }} Bài Thi Gần Nhất</span>
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
        <div class="section-title">Tiến Trình & Xu Hướng Điểm Số</div>
        {!! $svgTotalTrend !!}
    </div>

    {{-- Teacher's Performance Notes for Parents --}}
    <div style="margin-bottom: 6px;">
        <div class="section-title">Nhận Xét Từ Giáo Viên</div>
        <div class="narrative-box">
            @php($bestDomain = collect($dossier['domains'])->sortByDesc('accuracy')->first())
            @php($bestDomainName = $bestDomain ? ($domainMap[$bestDomain['domain']] ?? $bestDomain['domain']) : '')
            Kết quả hiện tại đạt <b>{{ $latest['total'] ?? '-' }}</b> điểm (Đọc & Viết: <b>{{ $latest['rw'] ?? '-' }}</b>, Toán: <b>{{ $latest['math'] ?? '-' }}</b>). 
            Thay đổi điểm số so với điểm ban đầu là <b>{{ $score['growth'] !== null && $score['growth'] >= 0 ? '+' : '' }}{{ $score['growth'] ?? 0 }} điểm</b>. 
            Lĩnh vực đạt kết quả xuất sắc nhất là <b>{{ $bestDomainName }}</b> với <b>{{ $bestDomain['accuracy'] ?? 0 }}%</b> độ chính xác.
        </div>
    </div>

    {{-- Score Stability & Recent Comparable Tests (2 columns) --}}
    <table class="layout">
        <tr>
            {{-- Left: Stability Summary --}}
            <td style="width: 36%; padding-right: 5px;">
                <div class="section-title">Độ Thống Nhất & Thống Kê Điểm</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="left" style="width: 60%;">Chỉ số</th>
                            <th class="right" style="width: 40%;">Giá trị</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="left">Trung vị điểm số</td>
                            <td class="right bold">{{ $fmt($score['median']) }}</td>
                        </tr>
                        <tr>
                            <td class="left">Biên độ chênh lệch</td>
                            <td class="right bold">{{ $fmt($score['range']) }} điểm</td>
                        </tr>
                        <tr>
                            <td class="left">{{ count($history) <= 3 ? 'TB 3 bài thi' : 'TB 3 bài tốt nhất' }}</td>
                            <td class="right bold">{{ $fmt($score['bestThreeAverage']) }}</td>
                        </tr>
                        <tr>
                            <td class="left">Mức tăng trung bình / bài</td>
                            <td class="right bold">{{ $score['velocity'] === null ? 'N/A' : (($score['velocity'] >= 0 ? '+' : '') . $score['velocity'] . ' đ/bài') }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>

            {{-- Right: Recent History Table --}}
            <td style="width: 64%; padding-left: 3px;">
                <div class="section-title">Các Bài Thi Thử Gần Đây</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="left" style="width: 18%;">Ngày thi</th>
                            <th class="left" style="width: 46%;">Bài thi thử</th>
                            <th class="center" style="width: 12%;">Đọc-Viết</th>
                            <th class="center" style="width: 12%;">Toán</th>
                            <th class="center" style="width: 12%;">Tổng điểm</th>
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
                                <td colspan="5" class="center muted">Chưa có bài thi thử hoàn thành.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        Digital SAT Báo Cáo Tiến Bộ Học Sinh &nbsp;•&nbsp; Trang 1: Tổng Quan & Xu Hướng Tiến Bộ
    </div>
</div>

{{-- ========================================================================= --}}
{{-- TRANG 2: THÀNH THẠO THEO CHỦ ĐỀ & KỸ NĂNG                                --}}
{{-- ========================================================================= --}}
<div class="page">
    <div class="head">
        <h2>Trang 2: Mức Độ Thành Thạo Theo Chủ Đề & Kỹ Năng</h2>
    </div>

    {{-- Domain Progress Cards (Side-by-Side) --}}
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            {{-- Left: Reading & Writing Topics --}}
            <td style="width: 50%; padding-right: 4px;">
                <div class="card" style="padding: 7px 9px;">
                    <div style="font-size: 8.5px; font-weight: bold; color: #0284c7; margin-bottom: 4px;">Chủ Đề Đọc & Viết</div>
                    <table class="layout">
                        @foreach($rw as $d)
                            <tr>
                                <td style="padding: 2.5px 0; font-size: 8.2px; color: #334155; width: 52%;">
                                    {{ $domainMap[$d['domain']] ?? $d['domain'] }}
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
                    <div style="font-size: 8.5px; font-weight: bold; color: #d97706; margin-bottom: 4px;">Chủ Đề Toán Học</div>
                    <table class="layout">
                        @foreach($math as $d)
                            <tr>
                                <td style="padding: 2.5px 0; font-size: 8.2px; color: #334155; width: 52%;">
                                    {{ $domainMap[$d['domain']] ?? $d['domain'] }}
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
    <div class="section-title">Kết Quả Theo Độ Khó Của Câu Hỏi</div>
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
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Câu Hỏi Dễ</div>
                    <div style="font-size: 18px; font-weight: bold; color: #16a34a; margin: 1px 0;">{{ $easyAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">Đúng {{ $easyCorrect }}/{{ $easyTotal }} câu &bull; <b style="color: #16a34a;">Thành thạo</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Nền tảng vững vàng</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-right: 2px; padding-left: 2px;">
                <div class="card-soft" style="text-align: center; border-top: 3px solid #d97706; padding: 7px 6px;">
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Câu Hỏi Trung Bình</div>
                    <div style="font-size: 18px; font-weight: bold; color: #d97706; margin: 1px 0;">{{ $medAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">Đúng {{ $medCorrect }}/{{ $medTotal }} câu &bull; <b style="color: #d97706;">Vững vàng</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Đạt chuẩn cốt lõi</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-left: 4px;">
                <div class="card-soft" style="text-align: center; border-top: 3px solid #2563eb; padding: 7px 6px;">
                    <div style="font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase;">Câu Hỏi Khó</div>
                    <div style="font-size: 18px; font-weight: bold; color: #2563eb; margin: 1px 0;">{{ $hardAcc }}%</div>
                    <div style="font-size: 7.5px; color: #475569;">Đúng {{ $hardCorrect }}/{{ $hardTotal }} câu &bull; <b style="color: #2563eb;">Vùng tiềm năng</b></div>
                    <div style="font-size: 6.8px; color: #64748b; margin-top: 2px;">Đòn bẩy bứt phá 1400+</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Top 5 Focus Skills to Improve --}}
    <div class="section-title">Top 5 Kỹ Năng Cần Tập Trung Cải Thiện</div>
    <table class="data-table" style="margin-bottom: 6px;">
        <thead>
            <tr>
                <th class="left" style="width: 30%;">Phân vùng kỹ năng</th>
                <th class="center" style="width: 14%;">Môn thi</th>
                <th class="center" style="width: 12%;">Chính xác</th>
                <th class="center" style="width: 15%;">Số Câu Đã Làm</th>
                <th class="center" style="width: 15%;">Điểm TH</th>
                <th class="center" style="width: 14%;">Xu hướng</th>
            </tr>
        </thead>
        <tbody>
            @php($priorityList = collect($dossier['skills'])->filter(fn($s) => $s['evidenceLevel'] !== 'Insufficient')->sortByDesc('opportunityIndex')->take(5))
            @forelse($priorityList as $s)
                <tr>
                    <td class="left">
                        <b>{{ $s['skill'] }}</b><br>
                        <span class="small muted">{{ $domainMap[$s['domain']] ?? $s['domain'] }}</span>
                    </td>
                    <td class="center {{ $s['sectionLabel'] === 'Math' ? 'orange' : 'blue' }} bold">{{ $s['sectionLabel'] === 'Math' ? 'Toán' : 'Đọc & Viết' }}</td>
                    <td class="center bold {{ $tone($s['accuracy']) }}">{{ $s['accuracy'] }}%</td>
                    <td class="center">{{ $s['correct'] }}/{{ $s['total'] }} <span class="small muted">({{ $s['evidenceLevel'] == 'High' ? 'Đầy đủ' : ($s['evidenceLevel'] == 'Moderate' ? 'Vừa đủ' : 'Thấp') }})</span></td>
                    <td class="center {{ $tone($s['masteryIndex']) }}">
                        <b>{{ $s['masteryIndex'] }}</b> <span class="small muted">({{ $badgeVi($s['masteryBand']) }})</span>
                    </td>
                    <td class="center">
                        <span class="badge {{ $badgeClass($s['trend']) }}">{{ $badgeVi($s['trend']) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center muted">Làm thêm các bài thi thử để xác định kỹ năng trọng tâm.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Mastery vs. Raw Accuracy Explanation --}}
    <div class="narrative-box" style="margin-top: 6px;">
        <b>Cách Tính Mức Độ Thành Thạo:</b> Tỷ lệ chính xác thô hiển thị phần trăm câu trả lời đúng. <b>Mức Thành Thạo</b> tổng hợp kết quả bài thi gần đây, độ khó câu hỏi và số lượng câu đã luyện tập. Thang đánh giá: 90+ Thành thạo, 80+ Vững vàng, 70+ Đang phát triển, 60+ Cần lưu ý, dưới 60 Tập trung ưu tiên.
    </div>

    <div class="footer">
        Digital SAT Báo Cáo Tiến Bộ Học Sinh &nbsp;•&nbsp; Trang 2: Mức Độ Thành Thạo Theo Chủ Đề & Kỹ Năng
    </div>
</div>

{{-- ========================================================================= --}}
{{-- TRANG 3: THÓI QUEN LÀM BÀI, TIỀM NĂNG & KẾ HOẠCH HÀNH ĐỘNG               --}}
{{-- ========================================================================= --}}
<div class="page {{ count($history) > 10 ? '' : 'last-page' }}">
    <div class="head">
        <h2>Trang 3: Phân Bổ Nhịp Độ, Tiềm Năng & Kế Hoạch Action</h2>
    </div>

    {{-- Score Potential Bridge Card --}}
    @if(!empty($ptLoss['avoidablePoints']))
    <div class="card" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; padding: 7px 10px; margin-bottom: 6px;">
        <table class="layout">
            <tr>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #475569; font-weight: bold; text-transform: uppercase;">Điểm Hiện Tại</div>
                    <div style="font-size: 15px; font-weight: bold; color: #1e293b;">{{ $latest['total'] ?? '-' }}</div>
                </td>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #166534; font-weight: bold; text-transform: uppercase;">Điểm Có Thể Thu Hồi</div>
                    <div style="font-size: 15px; font-weight: bold; color: #16a34a;">+{{ $ptLoss['avoidablePoints'] }} điểm</div>
                </td>
                <td style="width: 25%; text-align: center; border-right: 1px solid #dcfce7; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #0369a1; font-weight: bold; text-transform: uppercase;">Tiềm Năng Đạt Ngay</div>
                    <div style="font-size: 15px; font-weight: bold; color: #0284c7;">{{ $ptLoss['immediateAttainable'] }}</div>
                </td>
                <td style="width: 25%; text-align: center; padding: 2px 5px;">
                    <div style="font-size: 7.2px; color: #4f46e5; font-weight: bold; text-transform: uppercase;">Mục Tiêu Đặt Ra</div>
                    <div style="font-size: 15px; font-weight: bold; color: #4f46e5;">{{ $ptLoss['targetDisplay'] ?? '-' }}</div>
                </td>
            </tr>
        </table>
        <div style="font-size: 7.5px; color: #15803d; text-align: center; margin-top: 3px;">
            <b>Thu Hồi Điểm Nhanh:</b> Việc lấy lại tới <b>+{{ $ptLoss['avoidablePoints'] }} điểm</b> không đòi hỏi học thêm công thức hay từ vựng mới—chỉ cần loại bỏ lỗi bất cẩn, bẫy thời gian và câu bỏ trống.
        </div>
    </div>
    @endif

    {{-- Points Lost to Avoidable Errors (3 Cards) --}}
    <div class="section-title">Điểm Mất Do Lỗi Có Thể Tránh</div>
    <table class="layout" style="margin-bottom: 6px;">
        <tr>
            @php($careless = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Careless / Rushing Errors'))
            @php($timetrap = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Time Trap Over-Investment'))
            @php($omitted = collect($ptLoss['categories'] ?? [])->firstWhere('type', 'Unforced Omissions'))

            <td style="width: 33.3%; padding-right: 4px;">
                <div class="card-soft" style="border-left: 3px solid #dc2626; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Lỗi Bất Cẩn / Tốc Độ</div>
                    <div style="font-size: 14px; font-weight: bold; color: #dc2626; margin: 1px 0;">-{{ $careless['pointsLost'] ?? 0 }} điểm</div>
                    <div style="font-size: 7.2px; color: #64748b;">Sai {{ $careless['count'] ?? 0 }} câu ({{ $careless['perTest'] ?? 0 }} câu/bài)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Sai ở câu Dễ/Trung bình do làm vội.</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-right: 2px; padding-left: 2px;">
                <div class="card-soft" style="border-left: 3px solid #d97706; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Bẫy Quá Thời Gian</div>
                    <div style="font-size: 14px; font-weight: bold; color: #d97706; margin: 1px 0;">-{{ $timetrap['pointsLost'] ?? 0 }} điểm</div>
                    <div style="font-size: 7.2px; color: #64748b;">Sai {{ $timetrap['count'] ?? 0 }} câu ({{ $timetrap['perTest'] ?? 0 }} câu/bài)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Tốn &gt;1.4&times; thời gian dự kiến vẫn sai.</div>
                </div>
            </td>

            <td style="width: 33.3%; padding-left: 4px;">
                <div class="card-soft" style="border-left: 3px solid #64748b; padding: 6px 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #1e293b;">Câu Hỏi Bỏ Trống</div>
                    <div style="font-size: 14px; font-weight: bold; color: #475569; margin: 1px 0;">-{{ $omitted['pointsLost'] ?? 0 }} điểm</div>
                    <div style="font-size: 7.2px; color: #64748b;">Bỏ trống {{ $omitted['count'] ?? 0 }} câu ({{ $omitted['perTest'] ?? 0 }} câu/bài)</div>
                    <div style="font-size: 6.8px; color: #475569; margin-top: 2px;">Digital SAT không trừ điểm câu làm sai.</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Timing & Focus Diagnostic Insight Card --}}
    <div class="card" style="margin-bottom: 6px; padding: 6px 9px; background: #f8fafc;">
        <table class="layout">
            <tr>
                <td style="width: 50%; padding-right: 6px; border-right: 1px solid #e2e8f0;">
                    <div style="font-size: 8px; font-weight: bold; color: #0284c7;">Nhịp Độ: Tốc Độ Đầu vs Tập Trung Cuối</div>
                    <div style="font-size: 7.5px; color: #334155; margin-top: 2px; line-height: 1.35;">
                        Học sinh có xu hướng làm vội ở các câu đầu (Q1–10: trung bình 37s–42s, chính xác 70–76%), dễ mắc lỗi bất cẩn. Độ tập trung cuối module rất tốt (Q21+: chính xác 82%, trung bình 30s). Chậm lại 10s ở Q1–10 sẽ lấy lại điểm ngay.
                    </div>
                </td>
                <td style="width: 50%; padding-left: 6px;">
                    <div style="font-size: 8px; font-weight: bold; color: #16a34a;">Sức Bền & Độ Tập Trung Thi Đấu</div>
                    <div style="font-size: 7.5px; color: #334155; margin-top: 2px; line-height: 1.35;">
                        Qua 278 câu hỏi, độ chính xác không bị giảm sút về cuối module. Học sinh có sức bền tư duy tốt, cho thấy việc tăng điểm nên tập trung vào kỷ luật ở các câu đầu hơn là thể lực thi đấu.
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Action Plan & Next Steps --}}
    <div class="section-title">Kế Hoạch Hành Động & Các Bước Tiếp Theo</div>
    @forelse(array_slice($dossier['prescriptions'], 0, 3) as $r)
        <div class="card" style="border-left: 3.5px solid #2563eb; margin-bottom: 5px; padding: 6px 9px;">
            <table class="layout">
                <tr>
                    <td>
                        <b style="font-size: 9px; color: #1e293b;">{{ $r['skill'] }}</b> 
                        &nbsp;•&nbsp; <span class="bold {{ $tone($r['accuracy']) }}">{{ $r['accuracy'] }}% độ chính xác</span> 
                        <span class="small muted">qua {{ $r['total'] }} câu hỏi</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding-top: 3px; font-size: 7.8px; color: #334155; line-height: 1.4;">
                        <span style="color: #0284c7; font-weight: bold;">Mục tiêu:</span> {{ $r['objective'] }}<br>
                        <span style="color: #d97706; font-weight: bold;">Kế hoạch luyện:</span> {{ $r['practice'] }} &nbsp;|&nbsp; 
                        <span style="color: #16a34a; font-weight: bold;">Mục tiêu đạt được:</span> {{ $r['success'] }}
                    </td>
                </tr>
            </table>
        </div>
    @empty
        <div class="card center muted">Hoàn thành thêm các bài luyện tập để nhận khuyến nghị cá nhân hóa.</div>
    @endforelse

    <div class="footer">
        Digital SAT Báo Cáo Tiến Bộ Học Sinh &nbsp;•&nbsp; Trang 3: Nhịp Độ, Tiềm Năng & Kế Hoạch Hành Động
    </div>
</div>

{{-- ========================================================================= --}}
{{-- OPTIONAL PAGE 4: LỊCH SỬ BÀI THI (CHỈ XUẤT KHI >10 BÀI)                   --}}
{{-- ========================================================================= --}}
@if(count($history) > 10)
<div class="page last-page">
    <div class="head">
        <h2>Trang 4: Lịch Sử Thi Thử Đầy Đủ</h2>
    </div>

    <div class="section-title">Tất Cả Các Bài Thi Thử Đã Hoàn Thành</div>
    <table class="data-table">
        <thead>
            <tr>
                <th class="center" style="width: 8%;">#</th>
                <th class="left" style="width: 18%;">Ngày hoàn thành</th>
                <th class="left" style="width: 38%;">Tên bài thi thử</th>
                <th class="center" style="width: 12%;">Điểm Đọc-Viết</th>
                <th class="center" style="width: 12%;">Điểm Toán</th>
                <th class="center" style="width: 12%;">Tổng điểm</th>
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
        <b>Ghi chú:</b> Bảng này thống kê tất cả các bài thi thử full-length đã hoàn thành. Các bài thi theo từng section lẻ không gộp vào đây để đảm bảo tính so sánh chuẩn xác.
    </div>

    <div class="footer">
        Digital SAT Báo Cáo Tiến Bộ Học Sinh &nbsp;•&nbsp; Trang 4: Lịch Sử Bài Thi Thử
    </div>
</div>
@endif

</body>
</html>
