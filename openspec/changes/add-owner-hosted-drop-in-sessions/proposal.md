## Why

Khách chơi cầu lông vãng lai cần tìm buổi chơi cùng trình độ mà không phải có đội sẵn. Chủ cơ sở cần mở buổi giao lưu, nhận khách lẻ và chốt chi phí thực tế sau buổi; luồng góp tiền trước để xác nhận booking hiện tại không đáp ứng nghiệp vụ này.

## What Changes

- Thêm buổi cầu lông do chính chủ cơ sở làm host, gắn sân tại cơ sở của mình, ngày giờ, số chỗ và phân loại `giao lưu` / `đánh vãng lai`.
- Cho khách lẻ tìm và tham gia theo sân/địa điểm, thời gian, phân loại và các nhãn trình độ `newbie`, `Y`, `TBY`, `TB`, `K`; không yêu cầu đội chơi.
- Host là người tổ chức, không tự động chiếm chỗ hoặc được tính vào số người chia phí.
- Sau buổi, host chốt số người chia phí thực tế, số giờ sân và số quả cầu đã dùng. Chỉ tính `(giờ sân × giá giờ sân + số quả cầu × giá một quả) / số người`.
- Loại hoàn toàn nước, quấn cán, đá và mọi dịch vụ/phụ kiện khỏi bill của buổi giao lưu. Khách tự mua và trả riêng; không xóa nghiệp vụ bán lẻ hiện có.
- Lưu bảng chi phí thực tế và kết quả chia đều để xem lại; giá trước buổi, nếu hiển thị, chỉ là ước tính.
- Giữ nguyên luồng góp tiền đặt sân trước trận, thanh toán, hoàn tiền và các kèo thông thường. Không thêm thu tiền tự động, ví, QR hay theo dõi công nợ trong change này.

## Capabilities

### New Capabilities

- `owner-hosted-drop-in-sessions`: Mở buổi chơi do chủ cơ sở tổ chức, tìm khách lẻ theo trình độ, tham gia và chốt bill sau buổi chỉ gồm tiền sân và tiền cầu.

### Modified Capabilities

Không có. Capability mới chuyên biệt cho buổi cầu lông vãng lai; không thay đổi hợp đồng góp tiền trước trong `advanced-booking-marketplace`, thang trình độ chung trong `social-tournaments` hoặc nghiệp vụ bán lẻ.

## Impact

- Backend: luồng `MatchPost` / `MatchService`, quyền sở hữu cơ sở/sân, tham gia buổi chơi và dữ liệu chốt chi phí mới; tái sử dụng kiểm tra phân quyền và tham gia hiện có khi phù hợp.
- Frontend: mở kèo, tìm kèo, chi tiết buổi và giao diện chia chi phí theo ngữ cảnh buổi chơi. Không dùng dữ liệu demo hoặc suy ra bill buổi chơi từ mã booking.
- API: bổ sung dữ liệu và thao tác riêng cho buổi vãng lai; kiểm tra quyền host và tính tiền ở server.
- Kiểm thử: quyền chủ cơ sở, lọc trình độ, không tự tính host, số người thực tế, giờ lẻ, cầu theo quả, không có dịch vụ phụ, lưu/chốt bill và hồi quy luồng cũ.
- Không cần dependency hoặc hạ tầng mới. Chỉ tạo tài liệu kế hoạch ở bước này.
