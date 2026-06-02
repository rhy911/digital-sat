# Nền Tảng Luyện Thi Digital SAT (Digital SAT Online Testing System)

Hệ thống e-learning toàn diện chuyên biệt cho luyện thi và quản trị khảo thí **Digital SAT**. Giao diện giả lập chuẩn **Bluebook** (College Board), tích hợp công cụ phân tích tiến trình học tập (LMS), hệ thống điều phối đề thi thích ứng (Adaptive Test Engine) và bộ chấm điểm nâng cao theo mô hình IRT.

---

## Mục lục

- [Các Phân Hệ Chính (Core Modules)](#các-phân-hệ-chính-core-modules)
- [Công Nghệ Sử Dụng (Tech Stack)](#công-nghệ-sử-dụng-tech-stack)
- [Hướng Dẫn Cài Đặt & Chạy Local (Local Setup)](#hướng-dẫn-cài-đặt--chạy-local-local-setup)
- [Quy Chuẩn Phát Triển (Development Standards)](#quy-chuẩn-phát-triển-development-standards)
- [Checklist Triển Khai Production (V1 Production Checklist)](#checklist-triển-khai-production-v1-production-checklist)

---

## Các Phân Hệ Chính (Core Modules)

### 1. Identity & Access (Quản lý Định danh & Quyền hạn)

- **Xác thực:** Xử lý qua Laravel Sanctum/Fortify (Đăng ký, Đăng nhập, Xác thực Email, Đổi mật khẩu).
- **Vai trò người dùng:**
  - `Student` (Học sinh): Thực hiện bài thi thử, xem báo cáo, nhận đề xuất học tập.
  - `Teacher` (Giáo viên): Quản lý ngân hàng câu hỏi (Item Bank), theo dõi tiến độ & báo cáo chi tiết của học sinh.
  - `Admin` (Quản trị viên): Cấu hình hệ thống, thiết lập Blueprint đề thi, điều phối kỹ thuật.

### 2. Student Portal (Cổng thông tin Học sinh)

- **Dashboard:** Hiển thị tiến trình luyện tập, lịch sử điểm số và đề xuất thông minh.
- **Library (Thư viện đề):** Cung cấp các đề thi Full-length (đầy đủ) và Modular Practice (luyện tập theo phân khu).
- **Review & Analytics:** Hệ thống xem lại chi tiết bài làm, giải thích đáp án (rationale) và phân tích năng lực theo từng phân loại kiến thức (Domain/Skill).

### 3. Test Engine (Trình giả lập Phòng thi)

- **High-fidelity UI:** Tái hiện chính xác 100% giao diện và tính năng của ứng dụng Bluebook.
- **Bộ công cụ bổ trợ (Test Tools):**
  - Đồng hồ đếm ngược (Timer) đồng bộ server.
  - Tích hợp máy tính đồ họa **Desmos API**.
  - Công cụ gạch xóa (Strike-through), tô sáng (Highlight) và đánh dấu câu hỏi (Mark for Review).
  - Lưới xem lại câu hỏi (Review Grid) cuối mỗi Module.
- **Adaptive Routing:** Cơ chế chuyển nhánh câu hỏi ở Module 2 (dựa trên năng lực đo được ở Module 1 - Theta Routing).
- **Security (Bảo mật phòng thi):** Ngăn chặn copy/paste, chặn chuột phải và các tổ hợp phím thoát phòng thi.

### 4. Scoring Engine (Bộ não tính điểm IRT)

- **Mô hình định lượng:** Áp dụng mô hình **3PL IRT** (Item Response Theory 3 tham số):
  - Độ khó của câu hỏi ($b$)
  - Độ phân biệt ($a$)
  - Độ đoán mò ($c$)
- **Thuật toán ước lượng:** Lọc bỏ câu hỏi khảo sát (`is_pretest`). Dùng ước lượng hợp lý cực đại **MLE (Newton-Raphson)** để tính chỉ số Theta [$-4$, $4$], sau đó ánh xạ tuyến tính (Sigmoid mapping) sang thang điểm chuẩn SAT ($200$ - $800$ cho mỗi phần).

### 5. CMS & Bulk Import (Hệ thống quản lý & Nhập liệu)

- **Data Hierarchy:** Phân cấp dữ liệu chuẩn: `Test > Section > Module > Question`.
- **Bulk Import Pipeline:** Hỗ trợ tải đề thi hàng loạt qua file JSON, CSV, hoặc file nén ZIP (bao gồm hình ảnh đính kèm).
- **Media Handling:** Tự động phân tích cú pháp `[Media:id]` và liên kết chính xác asset hình ảnh qua ZIP quét đệ quy.

---

## Công Nghệ Sử Dụng (Tech Stack)

- **Backend:** Laravel 11+ (MVC + Service Layer, FormRequest validation).
- **Frontend:** Blade template + Vite.
  - **JavaScript:** Vanilla JS chịu trách nhiệm xử lý State Store và Test Engine cốt lõi (tối ưu phản hồi < 100ms).
  - **Styling:** Kết hợp **Tailwind CSS v4** (cho layout/spacing) và **Raw CSS** (cho các hiệu ứng chuyển động, custom gradient, shadow, hover).
  - **Math Rendering:** Tích hợp **KaTeX** hiển thị công thức toán học nhanh, mượt.
- **Database:** MySQL (Highly Normalized, đánh chỉ mục index trên `external_id`, `status`, `test_type`, hỗ trợ Soft Delete cascade).
- **Queues:** Laravel Database Queue (xử lý bất đồng bộ các tác vụ chấm điểm phức tạp).

---

## Hướng Dẫn Cài Đặt & Chạy Local (Local Setup)

Dành cho người mới bắt đầu (ngay cả khi bạn chưa từng làm việc với Laravel hay Node.js):

### 1. Chuẩn bị Môi trường Máy tính (Prerequisites)

Trước khi cài đặt dự án, bạn cần cài đặt một số công cụ cốt lõi trên máy tính của mình.

- **Git** (Dùng để tải mã nguồn từ GitHub):
  - Tải và cài đặt tại [git-scm.com](https://git-scm.com/).
- **Node.js & npm** (Dùng để quản lý các công cụ và giao diện frontend):
  - Tải phiên bản LTS tại [nodejs.org](https://nodejs.org/). Cài đặt này tự động bao gồm cả `npm` (Node Package Manager).
- **Môi trường chạy PHP & MySQL nhanh (Khuyên dùng)**:
  - **Trên Windows:** Tải và cài đặt **Laragon** (tải bản Laragon Full tại [laragon.org](https://laragon.org/)) hoặc **Laravel Herd** (tại [herd.laravel.com](https://herd.laravel.com/)).
  - **Trên macOS:** Tải và cài đặt **Laravel Herd** (tại [herd.laravel.com](https://herd.laravel.com/)) - môi trường chạy PHP nhanh nhất cho Mac.
  - *Laragon/Herd sẽ cài đặt sẵn cho bạn PHP >= 8.2, máy chủ MySQL, và các thư viện cần thiết một cách tự động.*
- **Composer** (Công cụ quản lý thư viện mã nguồn PHP):
  - Tải và cài đặt tại [getcomposer.org](https://getcomposer.org/).

---

### 2. Các Bước Cài Đặt Chi Tiết

#### Bước 2.1: Tải mã nguồn về máy (Clone)

Mở cửa sổ Command Prompt (Windows) hoặc Terminal (macOS), chuyển đến thư mục bạn muốn lưu dự án (ví dụ: `cd Documents`), rồi chạy lệnh:

```bash
git clone https://github.com/your-username/digital-sat.git
cd digital-sat
```

#### Bước 2.2: Cài đặt các thư viện backend (PHP)

Laravel sử dụng Composer để quản lý thư viện. Chạy lệnh dưới đây để tải tất cả các thư viện cần thiết về:

```bash
composer install
```

*Lưu ý: Nếu gặp lỗi thiếu extension PHP, hãy mở cấu hình Laragon/Herd để bật các extension như `bcmath`, `fileinfo`, `mbstring`, `openssl`, `pdo`, `xml`.*

#### Bước 2.3: Cài đặt các thư viện frontend (JavaScript/CSS)

Dự án sử dụng npm để cài đặt các công cụ CSS (Tailwind v4) và JavaScript. Chạy lệnh:

```bash
npm install
```

#### Bước 2.4: Thiết lập file cấu hình môi trường (`.env`)

Hệ thống Laravel cần một file cấu hình riêng cho từng máy local để lưu thông tin database, email, và cài đặt bảo mật.

1. Tạo bản sao file cấu hình mẫu bằng lệnh:

   ```bash
   cp .env.example .env
   ```

2. Mở file `.env` vừa tạo bằng phần mềm soạn thảo văn bản (như VS Code, Notepad) và tìm đến phần cấu hình Database. Điền thông tin kết nối MySQL của bạn (nếu dùng Laragon, mật khẩu mặc định thường để trống; nếu dùng Herd, hãy làm theo hướng dẫn kết nối database của Herd):

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=digital_sat
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

3. Tạo cơ sở dữ liệu: Hãy mở công cụ quản lý cơ sở dữ liệu của bạn (như HeidiSQL đi kèm Laragon, hoặc TablePlus) và **tạo mới một cơ sở dữ liệu** tên là `digital_sat` trước khi chuyển sang bước tiếp theo.

#### Bước 2.5: Tạo khoá bảo mật ứng dụng (App Key)

Tạo khoá mã hoá cho các thông tin nhạy cảm của dự án (session, cookie) bằng lệnh:

```bash
php artisan key:generate
```

#### Bước 2.6: Liên kết thư mục lưu trữ (Storage Link)

Để hiển thị các hình ảnh và tệp tải lên từ thư mục backend ra ngoài trình duyệt web, chạy lệnh tạo liên kết ảo:

```bash
php artisan storage:link
```

#### Bước 2.7: Cài đặt Cơ sở dữ liệu & Tạo dữ liệu mẫu (Migrate & Seed)

1. Chạy lệnh để Laravel tự động tạo ra cấu trúc các bảng trong cơ sở dữ liệu của bạn:

   ```bash
   php artisan migrate
   ```

2. Thêm các câu hỏi thi mẫu, cấu trúc đề thi thử và tài khoản thử nghiệm vào database:

   ```bash
   php artisan db:seed
   ```

   *(Bạn cũng có thể gộp 2 lệnh trên làm một bằng cách chạy: `php artisan migrate --seed`)*

   **Thông tin đăng nhập mặc định sau khi Seed:**
   - **Tài khoản Quản trị (Admin):** `admin@gmail.com` / Mật khẩu: `password`
   - *Ngoài ra hệ thống cũng tự động tạo ra một số tài khoản học sinh ngẫu nhiên để bạn test.*

---

### 3. Khởi Chạy Ứng Dụng Trên Local

Để ứng dụng hoạt động đầy đủ, bạn cần chạy đồng thời các dịch vụ sau (mở nhiều tab/cửa sổ Terminal khác nhau):

- **Dịch vụ 1: Web Server (PHP)**
    Nếu bạn không sử dụng Laravel Herd hay Laragon tự động chạy web server, hãy chạy lệnh dưới đây để khởi chạy máy chủ PHP nội bộ:

    ```bash
    php artisan serve
    ```

    *Mặc định web sẽ chạy ở địa chỉ: `http://127.0.0.1:8000`*

- **Dịch vụ 2: Trình biên dịch giao diện (Vite)**
    Để hệ thống tự động biên dịch CSS (Tailwind v4) và JavaScript mỗi khi bạn chỉnh sửa file, hãy chạy lệnh:

    ```bash
    npm run dev
    ```

- **Dịch vụ 3: Tiến trình chạy ngầm (Queue Worker) - BẮT BUỘC**
    *Hệ thống SAT Adaptive sử dụng cơ chế chấm điểm nâng cao theo thuật toán IRT (3PL) và tính toán điều hướng ở Module 2. Để tránh việc người dùng bị treo giao diện khi thi, các thuật toán này được xử lý bất đồng bộ ở chế độ nền thông qua Laravel Queue.*
    Bắt buộc phải mở một Terminal riêng biệt và chạy lệnh sau để xử lý hàng đợi:

    ```bash
    php artisan queue:work
    ```

---

## Quy Chuẩn Phát Triển (Development Standards)

Tuân thủ nghiêm ngặt các quy định sau:

1. **Đặt tên (Naming Conventions):**
    - **PHP:** Tên Class/Controller đặt theo `PascalCase`. Method/Variable đặt theo `camelCase`.
    - **JS:** Function/Variable đặt theo `camelCase`. Hằng số đặt theo `UPPER_SNAKE_CASE`. Tên file đặt theo `kebab-case.js`.
    - **CSS:** Tên class đặt theo `kebab-case` (ưu tiên chuẩn BEM cho component tự định nghĩa).
    - **Database:** Tên bảng dạng số nhiều `snake_case` (ví dụ `user_tests`). Khóa ngoại dùng `singular_table_id` (ví dụ `user_id`).
    - **Views:** Tên file blade đặt theo `kebab-case.blade.php`. Tên ảnh đặt theo `snake_case`.
2. **Kiến trúc:**
    - Business logic bắt buộc đưa vào lớp Dịch vụ `app/Services` (không viết logic trong Controller/Model).
    - Sử dụng `FormRequest` để validate dữ liệu từ request đầu vào.
3. **Quy trình Git:**
    - Luôn kiểm tra và chạy test cục bộ (`php artisan test`) trước khi tạo pull request hoặc push.
    - Commit message ngắn gọn, rõ ràng theo chuẩn Conventional Commits (ví dụ: `feat: add email verification success page`).

---

## Checklist Triển Khai Production (V1 Production Checklist)

Trước khi mở hệ thống thử nghiệm thực tế cho người dùng:

1. **Cấu hình Môi trường (.env):**

    ```env
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://your-domain.com
    ```

2. **Khởi tạo Database:**
    - Chạy lệnh cưỡng chế migrate: `php artisan migrate --force`
    - Cấu hình backup tự động định kỳ cho MySQL Database.

3. **Tối ưu hiệu năng bộ nhớ đệm (Cache):**
    - Chạy cache cấu hình, route và view để tối ưu hóa tốc độ tải trang:

        ```bash
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        ```

4. **Dịch vụ nền (Queue Worker):**
    - Cài đặt Supervisor hoặc Systemd để duy trì tiến trình chạy hàng đợi (queue) ổn định:

        ```bash
        php artisan queue:work --tries=3 --timeout=120
        ```

5. **Bảo mật:**
    - Bắt buộc cài đặt chứng chỉ SSL/HTTPS.
    - Bảo vệ dữ liệu bằng phân quyền thư mục lưu trữ (`storage` và `bootstrap/cache`) và cấu hình rate limiting đầy đủ.
