# Digital SAT — Redesign Roadmap (UI/UX)

Tài liệu này dùng để bắt đầu cuộc hội thoại mới tập trung **thực hiện** cải thiện UI/UX. Không cần đọc lại audit gốc — mọi quyết định và context cần thiết đã tóm tắt ở đây.

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

## Phase 1 — Design Tokens (nền tảng, làm trước tiên, không phụ thuộc gì)

- [ ] `app.css` `@theme`: chốt 1 accent color duy nhất — giữ `--color-brand: #324dc7` (token đã tồn tại), thay hết `indigo-600`/`#4f46e5`/`#4361EE` bằng `bg-brand`/`text-brand`.
- [ ] Dọn trùng lặp semantic color: danger (`red`+`rose` → chọn 1), success (`green`+`emerald` → chọn 1), warning (`yellow`+`amber` → chọn 1), info/primary (`blue`+`sky`+`indigo` → dùng brand).
- [ ] Chọn 1 icon set (SVG inline thống nhất) — bỏ Bootstrap Icons CDN (`admin/test-builder/index.blade.php:3`).
- [ ] Chọn 1 renderer toán — bỏ 1 trong 2 (KaTeX ở scores vs MathJax ở attempt-monitor).
- [ ] Chốt 1 font family — bỏ Roboto lặp không nhất quán (`app.css:79,95`, `classroom.css:27,1876`).
- [ ] Xóa side-stripe border cấm tuyệt đối: `student/scores.css:797` (`border-left: 4px solid #0077c8`).
- [ ] Xóa border trang trí: `app.css:206` (`border-top: 4px solid`).
- [ ] Ghi quyết định (accent, icon set, font, renderer) vào `DESIGN.md` (tạo mới nếu chưa có, dùng cho `/impeccable` các lần sau).

**Output**: token set chốt xong trong `@theme`, chưa động view/component nào.

---

## Phase 2 — Core Component Library (phụ thuộc Phase 1)

- [ ] `<x-ui.button variant="primary|secondary|danger">` — đủ state (default/hover/focus/active/disabled/loading). Thay 3+ hệ nút hiện có: Tailwind thô (admin), `class-button--primary` (teacher/classroom), `sd-hero-pill` (scores).
- [ ] `<x-ui.status-badge status="...">` — 1 map màu+icon+label ngữ nghĩa. Thay 4 hệ badge: `status-chip`, `status-badge` (+ unicode "✓"), `badge-sat`, `ds-badge`.
- [ ] `<x-ui.card>` — thay `class-panel` (teacher) + 3 card component riêng của student (`completed-practice-card`, `in-progress-practice-card`, `practice-test-card`); admin chưa có card component, dùng chung luôn.
- [ ] `<x-ui.dropdown>` — component đã có sẵn `components/ui/dropdown.blade.php`, review/mở rộng để 2 header dùng được (xem Phase 3).
- [ ] `<x-ui.alert>` — hợp nhất `components/auth/alerts.blade.php` + `components/admin/test-builder/modals/alert-container.blade.php`; show hết lỗi thay vì chỉ `$errors->first()`.
- [ ] `<x-ui.skeleton>` (row/card skeleton) — thay spinner/text loading hiện tại (`app.css:162-283` loading toàn cục, `app.css:738-838` overlay bảng, `livewire/teacher/workspace.blade.php:2-5`).

**Output**: bộ component trong `resources/views/components/ui/`, build + tự test riêng lẻ, chưa migrate view nào.

---

## Phase 3 — Migration: Shared Chrome (phụ thuộc Phase 2)

- [ ] Xóa dropdown tài khoản tự chế `components/student/headers/user-header.blade.php` → dùng `<x-ui.dropdown>`.
- [ ] Xóa dropdown tự chế `progress-header.blade.php` (đang sync `aria-expanded` qua `setTimeout`) → dùng `<x-ui.dropdown>`.
- [ ] Hợp nhất 2 cơ chế logout: form POST reload (`admin/test-builder/index.blade.php:220-230`) vs `initAjaxLogout`/localStorage (`components/layouts/student.blade.php:76-83`) → chọn 1.
- [ ] Áp `<x-ui.button>`/`<x-ui.status-badge>` vào layout/header dùng chung.

**Output**: header/nav/logout nhất quán xuyên student/teacher/admin.

---

## Phase 4 — Migration: Student Surfaces (phụ thuộc Phase 2-3)

- [ ] Thay `ds-*` button/badge/card bằng `x-ui.*` ở dashboard, practice, assignments, classroom, scores.
- [ ] Bỏ modal-first cho assignment start (`student/assignments/index.blade.php`) → nút "Start attempt" trực tiếp trên card, giữ chi tiết phụ dạng `<details>`.
- [ ] Viết lại copy score report (`student/scores/index.blade.php:14-64`): bỏ "EAP 3PL ability estimate", "adaptive_irt_provisional" khỏi hero copy → câu ngôn ngữ thường + disclosure "cách tính điểm này".
- [ ] Fix "sign out" giả ở dashboard empty-state (`student/dashboard/index.blade.php:16-19`, hiện `event.preventDefault()`+`querySelector`+`.click()`) → nút/link thật.
- [ ] Fix `<div role="button" tabindex="0">` ở `components/student/cards/practice-test-card.blade.php:57-64` → `<button>`/`<a>` thật, đảm bảo Enter/Space hoạt động.
- [ ] Thêm empty/loading/error state còn thiếu: `assignments/index`, `practice/index`, `practice/preview`, `practice/show`, `scores/index`, `scores/export-pdf`.
- [ ] Thêm responsive prefix (`sm:/md:/lg:`) cho 13/14 file student hiện chưa có (chỉ `dashboard/index.blade.php` có).
- [ ] Bớt lựa chọn nhiễu: bỏ/ẩn link "Administrator?" khỏi `auth/role-select.blade.php` cho luồng student/teacher.

**Output**: student experience dùng chung 1 hệ component, hết modal-first cho hành động chính.

---

## Phase 5 — Migration: Teacher Surfaces (phụ thuộc Phase 2-3, song song được với Phase 4)

- [ ] Thay `class-*` button/badge bằng `x-ui.*` ở `teacher/classes/show.blade.php`, `teacher/assignments/show.blade.php`.
- [ ] Chuyển "view attempts" (modal → tab → fetch) thành route thật `teacher/assignments/{assignment}/students/{student}` có prev/next, bỏ modal-per-row.
- [ ] Thêm bulk-approve cho roster pending (`teacher/classes/show.blade.php:190-200`) — checkbox multi-select + "duyệt đã chọn".
- [ ] Thêm CSV/print export bảng kết quả assignment — tái dùng pipeline PDF export của student.
- [ ] Thêm `<x-ui.skeleton>` cho `livewire/teacher/workspace.blade.php` thay text "Updating workspace...".
- [ ] Thêm responsive prefix cho 4/5 file teacher hiện chưa có (chỉ `attempt-monitor.blade.php` có).
- [ ] Nhân rộng pattern tab tốt của `teacher/classes/show.blade.php` (role="tablist", URL hash sync) sang chỗ khác nếu cần thêm tab UI mới.

**Output**: teacher hết modal-per-student, có bulk action, component nhất quán.

---

## Phase 6 — Migration: Admin / Test Builder (phụ thuộc Phase 2-3)

- [ ] Thay Tailwind thô + `indigo-*` + Bootstrap Icons bằng `x-ui.*` + token brand trong `admin/test-builder/index.blade.php` và các component con (`components/admin/test-builder/**`).
- [ ] Sidebar active-tab: chuyển từ `sessionStorage`-only sang sync URL/hash (theo đúng pattern `teacher/classes/show.blade.php` đã làm đúng) — cho deep-link được.
- [ ] Áp nguyên tắc giảm modal-first cho luồng "create test" (`admin/test-builder/index.blade.php:145`).
- [ ] Đồng bộ header/nav Test Builder với header chung đã làm ở Phase 3 (không còn "cảm giác sản phẩm khác").
- [ ] Dọn markup single-line dày đặc ở `admin/teacher-applications/index.blade.php` khi tiện tay sửa (không bắt buộc, dễ lỗi khi migrate).

**Output**: Test Builder cùng ngôn ngữ thị giác với student/teacher.

---

## Phase 7 — Cross-cutting Polish (phụ thuộc Phase 4-6 xong phần lớn)

- [ ] Accessibility sweep: audit input thiếu `for="..."` (grep signal ~40% chưa gắn label rõ), contrast `text-[#94a3b8]` trên nền trắng (`auth/signin.blade.php:77`, `auth/role-select.blade.php:32`) — làm đậm hơn để đạt 4.5:1 AA.
- [ ] Chuyển `aria-expanded` sync từ `setTimeout` hack sang binding khai báo (Alpine `x-bind`) — theo sau Phase 3 dropdown consolidation.
- [ ] Gỡ CDN không quản lý (KaTeX, Bootstrap Icons) nếu chưa gỡ hết ở Phase 1/6 — bundle qua Vite.
- [ ] Gỡ decorative motion còn sót: `.bento-card:hover { translateY(-4px) }`, emoji "🎉" (`teacher/assignments/show.blade.php:241`), unicode "✓ Completed" badge text (chuyển vào icon set của `<x-ui.status-badge>`).

---

## Phase 8 — Verify

- [ ] Chạy lại `/impeccable critique` toàn scope, so điểm với baseline 17/40.
- [ ] Đi lại 3 persona trong report gốc — xác nhận từng red flag đã hết:
  - **Alex** (power user): review nhiều học sinh không cần modal-per-row, có CSV export, có bulk-approve.
  - **Jordan** (first-timer): score report hiểu được trong 10s, sign-out là control thật, assignment start không cần modal.
  - **Sam** (accessibility): keyboard-only đi hết luồng chính, contrast đạt AA, dropdown không lệch trạng thái với screen reader.

---

## Cách dùng file này ở hội thoại mới

Mở conversation mới, dán: *"Đọc `docs/redesign-roadmap.md`, bắt đầu Phase 1"* (hoặc phase bất kỳ muốn làm trước). Không cần chạy lại audit — mọi quyết định, file cần sửa, và lý do đã có sẵn ở đây.
