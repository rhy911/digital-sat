
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
