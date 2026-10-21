## Why

Report Module 02 (`module-02-report.md`: FR-25..FR-52, BR-25..BR-48, 16 bước, UC-2.1..UC-2.33) mô tả mô hình Tổ chức → Cơ sở → Sân:

- Cơ sở và sân có 4 trạng thái hoạt động.
- Chủ cơ sở tự mở cơ sở và mở từng sân.
- Đình chỉ tách khỏi trạng thái hoạt động và chỉ gỡ qua duyệt khắc phục.
- Khi dừng vận hành, đơn bị ảnh hưởng vẫn được lưu rồi xử lý.

Đối chiếu code `311790f3` (`module-02-gap-audit.md`) cho kết quả: 1 khớp, 23 khớp một phần, 11 thiếu hẳn, 12 làm ngược report, 5 lệch theo spec cũ. Spec `venue-court-operations` vẫn theo report bản trước (FR-VEN-10..12): cổng readiness rộng, admin duyệt công khai, 8 trạng thái với `suspended` ghi đè trạng thái hoạt động, và từ chối mọi thay đổi chạm đơn đã xác nhận.

Pha 0 (merge `9dddc773`) đã vá lỗi prod: đọc chéo tổ chức, kiểm eligibility dưới lock, tự gỡ đình chỉ, cơ sở mới bị kẹt, giá giả. Change này đặc tả phần còn lại để các pha 2–7 bám report. Report thay thế các quyết định D13–D20 cũ (`archive/2026-09-27-align-report-and-direct-owner-payments`) và một phần delta của `direct-owner-cutover`.

## What Changes

- **Mô hình trạng thái (D3, D4).**
  - **BREAKING:** `venues.status` chỉ còn `inactive / active / paused / closed`. Sân có `status` riêng với cùng 4 giá trị, thay cho `is_active`.
  - `closed` là trạng thái cuối. Cơ sở và sân mới luôn bắt đầu ở `inactive`.
  - Đình chỉ là bản ghi riêng ở cấp cơ sở, không ghi đè trạng thái hoạt động. Sân không có đình chỉ riêng.
- **Chủ cơ sở tự mở hoạt động (D1, D2).**
  - **BREAKING:** bỏ bước admin duyệt công khai và snapshot đã duyệt. API công khai đọc dữ liệu đang lưu.
  - Mở cơ sở cần: đủ thông tin BR-26, có giờ hoạt động, không bị đình chỉ. Mở cơ sở không mở sân.
  - Mở sân cần: đủ thông tin, ≥1 khoảng lịch phục vụ, đã liên kết bảng giá cùng cơ sở và bảng giá phủ kín lịch, cơ sở `active` và không bị đình chỉ.
  - Thiếu điều kiện → 422 kèm danh sách mục thiếu.
  - Gói thuê bao, đối tác đã duyệt và kết nối nhận tiền chuyển sang điều kiện nhận đặt. Kết nối áp cho mọi đường đặt mới, không chỉ thanh toán online.
  - Sân `active` chỉ nhận đặt khi bảng giá phủ kín lịch phục vụ (BR-41, BR-47). Đây là điều kiện nhận đặt, nên sân có giá thiếu sau khi khôi phục cơ sở hoặc gỡ đình chỉ vẫn bị từ chối đặt.
- **Tạm ngừng, ngừng kinh doanh, khôi phục (D5, D6).**
  - Lý do 10–1000 ký tự sau trim. Bắt đầu ngay hoặc lên lịch, không ở quá khứ. Ngày dự kiến kết thúc tuỳ chọn, chỉ để theo dõi, không tự mở lại.
  - Chặn đặt mới chồng lên `[bắt đầu, lúc khôi phục)` ngay khi xác nhận. Trạng thái đổi khi tới giờ bắt đầu.
  - Bảo trì và tạm ngừng kinh doanh đều là pause. Pause đối tượng không `active` → 422; đối tượng `active` đã có pause đang mở hoặc hẹn trước → 409.
  - Staff tạm ngừng và khôi phục sân vì bảo trì, sự cố tại cơ sở được phân công. Tạm ngừng kinh doanh, khôi phục loại này và ngừng kinh doanh chỉ chủ cơ sở làm.
  - Khôi phục kiểm lại điều kiện như lúc mở. Không áp dụng cho `closed`.
  - **BREAKING:** bỏ route DELETE cơ sở và sân; dừng hẳn đi qua ngừng kinh doanh. API khoá sân (`court_blocks`) thay bằng lệnh tạm ngừng và khôi phục.
- **Đơn bị ảnh hưởng (D7, D8).**
  - Đổi giờ hoạt động, lịch sân, ngày ngoại lệ (kể cả xoá), tạm ngừng, ngừng kinh doanh, đình chỉ: xem trước đơn bị ảnh hưởng, lưu kèm xác nhận `acknowledge_affected`, rồi mở case cho từng đơn. Không còn từ chối khi chạm đơn.
  - Đơn bị ảnh hưởng: đơn chờ thanh toán, đã xác nhận hoặc đang sử dụng, chưa kết thúc.
  - Owner và staff (theo phân công) xem danh sách và ghi kết quả xử lý. Khách không liên lạc được thì đơn giữ nguyên, không tự huỷ hay đánh no-show.
  - **BREAKING:** requirement "Final bookings and released availability" có thêm ngoại lệ. Owner hoặc staff được chuyển sân, đổi giờ, huỷ đơn bị ảnh hưởng khi khách đồng ý. Lệnh kiểm lại lịch trống dưới lock, đóng mọi case mở của đơn và không phát sinh dòng tiền. Khách không tự dời hay huỷ đơn đã xác nhận; huỷ đơn chờ thanh toán của khách thuộc module 4.
  - Cùng ngoại lệ được ghi vào "Unversioned reservation API". "Trigger real-time status hooks" thay "incident court reassignment" bằng ba lệnh vận hành, stop và đình chỉ.
  - Đổi sân do sự cố ở quầy đi qua cùng lệnh chuyển sân.
- **Thông tin cơ sở và sân (D9–D12).**
  - BR-26: tên 2–100, địa chỉ 10–255, SĐT Việt Nam, email ≤254 và không khoảng trắng, mô tả ≤2000, đều trim. Tiện ích do chủ khai, không còn danh sách bịa sẵn: tối đa 20 mục, mỗi mục 1–50 ký tự. Giới hạn tiện ích không có trong report (D12).
  - **BREAKING:** chỉ còn một nơi ghi cơ sở là `partner/organizations/{org}/venues`. Bỏ `POST /admin/venues` và `PATCH /admin/venues/{venue}`.
  - **BREAKING:** tên sân thành mã sân 1–50 ký tự, duy nhất trong cơ sở sau trim và không phân biệt hoa thường. Bỏ `name` đa ngôn ngữ và slug; URL sân dùng `id`.
  - Sửa sân chỉ đổi mã, mô tả, ảnh. Đổi cơ sở hoặc môn → 422.
  - **BREAKING:** ảnh chỉ nhận upload (ảnh sân có sẵn của nền tảng từ seed vẫn giữ được, D11): tối đa 10 ảnh, JPG/JPEG/PNG, ≤5 MB mỗi ảnh. Ảnh sân `image_url` → `images`.
  - Lịch sân phải nằm trong giờ hoạt động của cơ sở (BR-31).
  - Staff xem thông tin, giờ hoạt động và lịch sân của cơ sở được phân công.
- **Giám sát, đình chỉ, khắc phục (D13, D14).**
  - Platform admin xem mọi cơ sở và sân, chỉ đọc, có audit.
  - **BREAKING:** bỏ endpoint lifecycle của admin (`POST /admin/venues/{venueId}/lifecycle`), gọi → 404. Đình chỉ chỉ qua `POST /admin/venues/{venue}/suspensions` (Pha 6); từ Pha 2 tới Pha 6 không đình chỉ mới được, đình chỉ đã migrate vẫn hiệu lực (D13). Admin gọi lệnh mở, pause, resume, close → 403. Admin không đổi trạng thái kinh doanh thay chủ.
  - **BREAKING:** endpoint chuyển trạng thái chung của partner (`operational-status`, `lifecycle`) trả 404. Trạng thái chỉ đổi qua lệnh mở, pause, resume, close.
  - Đình chỉ: lý do 10–1000 ký tự, hiệu lực ngay, chặn đặt mới ở mọi sân, giữ trạng thái từng sân. Cơ sở đang bị đình chỉ → 409.
  - Khắc phục: nội dung 10–5000 ký tự, 1–10 file PDF/JPG/JPEG/PNG ≤10 MB. Trạng thái Chờ xem xét / Yêu cầu bổ sung / Từ chối / Chấp thuận. Mỗi lần gửi bất biến; mỗi quyết định chỉ có một yêu cầu chờ.
  - Chấp thuận gỡ đình chỉ trong cùng giao dịch và không đổi trạng thái hoạt động. Đây là đường gỡ duy nhất.
  - Thông báo kèm link: chủ khi bị đình chỉ và khi có kết quả duyệt; admin khi chủ gửi hoặc gửi lại.
- **Phạm vi đọc.** Owner đọc cơ sở của tổ chức mình. Staff chỉ đọc cơ sở được phân công. Customer member không đọc dữ liệu vận hành của tổ chức. Code đã làm ở Pha 0; change này ghi vào spec.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `venue-court-operations`: 4 trạng thái, tự mở cơ sở và sân, tạm ngừng/ngừng kinh doanh, đơn bị ảnh hưởng, đình chỉ và khắc phục, giám sát, quyền dừng và khôi phục (admin chỉ giám sát và đình chỉ), mã sân, ảnh upload, lịch sân trong giờ cơ sở, liên kết bảng giá phía sân, độ phủ giá kín là điều kiện nhận đặt; mua lại slot cũng kiểm lại eligibility dưới lock, deadlock khi khoá trả 409. Quy tắc no-show khi còn case mở nằm ở "Affected bookings from operational changes".
- `reservations-and-pricing`: thêm ba lệnh vận hành cho đơn bị ảnh hưởng; ngoại lệ tương ứng trong "Final bookings and released availability" và "Unversioned reservation API"; sửa danh sách sự kiện của "Trigger real-time status hooks". Không đụng requirement bảng giá.
- `organization-access`: phạm vi đọc theo vai trò và mã 403/404 (owner, staff theo phân công, customer member và người ngoài bị chặn, admin không thay membership); chi tiết tổ chức theo vai trò; quyền kênh realtime của cơ sở; quyền vận hành của owner và staff.
- `on-site-attendance`: đổi sân do sự cố đi qua lệnh chuyển sân, ghi nguồn case làm lý do; "Booking attendance lifecycle" trả 409 khi đánh no-show đơn còn case mở, trỏ về quy tắc ở `venue-court-operations`.
- `direct-owner-payments`: kết nối nhận tiền là điều kiện nhận đặt cho mọi đường đặt mới, không còn chặn mở cơ sở.
- `api-alignment`: "Policy-aware changes and truthful finance interfaces" cho trang đơn của owner/staff hiện ba lệnh D8 khi đơn còn case mở; khách vẫn không huỷ, dời, và không ai được hoàn tiền qua nền tảng.
- `platform-readiness`: "Persisted and authorized browser journeys" thay nghiệm thu duyệt công khai bằng mở cơ sở và sân, kiểm lệnh D8 bị từ chối khi đơn không có case mở; "Truthful payment acceptance boundaries" thu hẹp scenario "Direct booking refund" theo D8.
- `realtime-updates`: "Broadcast court status updates" chỉ phát sự kiện khi stop bắt đầu và trạng thái hiệu lực đổi, và khi resume; actor là owner hoặc staff được phân công (D5, D6).
- `localization/database-i18n`: "Bilingual Data Validation" chỉ áp cho trường dịch gửi theo từng locale; tên cơ sở và mô tả sân gửi dạng chuỗi đơn validate theo BR-26, BR-27 (D9, D12).
- `advanced-booking-marketplace`: "Long-running recurring reservations" chỉ đổi sân hoặc giờ của một buổi qua lệnh D8.
- `owner-hosted-drop-in-sessions`: "Preserve existing booking and payment behavior" giữ hợp đồng đơn cuối cùng thay cho "hoàn tiền theo chính sách": hoá đơn sau phiên không thêm huỷ, dời cho khách hay hoàn tiền, và không chặn lệnh huỷ D8 của owner/staff.
- `wallet-payment`: "Legacy wallet conservation during booking changes" cho owner/staff huỷ, chuyển, đổi giờ qua lệnh D8, không phát sinh bút toán ví hay nghĩa vụ hoàn tiền.

Tên requirement ADDED dùng chung giữa các file: "Venue suspension and remediation", "Affected bookings from operational changes" (`venue-court-operations`); "Operator handling of affected bookings" (`reservations-and-pricing`).

## Impact

- **Backend theo pha** (`module-02-report-alignment.plan.md`, thứ tự `2 → 3 → 5 → 6 → 7`):
  - Pha 2 – trạng thái: migration `venues.status`, `courts.status`, `venue_suspensions`, `operational_stops` thay `court_blocks`. Action mở, tạm ngừng, khôi phục, ngừng kinh doanh. `VerifyEffectiveBookingEligibility` thêm đình chỉ và dừng vận hành. Xoá `SubmitVenueForPublication`, `ReviewVenuePublication`, `EvaluateVenueReadiness` và overlay snapshot. Lệnh chạy mỗi phút trong `routes/console.php` lật `status` và phát `CourtStatusUpdated`; stop hẹn sau không phát lúc ghi (`realtime-updates`).
  - Pha 2 – hai việc module 4 giao lại: `PeerToPeerResaleService::lock` kiểm lại eligibility dưới lock và trả 409; deadlock PostgreSQL (SQLSTATE `40P01`) khi khoá ở đường đặt, mua lại slot hoặc kiểm eligibility trả 409 kèm thông báo thử lại tiếng Việt thay cho 500.
  - Pha 3 – thông tin: một `SaveVenueRequest`; xoá `AdminController::storeVenue / updateVenue / destroyVenue`, `CreateVenue`, `UpdateVenue`. Siết endpoint upload. Mã sân, route sân theo id. BR-31 ở FormRequest và `VenueOperatingCalendar::isOpen`. Staff đọc lịch qua `canOperateVenue`. Tên cơ sở và mô tả sân dạng chuỗi đơn không qua rule dịch theo locale (`localization/database-i18n`).
  - Pha 5 – đơn bị ảnh hưởng: query BR-38, bảng `affected_reservation_cases`, endpoint xem trước, danh sách và ghi kết quả, lệnh `move-court / change-time / cancel`. `markNoShow` trả 409 khi còn case mở. Gộp `attendance/reassign-court` vào `move-court`. Lệnh không tạo bút toán ví hay nghĩa vụ hoàn tiền (`wallet-payment`), là đường duy nhất đổi sân hoặc giờ một buổi của chuỗi định kỳ (`advanced-booking-marketplace`). Hoá đơn chia tiền sau phiên không thêm huỷ, dời cho khách hay hoàn tiền (`owner-hosted-drop-in-sessions`).
  - Pha 6 – đình chỉ, khắc phục: endpoint đình chỉ và giám sát, bảng `suspension_remediations`, `PrivatePartnerDocumentStorage` tham số hoá prefix, ba thông báo `notify*`.
  - Test phải viết lại: `CalendarClosureAndUnifiedAvailabilityTest`, `VenueOperatingCalendarTest`, `VenueCourtCrudAndPreservationTest`, `PlanQuotaEnforcementAndDowngradeTest` (bỏ chuyển sân sang cơ sở khác).
- **API:**
  - Bỏ: `submit-publication`, `operational-status`, `lifecycle` của partner; `POST /admin/venues/{venueId}/lifecycle`; `readiness` (thay bằng danh sách mục thiếu của lệnh mở); `publication-queue`, `revisions/*` của admin; `POST /admin/venues`, `PATCH` và `DELETE /admin/venues/{venue}`; `DELETE /admin/courts/{court}`; `/admin/courts/{court}/blocks*`; `attendance/reassign-court`.
  - Thêm: mở, tạm ngừng, khôi phục, ngừng kinh doanh cho cơ sở và sân; xem trước đơn bị ảnh hưởng; `affected-reservations` cùng ba lệnh; đình chỉ, giám sát, khắc phục và duyệt.
  - Đổi hợp đồng: `status` 4 giá trị, sân dùng `code` và `images`, lưu lịch kèm `acknowledge_affected` thay cho 409 từ chối.
- **Dữ liệu prod** (backup trước theo memory `bt-server-demo-reseed`; test migration với đủ 9 giá trị cũ):
  - Map trạng thái theo D4. `suspended` → một dòng `venue_suspensions` đang hiệu lực; trạng thái lấy `previous_status` trong audit log, không có thì `paused`.
  - `courts.status` backfill từ `is_active`. `court_blocks` → `operational_stops` với `ended_at = ends_at`.
  - `courts.name` → `code` và `code_normalized`; xử lý mã trùng trong cùng cơ sở trước khi thêm unique. `image_url` → `images`.
  - Không kiểm lại BR-26 khi migrate. Cơ sở thiếu SĐT hoặc email bổ sung ở lần sửa kế tiếp.
- **Frontend:**
  - Owner: nút mở cơ sở và mở sân kèm mục thiếu; dialog tạm ngừng và ngừng kinh doanh; nhãn 4 trạng thái và badge đình chỉ; form cơ sở có tiện ích và lỗi từng trường; upload nhiều ảnh; nhãn "Mã sân"; bỏ ô địa chỉ trong `CourtFormModal`; banner đình chỉ, form khắc phục và lịch sử.
  - Owner và staff: trang chi tiết cơ sở và sân chỉ đọc; `AdminAffectedBookingsView`; trang đơn chỉ hiện ba lệnh D8 khi đơn còn case mở, không có nút hoàn tiền (`api-alignment`).
  - Admin: trang giám sát, dialog đình chỉ, hàng đợi và màn duyệt khắc phục. Xoá `AdminPublicationQueueView` và mục nav.
  - Mọi link `/courts/:slug` đổi sang id. Bản dịch vi/en và vitest.
  - Playwright: nghiệm thu mở cơ sở và sân thay cho duyệt công khai; lệnh D8 trên đơn không có case mở bị từ chối (`platform-readiness`).
- **Phụ thuộc module 3 (Pha 2 – bảng giá và độ phủ, gồm Pha 4 cũ của module 2):** change riêng của session module 3 làm `price_tables` (thuộc một cơ sở), liên kết sân–bảng giá (tên bảng/cột do design module 3 chốt), CRUD bảng giá, migrate rule, bỏ `CourtPriceSlot`, viết lại availability, service và endpoint độ phủ giá cùng mã lỗi 422. Change này chỉ đặc tả phía sân của FR-33/BR-32, không chốt cách lưu liên kết.
  - Change này giữ requirement "Court opening conditions" và việc gọi service độ phủ trong `OpenCourt`, resume sân và eligibility. Module 3 không thêm delta `venue-court-operations` trùng ý "độ phủ giá là điều kiện mở sân"; đây là yêu cầu phối hợp gửi module 3.
  - Chọn bảng giá trong form sân và cổng độ phủ chỉ làm được sau khi Pha 2 của module 3 merge.
- **Phối hợp `direct-owner-cutover`:** archive cutover trước, rồi mới tới change này.
  - Requirement cutover đã sửa trong 8 capability: `Catalog and operational calendar`, `Report-aligned draft venue readiness`, `Unified public and new-booking eligibility`, `Venue lifecycle and captured policy continuity`, `Verified owner collection connection`, `Role-based owner operations`, `On-site changes and incident handling`, `Trigger real-time status hooks`, `Versioned reservation API compatibility` (rebase sang header `Unversioned reservation API` của spec chính), `Final bookings and released availability` (cutover thêm mới), `Policy-aware changes and truthful finance interfaces`, `Truthful payment acceptance boundaries`, `Persisted and authorized browser journeys` và `Legacy wallet conservation during booking changes`. Khối MODIFIED nào trùng header thì dựng từ text delta của cutover.
  - D8 thay một phần quyết định "không dời lịch, chỉ đổi sân do sự cố" của cutover. Task 14.5 của cutover có ghi chú phối hợp: UC-2.17 giữ lệnh đổi giờ do owner/staff làm cho đơn bị ảnh hưởng; khách không tự dời hay huỷ đơn đã xác nhận.
- **Đồng bộ spec ngoài module 2:** sau khi cutover archive, một số requirement của capability khác vẫn mâu thuẫn với D1, D5, D6, D8, D9 hoặc D12. Change này có delta MODIFIED tối thiểu cho chúng (xem "Modified Capabilities"); khối nào cutover cũng sửa thì dựng từ text delta của cutover (`api-alignment`, `platform-readiness`, `wallet-payment`), còn lại dựng từ spec chính.
  - Không viết delta `financial-ledger-and-payouts`: scenario "Refund is processed" chỉ chạy khi có bằng chứng hoàn tiền ngoài được chấp nhận, mà ghi chú hoàn tiền của D8 không phải bằng chứng đó. Việc thu hẹp scenario này cho dữ liệu trước cutover thuộc cutover hoặc module 4.
  - Không sửa scenario "Multilingual Content Storage" của `localization/database-i18n` (actor còn là admin); để change sau nếu cần.
- **Chồng lấn module 4 (`align-module-04-booking`):**
  - `reservations-and-pricing`: module 4 mở lại khách huỷ đơn chờ thanh toán. Nếu hai change cùng sửa "Final bookings and released availability" hoặc "Unversioned reservation API", change archive sau dựng khối MODIFIED từ text của change archive trước. Lệnh cấm khách huỷ ở đây giữ nguyên; module 4 thu hẹp khi rebase.
  - Lệnh huỷ của owner/staff với đơn chờ thanh toán gọi `releaseHeld($locked, 'cancelled', 'operator_affected_booking')` của module 4 (D2), lý do nằm ở `metadata.release_reason`; đơn đã trả bị huỷ ghi cùng lý do. Khách thấy "cơ sở huỷ do thay đổi vận hành", không phải nhãn "Đã hủy thanh toán" (D10 của module 4).
  - Module 4 nhờ module 2 bỏ `hold_ttl_minutes` và `require_continuity` khỏi form và request chính sách cơ sở. Change này nhận việc đó (task 2.16), làm sau khi module 4 bỏ việc đọc hai trường.
  - Module 4 giao lại hai việc về eligibility: mua lại slot kiểm lại eligibility dưới lock (task 1.11a) và deadlock `40P01` trả 409 (task 1.11b). Hai scenario nằm trong "Unified public and new-booking eligibility".
  - Lệnh cấm khách huỷ trong `api-alignment`, `wallet-payment`, `owner-hosted-drop-in-sessions` và `platform-readiness` giữ nguyên ở change này; module 4 thu hẹp khi rebase, như với `reservations-and-pricing`.
  - `on-site-attendance`: module 4 thêm phạm vi đọc của staff. Kiểm trùng header trước khi archive.
  - Chi tiết đơn của module 4 hiển thị sự cố từ case đơn bị ảnh hưởng của change này.
  - Yêu cầu của module 4 cho availability (khoảng nửa mở, bỏ hold quá hạn) nay gửi module 3. Predicate dừng vận hành ở đây cũng nửa mở: `[starts_at, ended_at ?? ∞)`.
- **Non-goals:** mô hình và CRUD bảng giá (module 3); đình chỉ cấp đối tác (`partner-registration`); capability quyền đình chỉ riêng cho admin (D13); change `remove-venues` (đã gác).
