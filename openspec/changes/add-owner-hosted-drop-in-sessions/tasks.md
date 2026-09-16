## 1. Dữ liệu và quyền chủ cơ sở

- [x] 1.1 Thêm nhận diện buổi owner-hosted, sân, phân loại và tập nhãn trình độ được chấp nhận vào luồng kèo; giữ mặc định và dữ liệu kèo cũ không đổi.
- [x] 1.2 Thêm bản ghi bill một-một với buổi, đầu vào chi phí, snapshot kết quả, trạng thái nháp/chốt, version và người/thời điểm chốt; kiểm tra migration additive không đụng bill booking.
- [x] 1.3 Áp dụng kiểm tra host và quyền sở hữu cơ sở/sân hiện tại trên tạo, quản lý, kết thúc và chốt buổi; kiểm thử khách, nhân viên không phải chủ, chủ cơ sở khác và host đã mất quyền.

## 2. Mở buổi, tìm và tham gia

- [x] 2.1 Bổ sung contract tạo/cập nhật buổi với ngày giờ, số chỗ, phân loại, nhãn trình độ; giữ kiểm tra lịch sân hiện có, không tạo booking đã trả tiền hoặc ghi đè slot đã giữ.
- [x] 2.2 Chỉ với buổi mới, bỏ tự thêm host vào participation/count; giữ nguyên hành vi creator của kèo thông thường và kiểm thử cả hai nhánh.
- [x] 2.3 Bổ sung bộ lọc địa điểm/sân, thời gian, phân loại và nhãn trình độ cho discovery; kiểm thử khớp nhãn chính xác, không đổi nghĩa điểm trình độ cũ.
- [x] 2.4 Tái sử dụng tham gia cá nhân với kiểm tra open/capacity, duplicate và cạnh tranh chỗ cuối; bổ sung thao tác host kết thúc buổi để đóng đăng ký.

## 3. Bill sau buổi

- [x] 3.1 Bổ sung đọc/lưu nháp bill theo ID buổi, chỉ sau khi buổi kết thúc; validate số người nguyên dương, phút chơi dương, số quả nguyên không âm và đơn giá VND không âm.
- [x] 3.2 Tính hai thành tiền và tổng ở server, chuẩn hóa giờ lẻ sang phút, làm tròn tiền sân half-up; trả tổng/divisor và giá mỗi người với chỉ báo gần đúng khi không chia hết, bỏ qua tổng giả do client gửi.
- [x] 3.3 Chỉ cho phép đầu vào tiền sân và cầu; từ chối dòng dịch vụ phụ và không import POS. Cho nhập/xác nhận đơn giá khi không có mặc định, không tạo cấu hình giá mới.
- [x] 3.4 Thực hiện chốt snapshot duy nhất, version/conflict check và retry an toàn; không sửa bill đã chốt, không tạo payment share, wallet debit hay trạng thái paid.
- [x] 3.5 Cho host và khách đăng ký đọc lại bill đã chốt, kiểm tra quyền đọc/ghi và dữ liệu sau reload; giá danh mục thay đổi không làm đổi bill đã chốt.

## 4. Giao diện

- [x] 4.1 Thêm nhánh mở buổi cho chủ cơ sở với lựa chọn sân thuộc cơ sở, phân loại và nhãn `newbie`, `Y`, `TBY`, `TB`, `K`; không hiển thị host như người chơi mặc định.
- [x] 4.2 Cập nhật tìm kèo và chi tiết buổi để khách lẻ lọc/tham gia, xem host, số chỗ và trình độ; không yêu cầu đội và phân biệt ước tính trước buổi với bill thực tế.
- [x] 4.3 Thêm thao tác kết thúc, form số người/giờ sân/số cầu và đơn giá xác nhận, review/lưu nháp/chốt; chỉ hiển thị hai thành tiền, tổng và tiền mỗi người.
- [x] 4.4 Hiển thị bill đã chốt chỉ đọc theo quyền; loại CTA dịch vụ phụ, cách chia khác, QR, thanh toán và bảo lãnh khỏi ngữ cảnh buổi mới. Không đổi các route split-booking hoặc POS cũ.
- [x] 4.5 Nối dữ liệu API thật và trạng thái loading/error/empty/conflict, không fallback sang bill mẫu; kiểm thử reload và API thất bại.

## 5. Xác minh và bàn giao

- [x] 5.1 Kiểm thử phép tính chuẩn `2 giờ × 150000 + 8 quả × 25000 = 500000; 8 người = 62500/người`, giờ lẻ, 0 quả, 0 người bị từ chối, giá âm, số quả lẻ, làm tròn tiền sân và tổng không chia hết.
- [x] 5.2 Kiểm thử trọn luồng chủ cơ sở mở buổi → khách lẻ tham gia → kết thúc → số người thực tế khác đăng ký → chốt → khách đọc lại; bao gồm host không chơi và khách walk-in không có tài khoản.
- [x] 5.3 Chạy kiểm thử phân quyền, cạnh tranh tham gia/chốt bill, snapshot giá, từ chối dịch vụ phụ và không phát sinh thanh toán; đối chiếu tất cả scenario trong delta spec.
- [x] 5.4 Chạy hồi quy generic match, advance split booking/expiry/refund, lịch sân và POS; ghi kết quả cùng giới hạn một sân/một đơn giá, bill chốt chỉ đọc và không thu tiền tự động.
- [x] 5.5 Chạy kiểm tra định dạng/type/lint và test liên quan theo script sẵn có; kiểm tra rollback không xóa dữ liệu buổi/bill và cập nhật checklist bằng bằng chứng thực tế.
