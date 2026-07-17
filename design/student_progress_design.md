# Đề xuất thiết kế: Student Progress View

Tài liệu này mô tả ý tưởng và cấu trúc thiết kế giao diện cho trang **Tiến độ học tập & Điểm số của học sinh (Student Progress & Scores View)**, kế thừa phong cách giả lập học tập cơ học (Analog mechanical design) đồng bộ với lớp học: **Sổ tay da Manila đặt trên mặt bàn gỗ tối màu**.

Để giảm thiểu tải lượng nhận thức (cognitive load) cho học sinh, sổ tay da được thiết kế phân đôi bằng hai tab cọc sổ ở cạnh trên: **Overall Progress & Mastery (Tiến trình & Làm chủ kỹ năng)** và **Test Scores & Exam Ledger (Bảng điểm & Lịch sử thi thử)**.

---

## 1. Cấu trúc Layout & Ý tưởng thiết kế

Giao diện sử dụng bố cục App Shell 3 cột đồng nhất:
1. **Icon Rail (Thanh điều hướng nhanh - 72px)**: Giúp học sinh chuyển đổi giữa các khu vực chính của hệ thống.
2. **Sidebar (Danh sách bài làm - 280px)**: Danh sách các bài thi thử Đang làm dở và Đã hoàn thành.
3. **Workspace Pane (Không gian làm việc chính)**: Giả lập một **mặt bàn gỗ tối màu** (`desk-workspace`), bên trên đặt một cuốn **sổ tay kẹp còng da Manila** (`desk-journal`) có hai tab cọc phía trên để chuyển đổi tiêu điểm.

---

## 2. Các Phân hệ / Tab Tiêu điểm chính

### Tab 1: Overall Progress & Mastery (Tiến trình học tập chung)
Tập trung vào đánh giá năng lực khái niệm tổng quan và chỉ dẫn ôn luyện:

* **Trang Trái: Bảng chỉ dẫn & Tiến độ (Recommendation Board)**:
  * **Hàng thống kê nhanh (Stats strip)**: Thống kê số lượng Drill đã làm, Độ chính xác trung bình (%), và Tổng thời gian luyện tập.
  * **Tờ note ghi chú màu vàng ghim đè (Lined Sticky Note)**: Đính bằng đinh ghim đỏ 3D, hiển thị chủ đề/kỹ năng yếu nhất cần cải thiện dựa trên dữ liệu.
  * **Danh sách nhiệm vụ ưu tiên (Checklist Card)**: Ghi lại các đầu việc cần làm (ví dụ: Xem câu sai Test 2, Làm bài tập ngữ pháp 20 câu) có các nút checkbox tích chọn trực quan.
* **Trang Phải: Chi tiết kỹ năng (Concept Mastery)**:
  * Hai tab phân mục lớn: *Reading & Writing* và *Math*.
  * Các kỹ năng hiển thị độ chính xác (%) kèm thước đo tiến độ giả lập nét bút dạ highlight màu sắc thông minh:
    * **Xanh lá (High)**: Độ chính xác >70%.
    * **Vàng (Mid)**: Độ chính xác 50% - 70%.
    * **Đỏ (Low)**: Cần cải thiện gấp, độ chính xác <50%.

---

### Tab 2: Test Scores & Exam Ledger (Kết quả & Bảng điểm chi tiết)
Tập trung vào tổng điểm thi thử, xu hướng tăng trưởng và danh sách bài thi cụ thể:

* **Trang Trái: Chỉ số điểm & Đồ thị (Estimated Score Card)**:
  * **Vòng tròn đo điểm EAP (Circular Score Gauge)**: Vòng tròn progress bar động tải điểm số ước lượng mới nhất (ví dụ: `1480`) kèm chỉ số thay đổi (`+60 points`).
  * **Biểu đồ xu hướng (SVG Growth Trend Chart)**: Đồ thị đường thẳng vẽ trên nền giấy kẻ ô ly xanh nhạt thể hiện biến động điểm qua các bài test. Hover vào từng mốc điểm hiển thị tooltip thông tin chi tiết.
* **Trang Phải: Sổ ghi chép điểm số (Exam Ledger Sheet)**:
  * Giao diện dạng bảng sổ điểm cổ điển ghi lại: Ngày làm, Tên bài test, Phân loại bài làm (Self-Study vs Classroom Assignment), và Điểm số đạt được.
  * Click vào các hàng trong bảng sổ điểm sẽ tự động cập nhật vòng tròn đo điểm bên trang trái (Context Swapping).

---

## 3. Trải nghiệm tương tác (UX Micro-interactions)

1. **Top Tab Transitions**: Click vào tab cọc phía trên sẽ lật trang còng/thay đổi nội dung bên trong sổ tay mượt mà.
2. **Context Swapping**: Khi đang ở Tab Scores, click chọn một bài thi khác ở bảng sổ điểm hoặc sidebar sẽ cập nhật vòng tròn điểm số và đồ thị tương ứng của bài thi đó.
3. **Highlighter Accuracy Meters**: Thước đo kĩ năng giả lập nét bút dạ quang (highlighter stroke) có độ phủ ngẫu nhiên tự nhiên ở rìa cọ bút, tăng tính xúc giác cho giao diện số.
4. **Onboarding Task Checklist**: Đối với học sinh mới (chưa có lịch sử thi), trang giấy sẽ hiển thị danh sách kiểm tra hướng dẫn từng bước để làm quen hệ thống kèm đồ họa trực quan thúc đẩy làm bài thi thử đầu tiên.
