# Digital SAT — Redesign Roadmap (UI/UX)

Tài liệu này dùng để bắt đầu cuộc hội thoại mới tập trung **thực hiện** cải thiện UI/UX. Không cần đọc lại audit gốc — mọi quyết định và context cần thiết đã tóm tắt ở đây.

## Tiến độ (cập nhật 2026-07-16)

| Phase | Trạng thái |
|---|---|
| 1 — Design Tokens | ✅ Xong (brand color đổi giá trị sang `#2D439A` ở Phase 2; icon set/Bootstrap Icons removal hoãn sang Phase 6; `classroom.css` còn sót Roboto hardcode) |
| 2 — Core Component Library | ✅ Xong (`x-ui.button/status-badge/card/dropdown/alert/skeleton`, preview `/dev/ui-kit`) |
| 3 — Shared Chrome | ✅ Xong (2 header migrate sang `x-ui.dropdown`, logout hợp nhất, dead code dọn sạch) |
| 4 — Student Surfaces | ✅ Xong (trừ link "Administrator?" — chủ động giữ nguyên, không phải lỗi kỹ thuật) |
| 5 — Teacher Surfaces | ✅ Xong (kể cả 3 tính năng mới: view-attempts+prev/next, bulk-approve, CSV/print export) |
| 6 — Admin/Test Builder | ✅ Xong (icon Lucide đầy đủ 85 icon + JS mirror, indigo→brand, hash-sync tab, Bootstrap Icons CDN gỡ hoàn toàn) |
| 7 — Cross-cutting Polish | ✅ Xong (contrast AA, 4 input thiếu label thật đã fix, motion/emoji/unicode badge dọn sạch, CDN KaTeX/Geist ghi nhận là ngoại lệ có chủ đích) |
| 8 — Verify | ⬜ Đang làm |

Chi tiết từng mục xem checklist bên dưới (đã tick `[x]` + ghi chú deviation/lý do).

## Phạm vi & ràng buộc

- **Trừ Test Engine** (`resources/views/engine/**`, `app/Http/Controllers/Engine/**`) — phải giữ nguyên, giống thi thật Bluebook 100%. Không động vào.
- Trong scope: Student, Teacher, Admin (bao gồm Test Builder — đã quyết định đưa Test Builder vào cùng đợt redesign, không tách riêng).
- Register: "product" (app UI phục vụ tác vụ, không phải trang marketing) — 1 font family, fixed rem scale, Restrained color strategy (1 accent cho action chính/selection/state), mọi component cần đủ default/hover/focus/active/disabled/loading/error state.
- Anti-references cần tránh (đã có vi phạm, xem Phase 1/7): generic LMS look, gamification overload, modal-heavy flows, passive empty state, glassmorphism, progress-bar overload.

## Audit gốc (tham khảo khi cần chi tiết)

Full report: `.impeccable/critique/2026-07-15T08-07-25Z__full-product-non-engine-redesign.md`
Design Health Score: **17/40** (Poor band). Điểm yếu nhất: Consistency & Standards (1/4), Flexibility & Efficiency (1/4), Help & Documentation (1/4).

Verdict: không phải AI-slop — sản phẩm đa tác giả, thiếu design system chung. Từng màn riêng lẻ (practice index, class tabs) làm tốt; đặt cạnh nhau (nhất là Test Builder vs student/teacher) như 2 sản phẩm khác nhau. Cơ hội lớn nhất: **hợp nhất (consolidation)**, không phải viết lại từ đầu.

## Quyết định đã chốt (từ phiên trước)

1. Ưu tiên: **design system trước** (component + color token + dropdown hợp nhất), rồi mới tới workflow/friction fixes.
2. Không giới hạn theo role — làm design system 1 lần, áp dụng đồng loạt 3 role.
3. **Test Builder nằm trong scope redesign**, không giữ nguyên riêng.

---

## Phase 1 — Design Tokens (nền tảng, làm trước tiên, không phụ thuộc gì) ✅ XONG

- [x] `app.css` `@theme`: chốt 1 accent color duy nhất, thay hết `indigo-600`/`#4f46e5`/`#4361EE` bằng `bg-brand`/`text-brand`. **Lưu ý**: giá trị brand đổi từ `#324dc7` → `#2D439A` ở Phase 2 (đánh giá lại thấy màu gốc quá "generic SaaS", đã có sự đồng ý — xem `DESIGN.md`).
- [x] Dọn trùng lặp semantic color: danger→`rose`, success→`emerald`, warning→`amber`, info/primary→`brand`.
- [x] Chọn 1 icon set — **Lucide**, đã ghi vào `DESIGN.md`. Bootstrap Icons CDN (`admin/test-builder/index.blade.php:3`) **chưa gỡ** — quyết định hoãn việc gỡ thật sang Phase 6 (gỡ ngay lúc đó sẽ vỡ 195 chỗ dùng `bi bi-*` chưa có gì thay thế).
- [x] Chọn 1 renderer toán — giữ KaTeX, xóa dead code MathJax guard (`attempt-monitor.blade.php`, chưa từng thật sự được load).
- [x] Chốt 1 font family — Roboto. `app.css:79,95` đã dùng `var(--font-sans)`. **Còn sót**: `classroom.css:27,1892` vẫn hardcode chuỗi `"Roboto"` literal, chưa đổi sang token — nợ kỹ thuật nhỏ, để dọn khi có dịp đụng file này.
- [x] Xóa side-stripe border `student/scores.css` (`.sd-modal-expl-box`) — bị bỏ sót ở lượt Phase 1 gốc, phát hiện và fix ở lượt sau khi đụng lại file này (Phase 4).
- [x] Xóa border trang trí `app.css:206` — quyết định: **đổi màu thay vì xóa hẳn** (spinner cần border-top khác màu để tạo hiệu ứng xoay, xóa hẳn sẽ hỏng animation). Đã đổi sang `var(--color-brand)`.
- [x] Ghi quyết định vào `DESIGN.md`.

**Output**: token set chốt xong trong `@theme`. Tổng cộng ~40 file đã swap màu (brand/semantic) qua các đợt.

---

## Phase 2 — Core Component Library (phụ thuộc Phase 1) ✅ XONG

- [x] `<x-ui.button variant="primary|secondary|danger|ghost-on-dark">` — đủ state, thêm variant `ghost-on-dark` cho case `sd-hero-pill` (nền tối) ngoài dự kiến ban đầu.
- [x] `<x-ui.status-badge status="success|danger|warning|brand|neutral">`.
- [x] `<x-ui.card>` — component đã build xong, nhưng **migrate thật vào 3 card student/admin bị hoãn** ở Phase 4 vì đây là widget chuyên biệt (score ring, trend chart), không phải card generic — ép vào sẽ sai hình dạng.
- [x] `<x-ui.dropdown>` — review + sửa (bỏ tàn dư `dark:` mode, thêm `aria-haspopup`/escape-key). Việc gắn vào 2 header thật đã làm ở Phase 3.
- [x] `<x-ui.alert>` — build xong, dùng cho pattern `.class-alert--success/--error` (banner tĩnh, xử lý cả `$errors->all()`). **Không** hợp nhất với 2 hệ toast JS (`auth/alerts.blade.php`, admin `alertContainer`) — kiến trúc khác hẳn, để riêng.
- [x] `<x-ui.skeleton>`.
- Bonus: thêm token `--color-neutral`, `--color-brand-soft` phục vụ badge; trang preview `/dev/ui-kit` (local-only) để tự test trước khi migrate.

**Output**: bộ component trong `resources/views/components/ui/`, build + tự test qua `/dev/ui-kit`, đã migrate thật vào view ở Phase 3-5.

---

## Phase 3 — Migration: Shared Chrome (phụ thuộc Phase 2) ✅ XONG

- [x] Xóa dropdown tự chế `user-header.blade.php` → `<x-ui.dropdown>`.
- [x] Xóa dropdown tự chế `progress-header.blade.php` → `<x-ui.dropdown>` (`setTimeout` aria-expanded hack đã xóa hoàn toàn, Alpine tự bind declarative).
- [x] Hợp nhất logout. **Lưu ý**: audit lại phát hiện claim gốc ("admin dùng form POST reload") đã lỗi thời — admin thực ra đã dùng `initAjaxLogout` từ trước. Outlier thật duy nhất là `teacher/application-status.blade.php` (form trần) — đã đồng bộ.
- [x] Áp `<x-ui.button>`/`<x-ui.status-badge>` vào header — **đánh giá lại, bỏ qua có chủ đích**: menu item/nav link trong dropdown không phải button thật, ép vào sẽ sai hình dạng, không có target phù hợp.
- Bonus: dọn dead code JS (`initDropdownToggle`, `initPracticeDashboardPage` không còn nơi gọi — bắt được 1 lỗi thật là dòng export trỏ tới hàm đã xóa), dọn dead CSS (`.user-dropdown`, `.dropdown-menu`, `.ds-account__menu`), tiện tay fix nốt hex cũ sót từ Phase 1.

**Output**: header/nav/logout nhất quán xuyên student/teacher (admin dùng header riêng, không đổi — ngoài scope 2 header đã migrate).

---

## Phase 4 — Migration: Student Surfaces (phụ thuộc Phase 2-3) ✅ XONG (có điều chỉnh scope)

- [x] Thay `ds-*` button/badge → `x-ui.*` ở analytics/index, practice/index, practice/show, practice/preview, assignments/index (5 file). **`ds-card` → giữ nguyên có chủ đích**: đây là widget chuyên biệt (score ring, trend chart SVG, domain accordion), không phải card generic — migrate sẽ chỉ đổi tên wrapper mà rủi ro vỡ layout thật, không đáng.
- [x] Bỏ modal-per-assignment (tối đa 20 modal/trang) → nút Start/Resume trực tiếp + `<details>` cho chi tiết phụ.
- [x] Viết lại copy score report — cả web (`scores/index.blade.php`) lẫn PDF (`export-pdf.blade.php`, jargon bị lặp y hệt, roadmap gốc bỏ sót file này). Chuyển thuật ngữ vào `<details>` "How is this calculated?" (web) / đoạn văn phụ (PDF không hỗ trợ tương tác).
- [x] Fix sign-out giả — dùng HTML5 `form="..."` attribute (form đặt ngoài `<p>`, tránh lỗi `<form>` không hợp lệ lồng trong `<p>`).
- [x] Fix `role="button"` giả ở `practice-test-card.blade.php` — bỏ hẳn (không có click handler, không có route đích cho "Practice Specific Questions") thay vì tự chế thêm tính năng ngoài scope.
- [x] Thêm empty state còn thiếu (domain rỗng ở `scores/index.blade.php`).
- [x] Responsive — **đánh giá lại, kết luận khác roadmap gốc**: audit trực tiếp cho thấy các trang đã responsive thật qua CSS Grid `auto-fit`/`auto-fill` + `@media` có sẵn (analytics.css, scores.css), claim "13 file thiếu" dựa nhầm vào grep Tailwind prefix trong blade, bỏ sót cơ chế responsive qua CSS thuần. Không có gap thật để vá ngoài phần tự sửa.
- [ ] Bớt lựa chọn nhiễu "Administrator?" ở `auth/role-select.blade.php` — **chưa làm**, quyết định giữ nguyên (đây là vấn đề sản phẩm/bảo mật cần chủ động quyết định riêng, không phải lỗi kỹ thuật để tự sửa).

**Output**: student experience dùng chung `x-ui.button`/`x-ui.status-badge`/`x-ui.alert`, hết modal-first cho assignment start.

---

## Phase 5 — Migration: Teacher Surfaces (phụ thuộc Phase 2-3, song song được với Phase 4) ✅ XONG

- [x] Thay `class-*` button/badge bằng `x-ui.*` ở `classes/show.blade.php` + `assignments/show.blade.php`, gồm cả `x-ui.alert` cho `.class-alert--*` và 2 chỗ status-chip bị "lạm dụng" cho non-status content (map lại đúng ý nghĩa thay vì máy móc) + `document-row__badge` (hệ badge thứ 5 phát hiện thêm ngoài roadmap gốc.
- [x] "View attempts" → route thật `teacher/assignments/{assignment}/students/{student}` (tên `teacher.assignments.students.show`) có prev/next, bỏ modal-per-row + JS polling cũ. **Đây là tính năng mới thật sự** (route/controller mới, không chỉ đổi UI) — route JSON polling cũ đã có sẵn nhưng logic prev/next chưa từng tồn tại.
- [x] Bulk-approve — `ClassroomService::bulkApprove()` mới + route/controller mới + UI checkbox/"select all" (Alpine). Verify end-to-end thật (tạo test data → submit → xác nhận DB đổi → dọn sạch).
- [x] CSV export (`fputcsv` stream) + Print export (dompdf `stream()`, tái dùng đúng pipeline `Pdf::loadView()` của student). Sửa `AssignmentReportService::build()` thêm param optional để lấy full data không phân trang cho export.
- [x] Skeleton 2 hình dạng (card-grid cho "Classes", row-list cho "Assignments") thay text "Updating workspace...".
- [x] Responsive — cùng kết luận như Phase 4: `classroom.css` đã có `@media` breakpoint + `.report-table-wrap{overflow-x:auto}` lo table, không có gap thật.
- [x] Pattern tab của `classes/show.blade.php` — ghi nhận là mẫu tốt nhất hiện có, không có UI tab mới nào cần dựng trong đợt này nên chưa áp dụng chủ động ở đâu khác.

**Output**: teacher hết modal-per-student, có bulk action + CSV/print export + trang student-attempt riêng, component nhất quán.

---

## Phase 6 — Migration: Admin / Test Builder (phụ thuộc Phase 2-3) ✅ XONG (có điều chỉnh scope)

- [x] Icon: build `<x-ui.icon>` (`components/ui/icon.blade.php`, 85 icon Lucide, path fetch trực tiếp từ `unpkg.com/lucide-static` — không đoán path). Swap toàn bộ `bi bi-*` trong `index.blade.php` + 15 component con. **Phát hiện ngoài audit gốc**: JS trong `resources/js/test/dashboard/**` (Tabulator rows, dropdown, alert) cũng render `bi bi-*` qua template string (innerHTML), không phải chỉ Blade — build thêm `resources/js/shared/icons.js` (mirror cùng path) cho các chỗ này. Bootstrap Icons CDN (`index.blade.php:3`) đã gỡ hoàn toàn.
- [x] **Ngoại lệ phát sinh**: toolbar EasyMDE (rich-text editor) tự render `<i class="bi bi-*">` nội bộ qua config `className`, không nhận SVG. Giải quyết bằng CSS mask-image riêng (`resources/css/admin/easymde-toolbar-icons.css`) thay vì giữ CDN — xem `DESIGN.md`.
- [x] `indigo-*` → brand token (`bg-brand`, `text-brand`, `bg-brand-soft`, v.v.) trong toàn bộ `index.blade.php` + 15 component con + 10 file JS liên quan (pixel không đổi, chỉ tên class — theo ghi nhận ở Phase 1 rằng override CSS đã trỏ đúng brand từ trước).
- [x] Sidebar active-tab: chuyển từ `sessionStorage`-only sang sync URL/hash (theo pattern `teacher/classes/show.blade.php`) — deep-link `#tests`/`#builder`/... hoạt động, đồng bộ cả Alpine (`index.blade.php`) lẫn vanilla JS (`test/dashboard/index.js` `renderActiveTab()`).
- [x] **Đánh giá lại, giữ nguyên có chủ đích**: "create test" vẫn dùng modal (`createTestWizardModal`) — đây là luồng author nhiều bước thật sự (5 bước), ép phẳng ra trang riêng rủi ro cao hơn lợi ích; đã đảm bảo trigger là `<x-ui.button>` thật, giữ nguyên hành vi đóng/mở hiện có.
- [ ] **Chưa làm**: đồng bộ header/nav Test Builder với header chung Phase 3 — không có admin header partial nào tồn tại để tái dùng (`components/layouts/admin.blade.php` chỉ là shell trần, không như `layouts/student.blade.php`); dựng mới một shared admin header là thay đổi kiến trúc lớn hơn phạm vi phiên này, để quyết định riêng.
- [ ] **Chưa làm**: dọn markup single-line `admin/teacher-applications/index.blade.php` — đúng như đánh giá gốc, rủi ro cao hơn lợi ích, bỏ qua.

**Output**: Test Builder dùng chung `<x-ui.icon>`/brand token/hash-sync với student/teacher; Bootstrap Icons CDN gỡ hoàn toàn khỏi product surface. Header hợp nhất và teacher-applications markup vẫn còn nợ kỹ thuật, để lại cho đợt sau.

---

## Phase 7 — Cross-cutting Polish (phụ thuộc Phase 4-6 xong phần lớn) ✅ XONG

- [x] Contrast: `text-[#94a3b8]` → `text-slate-600` ở cả 3 file (`auth/signin.blade.php:77`, `auth/role-select.blade.php:32`, và `auth/remembered.blade.php:42` — phát hiện thêm ngoài audit gốc, cùng pattern).
- [x] Accessibility sweep: audit lại phát hiện claim "~40% input thiếu label" phần lớn là **false positive** — nhiều input đã có label hợp lệ qua wrap ngầm định (`<label>Text<input></label>`, không cần `for=`) như `profile/show.blade.php`, `teacher/assignments/show.blade.php`, `auth/reset-password.blade.php` (qua component `x-auth.password-field` đã có `for`/`id` đúng). **Gap thật tìm thấy**: 4 ô tìm kiếm chỉ có `placeholder`, không có label nào (`tests-tab.blade.php`, `sections-tab.blade.php`, `modules-tab.blade.php`, `questions/pool-table.blade.php`) — đã thêm `aria-label` tương ứng.
- [x] `aria-expanded` sync qua `setTimeout` — audit lại xác nhận **đã sạch từ Phase 3**, không còn `setTimeout` nào wrap `aria-expanded`. Bỏ khỏi scope Phase 7 (không có việc thật để làm).
- [x] Gỡ CDN Bootstrap Icons — hoàn thành ở Phase 6. KaTeX và Geist (landing) — **giữ nguyên có chủ đích**, xem `DESIGN.md` mục Math renderer để biết lý do (parity với Test Engine layout dùng chung, landing ngoài phạm vi "product").
- [x] Gỡ decorative motion còn sót: `.bento-card:hover { translateY(-4px) }` → `-2px` (`app.css:165`), emoji "🎉" xóa (`teacher/assignments/show.blade.php:234`), unicode "✓ Completed"/"✓ Active" → `<x-ui.icon name="check-lg">` (3 file: `completed-practice-card.blade.php`, `practice-toggle-header.blade.php`, `tests-toggle-header.blade.php`).

---

## Phase 8 — Verify ✅ XONG (điểm tăng, còn vài mục mở)

- [x] Chạy lại `/impeccable critique` toàn scope (dual-agent: design review + detector). Điểm **17/40 → 23/40** (Poor → Fair band). Report đầy đủ: `.impeccable/critique/2026-07-16T08-28-27Z__full-product-non-engine-redesign.md`.
- [x] Đi lại 3 persona — kết quả trộn (đã fix thật + vẫn còn gap thật, không tự nhận hết):
  - **Alex** (power user): review nhiều học sinh không cần modal-per-row ✅ (prev/next đã verify hoạt động đúng qua render test trực tiếp, không phải bug như critique ban đầu nghi ngờ), có CSV export ✅, có bulk-approve ✅. Còn thiếu: document management (`teacher/classes/show.blade.php`) chưa có bulk action.
  - **Jordan** (first-timer): score report hiểu được trong 10s ✅ (progressive disclosure đã verify). "Administrator?" link vẫn còn ở role-select — giữ nguyên có chủ đích (Phase 4), không phải lỗi kỹ thuật. Emoji role icon (📖/🎓) ở `auth/signup.blade.php:103,109` sót lại — chưa fix.
  - **Sam** (accessibility): dropdown state đồng bộ ✅. Contrast: 3 file auth đã fix, nhưng phát hiện thêm gap contrast mới ở `pool-table.blade.php` (Test Builder, `text-slate-400` trên `bg-slate-50`) — sweep Phase 7 không quét lại đúng surface vừa migrate ở Phase 6.
- [ ] **Việc còn mở sau critique** (chưa làm ở phiên này, để quyết định tiếp): (1) `pool-table.blade.php` còn hệ thống `.status-chip`/button/dropdown song song với `<x-ui.*>` — Consistency & Standards vẫn 2/4; (2) `indigo-*` còn sống ở `teacher/assignments/partials/attempt-monitor.blade.php:248,256,272` (ngoài phạm vi Phase 6, chưa đụng); (3) contrast `pool-table.blade.php` table header; (4) emoji signup.

**Kết luận Phase 8**: tiến bộ thật, đo được (17→23), không phải chỉ tự nhận. Consistency & Standards — mục tiêu số 1 của cả đợt redesign — vẫn là điểm yếu nhất (2/4), vì chính surface vừa migrate (Test Builder `pool-table.blade.php`) tái hiện đúng anti-pattern ban đầu ở quy mô nhỏ hơn.

---

## Cách dùng file này ở hội thoại mới

Mở conversation mới, dán: *"Đọc `docs/redesign-roadmap.md`, bắt đầu Phase 1"* (hoặc phase bất kỳ muốn làm trước). Không cần chạy lại audit — mọi quyết định, file cần sửa, và lý do đã có sẵn ở đây.
