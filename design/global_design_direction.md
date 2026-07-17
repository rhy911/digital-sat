# Định hướng thiết kế toàn cục (sau redesign Classroom)

Tài liệu này chốt lại hướng thiết kế thống nhất cho toàn bộ frontend (trừ **test engine** — `resources/views/engine/**`, giữ nguyên không đụng vào), dựa trên phân tích redesign Classroom (`student/classes/show.blade.php`, `teacher/classes/show.blade.php`, `classroom-workspace.css`, `components/classroom/*`) và các quyết định đã chốt với chủ dự án.

---

## 1. Ngôn ngữ hình ảnh: Paper / Manila / Corkboard — áp dụng toàn cục

Không chỉ riêng Classroom. Toàn bộ app dùng chung ẩn dụ "hồ sơ giấy":

- Nền giấy ngà ấm (`--binder-bg #FCF9F2`), tab manila (`--manila #F1E5C9`), gáy sổ (`--binder-spine #E2CE8B`).
- Corkboard gỗ (`--wood-back #4D3319`, `--cork-tan #A67C52`) cho các khu vực "ghim thông báo / digest" — không bắt buộc xuất hiện ở mọi trang, chỉ ở nơi có khu phụ (sidebar digest) hợp lý.
- Với màn hình dày dữ liệu (admin test-builder, bảng số liệu lớn) — giữ đúng palette và texture nhưng giảm hoạ tiết trang trí (không ép corkboard/ghim vào nơi không có chỗ), ưu tiên đọc được dữ liệu.

## 2. App Shell: Icon rail toàn cục, cột list là điều kiện

- **Icon rail (72px, nền tối `--rail-bg #0B1626`)**: nav chính, xuất hiện ở **mọi trang đã đăng nhập** (student + teacher + admin), thay cho top-header hiện tại (`x-student.headers.user-header`, `x-layouts.student` header mặc định).
- **Cột 2 (list, 280px)**: chỉ xuất hiện ở màn hình có hình dạng list+detail. Layout còn lại render dạng 2 cột: rail + nội dung full-width.

### Phân loại theo tính năng hiện có

| Khu vực | Layout | Ghi chú |
|---|---|---|
| Classroom (student + teacher) | 3 cột: rail + list lớp + ledger/corkboard | Đã xong, làm mẫu chuẩn |
| Student scores / attempts history | 3 cột: rail + list lượt làm bài + chi tiết attempt | Áp dụng pattern giống Classroom |
| Teacher assignments | 3 cột: rail + list assignment + roster/kết quả detail | Mirror tab pattern của teacher classroom |
| Student dashboard | 2 cột: rail + nội dung full-width | Không có list tự nhiên |
| Student practice (thư viện đề luyện) | 2 cột: rail + nội dung full-width | Grid card, không phải list+detail |
| Student analytics | 2 cột: rail + nội dung full-width | Biểu đồ, không phải list+detail |
| Admin test-builder | 2 cột: rail + nội dung full-width | Công cụ dựng đề, giữ nguyên density cao |
| Admin teacher-applications | 2 cột: rail + nội dung full-width (chưa chốt 3-cột) | Có thể nâng cấp lên 3-cột sau nếu cần |
| Test engine (làm bài) | **Không đổi** | Ngoài phạm vi redesign |

Điều hướng trong icon rail cần thống nhất theo role (student rail-items vs teacher rail-items như đã có trong Classroom), tái sử dụng `x-classroom.icon-rail` nhưng nên đổi namespace thành component dùng chung (xem mục 5).

## 3. Design tokens: tạo file nguồn chân lý duy nhất

Hiện trạng: `classroom-workspace.css` định nghĩa `--cw-*` fallback về `--ds-*` (`--ds-ink`, `--ds-primary`, `--ds-surface`, `--ds-border`...) nhưng **không có nơi nào định nghĩa `--ds-*` cả** — chỉ là fallback rải rác ở 3 file (`classroom.css`, `student/analytics.css`, `student/practice.css`).

Việc cần làm:

1. Tạo `resources/css/design-tokens.css` — định nghĩa chính thức toàn bộ `--ds-*` (ink, ink-strong, muted, border, surface, primary, primary-hover, primary-soft, danger, success, focus...), giá trị lấy từ palette Manila hiện có trong `classroom-workspace.css` (`--ink #1A202C`, `--accent #2A4D8F`, v.v.) làm chuẩn.
2. `app.css` migrate `--color-brand` và các biến Tailwind theme sang tham chiếu `--ds-primary` thay vì định nghĩa riêng `#2D439A` — tránh 2 hệ màu song song.
3. Mọi file CSS feature mới (scores, assignments...) chỉ dùng `--ds-*`, không tự định nghĩa lại palette.

## 4. Typography: self-host qua Vite build

Bỏ pattern hiện tại (Google Fonts CDN `<link>` chèn trong từng blade qua `@push('styles')`). Thay bằng:

- Đưa 3 font (`Architects Daughter`, `IBM Plex Mono`, `Plus Jakarta Sans`) vào build pipeline (vd. `@fontsource/*` qua npm, hoặc file `.woff2` local trong `resources/fonts` + `@font-face` trong CSS chính).
- Load một lần duy nhất ở tầng global (app.css hoặc file font riêng import trong layout), không lặp lại `<link>` ở từng trang.
- Khai báo font-family qua token (`--font-body`, `--font-mono`, `--font-hand`) trong `design-tokens.css` để mọi feature dùng chung biến thay vì hardcode tên font.

## 5. Component hoá: tách khỏi namespace `classroom`

Các component hiện nằm ở `components/classroom/*` (`icon-rail`, `sidebar-list`, `stat-row`, `tab-bar`, `corkboard`) thực chất là shell components dùng chung toàn app. Cần:

- Di chuyển/đổi tên sang namespace chung, ví dụ `components/shell/*` hoặc `components/ui/*` (`x-shell.icon-rail`, `x-shell.sidebar-list`...).
- Giữ classroom dùng lại đúng các component này (không fork riêng bản thứ 2).
- `index-card`, `status-badge`, `role-tag`, `type-tag` (hiện là class CSS thuần trong `classroom-workspace.css`) nên hoá thành Blade component dùng chung nếu tái sử dụng ở scores/assignments.

## 6. Phạm vi loại trừ

- `resources/views/engine/**` (test-taking engine) — không đổi.
- Trang auth (login/signup) — chưa nằm trong phạm vi bàn ở đây, cần hỏi riêng khi tới lượt.

## 7. Thứ tự triển khai đề xuất (chưa chốt, cần xác nhận khi bắt đầu)

1. Tạo `design-tokens.css` + migrate `app.css` (nền tảng, làm trước mọi redesign khác).
2. Tách shell components ra namespace dùng chung, self-host fonts.
3. Redesign **Student scores/attempts** (3-cột) — độ phức tạp tương tự Classroom, học lại được nhiều pattern nhất.
4. Redesign **Teacher assignments** (3-cột).
5. Redesign các trang 2-cột còn lại (dashboard, practice, analytics, admin) theo palette mới nhưng giữ layout đơn giản.
