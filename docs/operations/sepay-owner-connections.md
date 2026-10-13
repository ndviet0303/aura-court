# Vận hành SePay cho kết nối nhận tiền của chủ sân

Từ change `direct-owner-cutover`, mọi khoản thanh toán bằng QR (booking, hợp đồng định kỳ, POS, lệ phí giải đấu) chuyển
thẳng vào tài khoản ngân hàng của chủ sân. Nền tảng không giữ tiền; nó chỉ nhận thông báo biến động số dư từ SePay qua
webhook riêng của từng kết nối nhận tiền (receiving connection) để tự xác nhận khoản thanh toán.

Tài liệu này không ghi key hay số tài khoản thật. Key của bản demo nằm trong file env của server (không commit) và được
xoay sau buổi bảo vệ.

## Mã thanh toán

- Mỗi lượt thanh toán (owner payment attempt) có mã `AC` + 18 ký tự chữ in hoa và số, do server sinh.
  Khách phải giữ nguyên mã này trong nội dung chuyển khoản; QR VietQR đã điền sẵn.
- Khai báo cấu trúc mã thanh toán trên SePay:
  - tiền tố `AC`;
  - hậu tố dài từ 18 đến 18;
  - kiểu `NumberAndLetter`.
- Mã chuyển khoản bán lại slot có tiền tố `RS` + 10 ký tự. Tiền này đi thẳng từ người mua sang người bán, không qua chủ
  sân, nên không khai báo mẫu `RS` trên SePay.

## Webhook cho từng kết nối

Mỗi kết nối có một URL riêng, hiển thị trong màn hình cấu hình kết nối nhận tiền của cơ sở (cửa sổ sẵn sàng của cơ sở):

```
POST https://<api-host>/api/v1/webhooks/sepay/connections/{routingId}
```

Tạo webhook trên SePay với:

| Trường | Giá trị |
|---|---|
| URL | URL của kết nối, như trên |
| Sự kiện | `In_only` (chỉ tiền vào) |
| Tài khoản ngân hàng | Đúng tài khoản đã khai báo cho kết nối |
| Chứng thực | `Api_Key`; SePay gửi header `Authorization: Apikey <key>` |

Key là chuỗi 32–512 ký tự gồm chữ, số, `_` và `-`. Dán cùng một key vào SePay và vào ô "webhook API key" của kết nối.
Server so sánh header bằng `hash_equals`; thiếu hoặc sai key thì trả 401 và không ghi gì. SePay chờ tối đa khoảng 8 giây;
endpoint chỉ ghi nhận sự kiện rồi xử lý sau khi transaction commit, nên phản hồi nhanh.

Sự kiện tiền vào hợp lệ đầu tiên trên một kết nối đang chờ xác minh sẽ chuyển kết nối sang `verified`. Chỉ kết nối đã
xác minh mới tạo được mã QR.

### Bản demo

- Seed đặt key từ `SEED_DEMO_RECEIVER_WEBHOOK_KEY` cho kết nối của Cầu Giấy (`aura-court`) và in URL webhook của kết nối
  đó. `routingId` là UUIDv5 cố định của `auracourt-demo/aura-court`, nên URL không đổi sau mỗi lần seed lại.
- Mọi kết nối seed trỏ tới cùng một tài khoản ngân hàng (`SEED_DEMO_RECEIVER_*`), nhưng chỉ URL của Cầu Giấy được đăng
  ký trên SePay. Các cơ sở khác có key ngẫu nhiên: chuyển khoản tới QR của chúng vẫn vào tài khoản đó nhưng không tự xác
  nhận. Vì vậy mọi bước cần tự xác nhận khi trình diễn đều làm ở Cầu Giấy.

## Xoay key

1. Tạo key mới.
2. Lưu key vào kết nối: màn hình cấu hình kết nối, hoặc
   `POST /api/partner/organizations/{organizationId}/venues/{venueId}/receiving-connection/{connectionId}/credential`
   với body `{"webhook_api_key": "<key mới>"}`.
   - `credential_version` tăng 1, kết nối về `pending_verification` và ngừng tạo QR mới.
   - Từ lúc này key cũ bị từ chối (401).
3. Đổi key trong webhook SePay sang key mới ngay sau bước 2.
4. Chuyển một khoản nhỏ vào tài khoản; sự kiện hợp lệ đầu tiên xác minh lại kết nối.

API token dùng để truy vấn giao dịch SePay là credential khác, xoay qua `.../receiving-connection/{connectionId}/query-credential`.

Với bản demo: đặt key mới vào `SEED_DEMO_RECEIVER_WEBHOOK_KEY` thì lần seed sau ghi key đó và tăng `credential_version`;
nhớ đổi key trên SePay cho khớp.

## Tắt webhook nền tảng cũ

Webhook cũ `POST /api/v1/webhooks/sepay` nhận tiền vào tài khoản nền tảng (luồng ví và thanh toán qua nền tảng). Sau khi
deploy bản direct-owner, không còn QR nào trỏ tới tài khoản nền tảng: vào SePay và tắt (hoặc xóa) webhook trỏ tới URL
này. Nếu quên tắt cũng không gây hại; endpoint vẫn yêu cầu chữ ký hoặc API key của nền tảng và không ghi khoản nào cho
chủ sân.

## Tra tiền không khớp

Trang ngoại lệ tài chính của nền tảng không hiển thị khi payout tắt, nên tiền chuyển sai được tra trực tiếp trên SePay
(Giao dịch, Nhật ký webhook) hoặc ứng dụng ngân hàng của chủ sân. Server lưu mỗi sự kiện thành một receipt; các trạng
thái thường gặp:

| Mã | Nghĩa | Xử lý |
|---|---|---|
| `missing_payment_code` | Nội dung chuyển khoản không có mã `AC…` | Khách gõ sai nội dung; đối chiếu số tiền và giờ chuyển với booking rồi chủ sân tự xử lý với khách. Chuyển khoản bán lại slot (`RS…`) vào tài khoản demo cũng hiện mã này và là bình thường. |
| `unmatched_reference` | Có mã `AC…` nhưng không thuộc kết nối này | Kiểm tra khách có quét QR của cơ sở khác không |
| `partial_amount`, `excess_amount` | Chuyển thiếu hoặc thừa | Không tự xác nhận; chủ sân trả lại phần chênh cho khách ngoài hệ thống |
| `transfer_after_expiry`, `attempt_not_pending` | Tiền về sau khi mã hết hạn, hoặc mã đã được thanh toán | Không tự xác nhận; chủ sân trả lại tiền cho khách ngoài hệ thống |
| `reservation_hold_unavailable`, `pos_order_unavailable` | Booking hoặc đơn POS không còn chờ thanh toán | Như trên |
| `foreign_receiver` | Số tài khoản trong sự kiện khác tài khoản của kết nối | Webhook SePay đang gắn nhầm tài khoản; sửa cấu hình webhook |

Nếu SePay báo webhook lỗi 401, key trên SePay và key của kết nối không khớp: xoay key theo mục trên. Nếu SePay không gửi
gì, kiểm tra webhook có bật, đúng tài khoản và đúng sự kiện `In_only`.
