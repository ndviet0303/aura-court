## Why

Aura Court cần giao diện V2 theo bộ Erabook đã lưu cục bộ, đồng thời cho người dùng chủ động quay lại V1 từ dropdown profile. Tách lựa chọn giao diện khỏi sáng/tối giúp triển khai thiết kế mới mà không thay đổi dữ liệu, đường dẫn hoặc luồng đặt sân–thanh toán đang hoạt động.

## What Changes

- Thêm switch “Giao diện Erabook V2” trong dropdown profile của khách và người đăng nhập, cùng tùy chọn đồng bộ trong menu mobile. Mặc định V1, nhớ lựa chọn theo trình duyệt, độc lập với sáng/tối và ngôn ngữ.
- Chuyển V1 ↔ V2 tại chỗ, không reload, không reset form, bộ lọc, giờ sân, phiên giữ chỗ, thanh toán hoặc giỏ POS; không phát sinh lệnh nghiệp vụ do đổi giao diện.
- Áp dụng màu, typography Be Vietnam Pro, icon và ngôn ngữ component Erabook vào UI thật có tương tác; tái sử dụng component và logic hiện có, không dựng một ứng dụng thứ hai.
- Bao phủ toàn bộ route frontend hiện hành theo bốn đợt: nền tảng/shell → tìm sân/chọn lịch → tài khoản/đặt sân/thanh toán → cộng đồng/đối tác/quản trị/POS. Có bảng theo dõi route, trạng thái UI và bằng chứng; chỉ đổi màu không được tính là hoàn tất V2.
- Giữ V1 hoạt động và giao diện hiện hành, bao gồm dark mode; bổ sung điều kiện phiên bản vào hợp đồng theme hiện có để không bắt V2 dùng bảng màu Burgundy/Gold.
- Kiểm chứng cả hai phiên bản: chuyển đổi có giữ trạng thái, không thay quyền/nghiệp vụ, desktop/mobile, sáng/tối, VI/EN, bàn phím, tương phản và ảnh đối chiếu.

## Capabilities

### New Capabilities

- `ui-presentation-version`: lựa chọn V1/V2 theo trình duyệt, entry point profile/mobile, chuyển đổi an toàn, phạm vi màn hình và cổng nghiệm thu giao diện Erabook.

### Modified Capabilities

- `theme-system`: giới hạn bảng màu Premium Club hiện tại ở V1; bổ sung palette/typography/assets V2, kết hợp phiên bản với sáng/tối, giữ semantic tokens và tiêu chuẩn tương phản.

## Impact

- Frontend: khởi tạo preference, `App.vue`, stylesheet/token/font assets, shared components/layouts, dropdown profile/mobile drawer, i18n và phần trình bày trong các feature views/components hiện có.
- Kiểm thử: dùng bộ unit/browser test hiện có; phối hợp với `add-playwright-functional-coverage`, không sửa phạm vi change đó hoặc tạo hệ test thứ hai. Ghi bằng chứng UI riêng, không suy ra chứng nhận thanh toán thật từ kiểm thử giao diện.
- Nguồn thiết kế: `docs/design/erabook/`; giữ nguyên snapshot và provenance, chỉ đưa asset cần dùng vào runtime. Không phụ thuộc Figma MCP để bắt đầu.
- Không đổi API, schema database, quyền truy cập, payment model, URL/SEO, hay chính sách tiền. Không thêm thư viện UI, không đồng bộ preference lên tài khoản, không xóa V1, không tự bật V2 mặc định hoặc triển khai production trong change này.
