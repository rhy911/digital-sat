# Digital SAT Online Testing System

## Getting Started / Local Setup

Follow these steps to run the project locally:

1. **Database**: Turn on your **MySQL database** (ensure connection config in `.env` is correct).
2. **Web Server**: Turn on **Laravel Herd** (or your preferred local web server environment).
3. **Frontend Asset Bundler**: Open a terminal and run the dev server:
   ```bash
   npm run dev
   ```
4. **Queue Worker**: Background jobs like scoring use Laravel queues. Run the queue worker:
   ```bash
   php artisan queue:work
   ```

---

# [Technical Presentation] Hệ sinh thái Khảo thí & Luyện thi Digital SAT: Deep-Dive Outline

## Slide 1: Giới thiệu & Tầm nhìn Dự án

* **Tiêu đề:** Giải pháp Toàn diện cho Kỷ nguyên Khảo thí Số.
* **Nội dung:**
  * **Bối cảnh:** Sự chuyển dịch mang tính bước ngoặt của College Board sang Digital SAT.
  * **Dự án:** Xây dựng hệ thống mô phỏng High-fidelity kết hợp với nền tảng quản lý học tập (LMS).
  * **Tầm nhìn:** Tạo ra một môi trường giúp giáo viên và học sinh có thể tự tạo và luyện tập thi trên máy tính.
  * **Giá trị cốt lõi:** Trải nghiệm thi thực tế - Thuật toán thông minh - Quản trị nội dung tối ưu.

## Slide 2: Kiến trúc Hệ thống (System Overview)

* **Tiêu đề:** Hệ sinh thái 4 Phân hệ Tích hợp.
* **Nội dung:**
  * **Identity & Access:** Quản lý User Life-cycle, phân quyền đa cấp (Student, Teacher, Developer).
  * **Student Portal:** Trung tâm học tập, thư viện đề thi và dashboard phân tích tiến độ cá nhân.
  * **Test Engine:** Trình giả lập phòng thi tập trung vào hiệu năng và độ chính xác UX.
  * **Admin/Teacher CMS:** Hệ thống điều hành nội dung, xử lý dữ liệu thô và cấu hình thuật toán adaptive.

## Slide 3: Phân hệ Xác thực & Bảo mật (Identity)

* **Tiêu đề:** Quản lý Người dùng & Bảo mật Thông tin.
* **Nội dung:**
  * **Xác thực:** Triển khai qua Laravel Sanctum/Fortify cho Web & API.
  * **Quy trình:** Đăng ký, xác thực Email, quản lý phiên làm việc và bảo mật 2 lớp (2FA).
  * **Phân quyền:**
    * **Student:** Truy cập kho đề, thực hiện bài thi.
    * **Teacher:** Quản trị Item Bank, xem báo cáo học sinh.
    * **Admin:** Quản trị hệ thống, cấu hình Blueprint và kỹ thuật.

## Slide 4: Trình giả lập Test Engine (Phần 1: UI/UX)

* **Tiêu đề:** Mô phỏng Bluebook "Pixel-Perfect".
* **Nội dung:**
  * **Giao diện:** Tái hiện chính xác Header (Timer, Navigation) và Footer.
  * **Công cụ Hỗ trợ:**
    * Máy tính đồ họa Desmos tích hợp.
    * Công cụ gạch chân (Highlighter) và loại trừ đáp án (Strike-through).
    * Hệ thống đánh dấu câu hỏi (Mark for Review) thời gian thực.
  * **Layout Thích ứng:** Tự động chuyển đổi Math (1 cột) và Reading (2 cột resizable).

## Slide 5: Trình giả lập Test Engine (Phần 2: Mechanics)

* **Tiêu đề:** Thuật toán Adaptive Testing & State Management.
* **Nội dung:**
  * **Multi-stage Adaptive:** Cơ chế điều hướng Module 2 (Easy/Hard) dựa trên hiệu suất thực tế.
  * **State Store:** Sử dụng Vanilla JS Centralized Store để quản lý trạng thái làm bài ổn định.
  * **Session Persistence:** Đảm bảo dữ liệu không bị mất khi chuyển câu hoặc gặp sự cố kết nối.
  * **Time Tracking:** Đồng bộ hóa bộ đếm ngược với Server để đảm bảo tính công bằng.

## Slide 6: Hệ thống Quản trị Nội dung (CMS Pipeline)

* **Tiêu đề:** Số hóa Quy trình Quản lý Ngân hàng câu hỏi (Item Bank).
* **Nội dung:**
  * **Data Hierarchy:** Cấu trúc phân cấp nghiêm ngặt `Test > Section > Module > Question`.
  * **Universal Content Editor:** Soạn thảo Passage (đoạn văn đơn/đôi) và Question đa định dạng.
  * **Media Handling:** Tự động ánh xạ `[Media:id]` thành asset thực tế qua cơ chế quét ZIP đệ quy.
  * **Atomic Imports:** DB Transactions đảm bảo tính toàn vẹn dữ liệu khi nhập liệu hàng loạt.

## Slide 7: Cơ chế Nhập liệu Siêu tốc (Bulk Import)

* **Tiêu đề:** Tự động hóa Pipeline Dữ liệu.
* **Nội dung:**
  * **Hỗ trợ đa định dạng:** Import trực tiếp từ tệp JSON, CSV hoặc ZIP (kèm ảnh).
  * **Validation Layer:** Tự động kiểm tra tính đầy đủ (Difficulty, Domain, SPR correctness) trước khi lưu trữ.
  * **Auto-positioning:** Thuật toán tự động sắp xếp lại vị trí câu hỏi khi chèn dữ liệu vào các Module hiện có.

## Slide 8: Kiến trúc Kỹ thuật & Service Layer

* **Tiêu đề:** Thiết kế Hệ thống cho Khả năng Mở rộng (Scalability).
* **Nội dung:**
  * **Backend:** Laravel 12 với kiến trúc Service Layer tách biệt Business Logic khỏi Controller.
  * **Service Layer Examples:** `ScoringService`, `AdaptiveRoutingService`, `ImportService`.
  * **Frontend Engine:** Tối ưu hóa DOM Manipulation qua Vanilla JS để đạt phản hồi < 100ms.
  * **Rendering Pipeline:** Tích hợp KaTeX cho biểu thức toán học và CSS Engine cho Poetry formatting.

## Slide 9: Cơ sở Dữ liệu & Tối ưu hóa (Database)

* **Tiêu đề:** Quản trị Dữ liệu Phức tạp & Hiệu năng.
* **Nội dung:**
  * **Normalization:** Thiết kế 30+ bảng chuẩn hóa để xử lý quan hệ đa cấp và Paired Passages.
  * **Data Integrity:** Sử dụng Foreign Keys, Constraints và Soft Deletes triệt để.
  * **Optimization:** Đánh Index chiến lược trên các cột truy vấn trọng yếu (`external_id`, `is_complete`, `test_type`).

## Slide 10: Chặng đường Phát triển (Roadmap: Scoring Engine)

* **Tiêu đề:** Hoàn thiện Bộ não Tính điểm & Adaptive Logic.
* **Nội dung:**
  * **Raw-to-Scaled Conversion:** Phát triển bảng chuyển đổi điểm sang thang 1600.
  * **IRT Implementation:** Nghiên cứu áp dụng Item Response Theory để tính điểm dựa trên trọng số độ khó từng câu.
  * **Adaptive Blueprint:** Tối ưu hóa các ngưỡng threshold điều hướng Module 2.

## Slide 11: Chặng đường Phát triển (Roadmap: Review & Analytics)

* **Tiêu đề:** Nâng cao Trải nghiệm Học tập & Phân tích Năng lực.
* **Nội dung:**
  * **Review Mode:** Cho phép xem lại toàn bộ bài làm kèm lời giải chi tiết (Rationale/Explanation).
  * **Domain Analytics:** Báo cáo thế mạnh/điểm yếu theo từng Domain (Algebra, Craft & Structure...).
  * **Progress Tracking:** Biểu đồ xu hướng điểm số qua các kỳ thi thử.

## Slide 12: Tổng kết & QA

* **Nội dung:**
  * **Kết luận:** Hệ thống đã sẵn sàng cho quy mô Production.
  * **Thông điệp:** Một nền tảng không chỉ để thi, mà để hiểu rõ năng lực bản thân.
  * **Lời cảm ơn:** Cảm ơn thầy cô và các bạn đã dành thời gian theo dõi.
  * *Mời đặt câu hỏi thảo luận.*
