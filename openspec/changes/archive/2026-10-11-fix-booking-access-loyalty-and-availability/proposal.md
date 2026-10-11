## Why

Các luồng truy cập, quản trị cơ sở và POS hiện gây lỗi hoặc thông báo khó hiểu; khách hàng chưa có giao diện dùng điểm dù API có thể trừ điểm mà không áp dụng quyền lợi vào giao dịch. Lịch trang chủ chỉ hiển thị một sân nhưng tiêu đề chung khiến việc đặt 19–21h bị hiểu nhầm là hết khung giờ ở tất cả sân.

## What Changes

- Chuyển người đã đăng nhập khỏi `/login` và `/register`, thống nhất với điều hướng sau đăng nhập và onboarding; giữ liên kết nội bộ hợp lệ, không tạo vòng lặp.
- Sửa hàm dùng chung lấy danh sách cơ sở về route v1 thực có; kiểm tra các trang sử dụng, không tạo route v2 trùng chức năng để che lỗi.
- Chuẩn hóa điều kiện truy cập tổ chức/POS: vai trò đúng tổ chức và tính năng được bật là hai điều kiện độc lập. Chuẩn bị quy trình cấp hoặc nâng quyền đúng tài khoản, đúng tổ chức, đúng môi trường; không cấp quyền platform admin đại trà hoặc tắt kiểm soát tính năng.
- Tái sử dụng modal lỗi hiện có, bổ sung mã lỗi ổn định và bản dịch Việt–Anh cho lỗi API/capability/quyền/phiên đăng nhập trong phạm vi quản trị cơ sở, tổ chức, POS và đổi điểm. Không hiển thị đồng thời nhiều modal trùng lỗi; giữ dữ liệu đang nhập khi thất bại.
- Thêm lựa chọn sử dụng điểm tại bước xác nhận đặt sân của khách hàng, trước khi checkout trừ ví. Xem trước không trừ điểm; backend xác nhận chính sách, trừ điểm, ghi giảm giá, trừ số tiền ví còn lại và xác nhận booking trong cùng giao dịch. Hỗ trợ nhất quán V1/V2, snapshot quy đổi và kết quả sau tải lại.
- **BREAKING**: không tiếp tục cho `/api/v2/loyalty/redeem` trừ điểm bằng `source_type/source_id` tùy ý mà không gắn quyền lợi vào giao dịch hợp lệ. Payload cũ thiếu ngữ cảnh được xác thực phải bị từ chối không có tác dụng phụ; không có caller frontend hiện tại được tìm thấy.
- Ghi rõ sân và ngày ngay cạnh lịch trang chủ, đặt bộ chọn sân sát lịch và thể hiện khoảng bắt đầu–kết thúc của mỗi ca. Đặt sân A từ 19–21h khóa hai ca tương ứng của A, không đồng nghĩa khóa B/C.

## Capabilities

### New Capabilities

Không thêm capability độc lập; mở rộng các hợp đồng hiện có.

### Modified Capabilities

- `account-lifecycle`: điều hướng trang dành cho khách chưa đăng nhập dựa trên phiên và onboarding.
- `api-alignment`: hợp đồng danh sách cơ sở, modal lỗi Việt–Anh và tải dữ liệu theo capability.
- `organization-access`: truy cập giao diện tổ chức/POS và quy trình cấp quyền có phạm vi, kiểm chứng được.
- `point-of-sale-and-loyalty`: đổi điểm có quyền lợi thực, quy đổi nhất quán, liên kết giao dịch và kiểm soát xử lý lặp.
- `reservations-and-pricing`: dùng điểm trước checkout ví và trình bày lịch theo sân/ngày rõ ràng.

## Impact

- Frontend: router/auth redirect, `adminApi`, các caller danh sách cơ sở, `useTenantCapabilities`, POS loading/error handling, `ErrorModal`/`errorMessage`, locale `vi`/`en`, checkout khách hàng và `HomeView`.
- Backend: feature error response, quyền theo tổ chức, quy trình provisioning hiện có, loyalty rule/ledger và quote/checkout thông thường; dữ liệu snapshot và định danh chống lặp nếu thiếu.
- Test: route/API contract, capability/quyền, hai ngôn ngữ và accessibility, atomic checkout/concurrency/retry, lịch nhiều sân và ngày; ghi môi trường test thực khi thực hiện.
- Không thêm thư viện hay hệ thống phân quyền/khuyến mãi mới. Không thay đổi chính sách wallet-only, mở lại hủy/đổi lịch, làm lịch tổng hợp toàn bộ sân, thêm POS redemption UI, hoặc tự động hoàn điểm lịch sử chưa đối soát.
- Thông tin vận hành chưa có: cấp mới hay nâng quyền, tài khoản, tổ chức và môi trường. Phần cấp quyền thực tế phải chờ xác nhận; proposal không thực hiện thay đổi tài khoản/cấu hình/dữ liệu.
