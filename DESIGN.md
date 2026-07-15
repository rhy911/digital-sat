# DESIGN.md

Token quyết định cho redesign (xem `docs/redesign-roadmap.md` Phase 1). Dùng file này làm nguồn tham chiếu cho `/impeccable` các lần sau và cho Phase 2+ khi build component library.

## Accent color

- Nguồn duy nhất: `--color-brand: #324dc7` (`resources/css/app.css` `@theme`).
- Hover variant: `--color-brand-hover: #283d9f`.
- Đã gộp vào brand: `indigo-600` (qua override token ở admin, qua rename `text-brand`/`bg-brand` ở nơi khác), `#4f46e5`, `#4361EE`/`#4361ee`, `#3347b9` (admin/test-builder cục bộ cũ).
- **Ngoại lệ (không đụng)**: landing page (`components/layouts/landing.blade.php`, `.landing-*` trong `app.css`) — marketing register, ngoài scope "product". Test Engine (`resources/css/engine/**`, `resources/views/engine/**`, và layout dùng riêng `components/layouts/test.blade.php`) — giữ nguyên tuyệt đối, giống thi thật Bluebook.

## Semantic color

- Danger = `rose` (Tailwind), alias `--color-danger: var(--color-rose-600)`.
- Success = `emerald` (Tailwind), alias `--color-success: var(--color-emerald-600)`.
- Warning = `amber` (Tailwind), alias `--color-warning: var(--color-amber-600)`.
- Minority `red`/`green`/`yellow` đã gộp về rose/emerald/amber (9 chỗ, ngoài Test Engine layout).
- Token `--color-danger/success/warning` mới đặt tên cho Phase 2 dùng (`<x-ui.status-badge>`), **chưa** rename toàn bộ 136 chỗ rose/emerald/amber hiện có sang class `danger-*`/`success-*`/`warning-*` — việc đó để Phase 2/3 làm cùng lúc build component, tránh sửa 2 lần.

## Font

- Family: **Roboto** (`--font-sans`, `@fontsource/roboto`), áp dụng cho product surfaces (student/teacher/admin/auth).
- Đã bỏ hardcode `"Roboto"` lặp lại (`app.css:79,95`) → dùng `var(--font-sans)`.
- **Ngoại lệ (không đụng)**: landing page dùng **Geist** riêng (`--font-geist`, marketing register). Test Engine dùng **Inter** (UI chrome) + **Noto Serif** (reading passages) riêng — giữ nguyên tuyệt đối, không phải nợ kỹ thuật, là quyết định đọc-hiểu cho bài thi.
- `public/css/auth.css` (font Inter, `--primary-hover: #4f46e5`) xác nhận là **file mồ côi** — không nằm trong `vite.config.js`, không blade nào load. Không sửa vì không ảnh hưởng runtime; cân nhắc xóa ở lần dọn dẹp sau (ngoài scope Phase 1 — xóa file cần quyết định riêng).

## Icon set

- Chọn: **Lucide** (SVG path copy trực tiếp, MIT license, không cần thêm npm package).
- Hiện trạng: Bootstrap Icons CDN (195 chỗ `bi bi-*`, 24 file, toàn bộ admin/test-builder) và inline SVG tự vẽ rải rác (87 chỗ, 28 file, không có shared component) **chưa đổi** — quyết định ghi lại ở đây, thực thi thật sự dời sang **Phase 6** (Admin/Test Builder migration) khi build `<x-ui.icon>`.

## Math renderer

- Chọn: **KaTeX** (đang chạy thật ở `components/layouts/test.blade.php`, `student/scores/index.blade.php`, admin builder `helpers.js`).
- Đã xóa guard chết `window.MathJax` ở `teacher/assignments/partials/attempt-monitor.blade.php` — MathJax chưa từng được load trong repo, block đó là dead code, không có hành vi nào thay đổi.

## Nợ kỹ thuật đã biết (không xử lý ở Phase 1)

- `--ds-*`/`--cw-*` (oklch, `resources/css/student/analytics.css`, `classroom.css`, `practice.css`) — hệ token semantic riêng (ink/surface/border/primary/danger/warning/progress, có soft/strong/hover variant), chỉ scope trong `.ds-home-shell`/`.classroom-body` (student dashboard/analytics/practice, phần teacher dùng chung classroom.css). Không mở rộng, không xóa ở Phase 1. Kế hoạch: hợp nhất hoặc retire khi có `<x-ui.*>` component thật (Phase 2/3).
- 260+ chỗ class `bg-indigo-600`/`text-indigo-650`/`border-indigo-100`/`border-indigo-200`/`bg-indigo-50`/`hover:bg-indigo-700` trong 21 file blade + 7 file JS admin/test-builder — **màu hiển thị đã đúng brand** (nhờ override `--primary`/`--primary-hover` trong `resources/css/admin/test-builder.css:8-9` trỏ về `var(--color-brand)`/`var(--color-brand-hover)`), chỉ còn *tên class* chưa khớp nghĩa. Rename thật sự dời sang Phase 2/3 khi các nút này được bọc vào `<x-ui.button>` — tránh sửa 2 lần.
- Bootstrap Icons CDN — xem mục Icon set ở trên.
