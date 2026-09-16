## Context

Xem `proposal.md` cho mục đích nghiệp vụ. Change đi qua social, quyền cơ sở và giao diện chi phí nên cần thiết kế để phân biệt với thanh toán booking.

- `backend/app/Services/Social/MatchService.php` đã có tạo, tìm và tham gia kèo; hiện tạo kèo tự thêm creator vào participation và đặt `joined_count = 1`. Không thể giữ mặc định này cho chủ cơ sở chỉ tổ chức.
- `backend/app/Models/MatchPost.php` có metadata và participation có thể tái sử dụng. Thang `min_skill` / `max_skill` hiện tại là 1–5, không tự chuyển sang nhãn cầu lông mới.
- `frontend/src/features/social/composables/useCreateMatch.ts` và `components/match/MatchSettingsForm.vue` đang giả định host là người chơi, với giá/người trước trận và lựa chọn chia phí.
- `frontend/src/features/finance/composables/useGroupSplitBill.ts` chứa bill mẫu, bốn cách chia và hành động thanh toán/bảo lãnh. Không dùng state demo hay các hành động này làm nguồn dữ liệu buổi vãng lai.
- `openspec/specs/advanced-booking-marketplace/spec.md` quy định góp tiền trước để xác nhận đặt sân. Luồng này giữ nguyên.

## Goals / Non-Goals

**Goals:** Tái sử dụng tìm kèo/tham gia, phân quyền server theo chủ cơ sở và sân; giữ dữ liệu buổi chơi, người đăng ký và bill thực tế phân biệt; tính tiền có thể tái hiện sau reload.

**Non-Goals:** Không làm bộ máy chia tiền đa chế độ, danh sách công nợ theo tài khoản, thanh toán tự động, dịch vụ phụ, hệ thống trình độ mới cho mọi môn hoặc cơ chế giữ sân mới. Không thay đổi dữ liệu billing/booking cũ.

## Decisions

### 1. Mở rộng kèo hiện có bằng loại buổi riêng

Nhận diện rõ kèo thông thường và buổi do chủ cơ sở tổ chức. Buổi mới gắn một sân, phân loại `giao lưu` / `đánh vãng lai`, danh sách nhãn trình độ chấp nhận và giờ dự kiến. Tái sử dụng `MatchPost` / participation; các trường chuyên biệt nhỏ có thể nằm trong metadata có validation rõ ràng. Không tạo song song hệ thống matchmaking.

Danh sách trình độ là tập nhãn chấp nhận, không suy ra từ điểm 1–5. Khách lọc theo nhãn; không tạo thêm cơ chế chứng nhận hoặc cưỡng chế trình độ khi tham gia. Hai phân loại dùng chung quy tắc tham gia và chi phí ở bản này.

Chỉ nhánh buổi mới bỏ việc tự đăng ký host; kèo cũ giữ nguyên. Kiểm tra quyền sở hữu hiện tại ở mọi thao tác quản lý, không chỉ lúc tạo, và không tin `organization_id`/host ID từ client.

**Thay vì:** đổi nghĩa toàn bộ kèo và thang trình độ cũ — dễ gây hồi quy ngoài phạm vi.

### 2. Tách tổ chức buổi khỏi thanh toán và giữ chỗ sân

Luồng tối thiểu: `mở nhận người → kết thúc buổi → bill nháp → bill đã chốt`; trạng thái hết chỗ dùng cơ chế participation hiện có. Host kết thúc buổi một cách tường minh; không suy ra đã chốt từ đồng hồ hoặc booking đã thanh toán.

Đăng bài không tự tạo booking đã trả tiền hoặc quyền sử dụng sân. Chủ cơ sở quản lý lịch sân bằng cơ chế hiện có; đường tạo/cập nhật buổi phải tôn trọng kiểm tra sân và xung đột hiện hành, không ghi đè lịch đã giữ. Không thêm giữ chỗ, cọc hoặc tính tiền để mở bài.

**Thay vì:** dùng `SplitPaymentService` sau trận — dịch vụ đó gắn với expiry và xác nhận booking trước trận, không phù hợp bill thực tế.

### 3. Một bản ghi bill cho mỗi buổi, không phải sổ thu tiền

Dùng bản ghi bill liên kết duy nhất với kèo buổi mới, gồm số người thực tế, thời lượng phút, đơn giá giờ, số quả, giá/quả, trạng thái nháp/chốt, phiên bản và người/thời điểm chốt. Lưu snapshot hai thành tiền và tổng tại lúc chốt. Không tạo danh sách share thanh toán theo user: khách vãng lai thực tế có thể chưa có tài khoản.

Số người đăng ký chỉ là tham khảo, host nhập xác nhận số người chia phí; không bị giới hạn bởi số tài khoản đăng ký. Chủ cơ sở chỉ được tính khi thực sự chơi và nằm trong số người host xác nhận. Không thu dữ liệu cá nhân của khách walk-in chỉ để chia tiền.

Lưu nháp và chốt qua API riêng theo ID buổi. Dùng ràng buộc duy nhất và transaction/version check theo pattern có sẵn để tránh chốt trùng hoặc ghi đè nháp mới hơn. Bill đã chốt chỉ đọc trong bản này; không xây lịch sử điều chỉnh/hoàn tiền.

**Thay vì:** thêm bill dịch vụ tổng quát và payment shares — vượt yêu cầu chỉ nhập số người rồi chia đều.

### 4. Chỉ hai thành tiền, tính ở server

- Thời lượng UI nhập giờ/phút, chuẩn hóa về phút nguyên; đơn giá và thành tiền dùng VND nguyên, không tính tiền bằng float thiếu kiểm soát.
- Tiền sân = làm tròn half-up `(phút × giá giờ) / 60` đến VND; tiền cầu = `số quả × giá/quả`; tổng = hai khoản.
- Phần mỗi người có nguồn chuẩn là `tổng / số người`. Nếu không chia hết, hiển thị giá gần đúng với dấu `≈` và vẫn hiện tổng/số người; không ép số làm tròn thành tổng mới hoặc tự giao tiền dư cho host. Đây là bảng tính, không phải lệnh thu tiền.
- Cho host xác nhận/nhập đơn giá ngay trong bill; tận dụng giá mặc định hiện có nếu phù hợp, không tạo màn quản trị giá mới. Giá đã chốt không thay đổi theo danh mục sau này.
- Chỉ nhận các trường chi phí cho phép; từ chối dòng dịch vụ ngoài. Giữ POS/bán lẻ độc lập, không tự import đơn hàng mua thêm.

**Thay vì:** chia theo món, giờ của từng người hoặc phần trăm — không đúng cách chia đều đã thống nhất.

### 5. Giao diện theo ngữ cảnh buổi chơi

Thêm nhánh chủ cơ sở vào mở kèo; tìm kèo có phân loại và các nhãn trình độ mới. Chi tiết buổi cho host kết thúc rồi nhập bill tối giản; khách đã đăng ký xem bảng chốt chỉ đọc. Có trạng thái loading/error/empty thực, không fallback sang dữ liệu mẫu khi API lỗi. Không hiển thị CTA thanh toán, bảo lãnh, thêm dịch vụ hoặc lựa chọn cách chia trên bill này.

Tái sử dụng thành phần trình bày nhỏ nếu không kéo theo giả định bill booking. Định tuyến bill buổi bằng ID buổi rõ ràng, không đổi nghĩa link split-booking cũ hoặc xóa bán lẻ. Không cần link bill công khai cho khách chưa có tài khoản ở bản đầu.

## Risks / Trade-offs

- [Host nhập sai số người hoặc số cầu] → Hiện toàn bộ đầu vào và hai thành tiền để review trước khi chốt; cho sửa nháp, chưa mở lại bill đã chốt.
- [Thang trình độ bị hiểu là tương đương điểm cũ] → Giữ nhãn riêng theo buổi cầu lông, không backfill hay sửa profile chung.
- [Một đơn giá giờ không biểu diễn nhiều khung giá hoặc nhiều sân] → Bản đầu một sân/một đơn giá được host xác nhận cho mỗi buổi; không thêm biểu phí phức tạp trong bill.
- [Người đọc nhầm bill đã chốt thành đã thanh toán] → Trạng thái và nội dung chỉ nói chốt chi phí; không đánh dấu paid hoặc gọi payment API.
- [Tái sử dụng UI/demo làm sai số hoặc quyền] → Server là nguồn chuẩn; test tải lại và lỗi API, owner khác cơ sở và người chỉ là participant.
- [Đăng bài bị hiểu là đã giữ sân] → Nêu rõ ngữ cảnh lịch sân, giữ nguyên các kiểm tra và quyền sử dụng sân hiện hành; không tự xác nhận booking.

## Migration Plan

1. Thêm dữ liệu buổi chuyên biệt và bill một-một theo hướng additive; kèo không có loại mới tiếp tục dùng hành vi cũ. Không chuyển bill demo hay split-booking cũ thành bill mới.
2. Triển khai backend có phân quyền, validation, tính tiền và kiểm thử trước khi bật entry point giao diện; frontend chỉ dùng contract đã có.
3. Kiểm tra trọn luồng với chủ cơ sở và khách lẻ; chạy hồi quy generic match, advance split booking và POS.
4. Nếu cần rollback, tắt entry point mới và rollback ứng dụng theo quy trình hiện có; giữ dữ liệu buổi/bill đã tạo, không drop bảng có dữ liệu hoặc sửa lịch sử thanh toán.
