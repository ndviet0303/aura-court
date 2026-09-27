## Why

Báo cáo `dacn_report.docx` và source hiện tại lệch ở 71 nhóm phát hiện trên 7 module: mô hình dòng tiền, chính sách booking, phân quyền, hợp đồng API, dữ liệu mô phỏng và tính toàn vẹn nghiệp vụ. Người dùng yêu cầu sửa toàn bộ các điểm đã liệt kê; change này lấy các yêu cầu chức năng/quy tắc trong báo cáo làm đích, với SePay nhận tiền trực tiếp cho chủ sân và cấu hình thủ công ban đầu.

## What Changes

- **BREAKING**: booking mới dùng QR/chuyển khoản trực tiếp tới tài khoản chủ sân đã xác minh, giữ chỗ chờ thanh toán và chỉ xác nhận từ bằng chứng server; phí subscription đi vào tài khoản Aura Court riêng. Không sinh platform custody, tenant payable hoặc payout cho doanh thu trực tiếp.
- **BREAKING**: khôi phục yêu cầu đổi lịch, hủy toàn bộ/một phần và theo dõi hoàn tiền do chủ sân thực hiện theo chính sách được snapshot. Không dùng callback/client tự khai làm bằng chứng đã trả/hoàn tiền.
- Hoàn thiện tài khoản, KYC, gói 1/6/12 tháng, quota và quyền theo tổ chức–cơ sở–chức năng; nối đúng toàn bộ hành trình frontend/backend.
- Dùng lịch vận hành, inventory, giá, khuyến mãi và quyền lợi thành viên thống nhất cho V1/V2; sửa định kỳ, chia tiền, chuyển nhượng và check-in theo người có quyền sử dụng thực.
- Hoàn thiện POS/ca/tồn kho, checkout còn công nợ, hoạt động cộng đồng, giải đấu và vòng đời khiếu nại có bằng chứng.
- Loại bỏ dữ liệu mẫu/false-success khỏi luồng nghiệp vụ; sửa tác vụ hết hạn/đối soát và cung cấp báo cáo phân tách nguồn tiền.
- Giữ nguyên số dư, giao dịch, commission lịch sử, snapshot và nghĩa vụ cũ. Cắt chuyển bằng phiên bản mô hình thanh toán và đối soát có kiểm soát, không migration xóa/đổi tiền.
- Nghiệm thu từng phát hiện D01–D71 và lập ma trận FR cho cả 126 yêu cầu; các khoảng trống báo cáo không được tự coi là đã đạt.

## Capabilities

### New Capabilities

- `direct-owner-payments`: cấu hình SePay theo đối tác, receipt xác thực, dòng tiền trực tiếp và hoàn tiền ngoài hệ thống.
- `support-case-management`: hồ sơ khiếu nại, bằng chứng, phân xử, thi hành, thông báo và xem xét lại.

### Modified Capabilities

- `account-lifecycle`: trạng thái hạn chế và xác thực nhất quán.
- `organization-access`: quyền cơ sở/chức năng, lời mời thật, bảo vệ dữ liệu.
- `platform-subscriptions`: quản trị gói, kỳ hạn, xác minh thanh toán, quota.
- `venue-court-operations`: cấu hình/lịch thực, sẵn sàng và công khai thống nhất.
- `reservations-and-pricing`: giữ chỗ, giá/ưu đãi chung, đổi/hủy có chính sách.
- `wallet-payment`: kết thúc wallet-only cho booking mới; bảo toàn nghĩa vụ ví cũ.
- `advanced-booking-marketplace`: định kỳ/chia tiền/chuyển quyền có bằng chứng.
- `financial-ledger-and-payouts`: sổ theo bên nhận tiền, payout chỉ cho nghĩa vụ cũ, đối soát thật.
- `point-of-sale-and-loyalty`: ca, receipt, giữ tồn, hoàn trả và ưu đãi nhất quán.
- `on-site-attendance`: bàn giao đúng quyền/thời gian; checkout độc lập công nợ.
- `social-tournaments`: quyền tổ chức, điều kiện tham gia và kết quả có thể kiểm chứng.
- `owner-hosted-drop-in-sessions`: chia sẻ inventory nhưng giữ bill sau buổi là thông tin, không phải thanh toán.
- `api-alignment`: hành trình thực và API nhất quán, khôi phục giao diện đổi/hủy/hoàn.
- `runtime-execution`: expiry/recovery thực thi được và không trùng hiệu ứng.
- `platform-readiness`: traceability, migration và nghiệm thu theo mô hình mới.

## Impact

Backend domain/services/controllers/routes/schema; frontend router/API/forms và locale Việt–Anh; tests, migration/readiness và tài liệu nghiệp vụ. Tái sử dụng provider adapters, audit/outbox, identity mapping và ledger hiện có; không mặc định thêm dependency hay hệ thống song song. OAuth/Bank Hub, tự động trích tiền chủ sân, native mobile và hoàn thiện các phần học thuật trống của DOCX không thuộc change. Proposal không cấp quyền triển khai, cấu hình SePay thật hoặc chuyển tiền.

