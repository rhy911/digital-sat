# Dự án Hệ thống Thi thử Digital SAT trực tuyến

## 1. Tổng quan dự án

Dự án này là một nền tảng E-learning chuyên biệt, mô phỏng toàn diện hệ thống thi Digital SAT của College Board. Mục tiêu cốt lõi không chỉ là cung cấp môi trường làm bài giống ứng dụng **Bluebook**, mà còn là một hệ thống quản lý học tập (LMS) hoàn chỉnh giúp thí sinh theo dõi tiến độ và Admin quản lý kho dữ liệu đề thi đồ sộ.

## 2. Các Phân hệ Chính (Modules)

Hệ thống được chia thành 4 phân hệ chính:

### A. Phân hệ Xác thực & Người dùng (Identity & Access)

- **Chức năng:** Đăng ký, Đăng nhập, Xác thực Email, Quên mật khẩu.
- **Đặc điểm:** Giao diện được tùy chỉnh theo phong cách hiện đại, tối giản. Sử dụng Laravel Sanctum/Fortify để đảm bảo bảo mật.
- **Phân quyền:** Tách biệt rõ ràng giữa Thí sinh (Candidate) và Quản trị viên (Admin).

### B. Cổng Thí sinh (Candidate Portal)

- **Dashboard:** Hiển thị lộ trình học tập, các bài thi gần đây và gợi ý bài thi.
- **Test Library:** Danh sách các đề Full-length và các bài luyện tập theo kỹ năng (Reading & Writing, Math).
- **History & Analytics:** Xem lại lịch sử thi, phân tích điểm số theo từng Domain (ví dụ: Algebra, Standard English Conventions) và xem giải thích chi tiết.

### C. Công cụ làm bài (Test Engine - Bluebook Clone)

- **Giao diện:** Mô phỏng chính xác Bluebook (Header, Footer, công cụ hỗ trợ).
- **Tính năng:**
  - **Adaptive Logic:** Tự động điều hướng Module 2 dựa trên kết quả của Module 1.
  - **Tooling:** Timer, Mark for Review, Strike-through (loại trừ đáp án), Calculator, và Highlight.
  - **Security:** Chế độ Lockdown Browser giả lập (chặn copy/paste, chặn chuột phải).

### D. Hệ thống Quản trị (Admin/CMS)

- **Test Management:** Quản lý cấu trúc đề thi phức tạp (Test > Section > Module > Question).
- **Content Management:** Trình soạn thảo chuyên biệt cho Passage (hỗ trợ paired passages) và Question (hỗ trợ công thức toán học, media).
- **User Management:** Quản lý danh sách thí sinh, theo dõi tiến độ và hỗ trợ người dùng.

## 3. Kiến trúc Kỹ thuật (Technical Architecture)

### Backend (Laravel 11)

- **Mô hình:** MVC kết hợp với Service Layer.
- **Service Layer:** Toàn bộ logic nghiệp vụ (tính điểm, xử lý adaptive, logic chuyển đổi raw-to-scaled score) phải nằm trong `app/Services`.
- **Validation:** Sử dụng `FormRequest` để tách biệt logic kiểm tra dữ liệu đầu vào.
- **Security:** CSRF, SQL Injection Protection, Rate Limiting cho API.

### Frontend (Blade & Modern JS)

- **Asset Management:** Vite.
- **JS Strategy:** Sử dụng Vanilla JS cho các tương tác hiệu năng cao trong Test Engine để đảm bảo tốc độ và sự ổn định.
- **Styling:** Sử dụng Tailwind CSS (v4) làm framework chính cho UI. Không sử dụng Bootstrap 5 cho các thành phần mới. Các component cũ sẽ được chuyển đổi dần sang Tailwind. Ưu tiên utility classes trực tiếp trong Blade views để tối ưu hiệu năng và dễ bảo trì.

### Database (MySQL)

- **Normalization:** Thiết kế chuẩn hóa cao để phục vụ cấu trúc đề thi đa cấp.
- **Optimization:** Sử dụng Index cho các cột thường xuyên truy vấn (`external_id`, `status`, `test_type`).
- **Data Integrity:** Khóa ngoại bắt buộc để đảm bảo khi xóa Đề thi thì các Section/Module liên quan được xử lý đúng (Soft Delete).

## 4. Quy tắc Phát triển (Coding Standards)

- **Surgical Updates:** Khi chỉnh sửa code, chỉ tập trung vào phần được yêu cầu, tránh refactor lan man trừ khi được chỉ định.
- **Convention:** Tuân thủ PSR-12 cho PHP và CamelCase cho JavaScript, sử dụng các hàm mới của Lavarel, không dùng kiểu php cũ.
- **Documentation:** Luôn cập nhật Migration và Model DocBlock khi thay đổi cấu trúc dữ liệu.
- **Structure**: Ưu tiên chia nhỏ các tính năng thành nhiều file, đóng gói trong từng folder nhằm dễ tìm kiếm và quản lý.
- **Testing:**
  - Test Schema: Đảm bảo Database luôn đúng cấu trúc.
  - Test Logic: Tập trung vào các hàm tính điểm và logic adaptive.

## 5. Quy trình làm việc (Workflow)

1. **Nghiên cứu:** Kiểm tra `artisan` và cấu trúc file hiện tại.
2. **Thiết kế:** Cập nhật Database (nếu cần) qua Migration.
3. **Thực thi:**
    - Tạo FormRequest -> Service -> Controller.
    - Cập nhật giao diện Blade & Assets.
4. **Kiểm chứng:** Chạy `php artisan test` và kiểm tra thủ công trên trình duyệt.

---

## Current Plan

Simplifies workflow by focusing on high-volume entry while fixing the "Bank" management logic.

### Analysis of Changes

- Remove "Create Question" form: Good. Manual entry is slow. Bulk import (Excel/JSON) is the industry standard for
  SAT data.
- Remove "Create Passage" form: Logical for R&W. In Digital SAT, one passage = one question. Bundling them in the
  import (inline passage) prevents orphaned passages and accidental reuse.
- Add "Edit Question" function: CRITICAL. Since manual "Create" is gone, users need a way to fix typos or adjust
  AI-detected domains without re-importing the whole file.

### 1. Implementation Strategy

A. Dashboard UI Cleanup

- Delete the "Create Passage" card.
- Delete the "Create New Question" card.
- Keep: "Attach Existing Question from Bank" (still useful to reuse a Math question in a different test).
- Keep: "Bulk Import" (make this the primary entry point).

B. The "Edit Question" Feature

- Modal: Clicking "Edit" on the Questions Table should open a modal.
- Fields: Stem, Question Type, Difficulty, Domain, Subdomain, SPR Hint.
- Passage Edit: If R&W, allow editing the associated passage content directly in the question edit modal.
- Logic: Updates the global questions table. Changes reflect everywhere that question is used.

C. Backend

- Add updateQuestion method to TestDashboardController.
- Update BulkQuestionImportService to ensure passage and question remain strictly linked for R&W.
