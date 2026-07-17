# Đề xuất thiết kế: Student Classroom View

Đề xuất này mô tả cấu trúc giao diện và trải nghiệm người dùng (UX/UI) cho trang chi tiết lớp học phía học sinh (**Student View Classroom**), được kế thừa và đồng bộ hóa với thiết kế kẹp hồ sơ Manila & bảng ghim gỗ của trang giáo viên.

---

## 1. Cấu trúc Layout 3 cột (App Shell)

Thiết kế sử dụng toàn bộ khung màn hình (`100vh`, không scroll toàn trang) và chia làm 3 cột đồng nhất với view giáo viên:

### Cột 1: Icon Rail (Thanh điều hướng nhanh - 72px)
* **Vị trí**: Nằm bên trái ngoài cùng, nền tối (`#0B1626`).
* **Nội dung**: Các shortcut điều hướng của học sinh:
  * Logo Dashboard
  * Lớp học của tôi (Active)
  * Thư viện đề luyện tập (Library)
  * Lịch sử điểm & Phân tích (Review & Scores)
  * Avatar học sinh ở cuối cùng.

### Cột 2: Class List (Danh sách lớp học dạng Thẻ kẹp - 280px)
* **Vị trí**: Cột ở giữa, cuộn độc lập (`overflow-y: auto`).
* **Đặc điểm**:
  * Thanh tìm kiếm lớp và chip bộ lọc (Tất cả, Đang học, Lưu trữ) trên đầu.
  * **Nút hành động**: Đổi từ *"Tạo lớp mới"* (của Giáo viên) thành nút nét đứt **"Tham gia lớp bằng mã"**. Click sẽ mở Modal nhập mã code tham gia lớp học.
  * **Thẻ lớp học (Manila tab)**: Thể hiện thông tin tên lớp, tên giáo viên và số lượng bài tập chưa làm. Thẻ đang chọn sẽ trượt sang phải và đổi màu sang giấy Manila ấm áp (`#F1E5C9`) với gáy thẻ màu đỏ nổi bật.

### Cột 3: Ledger Pane (Không gian làm việc & Bảng ghim)
Được chia làm 2 khu vực chính:

#### A. Binder Panel (Sổ bìa da học tập - Cột rộng bên trái)
* Thiết kế dạng trang giấy ngà ấm áp (`#FCF9F2`) với gáy sổ kẹp màu vàng (`#E2CE8B`) bên trái tạo chiều sâu.
* **Hàng thống kê nhanh (Student Stats)**: Hiển thị tiến độ cá nhân trong lớp:
  * *Bài tập hoàn thành* (ví dụ: `3 / 5` bài)
  * *Điểm trung bình* (ví dụ: `1440` điểm)
  * *Tài liệu chia sẻ* (số file/link giáo viên đã upload)
  * *Sĩ số lớp* (số bạn học)
* **Thanh Tab phân đoạn (Teams-style)**:
  * **Bài tập**: Hiển thị lưới card bài tập (Assignments). Mỗi card có thông tin hạn chót rõ ràng (chữ màu đỏ nếu sắp trễ hạn), trạng thái bài làm (*Chưa bắt đầu*, *Đang làm dở*, *Đã hoàn thành kèm số điểm*, *Quá hạn*), và nút bấm hành động tương ứng (*Làm bài*, *Làm tiếp*, *Xem kết quả*).
  * **Tài liệu**: Danh sách bảng tài liệu dạng File (PDF, DOCX) hoặc Liên kết ngoài (Desmos, Khan Academy) có nút Mở/Tải về trực quan.
  * **Giảng dạy**: Danh sách giáo viên chủ nhiệm & trợ giảng quản lý lớp để học sinh tiện liên hệ khi cần.
  * **Bạn học**: Danh sách bạn học cùng lớp tạo không khí học tập cộng đồng thi đua.
  * **Cấu hình**: Đặt biệt danh của học sinh trong lớp hoặc hành động rời khỏi lớp học (Leave Class).

#### B. Corkboard Pane (Bảng ghim gỗ thi đua - 320px bên phải)
* Khung viền gỗ dày 12px và bề mặt hạt gỗ bần (Corkboard) đặc trưng.
* **Ghim thông báo & Điểm nhấn**: Các tờ note giấy Manila được đính bằng đinh ghim màu đỏ (`::before` gradient tròn):
  * **Sắp đến hạn**: Danh sách bài tập cận hạn chót (trong vòng 48 giờ) để nhắc nhở học sinh.
  * **Thông báo từ Giáo viên**: Lời nhắn gần nhất của giáo viên đính lên lớp (ví dụ: yêu cầu đọc tài liệu trước buổi học tiếp theo).
  * **Bảng thi đua tuần (Leaderboard)**: Top 3 học sinh có điểm trung bình cao nhất lớp trong tuần để kích thích thi đua học tập.

---

## 2. Quy chuẩn công nghệ frontend đề xuất

* **CSS**: Sử dụng CSS Variables chung hệ màu Manila Warm từ file gốc. Tích hợp Tailwind v4 cho layout/spacing nhanh và Raw CSS cho các hiệu ứng chuyển tab, gáy kẹp file, bóng đổ giấy đè lên nhau, hiệu ứng đinh ghim bảng gỗ.
* **JS**: Vanilla JS / Alpine.js đồng bộ dữ liệu nhanh và mượt mà, không reload trang khi đổi lớp hoặc đổi tab.
