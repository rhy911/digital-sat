# DESIGN.md

Token quyết định cho redesign (xem `docs/redesign-roadmap.md` Phase 1). Dùng file này làm nguồn tham chiếu cho `/impeccable` các lần sau và cho Phase 2+ khi build component library.

## Accent color

- Nguồn duy nhất: `--color-brand: #2D439A` (`resources/css/app.css` `@theme`) — đổi từ `#324dc7` ở Phase 2 sau khi đánh giá: hue cũ (~229°, cobalt) đúng hướng nhưng saturation/lightness (~60%/49%) rơi vào vùng "xanh vivid trung bình" phổ biến ở hàng loạt SaaS/ed-tech khác, dễ đọc thành "generic LMS look". `#2D439A` giữ cùng họ hue (~228°), giảm saturation/lightness (~55%/39%) → cảm giác institutional/premium hơn, contrast trên nền trắng ~8.8:1 (AAA). Vì Phase 1 đã tokenize hết qua `var(--color-brand)`/`bg-brand`/`text-brand`, đổi giá trị chỉ cần sửa 1 dòng token, không cần sửa lại 41 file đã dùng.
- Hover variant: `--color-brand-hover: #24367B`.
- Soft tint (badge/background nhạt): `--color-brand-soft: color-mix(in oklab, var(--color-brand) 12%, white)`.
- Đã gộp vào brand: `indigo-600` (qua override token ở admin, qua rename `text-brand`/`bg-brand` ở nơi khác), `#4f46e5`, `#4361EE`/`#4361ee`, `#3347b9` (admin/test-builder cục bộ cũ).
- **Ngoại lệ (không đụng)**: landing page (`components/layouts/landing.blade.php`, `.landing-*` trong `app.css`) — marketing register, ngoài scope "product". Test Engine (`resources/css/engine/**`, `resources/views/engine/**`, và layout dùng riêng `components/layouts/test.blade.php`) — giữ nguyên tuyệt đối, giống thi thật Bluebook.

## Semantic color

- Danger = `rose` (Tailwind), alias `--color-danger: var(--color-rose-600)`.
- Success = `emerald` (Tailwind), alias `--color-success: var(--color-emerald-600)`.
- Warning = `amber` (Tailwind), alias `--color-warning: var(--color-amber-600)`.
- Neutral = `slate` (Tailwind), alias `--color-neutral: var(--color-slate-500)` — thêm ở Phase 2 cho badge/button "archived/readonly/secondary" (không có token trước đó, hardcode hex rời rạc).
- Minority `red`/`green`/`yellow` đã gộp về rose/emerald/amber (9 chỗ, ngoài Test Engine layout).
- Token `--color-danger/success/warning/neutral` dùng trong `<x-ui.status-badge>` (Phase 2), **chưa** rename toàn bộ 136 chỗ rose/emerald/amber hiện có sang class `danger-*`/`success-*`/`warning-*` — việc đó để Phase 3+ làm cùng lúc migrate view, tránh sửa 2 lần.

## Component API (`resources/views/components/ui/`, Phase 2)

- `<x-ui.button variant="primary|secondary|danger|ghost-on-dark" size="sm|md" loading href type>` — đủ state default/hover/focus-visible/active/disabled/loading. `href` → render `<a>`, không thì `<button>`. `ghost-on-dark` dành cho nền tối (thay `.sd-hero-pill` scores hero khi migrate).
- `<x-ui.status-badge status="success|danger|warning|brand|neutral">` — slot mặc định = label, slot `icon` tùy chọn.
- `<x-ui.card padded shadow="sm|none">` — slot `header`/mặc định/`footer`, đều tùy chọn trừ mặc định.
- `<x-ui.dropdown align="left|right|top" width trigger content>` — đã có sẵn từ trước, Phase 2 chỉ vá (bỏ tàn dư `dark:` mode, thêm `aria-haspopup`/`aria-expanded`/Escape-to-close). Chưa gắn vào header thật nào (Phase 3).
- `<x-ui.alert type="success|danger|warning" :messages="$errors->all()" dismissible>` — `messages` là mảng thì hiện list nếu >1 phần tử; không truyền `messages` thì dùng slot mặc định (custom message đơn). Không tự render nếu rỗng cả hai.
- `<x-ui.skeleton count class>` — `class` truyền width/height (vd `h-4 w-1/3`), `count` > 1 lặp nhiều dòng.
- Dev preview: `/dev/ui-kit` (route guard `app()->environment('local')`, `routes/web.php`) — show hết variant/state, dùng để test trước khi migrate view thật ở Phase 3+.

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
