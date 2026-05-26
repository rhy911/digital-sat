# Dự án Hệ thống Thi thử Digital SAT trực tuyến

## 1. Tổng quan dự án

Dự án này là một nền tảng E-learning chuyên biệt, mô phỏng toàn diện hệ thống thi Digital SAT của College Board. Mục tiêu cốt lõi không chỉ là cung cấp môi trường làm bài giống ứng dụng **Bluebook**, mà còn là một hệ thống quản lý học tập (LMS) hoàn chỉnh giúp thí sinh theo dõi tiến độ và Admin quản lý kho dữ liệu đề thi đồ sộ.

## 2. Các Phân hệ Chính (Modules)

Hệ thống được chia thành 4 phân hệ chính:

### A. Phân hệ Xác thực & Người dùng (Identity & Access)

- **Chức năng:** Đăng ký, Đăng nhập, Xác thực Email, Quên mật khẩu.
- **Đặc điểm:** Giao diện được tùy chỉnh theo phong cách hiện đại, tối giản. Sử dụng Laravel Sanctum/Fortify để đảm bảo bảo mật.
- **Phân quyền:**
  - **Học sinh (Student):** Người tham gia thi và luyện tập.
  - **Giáo viên (Teacher):** Quản lý nội dung đề thi, theo dõi tiến độ học sinh.
  - **Quản trị viên (Admin):** Dành riêng cho nhà phát triển để quản trị hệ thống và cấu hình kỹ thuật.

### B. Cổng Học sinh (Student Portal)

- **Dashboard:** Hiển thị lộ trình học tập, các bài thi gần đây và gợi ý bài thi.
- **Test Library:** Danh sách các đề Full-length và các bài luyện tập theo kỹ năng (Reading & Writing, Math).
- **History & Analytics:** Xem lại lịch sử thi, phân tích điểm số theo từng Domain (ví dụ: Algebra, Standard English Conventions) và xem giải thích chi tiết.

### C. Công cụ làm bài (Test Engine - Bluebook Clone)

- **Giao diện:** Mô phỏng chính xác Bluebook (Header, Footer, công cụ hỗ trợ).
- **Tính năng:**
  - **Adaptive Logic:** Tự động điều hướng Module 2 dựa trên kết quả của Module 1.
  - **Tooling:** Timer, Mark for Review, Strike-through (loại trừ đáp án), Calculator, và Highlight.
  - **Security:** Chế độ Lockdown Browser giả lập (chặn copy/paste, chặn chuột phải).

### E. Logic Tính điểm (Scoring Logic - Advanced IRT)

Hệ thống sử dụng phương pháp **Item Response Theory (IRT)** với mô hình **3PL (3-Parameter Logistic)** để đạt độ chính xác cao:

- **Tham số câu hỏi (Item Parameters):**
  - **Difficulty (b):** Độ khó (Easy: -1.2, Medium: 0.0, Hard: 1.4).
  - **Discrimination (a):** Độ phân biệt (MCQ: 0.9, SPR: 1.3).
  - **Guessing (c):** Xác suất đoán mò (MCQ: 0.25, SPR: 0.0).
- **Cơ chế tính toán:**
  - Loại bỏ các câu `is_pretest = true` khỏi kết quả tính điểm.
  - Sử dụng **Maximum Likelihood Estimation (MLE)** qua thuật toán Newton-Raphson để ước tính chỉ số năng lực **Theta (θ)** trong khoảng [-4.0, 4.0].
  - Chuyển đổi θ sang thang điểm 200–800 bằng hàm **Sigmoid Mapping** để mô phỏng đường cong điểm số thực tế.
  - **Adaptive Routing:** Sử dụng θ sau Module 1 để quyết định nhánh Easy/Hard cho Module 2.

*Chi tiết xem tại: `prompts/digital-sat-scoring-pipeline.md` và `prompts/feature_memory.md`.*

---

## 3. Quy tắc Phát triển (Coding Standards)

### Backend (Laravel 11)

- **Mô hình:** MVC kết hợp với Service Layer.
- **Service Layer:** Toàn bộ logic nghiệp vụ (tính điểm, xử lý adaptive, logic chuyển đổi raw-to-scaled score) phải nằm trong `app/Services`.
- **Validation:** Sử dụng `FormRequest` để tách biệt logic kiểm tra dữ liệu đầu vào.
- **Security:** CSRF, SQL Injection Protection, Rate Limiting cho API.

### Frontend (Blade & Modern JS)

- **Asset Management:** Vite.
- **JS Strategy:** Sử dụng Vanilla JS cho các tương tác hiệu năng cao trong Test Engine để đảm bảo tốc độ và sự ổn định.
- **Styling:** Sử dụng mô hình hybrid giữa Tailwind và Raw CSS:
  - **Raw CSS:** Dùng cho các kiểu dáng phức tạp và nâng cao (complex styles) như transitions, animations, shadows, hover effects, custom gradients, etc.
  - **Tailwind CSS (v4):** Dùng làm framework chính cho các utility classes đơn giản, cơ bản (layouts, padding, margin, flexbox, grid, text sizing, colors, etc.) trực tiếp trong Blade views để tối ưu hiệu năng và dễ bảo trì. Không sử dụng Bootstrap 5 cho các thành phần mới. Các component cũ sẽ được chuyển đổi dần sang hybrid model này.

### Database (MySQL)

- **Normalization:** Thiết kế chuẩn hóa cao để phục vụ cấu trúc đề thi đa cấp.
- **Optimization:** Sử dụng Index cho các cột thường xuyên truy vấn (`external_id`, `status`, `test_type`).
- **Data Integrity:** Khóa ngoại bắt buộc để đảm bảo khi xóa Đề thi thì các Section/Module liên quan được xử lý đúng (Soft Delete).

## 4. Quy trình & Nguyên tắc Làm việc (Agent/Dev Workflow)

- **Surgical Updates:** Khi chỉnh sửa code, chỉ tập trung vào phần được yêu cầu, tránh refactor lan man trừ khi được chỉ định.
- **Session Memory:** Sau mỗi phiên làm việc (session), bắt buộc ghi lại tóm tắt các thay đổi vào file `Herd/digital-sat/prompts/agent_memory.md`. Luôn sử dụng skill `caveman-compress` để nén tóm tắt thành dạng Caveman (Caveman-speak) nhằm tối ưu hóa tối đa dung lượng token đầu vào (input tokens) cho các session tiếp theo.
- **Feature Tracking:** Bắt buộc cập nhật mọi tính năng mới hoặc thay đổi logic quan trọng vào `prompts/feature_memory.md`. Đây là cơ sở để AI nắm bắt project nhanh và dùng để trích xuất báo cáo sau này.
- **Convention:** Tuân thủ PSR-12 cho PHP và CamelCase cho JavaScript, sử dụng các hàm mới của Lavarel, không dùng kiểu php cũ.
- **Documentation:** Luôn cập nhật Migration và Model DocBlock khi thay đổi cấu trúc dữ liệu.
- **Structure**: Ưu tiên chia nhỏ các tính năng thành nhiều file, đóng gói trong từng folder nhằm dễ tìm kiếm và quản lý.
- **Testing:**
  - Test Schema: Đảm bảo Database luôn đúng cấu trúc.
  - Test Logic: Tập trung vào các hàm tính điểm và logic adaptive.

## 5. Quy ước Đặt tên (Project Naming Conventions)

To ensure consistency and maintainability, all developers must adhere to the following naming standards:

### A. Backend (PHP - Laravel)

- **Classes/Models/Controllers:** `PascalCase` (e.g., `QuestionController`, `SatScoringService`).
- **Methods:** `camelCase` (e.g., `estimateTheta`, `getPayloadFromRequest`).
- **Variables/Properties:** `camelCase` (e.g., `count`, `domain`, `pos`). Prioritize brevity without losing technical context.
- **Example:** Use `domain` instead of `skillDomain`, `subdomain` instead of `skillSubdomain`.
- **Namespaces:** `PascalCase` (e.g., `App\Http\Controllers`).

### B. Frontend (JavaScript)

- **Functions:** `camelCase` (e.g., `showQuestion`, `smartRenderMath`).
- **Variables/Constants:** `camelCase` (e.g., `currentIndex`, `isReviewVisible`).
- **Global Constants/Environment:** `UPPER_SNAKE_CASE` (e.g., `API_BASE_URL`).
- **Files:** `kebab-case.js` (e.g., `test-navigation.js`, `ui-handlers.js`).

### C. Styling (CSS/Tailwind)

- **Standard CSS Classes:** `kebab-case` (e.g., `test-container`, `btn-primary`).
- **BEM (Block Element Modifier) for Custom Components:** `block__element--modifier` (e.g., `passage__content--highlighted`).
- **Tailwind Classes:** Use utility-first approach directly in Blade.

### D. Database (MySQL)

- **Tables:** `snake_case` (plural) (e.g., `questions`, `answer_choices`).
- **Columns:** `snake_case` (singular) (e.g., `is_pretest`, `skill_domain`).
- **Foreign Keys:** `singular_table_id` (e.g., `passage_id`, `module_id`).

### E. Views & Assets (Blade)

- **View Files:** `kebab-case.blade.php` (e.g., `take-math.blade.php`).
- **Component Folders:** `kebab-case` (e.g., `resources/views/components/test-dashboard`).
- **Image Assets:** `snake_case` (e.g., `test_preview.png`).

### F. API Routes

- **Endpoints:** `kebab-case` (plural) (e.g., `/api/user-tests`, `/api/module-questions`).
- **Parameters:** `snake_case` (e.g., `{test_id}`).

---

## 6. Chế độ Caveman (Caveman Mode - Always On)

The agent MUST read and apply the rules defined in the installed workspace skill:

- [caveman/SKILL.md](.agents/skills/caveman/SKILL.md)

Rules:

- Auto-activates on every session start.
- Follow the rules, intensity levels (lite, full, ultra, wenyan), examples, and auto-clarity boundaries detailed in the `SKILL.md` file.
- Default intensity level: `full`.
