# Tài liệu Kiến trúc Hệ thống (System Architecture Specification)

> **Trạng thái:** Chốt chính thức (Locked 2026-08-04)
> **Phiên bản:** 4.0 (Rewritten — chỉ mô tả kiến trúc hiện tại, đã bỏ toàn bộ phần lộ trình quy hoạch)
>
> [!NOTE]
> Tài liệu này mô tả **kiến trúc thực tế đang chạy** của repo `digital-sat`, được verify trực tiếp từ migrations, service layer, config và CI pipeline. Repo này phục vụ mục đích **tối ưu hoá và cải thiện** web Digital SAT hiện có, chạy trên **iNET cPanel shared hosting (không có quyền root)**.
>
> Các hạng mục quy hoạch trước đây trong bản v3.0 (containerize Docker, Redis, migrate Inertia.js/React, Exam Driver đa kỳ thi TSA/THPT Quốc gia) đã được xác nhận thuộc về **lộ trình của một dự án khác**, không nằm trong phạm vi repo này — cPanel hiện tại của hệ thống không hỗ trợ Redis/Docker nên các hạng mục đó không áp dụng được ở đây. Tài liệu này vì vậy không còn mục lộ trình (roadmap).

---

## Mục lục (Table of Contents)

- [Tài liệu Kiến trúc Hệ thống (System Architecture Specification)](#tài-liệu-kiến-trúc-hệ-thống-system-architecture-specification)
  - [Mục lục (Table of Contents)](#mục-lục-table-of-contents)
  - [1. Tổng quan \& Mục tiêu (Overview \& Objectives)](#1-tổng-quan--mục-tiêu-overview--objectives)
    - [1.1 Bối cảnh \& Mục đích (Context \& Purpose)](#11-bối-cảnh--mục-đích-context--purpose)
    - [1.2 Phạm vi (Scope: In-scope \& Out-of-scope)](#12-phạm-vi-scope-in-scope--out-of-scope)
      - [**In-scope (Nằm trong phạm vi):**](#in-scope-nằm-trong-phạm-vi)
      - [**Out-of-scope (Không nằm trong phạm vi):**](#out-of-scope-không-nằm-trong-phạm-vi)
    - [1.3 Thuật ngữ \& Từ viết tắt (Glossary)](#13-thuật-ngữ--từ-viết-tắt-glossary)
    - [1.4 Sơ đồ Phân rã Chức năng (Functional Decomposition Map)](#14-sơ-đồ-phân-rã-chức-năng-functional-decomposition-map)
      - [**A. Phân hệ Học sinh (Student Portal)**](#a-phân-hệ-học-sinh-student-portal)
      - [**B. Phân hệ Giáo viên \& Trung tâm (Teacher / Center Admin)**](#b-phân-hệ-giáo-viên--trung-tâm-teacher--center-admin)
      - [**C. Phân hệ Quản trị Hệ thống (System Admin)**](#c-phân-hệ-quản-trị-hệ-thống-system-admin)
      - [**D. Phân hệ Core Engine \& Chấm điểm (SAT Engine)**](#d-phân-hệ-core-engine--chấm-điểm-sat-engine)
  - [2. Kiến trúc Hệ thống Tổng thể (High-Level Architecture - HLA)](#2-kiến-trúc-hệ-thống-tổng-thể-high-level-architecture---hla)
    - [2.1 Sơ đồ Kiến trúc Tổng thể (System Architecture Diagram)](#21-sơ-đồ-kiến-trúc-tổng-thể-system-architecture-diagram)
    - [2.2 Phong cách Kiến trúc (Architecture Pattern)](#22-phong-cách-kiến-trúc-architecture-pattern)
    - [2.3 Bảng Công nghệ Sử dụng (Tech Stack)](#23-bảng-công-nghệ-sử-dụng-tech-stack)
  - [3. Thiết kế Chi tiết (Low-Level Design - LLD)](#3-thiết-kế-chi-tiết-low-level-design---lld)
    - [3.1 Sequence Diagram: Luồng Nộp bài Async \& Polling Trạng thái (Module Submit)](#31-sequence-diagram-luồng-nộp-bài-async--polling-trạng-thái-module-submit)
    - [3.2 Thiết kế Cơ sở Dữ liệu (Database Design \& ERD)](#32-thiết-kế-cơ-sở-dữ-liệu-database-design--erd)
      - [**A. Diagram: Core Entity-Relationship Diagram (ERD)**](#a-diagram-core-entity-relationship-diagram-erd)
      - [**B. Chiến lược Đánh chỉ mục \& Retention Policy (Indexing \& Retention)**](#b-chiến-lược-đánh-chỉ-mục--retention-policy-indexing--retention)
    - [3.3 Thiết kế API \& Giao tiếp (API \& Interface Design)](#33-thiết-kế-api--giao-tiếp-api--interface-design)
      - [**A. Endpoint Standard \& Protocol**](#a-endpoint-standard--protocol)
      - [**B. Chi tiết các Endpoints chính (Hot Path Engine)**](#b-chi-tiết-các-endpoints-chính-hot-path-engine)
        - [1. Submit Module Answers (Gửi bài làm module)](#1-submit-module-answers-gửi-bài-làm-module)
        - [2. Poll Submit Status (Kiểm tra trạng thái chấm điểm)](#2-poll-submit-status-kiểm-tra-trạng-thái-chấm-điểm)
  - [4. Yêu cầu Phi chức năng \& Hạ tầng (NFR \& Infrastructure)](#4-yêu-cầu-phi-chức-năng--hạ-tầng-nfr--infrastructure)
    - [4.1 An ninh \& Bảo mật (Security Invariants)](#41-an-ninh--bảo-mật-security-invariants)
    - [4.2 Hiệu năng \& Khả năng mở rộng (Performance \& Scalability)](#42-hiệu-năng--khả-năng-mở-rộng-performance--scalability)
    - [4.3 Hạ tầng \& Triển khai (Infrastructure \& Deployment)](#43-hạ-tầng--triển-khai-infrastructure--deployment)
      - [**A. Mô hình Hosting hiện tại**](#a-mô-hình-hosting-hiện-tại)
      - [**B. Quy trình CI/CD Pipeline (GitHub Actions)**](#b-quy-trình-cicd-pipeline-github-actions)
  - [5. Giám sát, Bảo trì \& Phục hồi (Observability \& Reliability)](#5-giám-sát-bảo-trì--phục-hồi-observability--reliability)
    - [5.1 Logging \& Monitoring (Giám sát \& Ghi log)](#51-logging--monitoring-giám-sát--ghi-log)
    - [5.2 Sao lưu \& Phục hồi Thảm họa (Backup \& Disaster Recovery)](#52-sao-lưu--phục-hồi-thảm-họa-backup--disaster-recovery)

---

## 1. Tổng quan & Mục tiêu (Overview & Objectives)

### 1.1 Bối cảnh & Mục đích (Context & Purpose)

Hệ thống là **Nền tảng luyện thi Digital SAT trực tuyến** (Digital SAT Practice Platform), mô phỏng chính xác giao diện thi Digital SAT (Bluebook clone) của College Board với thuật toán chấm điểm thích ứng 3PL IRT.

**Đối tượng sử dụng chính:**

- **Học sinh (Student):** Luyện thi bài thi mô phỏng (Full-length & Section practice), xem kết quả phân tích năng lực (Domain performance, subscore SE, review chi tiết từng câu).
- **Giáo viên & Trung tâm (Teacher/Center Admin):** Tạo và quản lý ngân hàng câu hỏi, biên soạn đề thi, giao bài thi cho lớp học (Classroom LMS), xem báo cáo phân tích lớp học và xuất kết quả PDF/Print.
- **Quản trị hệ thống (System Admin):** Cấu hình hệ thống, duyệt tài khoản giáo viên, quản lý dữ liệu toàn cầu.

### 1.2 Phạm vi (Scope: In-scope & Out-of-scope)

#### **In-scope (Nằm trong phạm vi):**

- **SAT:** Bài thi thích ứng 2 phần (Reading & Writing, Math), chấm điểm IRT 3PL EAP thang 400–1600.
- **Engine Thi (Test Engine):** Mô phỏng 100% Bluebook UI (Timer, Desmos Calculator, Strike-through, Highlight, Review Grid, Lock-down browser mock).
- **Test Builder & Bank:** Trình soạn thảo đề thi, ngân hàng câu hỏi phân quyền (Mine/Shared), nhập liệu hàng loạt (JSON/CSV/ZIP media).
- **Classroom LMS:** Quản lý lớp học, bài đăng thông báo (Announcements), lịch thi (Class Calendar), giao bài thi (Assignments).
- **Tối ưu hoá liên tục trên nền hạ tầng hiện có:** cải thiện hiệu năng, chất lượng code (PHPStan/Larastan), độ phủ test, và bảo mật trong giới hạn của cPanel shared hosting.

#### **Out-of-scope (Không nằm trong phạm vi):**

- Sàn thương mại điện tử B2C bán khóa học/đề thi trực tiếp cho người dùng lẻ.
- Tự động phát hiện gian lận bằng AI webcam/micro recording (chỉ áp dụng client-side event lock browser).
- Chạy hệ thống trên Multi-region AWS/GCP.
- **Đa kỳ thi (TSA ĐHBK Hà Nội, THPT Quốc gia), containerize Docker/Redis, migrate frontend sang Inertia.js/React** — đây là các hạng mục thuộc lộ trình của một dự án khác. cPanel hosting hiện tại của hệ thống này không có quyền root và không hỗ trợ Redis/Docker nên các hạng mục đó không áp dụng cho repo này.

### 1.3 Thuật ngữ & Từ viết tắt (Glossary)

| Thuật ngữ | Tên đầy đủ                                               | Giải thích                                                                         |
| --------- | -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| **IRT**   | Item Response Theory                                     | Lý thuyết Ứng đáp Câu hỏi - Thuật toán ước lượng năng lực thí sinh $\theta$.       |
| **3PL**   | 3-Parameter Logistic                                     | Mô hình IRT 3 tham số: Độ khó ($b$), Độ phân biệt ($a$), Độ đoán mò ($c$).         |
| **EAP**   | Expected A Posteriori                                    | Thuật toán ước lượng Bayes tính giá trị $\theta$ trên lưới điểm cố định $[-4, 4]$. |
| **IDOR**  | Insecure Direct Object Reference                         | Lỗi bảo mật truy cập trái phép tài nguyên khi thay đổi tham số ID.                 |
| **SPR**   | Student-Produced Response                                | Dạng câu hỏi trả lời ngắn (tự điền số/văn bản).                                    |
| **MCQ**   | Multiple Choice Question                                 | Câu hỏi trắc nghiệm nhiều lựa chọn.                                                |
| **BEM**   | Block Element Modifier                                   | Phương pháp đặt tên CSS class để quản lý style độc lập.                            |
| **ULID**  | Universally Unique Lexicographically Sortable Identifier | ID dạng 26 ký tự, dùng thay thế UUID cho URL-safe routing.                         |
| **SE**    | Standard Error                                           | Sai số chuẩn ước lượng $\theta$, dùng tính dải điểm (score band).                  |
| **TTL**   | Time To Live                                             | Thời gian tồn tại của cache entry trước khi tự hết hạn.                            |
| **FPM**   | FastCGI Process Manager                                  | PHP process manager quản lý bởi cPanel (MultiPHP Manager).                         |
| **RPO**   | Recovery Point Objective                                 | Mức mất mát dữ liệu tối đa chấp nhận được khi khôi phục hệ thống.                  |
| **RTO**   | Recovery Time Objective                                  | Thời gian tối đa để phục hồi hệ thống sau sự cố.                                   |
| **PII**   | Personally Identifiable Information                      | Thông tin cá nhân nhạy cảm (tên, email, mật khẩu).                                 |

### 1.4 Sơ đồ Phân rã Chức năng (Functional Decomposition Map)

```mermaid
graph TD
    Root["Nền tảng Luyện thi Digital SAT\n(Digital SAT Practice Platform)"]

    %% 1. STUDENT PORTAL
    subgraph StudentPortal["1. Phân hệ Học sinh (Student Portal)"]
        S_Auth["Xác thực & Tài khoản\n(Auth / Sanctum / Fortify / Password Reset)"]
        S_Dash["Dashboard & Tiến độ\n(Progress Chart / Next Recommended Test)"]
        S_Practice["Thư viện & Luyện thi\n- Full-length Adaptive SAT Test\n- Section / Module Practice\n- Custom Domain & Subdomain Drill"]
        S_LMS["Classroom LMS (Học sinh)\n- Lớp học đã tham gia & Mã Join Code\n- Bài tập được giao (Assignments)\n- Lịch thi & Hạn nộp bài (Class Calendar)"]
        S_Review["Phân tích & Review Bài thi\n- Score Card (400-1600 / Subscores / SE)\n- Detailed Review & Explanations\n- Domain & Subdomain Performance\n- Dynamic Passage Viewer (Single & Paired)"]
    end

    %% 2. TEACHER PORTAL
    subgraph TeacherPortal["2. Phân hệ Giáo viên & Trung tâm (Teacher / Center Admin)"]
        T_Class["Quản lý Lớp học (Classroom LMS)\n- Tạo & Quản lý Lớp học / Join Code\n- Danh sách Học sinh & Bài nộp\n- Giao bài thi (Assignments: Full / Section / Module)\n- Lịch thi & Bài đăng thông báo (Announcements)"]
        T_Builder["Biên soạn Đề thi (Test Builder)\n- Tạo đề & Cấu hình Ma trận đề\n- Quản lý Section & Pivot Section-Module\n- Đóng băng dữ liệu (Content Lock Service)"]
        T_Bank["Ngân hàng Câu hỏi (Question Bank)\n- Tạo/Sửa câu hỏi (MCQ đơn, SPR)\n- Bài đọc Đơn / Bài đọc Đôi (Passages)\n- Tham số IRT 3PL (a, b, c)\n- Phân quyền (Mine / Shared Bank)\n- Import hàng loạt (JSON/CSV/ZIP Media)"]
        T_Analytics["Báo cáo Phân tích Lớp học\n- Bảng điểm tổng hợp (Class Gradebook)\n- Thống kê phổ điểm & Tỷ lệ làm đúng câu\n- Phân tích lỗ hổng kiến thức cả lớp\n- Xuất kết quả PDF / Print"]
    end

    %% 3. ADMIN PORTAL
    subgraph AdminPortal["3. Phân hệ Quản trị Hệ thống (System Admin)"]
        A_User["Quản lý Người dùng & Duyệt Role\n- Duyệt Giáo viên (Teacher Approval: pending/approved)\n- Khóa / Kích hoạt tài khoản"]
        A_Config["Quản lý Hệ thống & Kỳ thi\n- Cấu hình Score Conversion Sets\n- Phê duyệt bảng quy đổi điểm Adaptive Curves\n- Quản lý Dữ liệu Ngân hàng Toàn cầu"]
    end

    %% 4. CORE ENGINE & SCORING
    subgraph CoreEngine["4. Core Engine & Chấm điểm (SAT Engine)"]
        E_UI["Bluebook Test Engine UI\n- Dynamic Section/Module Navigation\n- Timer / Desmos Calc / Review Grid\n- Strike-through / Highlight / Bookmark\n- Lockdown Browser Mock (Block Copy/Paste)"]
        E_Collector["DOM Answer Collector\n- MCQ Single (Chữ cái A/B/C/D)\n- Short Answer SPR (Chữ / Số / Phân số)"]
        E_Score["Async Scoring Engine\n- 3PL IRT EAP Grid Estimation (theta [-4,4])\n- Path-aware Piecewise-linear Conversion\n- Snapshot Freezing (Chống Data Drift)"]
    end

    Root --> StudentPortal
    Root --> TeacherPortal
    Root --> AdminPortal
    Root --> CoreEngine
```

Mô tả phân rã chi tiết từng Phân hệ:

#### **A. Phân hệ Học sinh (Student Portal)**

- **Xác thực & Hồ sơ:** Đăng ký, đăng nhập, xác thực email, đổi mật khẩu (Laravel Sanctum/Fortify).
- **Luyện thi đa hình thức:**
  - *Full-length Adaptive Test:* Bài thi hoàn chỉnh mô phỏng thi thật với định tuyến Module 2 thích ứng.
  - *Section / Module Practice:* Luyện tập riêng từng phần (Reading & Writing hoặc Math).
  - *Custom Domain Drill:* Luyện tập tùy chọn theo miền kiến thức (Information & Ideas, Craft & Structure, Expression of Ideas, Standard English Conventions, Algebra, Advanced Math, Problem-Solving, Geometry & Trigonometry).
- **Classroom LMS (Học sinh):** Đăng ký tham gia lớp học qua mã Join Code 8 ký tự, theo dõi danh sách bài thi được giao (`assignments`), nộp bài và xem lịch thi (`class calendar`).
- **Phân tích Kết quả Chi tiết (Detailed Review):** Xem tổng điểm (400–1600), dải điểm sai số chuẩn (Score Band SE), chi tiết từng câu hỏi làm đúng/sai kèm giải thích (`question_explanations`), xem bài đọc đơn/bài đọc đôi tương ứng.

#### **B. Phân hệ Giáo viên & Trung tâm (Teacher / Center Admin)**

- **Quản lý Lớp học (Classroom LMS):** Tạo lớp học, quản lý danh sách học sinh, sinh mã Join Code, đăng thông báo (Announcements), giao bài thi theo hạn nộp và giới hạn số lần làm bài (`attempt_limit`).
- **Soạn thảo Đề thi (Test Builder):** Tạo đề thi mới, cấu hình ma trận Section/Module, liên kết ngân hàng câu hỏi, kích hoạt khóa dữ liệu (`TestContentLockService`) ngăn chỉnh sửa đề đã giao.
- **Ngân hàng Câu hỏi (Question Bank):**
  - Quản lý câu hỏi cá nhân (`Mine`) và ngân hàng chia sẻ (`Shared`).
  - Hỗ trợ bài đọc ngắn, bài đọc dài, bài đọc đôi (`passages`, `paired_passages`).
  - Quản lý tham số IRT 3PL ($a$: độ phân biệt, $b$: độ khó, $c$: độ đoán mò).
  - Nhập liệu hàng loạt (Bulk Import) qua file JSON/CSV/ZIP chứa media.
  - Hỗ trợ 2 dạng câu hỏi: MCQ đơn, Tự điền số/văn bản (SPR).
- **Báo cáo Phân tích Lớp học (Class Analytics):** Bảng điểm tổng hợp của lớp (Gradebook), thống kê phổ điểm, tỷ lệ trả lời đúng/sai từng câu hỏi để phát hiện lỗ hổng kiến thức chung của lớp, xuất file báo cáo.

#### **C. Phân hệ Quản trị Hệ thống (System Admin)**

- **Duyệt Giáo viên (Teacher Approval Workflow):** Xem danh sách tài khoản đăng ký vai trò Giáo viên, xét duyệt (`teacher_approval_status`: `pending` $\rightarrow$ `approved`/`rejected`).
- **Quản lý Cấu hình Thang điểm (Score Conversion Sets):** Quản lý phiên bản quy đổi điểm IRT Theta sang Scaled Score (200-800), phê duyệt/đóng băng bộ quy đổi điểm (`SCORE_CONVERSION_SETS`).
- **Quản lý Dữ liệu Toàn cầu:** Giám sát ngân hàng câu hỏi dùng chung toàn hệ thống, quản lý media tĩnh.

#### **D. Phân hệ Core Engine & Chấm điểm (SAT Engine)**

- **Giao diện Thi Bluebook Replica (Test Engine UI):**
  - Chạy giao diện chuẩn Bluebook (Timer đếm ngược, Desmos Calculator tích hợp, Strike-through gạch đáp án, Highlight văn bản, Review Grid lưới câu hỏi, Bookmark đánh dấu).
  - Trình phong tỏa client (Lockdown browser mock) chặn Copy/Paste/Right-click và phím tắt.
- **Bộ Thu thập Đáp án DOM (Answer Collector Contract):** Hợp đồng giao tiếp DOM thống nhất (`read`, `restore`, `isAnswered`, `watch`) thu thập kết quả cho 2 dạng câu hỏi (MCQ đơn, SPR).
- **Hệ thống Chấm điểm Bất đồng bộ (Async IRT Scoring):**
  - Ước lượng năng lực thí sinh $\theta$ qua thuật toán **3PL IRT EAP Grid** trên lưới điểm $[-4, 4]$ step $0.05$.
  - Quy đổi $\theta \rightarrow$ điểm 200-800 qua đường cong piecewise-linear (`config/sat_scoring.php`).
  - Ghi nhận `question_snapshot` vào `user_test_answers` để đóng băng dữ liệu thi tại thời điểm nộp bài, chống data drift khi câu hỏi bị sửa về sau.
  - Receipt idempotency check trong `user_test_module_submissions` bảo đảm không chấm điểm trùng nộp bài.

---

## 2. Kiến trúc Hệ thống Tổng thể (High-Level Architecture - HLA)

### 2.1 Sơ đồ Kiến trúc Tổng thể (System Architecture Diagram)

```mermaid
flowchart TB
    subgraph ClientLayer["1. Client Layer (Browser)"]
        FE["Blade + Alpine.js + Livewire\n(Vanilla JS Test Engine)"]
    end

    subgraph AppLayer["2. Application Layer (Laravel 12, PHP-FPM via cPanel)"]
        Router["Laravel Router & Middleware"]
        AuthMiddleware["Auth & Ownership Guards\n(Sanctum / Fortify / FormRequest)"]

        subgraph Services["Service Layer"]
            SatScoring["SatScoringService\n(3PL IRT EAP Grid)"]
            ContentLock["TestContentLockService"]
            ScoreConversion["ScoreConversionService\n(Adaptive + Default)"]
        end
    end

    subgraph AsyncLayer["3. Async Processing Layer"]
        Queue["MySQL Queue (database driver)\ncPanel Cron -> php artisan queue:restart"]
    end

    subgraph StorageLayer["4. Data & Storage Layer"]
        Storage["MySQL 8.0 (SESSION_DRIVER / CACHE_STORE / QUEUE_CONNECTION = database)\nLocal Disk (FILESYSTEM_DISK=local)"]
    end

    FE -->|HTTPS| Router
    Router --> AuthMiddleware
    AuthMiddleware --> Services
    Services -->|Read / Write| Storage
    Queue -->|Pop Jobs from MySQL| Services
    Queue -->|Read / Write| Storage
```

### 2.2 Phong cách Kiến trúc (Architecture Pattern)

- **Layered Monolith with Async Worker Pattern:** Laravel 12 đóng vai trò Monolith backend sạch (MVC + Mandatory Service Layer). Logic chấm điểm nặng và đắt đỏ được đẩy xuống hàng đợi Async Queue (MySQL `database` driver), xử lý bởi worker chạy qua cPanel cron.

### 2.3 Bảng Công nghệ Sử dụng (Tech Stack)

| Tầng | Công nghệ | Ghi chú |
| --- | --- | --- |
| **Backend Framework** | Laravel 12 (PHP 8.2) | |
| **Frontend UI** | Blade + Vanilla JS + Alpine.js 3 + **Livewire 4.3** | |
| **Data Table** | Tabulator.js | |
| **Session & Cache** | MySQL (`SESSION_DRIVER=database`, `CACHE_STORE=database`) | cPanel shared hosting không hỗ trợ Redis, nên giữ nguyên MySQL `database` driver. |
| **Queue Connection** | MySQL (`QUEUE_CONNECTION=database`) | |
| **Queue Worker** | cPanel Cron gọi `php artisan queue:restart` (`deploy.sh`) | Không có Supervisor daemon do cPanel không có quyền root. |
| **Code Quality** | Larastan v3 (`composer.json`) + `phpstan.neon` (Level 6, `app/`) | Chưa được wire vào CI pipeline. |
| **CI/CD** | GitHub Actions: `composer audit` + `php artisan test` (SQLite in-memory) + `npm audit` | Xem chi tiết pipeline tại [4.3.B](#b-quy-trình-cicd-pipeline-github-actions). |
| **Hosting** | iNET cPanel shared hosting (không có quyền root, không hỗ trợ Redis/Docker) | |

---

## 3. Thiết kế Chi tiết (Low-Level Design - LLD)

### 3.1 Sequence Diagram: Luồng Nộp bài Async & Polling Trạng thái (Module Submit)

> Sơ đồ này phản ánh **luồng hiện tại** trong `SubmissionController.php`, đã được verified.

```mermaid
sequenceDiagram
    autonumber
    actor Student as Student (Blade + Vanilla JS Engine)
    participant Controller as SubmissionController
    participant Lock as Cache Lock
    participant DB as MySQL DB
    participant Queue as Queue (database driver)
    participant Worker as Queue Worker
    participant Scoring as SatScoringService

    Student->>Controller: POST /engine/submit-module (Answers Payload)
    Controller->>Controller: DB::transaction + UserTest::lockForUpdate()
    Controller->>Controller: Check UserTestModuleSubmission receipt (idempotency)
    Controller->>Lock: Cache::lock("module_submit_lock_{userTestId}_{moduleId}", 90s)
    alt Lock acquired successfully
        Controller->>DB: UserTestAnswer::upsert (Bulk single query)
        Controller->>Controller: Cache::forget("scoring_result_{userTestId}")
        Controller->>Queue: ScoreModuleJob::dispatch(userTestId, moduleId, sectionId, timedOut, lockKey)
        Controller-->>Student: 200 OK { status: "scoring" }
    else Lock already active
        Controller-->>Student: 409 Conflict { status: "scoring" }
    end

    par Client Polling
        loop Every 1.5s (fixed interval)
            Student->>Controller: GET /engine/submit-status
            Controller->>Lock: Cache::get("scoring_result_{userTestId}")
            Controller-->>Student: { status: "scoring" }
        end
    and Worker Processing (timeout: 60s, tries: 1)
        Worker->>Queue: Pop ScoreModuleJob
        Worker->>Scoring: TestProgressionService::submit(userTest, module)
        Scoring-->>Worker: theta, scaledScore, scoreBand, path
        Worker->>DB: Update user_tests (scores, theta, rw_m2_path/math_m2_path, status)
        Worker->>DB: Create UserTestModuleSubmission receipt
        Worker->>Lock: Cache::put("scoring_result_{userTestId}", result, 300s)
        Worker->>Lock: Cache::lock(lockKey)->forceRelease()
    end

    Student->>Controller: GET /engine/submit-status
    Controller->>Lock: Cache::get("scoring_result_{userTestId}")
    Controller-->>Student: 200 OK { status: "success", next_module_id: 102 }
    Student->>Student: Navigate to Next Module UI
```

### 3.2 Thiết kế Cơ sở Dữ liệu (Database Design & ERD)

#### **A. Diagram: Core Entity-Relationship Diagram (ERD)**

> [!NOTE]
> ERD dưới đây phản ánh **schema thực tế** từ 61 migration files. Column names và enum values đã được verified. Các bảng phụ (passages, media, blog, forum) được lược bỏ để giữ sơ đồ đọc được.

```mermaid
erDiagram
    USERS ||--o{ USER_TESTS : takes
    USERS ||--o{ TESTS : creates
    TESTS ||--|{ SECTIONS : contains
    SECTIONS }|--|{ MODULES : "M2M via section_modules"
    MODULES ||--o{ ANSWER_CHOICES : "via questions"
    MODULES ||--o{ QUESTIONS : "via module_id FK"
    USER_TESTS ||--o{ USER_TEST_ANSWERS : records
    USER_TESTS ||--o{ USER_TEST_MODULE_SUBMISSIONS : validates
    QUESTIONS ||--o| QUESTION_EXPLANATIONS : has
    QUESTIONS ||--o{ ANSWER_CHOICES : has
    QUESTIONS ||--o{ SPR_CORRECT_ANSWERS : has
    TESTS ||--o{ SCORE_CONVERSION_SETS : calibrates
    CLASSROOMS ||--o{ ASSIGNMENTS : assigns
    ASSIGNMENTS ||--o{ USER_TESTS : generates

    USERS {
        bigint id PK
        string name
        string username UK "nullable"
        string email UK
        enum role "student | teacher | admin"
        string teacher_approval_status "nullable: pending | approved | rejected"
        boolean share_independent_practice "default false"
        boolean is_active "default true"
        datetime email_verified_at "nullable"
        datetime deleted_at "SoftDeletes"
    }

    TESTS {
        bigint id PK
        char_26 ulid UK "nullable"
        string title
        enum test_type "full_length | adaptive_full_length | section_only | module_only | short_test | custom_test"
        enum status "draft | active | archived"
        int total_duration_minutes "default 134"
        int break_duration_minutes "default 10"
        boolean is_public "default false"
        bigint created_by FK
        datetime content_locked_at "nullable, indexed"
        datetime deleted_at "SoftDeletes"
    }

    SECTIONS {
        bigint id PK
        bigint test_id FK
        string name
        enum type "reading_writing | math"
        int order "default 1"
        bigint created_by FK "nullable"
        boolean is_public "default false"
        datetime deleted_at "SoftDeletes"
    }

    SECTION_MODULES {
        bigint id PK "pivot table"
        bigint section_id FK
        bigint module_id FK
        string __unique "UNIQUE(section_id, module_id)"
    }

    MODULES {
        bigint id PK
        string key UK "nullable"
        char_26 ulid UK "nullable"
        bigint section_id FK "nullable — also linked via section_modules pivot"
        int module_number "1 = Module 1, 2 = Module 2"
        enum difficulty_level "standard | easy | hard"
        int duration_minutes "default 32"
        int total_questions "default 27"
        int order "default 1"
        bigint created_by FK "nullable"
        boolean is_public "default false"
        datetime deleted_at "SoftDeletes"
    }

    QUESTIONS {
        bigint id PK
        string external_id "nullable"
        bigint passage_id FK "nullable"
        bigint paired_passage_id FK "nullable"
        text stem "HTML"
        enum question_type "multiple_choice | student_produced_response"
        enum difficulty "easy | medium | hard"
        enum section_type "reading_writing | math"
        string skill_domain "varchar 50"
        string skill_subdomain "nullable, varchar 100"
        boolean is_pretest "default false"
        boolean is_complete "default true"
        boolean calculator_allowed "default true"
        decimal_4_2 irt_a "default 0.90"
        decimal_4_2 irt_b "default 0.00"
        decimal_4_2 irt_c "default 0.25"
        enum irt_calibration_status "provisional | calibrated"
        int expected_time "nullable"
        string spr_hint "nullable"
        bigint created_by FK "nullable"
        datetime deleted_at "SoftDeletes"
    }

    ANSWER_CHOICES {
        bigint id PK
        bigint question_id FK "CASCADE"
        string label "A/B/C/D (varchar 5)"
        text content "HTML"
        boolean is_correct "default false"
        int order "default 1"
    }

    SPR_CORRECT_ANSWERS {
        bigint id PK
        bigint question_id FK "CASCADE"
        string answer "varchar 100"
        enum answer_type "exact | range | fraction_equivalent"
        decimal tolerance
    }

    QUESTION_EXPLANATIONS {
        bigint id PK
        bigint question_id FK "UNIQUE, CASCADE"
        text explanation "HTML"
        text rationale_a "nullable"
        text rationale_b "nullable"
        text rationale_c "nullable"
        text rationale_d "nullable"
        text strategy_tip "nullable"
        text common_mistakes "nullable"
    }

    USER_TESTS {
        bigint id PK "table: user_tests"
        char_26 ulid UK "nullable"
        bigint user_id FK "CASCADE"
        bigint test_id FK "RESTRICT"
        bigint assignment_id FK "nullable, RESTRICT"
        tinyint attempt_number "nullable"
        string attempt_type "default: full"
        string section_type "nullable"
        bigint current_module_id FK "nullable"
        datetime current_module_started_at "nullable"
        int current_module_elapsed_seconds "default 0"
        enum rw_m2_path "nullable: easy | hard"
        decimal_5_3 rw_theta "nullable"
        decimal_5_3 rw_theta_se "nullable"
        int score_reading_writing "nullable"
        int score_reading_writing_lower "nullable"
        int score_reading_writing_upper "nullable"
        enum math_m2_path "nullable: easy | hard"
        decimal_5_3 math_theta "nullable"
        decimal_5_3 math_theta_se "nullable"
        int score_math "nullable"
        int score_math_lower "nullable"
        int score_math_upper "nullable"
        int total_score "nullable"
        int total_score_lower "nullable"
        int total_score_upper "nullable"
        string score_estimate_kind "nullable, varchar 32"
        string scoring_method "nullable, varchar 32"
        bigint score_conversion_set_id FK "nullable"
        string status "default: completed"
        datetime completed_at "nullable"
    }

    USER_TEST_ANSWERS {
        bigint id PK
        bigint user_test_id FK "CASCADE"
        bigint module_id FK "CASCADE"
        bigint question_id FK "RESTRICT"
        string selected_answer "nullable, varchar 255"
        boolean is_correct "default false"
        int time_spent "default 0, unsigned"
        json question_snapshot "nullable — IRT params + stem + choices"
        string __unique "UNIQUE(user_test_id, module_id, question_id)"
    }

    USER_TEST_MODULE_SUBMISSIONS {
        bigint id PK
        bigint user_test_id FK "CASCADE"
        bigint module_id FK "RESTRICT"
        bigint issued_next_module_id FK "nullable"
        json result
        datetime submitted_at
        string __unique "UNIQUE(user_test_id, module_id)"
    }

    SCORE_CONVERSION_SETS {
        bigint id PK
        bigint test_id FK
        int version "unsigned"
        enum status "draft | approved | retired"
        string source_name
        char_64 checksum
        char_64 form_checksum
        bigint approved_by FK "nullable"
        datetime approved_at "nullable"
        string __unique "UNIQUE(test_id, version)"
    }

    CLASSROOMS {
        bigint id PK
        char_26 ulid UK
        bigint owner_id FK "RESTRICT"
        string name "varchar 150"
        string join_code UK "varchar 8"
        enum status "active | archived"
    }

    ASSIGNMENTS {
        bigint id PK
        char_26 ulid UK
        bigint classroom_id FK "RESTRICT"
        bigint teacher_id FK "RESTRICT"
        bigint test_id FK "RESTRICT"
        string assign_type "default: full"
        string section_type "nullable"
        string title "varchar 180"
        tinyint attempt_limit "default 1"
        enum status "draft | published"
        datetime available_at "nullable"
        datetime due_at "nullable"
        datetime deleted_at "SoftDeletes"
    }
```

#### **B. Chiến lược Đánh chỉ mục & Retention Policy (Indexing & Retention)**

- **Chỉ mục B-Tree (Indexes):**
  - `questions`: Index `(module_id)`, `external_id`, `is_complete`, `section_type`.
  - `user_tests`: Composite `(user_id, test_id, status)`, `(assignment_id, user_id, attempt_number)` UNIQUE.
  - `user_test_answers`: UNIQUE Compound `(user_test_id, module_id, question_id)`.
  - `assignments`: Index `available_at`, `due_at`, `status`.
  - `tests`: Index `content_locked_at`.
- **Chính sách Retention (Lưu trữ):**
  - Soft Deletes trên `tests`, `sections`, `modules`, `questions`, `users`, `assignments` để phục vụ audit và không làm hỏng kết quả thi lịch sử của học sinh.
  - Receipt `user_test_module_submissions` lưu trữ vĩnh viễn để bảo đảm tính không thể chối bỏ (Idempotency check).
  - `question_snapshot` JSON trong `user_test_answers` đóng băng IRT params + stem + choices tại thời điểm nộp bài, ngăn data drift khi câu hỏi bị chỉnh sửa sau.

### 3.3 Thiết kế API & Giao tiếp (API & Interface Design)

#### **A. Endpoint Standard & Protocol**

Hệ thống sử dụng **Blade Views over HTTPS** cho trang web và **RESTful JSON Endpoints** cho các thao tác AJAX Engine.

#### **B. Chi tiết các Endpoints chính (Hot Path Engine)**

##### 1. Submit Module Answers (Gửi bài làm module)

- **Method / Path:** `POST /engine/submit-module`
- **Headers:** `Content-Type: application/json`, `X-CSRF-TOKEN: <token>`
- **Request Body Payload:**

  ```json
  {
    "user_test_id": 1048,
    "module_id": 52,
    "answers": {
      "q_101": { "selected": "B", "time_spent": 42 },
      "q_102": { "value": "12/5", "time_spent": 85 }
    }
  }
  ```

- **Response Success (200 OK):**

  ```json
  {
    "status": "scoring",
    "message": "Scoring in progress..."
  }
  ```

- **Response Error (409 Conflict - Duplicate submission):**

  ```json
  {
    "status": "scoring",
    "message": "Submission already in progress."
  }
  ```

##### 2. Poll Submit Status (Kiểm tra trạng thái chấm điểm)

- **Method / Path:** `GET /engine/submit-status`
- **Query Params:** `?user_test_id=1048`
- **Response Pending (200 OK):**

  ```json
  { "status": "scoring" }
  ```

- **Response Completed (200 OK):**

  ```json
  {
    "status": "success",
    "next_module_id": 53,
    "redirect_url": "/engine/session/MOD-ULID-002"
  }
  ```

---

## 4. Yêu cầu Phi chức năng & Hạ tầng (NFR & Infrastructure)

### 4.1 An ninh & Bảo mật (Security Invariants)

1. **Phòng chống IDOR (Insecure Direct Object Reference):**
   Ràng buộc sở hữu bắt buộc được kiểm tra trực tiếp trong câu lệnh SQL query, không bao giờ `find()` rồi mới kiểm tra:

   ```php
   // CORRECT Pattern (Checked at Query Level):
   $userTest = UserTest::where('id', $id)
       ->where('user_id', Auth::id())
       ->where('status', 'in_progress')
       ->firstOrFail();
   ```

2. **Khóa nội dung thương mại (Content Lock Service):**
   Khi bài thi `Test` đã có `Assignment` được xuất bản hoặc có `ScoreConversionSet` được phê duyệt, hệ thống kích hoạt `TestContentLockService` ngăn chặn toàn bộ thao tác `UPDATE`/`DELETE` trên cây dữ liệu `Test > Section > Module > Question > AnswerChoice > QuestionExplanation`. Đăng ký qua Eloquent model events (`updating`/`deleting`) trong `AppServiceProvider::boot()`.
3. **An toàn thông tin (OWASP Compliance):**
   - **XSS:** LaTeX render qua KaTeX (client-side). Markdown render qua `league/commonmark` (server-side). Blade `{{ }}` auto-escaping cho tất cả output.
   - **CSRF:** Bảo vệ toàn bộ POST/PUT/DELETE routes bằng Laravel CSRF tokens.
   - **SQL Injection:** 100% truy vấn sử dụng Eloquent ORM và Query Builder Parameter Binding.
   - **Privacy & Logging:** Không bao giờ log request payload chứa thông tin cá nhân (PII) hoặc password.

### 4.2 Hiệu năng & Khả năng mở rộng (Performance & Scalability)

- **Chiến lược Cache (MySQL `database` driver):**
  - `Cache-aside` cho câu hỏi đề thi và thông tin cấu hình module.
  - TTL Cache 300s cho kết quả chấm điểm tạm thời (`scoring_result_{userTestId}`).
  - Cache Lock 90s ngăn chặn việc dồn nộp bài trùng lặp (`module_submit_lock_{userTestId}_{moduleId}`).
- **Polling trạng thái chấm điểm:** Frontend gọi `/submit-status` mỗi 1.5s cố định (fixed interval, không có jitter/backoff).

### 4.3 Hạ tầng & Triển khai (Infrastructure & Deployment)

#### **A. Mô hình Hosting hiện tại**

Hệ thống chạy trên **iNET cPanel shared hosting**, không có quyền root, không hỗ trợ Redis/Docker:

- **Web/App:** PHP-FPM quản lý qua cPanel MultiPHP Manager.
- **Database:** MySQL 8.0 qua cPanel (cũng đóng vai trò Session/Cache/Queue driver).
- **File Storage:** Local Disk (`FILESYSTEM_DISK=local`).
- **Queue Worker:** cPanel Cron Job gọi `php artisan queue:restart` (`deploy.sh`), không có Supervisor daemon 24/7.
- **Deploy:** Script `deploy.sh` chạy thủ công/qua cron trên server, không dùng container.

#### **B. Quy trình CI/CD Pipeline (GitHub Actions)**

> Phản ánh đúng `.github/workflows/ci.yml` hiện tại — chạy trên push/PR vào `main`.

```mermaid
flowchart LR
    Push["Git Push / PR to main"] --> Setup["Setup PHP 8.2\n(mbstring, dom, fileinfo, sqlite3)"]
    Setup --> Install["composer install"]
    Install --> Audit["composer audit --locked"]
    Audit --> Test["php artisan test\n(DB_CONNECTION=sqlite, :memory:)"]
    Test --> Node["Setup Node 20 + npm ci"]
    Node --> NpmAudit["npm audit --audit-level=high"]
```

- **Chưa được wire vào CI:** PHPStan/Larastan (config `phpstan.neon` Level 6 đã có nhưng chưa chạy trong workflow), `npm run build` check.

---

## 5. Giám sát, Bảo trì & Phục hồi (Observability & Reliability)

### 5.1 Logging & Monitoring (Giám sát & Ghi log)

- **Tập trung Log:** Laravel Stack Channel (daily + stderr). File `storage/logs/laravel.log` tự động xoay vòng (rotation 14 ngày, cấu hình `LOG_DAILY_DAYS`).
- **Health Check Endpoint:** `GET /up` (Laravel mặc định, đăng ký qua `bootstrap/app.php` `health: '/up'`) — chỉ xác nhận ứng dụng đã boot thành công, không kiểm tra chi tiết kết nối MySQL.
- **Queue Monitoring:** Theo dõi số lượng hỏng job qua lệnh `php artisan queue:failed` và lưu log tại bảng `failed_jobs`. `ScoreModuleJob` có `failed()` handler tự động ghi error vào cache + force-release lock.

### 5.2 Sao lưu & Phục hồi Thảm họa (Backup & Disaster Recovery)

- **Chính sách Sao lưu DB (Backup Policy):**
  - Daily Full Database Dump (`mysqldump`) lúc 02:00 AM UTC+7, nén mã hóa và lưu trữ tại storage an toàn độc lập.
  - Hourly Transaction Log Backup cho phép khôi phục về từng thời điểm (Point-in-Time Recovery).
- **Chỉ số Mục tiêu Phục hồi:**
  - **RTO (Recovery Time Objective):** $< 2$ giờ (Thời gian tối đa để dựng lại hệ thống từ Database Dump trên cPanel).
  - **RPO (Recovery Point Objective):** $< 1$ giờ (Mức độ mất mát dữ liệu tối đa chấp nhận được).
