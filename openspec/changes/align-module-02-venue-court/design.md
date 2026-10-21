## Context

Động cơ xem `proposal.md` (Why). Hành vi bắt buộc xem các delta spec trong change này. Mục này chỉ ghi hiện trạng ảnh hưởng tới cách làm, tại HEAD `9dddc773` (sau Pha 0).

- **Trạng thái cơ sở.**
  - `venues.status` có CHECK 9 giá trị: `draft`, `pending_publication`, `revision_required`, `published`, `active`, `temporarily_closed`, `suspended`, `closed`, `archived` (migration `2026_10_11_000007`).
  - Đình chỉ ghi đè trạng thái hoạt động. `TransitionVenueLifecycle` ghi audit `venue.lifecycle.transitioned` với metadata `previous_status`, `new_status`, `reason`, `is_platform_admin`, và ghi lý do vào `status_reason`.
  - Guard Pha 0: `closed` chỉ sang `archived`; `archived` là trạng thái cuối; cơ sở chưa qua duyệt không vào cặp `published` / `temporarily_closed`.
- **Duyệt công khai.** Bảng `venue_publication_revisions` cùng cột JSON `venues.approved_public_snapshot` (migration `2026_09_10_000004`). API công khai đọc snapshot, không đọc giá trị đang lưu.
- **Sân.**
  - `courts.is_active` mặc định `true`.
  - `name` và `description` dùng `HasTranslations` (JSON theo locale). Slug sinh từ `name`; route công khai dùng slug.
  - Mỗi sân có một `image_url`. Seed dùng preset tương đối `/courts/*.jpg`; 28 file nằm trong `backend/public/courts`.
  - Sửa sân vẫn đổi được `venue_id` và `sport_type`.
- **Khoá sân.** `court_blocks` có `court_id` (cascade), `starts_at`, `ends_at` NOT NULL, `reason` nullable. Không có loại. Xoá cứng.
- **Xung đột lịch.**
  - Đổi giờ, lịch sân, ngoại lệ trả 409 kèm mã đơn khi chạm đơn đã xác nhận.
  - Xoá ngoại lệ không kiểm gì.
  - Chưa có danh sách đơn bị ảnh hưởng hay case xử lý.
- **Hai đường ghi cơ sở.** `partner/organizations/{org}/venues` và `AdminController::storeVenue / updateVenue / destroyVenue` (qua `CreateVenue`, `UpdateVenue`).
- **Upload ảnh.** `AdminController::upload` nhận `jpeg,png,jpg,webp,avif,gif` tới 10 MB, lưu qua `FileStorageService` (disk `r2`, `s3` hoặc `public`) và trả URL.
- **Admin.** `POST /admin/venues/{venueId}/lifecycle` đặt được mọi trạng thái. Có `publication-queue` và `revisions/*`.
- **Eligibility.** `VerifyEffectiveBookingEligibility` (`getEntitlement`, `execute`, `evaluateVenue`, `assertEligible`) là nguồn duy nhất. `ReservationService::hold` (dòng 65-73) và `OrdinaryCheckoutService` (dòng 56-60) đã kiểm lại dưới lock từ Pha 0: khoá `platform_subscriptions` qua `getEntitlement`, rồi `lockForUpdate` sân, rồi cơ sở, rồi `execute`, rồi `SlotSelectionPolicy::assertAllowed`. `MatchService` (dòng 1022) gọi `execute($court)`.
- **Có sẵn để dùng lại.**
  - `PrivatePartnerDocumentStorage::storePrivate(UploadedFile, int $userId, string $documentType)`, nhưng prefix cứng `private/kyc/{userId}/...`.
  - Outbox thông báo có `action_url`, `recordAudit`, mẫu `Schedule::call` trong `routes/console.php`.
  - `CourtCommitmentConflictService`, `ManageCourtBlocks`, `AttendanceService` (`attendance/reassign-court`, `api.php:415`).
- **Prod runtime.**
  - Không có Redis: broadcast lỗi, mọi màn hình phải poll.
  - Cron host chạy `schedule:run`; job chạy in-process.
  - Image không có bước migrate. Schema lên prod bằng SQL tay trong một transaction có `lock_timeout`, cộng một dòng `migrations` ở batch max+1; hoặc qua reseed.
  - PostgreSQL 18.6, server UTC; kiểm dữ liệu cần `PGTZ=Asia/Ho_Chi_Minh`.
  - Pipeline validate khoảng 7 phút trước khi deploy.
- **Session song song.** Module 3 (bảng giá) và module 4 (`align-module-04-booking`) đang soạn change riêng, cùng chạm `reservations-and-pricing`, `on-site-attendance` và availability.

## Goals / Non-Goals

**Goals:**
- Một mô hình trạng thái 4 giá trị cho cả cơ sở và sân; đình chỉ là bản ghi riêng.
- Chủ cơ sở tự mở cơ sở và từng sân, thiếu gì thì 422 kèm danh sách.
- Dừng vận hành chặn đặt mới ngay khi xác nhận, không phụ thuộc job.
- Thay đổi chạm đơn được lưu kèm xác nhận; đơn bị ảnh hưởng có case và ba lệnh xử lý.
- Đình chỉ và khắc phục theo report: lý do, impact, hồ sơ bất biến, gỡ chỉ qua duyệt.
- Một đường ghi cơ sở; validate BR-26, BR-27, BR-29, BR-31.
- Staff đọc cơ sở được phân công; admin giám sát chỉ đọc, có audit.

**Non-Goals:**
- Pha 4 cũ của module 2 (mô hình, CRUD, độ phủ bảng giá, bỏ `CourtPriceSlot`, viết lại availability). Nay là Pha 2 (bảng giá và độ phủ) của module 3.
- Khách tự huỷ hoặc dời đơn. Hoàn tiền trong nền tảng.
- Capability quyền đình chỉ riêng. Đình chỉ cấp sân. Đình chỉ cấp đối tác.
- Push realtime. Màn hình vẫn poll.
- Redirect từ URL slug cũ sang id.
- Viết lại lịch sử: tranh chấp giữ snapshot `court_name`.
- Bỏ hoặc mở rộng các trường D16 (`surface_type`, `capacity`, `slot_duration`, `amenities` của sân).

## Decisions

### D1. Chủ cơ sở tự mở cơ sở
- Chọn: bỏ duyệt công khai. `OpenVenue` mở cơ sở `inactive` khi đủ 3 điều kiện: dữ liệu BR-26, có giờ hoạt động với ít nhất một khoảng mở, không có đình chỉ đang hiệu lực. Thiếu → 422 kèm `missing_items[]`.
- Bỏ `approved_public_snapshot` và overlay snapshot. API công khai đọc giá trị đang lưu.
- Xoá `SubmitVenueForPublication`, `ReviewVenuePublication`, `EvaluateVenueReadiness`, route `submit-publication`, `readiness`, `publication-queue`, `revisions/*`.
- Phương án khác:
  - Giữ duyệt như bước tuỳ chọn. Loại: report không có, giữ lại là hai luồng song song.
  - Giữ snapshot làm bản công khai. Loại: không còn ai duyệt nên snapshot không bao giờ cập nhật.
- Lý do: report bước 5 và FR-34 giao việc mở cho chủ; admin chỉ giám sát và đình chỉ.

### D2. Gói, đối tác, kết nối nhận tiền thành điều kiện nhận đặt
- Chọn: gói thuê bao hoặc trial, đối tác đã duyệt, kết nối nhận tiền đã xác minh và chính sách đặt chỉ chặn nhận đặt, qua `VerifyEffectiveBookingEligibility`. Không chặn mở cơ sở hay sân.
- `direct-owner-payments` "Verified owner collection connection" đổi scenario: kết nối chưa đủ → cơ sở không nhận đặt, thay vì không được công khai.
- Phương án khác: giữ trong điều kiện mở. Loại: report giới hạn điều kiện mở ở dữ liệu cơ sở và giờ hoạt động.
- Lý do: một nguồn eligibility đã có, đã kiểm dưới lock. Thêm điều kiện vào đó không tạo đường mới.

### D3. Bốn trạng thái và đình chỉ tách riêng
- Chọn: `inactive / active / paused / closed` cho `venues.status` và `courts.status` mới. `closed` là trạng thái cuối.
- Chuyển hợp lệ: `inactive → active` (mở), `active → paused` (pause tới giờ bắt đầu), `paused → active` (resume), `inactive|active|paused → closed` (closure tới giờ bắt đầu). Còn lại → 422.
- Một `LifecycleGuard` cho cả cơ sở và sân, thay guard Pha 0.
- Chỉ lệnh mở, pause, resume, close đổi trạng thái. Xoá route partner `operational-status`, `lifecycle`, `transitionLifecycle` và route admin `POST /admin/venues/{venueId}/lifecycle` (D13); gọi route cũ → 404.
- Bảng `venue_suspensions`: `venue_id`, `reason`, `suspended_by`, `suspended_at`, `lifted_by`, `lifted_at`, `remediation_id` nullable. Partial unique index `(venue_id) WHERE lifted_at IS NULL`.
- Trạng thái cơ sở không ghi xuống sân.
- Phương án khác:
  - Giữ `suspended` trong `status`. Loại: ghi đè mất trạng thái hoạt động, gây lỗi tự gỡ đình chỉ ở Pha 0.
  - Cột `suspended_at` trên `venues`. Loại: mất lịch sử nhiều lần đình chỉ và không gắn được hồ sơ khắc phục.
- Lý do: report tách đình chỉ khỏi trạng thái hoạt động; bảng riêng giữ lịch sử và chặn trùng bằng index.

### D4. Ánh xạ trạng thái cũ
- Chọn:
  - `draft`, `pending_publication`, `revision_required` → `inactive`.
  - `published`, `active` → `active`.
  - `temporarily_closed` → `paused`.
  - `closed`, `archived` → `closed`.
  - `suspended` → một dòng `venue_suspensions` đang hiệu lực. Trạng thái lấy `previous_status` của dòng audit gần nhất có `new_status='suspended'`, map qua bảng trên. Không có audit, hoặc `previous_status` cũng là `suspended` → `paused`.
- Sân: `courts.status` backfill từ `is_active` (`true → active`, `false → paused`).
- Chi tiết dữ liệu ở Migration Plan.
- Phương án khác: `suspended` → `inactive`. Loại: cơ sở đang chạy sẽ phải mở lại sau khi gỡ, sai với report (gỡ đình chỉ không đổi trạng thái).
- Lý do: `paused` là mặc định an toàn: không nhận đặt, chủ resume được khi hết đình chỉ.

### D5. Một bảng `operational_stops`
- Chọn: bảng `operational_stops` thay `court_blocks`. Cột: `venue_id`, `court_id` nullable, `kind` (`maintenance / business / closure`), `reason`, `starts_at`, `expected_end_at`, `ended_at`, `created_by`, `ended_by`, timestamps.
- `maintenance` chỉ cho sân. `court_id` null nghĩa là dừng cả cơ sở.
- Chặn đặt trên `[starts_at, ended_at ?? ∞)`. Đơn `[s, e)` chồng khi `e > starts_at` và (`ended_at` null hoặc `s < ended_at`). `expected_end_at` chỉ để theo dõi.
- Không xoá stop. Resume ghi `ended_at = now()` và `ended_by`.
- `maintenance` và `business` đều là pause. Pause chỉ nhận đối tượng `active`: đối tượng `inactive`, `paused`, `closed` → 422 (chuyển trạng thái sai, cùng quy tắc D3); đối tượng `active` đã có pause đang mở hoặc hẹn trước → 409.
- `court_blocks` cũ chuyển thành `maintenance` với `expected_end_at = ended_at = ends_at`.
- Thay `ManageCourtBlocks` và route `/admin/courts/{court}/blocks*` bằng lệnh tạm ngừng và resume.
- Phương án khác:
  - Hai bảng cho cơ sở và sân. Loại: predicate và preview phải viết hai lần.
  - Giữ `court_blocks` và thêm bảng cho cơ sở. Loại: hai nguồn chặn, block cũ không có loại và bị xoá cứng.
- Lý do: một predicate dùng chung cho eligibility, availability và preview (BR-38).

### D6. Chặn bằng predicate, lật trạng thái bằng lệnh mỗi phút
- Chọn: eligibility đọc thẳng `operational_stops` theo predicate D5. Chặn có hiệu lực từ lúc lưu stop.
- Stop bắt đầu ngay: action đổi `status` trong cùng transaction.
- Stop tương lai: một lệnh mỗi phút trong `routes/console.php` tìm stop có `ended_at IS NULL AND starts_at <= now()` mà đối tượng chưa ở trạng thái tương ứng; khoá dòng, đổi `status`, phát `CourtStatusUpdated` sau commit, chỉ khi trạng thái thực sự đổi. Chạy lại không đổi gì.
- Stop cũ từ `court_blocks` đã có `ended_at`, nên lệnh lật bỏ qua; sân giữ `active`, predicate vẫn chặn đúng khung cũ.
- Phương án khác: job hẹn giờ cho từng stop. Loại: prod chạy job in-process, không có queue bền; job lỡ thì không chặn.
- Lý do: chặn không phụ thuộc lịch chạy. Lệnh trễ chỉ làm nhãn trạng thái trễ, không mở lỗ đặt.

### D7. Xem trước rồi lưu kèm xác nhận
- Chọn: mọi thay đổi chạm đơn (giờ hoạt động, ngày đóng, ngoại lệ cơ sở hoặc sân kể cả xoá, lịch sân, mọi stop, đình chỉ) có endpoint xem trước, không lưu gì.
- Lưu khi danh sách không rỗng cần `acknowledge_affected: true`. Thiếu → 409 kèm danh sách. Giữ quy ước 409 cho xung đột hiện có.
- Khi lưu: khoá dòng cơ sở hoặc sân, tính lại danh sách dưới lock, lưu thay đổi, mở case cho mọi đơn của danh sách tính lại.
- `acknowledge_affected` là boolean, không phải danh sách id. Đơn chen vào giữa lúc xem trước và lúc lưu vẫn có case.
- `AffectedReservationQuery` là query BR-38 duy nhất. Đơn bị ảnh hưởng: `held`, `confirmed` hoặc đang sử dụng (`in_use`, hoặc `confirmed` có `metadata.operational_status = in_use`), chưa kết thúc. Không ảnh hưởng nếu đơn `cancelled`, `expired`, `completed`, `no_show`, hoặc đã kết thúc. Report dòng 792 tính cả đơn đang sử dụng.
- Bảng `affected_reservation_cases`: unique `(reservation_id, source_type, source_id)`; trạng thái `pending / contacting / unreachable` (mở) và `moved / time_changed / cancelled / kept` (đóng). Lưu lại cùng thay đổi không tạo case thứ hai.
- Bỏ 409 từ chối của `CourtCommitmentConflictService`.
- Phương án khác:
  - 422 khi thiếu xác nhận. Loại: 422 dành cho dữ liệu sai; đây là xung đột với đơn hiện có.
  - Xác nhận bằng danh sách id phải khớp. Loại: stop sự cố bị từ chối liên tục khi có đơn mới chen vào, trong khi case vẫn mở cho mọi đơn.
- Lý do: report bước 9 cho lưu rồi xử lý. Stop sự cố phải có hiệu lực ngay.

### D8. Ba lệnh vận hành cho đơn bị ảnh hưởng
- Chọn: `move-court`, `change-time`, `cancel`, do owner hoặc staff được phân công làm, chỉ cho đơn còn case mở.
- Mỗi lệnh cần `customer_agreed` và kênh liên hệ (`phone / zalo / chat`). Thiếu → 422.
- Áp cho đơn `held`, `confirmed` hoặc đang sử dụng, như D7.
- Đọc đơn không lock để lấy sân gốc. Khoá theo thứ tự toàn cục (mục "Kiểm eligibility dưới lock"). Đọc lại đơn dưới lock; sân, giờ hoặc trạng thái đã đổi → 409.
- Kiểm lại khung đích dưới lock bằng chính điều kiện sân, cơ sở, đình chỉ, stop và giờ phục vụ hiệu lực của "Unified public and new-booking eligibility"; bỏ điều kiện đối tác, gói, giá, chính sách và kết nối nhận tiền. Thêm cửa sổ đặt và quy tắc slot theo chính sách đã ghi trên đơn, và không chồng inventory khác ngoài slot của chính đơn. Hỏng → 409, đơn và case giữ nguyên.
- Ngoại lệ của kiểm slot:
  - Không kiểm thời lượng tối thiểu, tối đa: lệnh giữ thời lượng phần đổi, kể cả khi phần chưa kết thúc ngắn hơn mức tối thiểu.
  - `move-court` nhận slot đang diễn ra ở giờ gốc, để chuyển đơn đang sử dụng giữa slot (scenario "Checked-in booking during an incident stop"). Slot đã kết thúc ở lại sân gốc.
  - `change-time` đặt slot mới từ hiện tại trở đi, trong hạn đặt trước của chính sách đã ghi.
  - Phương án khác: theo chính sách đang hiệu lực của cơ sở. Loại: trái "authorized servicing SHALL use the captured policy"; đổi chính sách sau khi đặt sẽ chặn xử lý đơn.
- Lệnh đóng mọi case mở của đơn với cùng kết quả. Lịch sử đơn ghi nguồn case làm lý do.
- Không mở case mới sau khi chuyển hay đổi giờ: kiểm dưới lock đã loại khung trùng stop, đình chỉ hoặc ngoài giờ phục vụ. Phương án mở lại case cho khung mới bị loại vì không có khung đích nào lọt qua kiểm tra mà vẫn bị ảnh hưởng.
- `move-court`: giữ giờ, cùng tổ chức, cùng môn; khác môn → 422. `change-time`: giữ sân và tổng thời lượng; khác thời lượng → 422.
- Đơn `held` đang giữ lượt voucher (`VoucherUsageService::holdsVoucher`, task 5.2 của module 3): `move-court` và `change-time` trả 409, đơn, lượt và case giữ nguyên; operator huỷ đơn thay thế.
  - Lý do: `consume` của module 3 (D11 của họ) kiểm lại voucher theo sân và giờ của đơn lúc xác nhận. Đơn chuyển sang sân ngoài tập sân của chương trình, hoặc sang giờ có mức giảm khác, sẽ hết hạn với lý do `voucher_changed` và chuyển khoản thành ngoại lệ, dù khách đã đồng ý.
  - Phương án khác: kiểm lại voucher cho khung đích bằng engine của module 3 dưới lock. Loại: thêm khoá chương trình và phụ thuộc engine vào lệnh D8 cho một trường hợp hiếm (đơn chờ chỉ sống vài phút).
  - Trước task 5.1–5.3 của module 3 chưa đơn nào giữ lượt, nên quy tắc chưa có hiệu lực. Ai làm trước thì viết kiểm: module 2 ở task 4.8–4.9, hoặc task 5.4 của module 3 nếu lúc đó lệnh còn `ponytail:`; task 5.4 cũng chặn `attendance/reassign-court` nếu route còn, và task 4.12 xoá chặn đó cùng route.
- Đổi giờ cần loại trừ slot của chính đơn khỏi kiểm chồng. Thêm lại tham số loại trừ cho `SlotSelectionPolicy::assertAllowed`, mà cutover Decision 7 đã bỏ. Lệnh D8 truyền chính sách đã ghi của đơn thay cho chính sách đang hiệu lực, và áp các ngoại lệ ở trên.
- `cancel` giải phóng slot chưa kết thúc một lần; đơn chờ thanh toán thì kết thúc nghĩa vụ thanh toán và nhả lượt voucher đang giữ đúng một lần.
  - Đơn `held`: gọi `releaseHeld($locked, 'cancelled', 'operator_affected_booking')` của module 4 (D2 của họ, đã có trên main), trong thứ tự lock ở mục "Kiểm eligibility dưới lock". Hàm này nhả slot, attempt chờ và lượt giữ, ghi lý do vào `metadata.release_reason`. Lệnh D8 không gọi `release` của voucher riêng: từ task 5.5 của module 4, `releaseHeld` gọi `VoucherUsageService::release` của module 3 (D11 của họ).
  - Đơn `confirmed` hoặc đang sử dụng: `releaseHeld` trả false, nên lệnh tự đặt `cancelled`, nhả slot chưa kết thúc và ghi cùng `metadata.release_reason = operator_affected_booking` trong transaction đó.
  - Khách thấy nhãn riêng "cơ sở huỷ do thay đổi vận hành", theo `release_reason = operator_affected_booking`, qua một nhánh mới trong `statusLabel` (`frontend/src/features/booking/utils/reservation.ts`). Không dùng "Đã hủy thanh toán" của module 4 (D10 của họ).
- Không tạo quote, payment, journal, refund. Giữ số tiền đã ghi. Chỉ owner ghi chú hoàn tiền ngoài nền tảng; staff → 403.
- `markNoShow` trả 409 khi đơn còn case mở.
- `attendance/reassign-court` gộp vào `move-court`. Đổi sân do sự cố đi qua stop `maintenance` rồi `move-court`.
- Khách không tự dời hay huỷ đơn đã xác nhận. Huỷ đơn chờ thanh toán của khách thuộc module 4 (D1 của module 4).
- Thay một phần quyết định "không dời lịch" của cutover và delta `reservations-and-pricing` của nó: "Final bookings and released availability" và "Unversioned reservation API" có thêm ngoại lệ cho owner/staff; "Trigger real-time status hooks" thay "incident court reassignment" bằng ba lệnh, stop và đình chỉ. Task 14.5 của cutover có ghi chú UC-2.17 giữ lệnh đổi giờ của owner và staff.
- Giữ "operational readiness changes" trong "Trigger real-time status hooks": đó là kiểm tra sân sau khi chơi của `on-site-attendance` ("Booking attendance lifecycle"), không phải readiness công khai đã bỏ.
- Phương án khác:
  - Tự huỷ đơn khi dừng vận hành. Loại: report cấm; khách không liên lạc được thì giữ đơn.
  - Dùng lại `RescheduleService`. Loại: đã bị cutover gỡ, kéo theo luồng thu chênh lệch.
- Lý do: phạm vi hẹp nhất report cho phép, không mở lại luồng tiền.

### D9. Mã sân thay tên sân
- Chọn: thêm `courts.code` varchar(50) và `code_normalized`. `code_normalized` là cột generated stored `lower(btrim(code))`, app không ghi. Unique `(venue_id, code_normalized)`.
- Bỏ `name` JSON và slug sân. Mọi URL sân dùng id.
- `description` giữ `HasTranslations`; giới hạn 2000 áp cho chuỗi gửi lên.
- Phương án khác:
  - Unique theo biểu thức, không có cột. Gọn hơn nhưng plan D9 chốt cột; cột generated giữ đúng plan mà không có đường ghi thứ hai.
  - Giữ slug. Loại: slug sinh từ tên, đổi mã làm gãy link.
- Lý do: BR-27 coi mã là tên hiển thị, duy nhất trong cơ sở, không phân biệt hoa thường.

### D10. Sửa sân chỉ đổi mã, mô tả, ảnh
- Chọn: request sửa sân chỉ nhận `code`, `description`, `images`. Gửi `venue_id` hoặc `sport_type` khác giá trị đang lưu → 422.
- Giữ các trường D16 (`surface_type`, `capacity`, `slot_duration`, `amenities`), không mở rộng.
- `PlanQuotaEnforcementAndDowngradeTest` bỏ ca chuyển sân sang cơ sở khác.
- Phương án khác: bỏ qua im lặng trường không cho sửa. Loại: client tưởng đã đổi.
- Lý do: BR-27/FR-28 khoá cơ sở và môn sau khi tạo.

### D11. Ảnh chỉ qua upload
- Chọn: endpoint upload nhận JPG/JPEG/PNG, kiểm cả đuôi và nội dung, ≤5 MB. Mỗi cơ sở và sân ≤10 ảnh. `courts.image_url` → `courts.images` (mảng).
- Lúc lưu, mỗi phần tử phải là ảnh nền tảng lưu: URL trên disk ảnh của `FileStorageService`, hoặc preset seed `/courts/*.jpg` có file trong `backend/public/courts`. Link ngoài → 422.
- Phương án khác:
  - Chỉ nhận URL disk ảnh. Loại: dữ liệu seed và prod dùng preset; chủ sẽ không lưu được sân nào cho tới khi xoá ảnh.
  - Chấp nhận mọi phần tử đã lưu sẵn. Loại: là shim, link ngoài cũ sống mãi.
- Lý do: preset là file nền tảng tự phục vụ, không phải link ngoài. Spec cấm link tới website khác; điều đó vẫn giữ.

### D12. Một đường ghi cơ sở
- Chọn: chỉ `partner/organizations/{org}/venues` tạo và sửa cơ sở, qua một `SaveVenueRequest` (BR-26, trim trước khi validate). Hạn mức cơ sở theo gói kiểm khi tạo.
- Xoá `AdminController::storeVenue / updateVenue / destroyVenue`, `CreateVenue`, `UpdateVenue`. Xoá route DELETE cơ sở và sân; dừng hẳn đi qua closure.
- Tiện ích do chủ khai, không còn danh sách cố định.
- Tiện ích: tối đa 20 mục, mỗi mục 1–50 ký tự sau trim; sai → 422. Không có trong report, đặt để chặn dữ liệu rác. Phương án khác: không giới hạn. Loại vì không chặn được payload lớn.
- Phương án khác: giữ đường admin cho hỗ trợ. Loại: admin chỉ giám sát (D13), và hai đường validate khác nhau.
- Lý do: một nơi validate, một nơi kiểm quyền.

### D13. Mọi platform admin được đình chỉ
- Chọn: mọi platform admin không bị khoá được đình chỉ, không cần capability riêng. `POST /admin/venues/{venue}/suspensions`; cơ sở đã bị đình chỉ → 409.
- Gỡ đình chỉ chỉ qua chấp thuận khắc phục. Không có endpoint gỡ trực tiếp.
- Bỏ hẳn `POST /admin/venues/{venueId}/lifecycle` (→ 404, scenario "Retired status-transition endpoints"). `/suspensions` là đường đình chỉ duy nhất. Admin gọi lệnh mở, pause, resume, close → 403.
  - Phương án khác: giữ route lifecycle cho riêng đình chỉ. Loại: đình chỉ là bản ghi riêng, không phải chuyển trạng thái; hai đường đình chỉ.
- Khoảng giữa Pha 2 và Pha 6: route lifecycle bỏ ở Pha 2 (task 1.14), `/suspensions` và duyệt khắc phục tới ở Pha 6 (task 5.1, 5.7). Trong khoảng đó admin không đình chỉ mới được; đình chỉ đã migrate vẫn chặn đặt và chưa gỡ được. Fail closed, đúng spec: chỉ duyệt khắc phục gỡ đình chỉ.
  - Phương án khác: giữ hành động đình chỉ trên route lifecycle tới Pha 6, ghi dòng `venue_suspensions`. Loại: shim, và đường đó không có xem trước hay case (D7).
  - Phương án khác: đưa 5.1 lên Pha 2. Loại: đình chỉ cần xem trước và case của Pha 5.
- Giám sát: `GET /admin/monitoring/venues` và chi tiết, chỉ đọc, mỗi lần xem ghi một dòng audit.
- Phương án khác: capability `venues.suspend`. Loại: report không phân quyền admin con; thêm capability là việc không ai yêu cầu.
- Lý do: report giao đình chỉ cho platform admin nói chung.

### D14. Hồ sơ khắc phục bất biến
- Chọn: bảng `suspension_remediations`, mỗi lần gửi một dòng: `venue_suspension_id`, `content`, `files` (JSON đường dẫn private), `status`, `submitted_by`, `submitted_at`, `reviewed_by`, `reviewed_at`, `review_note`.
- Trạng thái của yêu cầu là trạng thái dòng mới nhất. Partial unique index `(venue_suspension_id) WHERE status = 'pending'`.
- Gửi lần đầu khi chưa có dòng; gửi lại khi dòng mới nhất là `more_info_requested` hoặc `rejected`. Dòng mới nhất `pending` hoặc `approved`, hoặc đình chỉ đã gỡ → 409.
- Admin duyệt `request_more_info / reject / approve` qua `GET /admin/venue-remediations` và `POST /admin/venue-remediations/{id}/review`. `approve` gỡ đình chỉ trong cùng transaction, không đổi trạng thái hoạt động.
- File qua `PrivatePartnerDocumentStorage` với prefix tham số hoá (`private/kyc` cho KYC, `private/remediations` cho khắc phục). Đọc qua link có hạn cho owner của tổ chức và platform admin.
- Thông báo kèm link: `notifyVenueSuspended`, `notifyRemediationSubmitted`, `notifyRemediationReviewed`.
- Phương án khác: một dòng yêu cầu được cập nhật. Loại: mất lịch sử, trái BR-45.
- Lý do: lịch sử là chính bảng; index chặn hai yêu cầu chờ kể cả khi gửi đồng thời.

### Q1. Liên kết bảng giá và phần của module 3
- Chọn (Q1 = A, bản cập nhật): bảng giá thuộc một cơ sở (`price_tables.venue_id`). Sân chỉ liên kết bảng giá cùng cơ sở.
- Module 3 làm Pha 2 (bảng giá và độ phủ, gồm Pha 4 cũ của module 2) trong change riêng: model, CRUD, liên kết sân–bảng giá (tên bảng/cột do design module 3 chốt; có loại thường/ngày lễ và thời gian hiệu lực theo D2 của module 3), migrate rule, bỏ `CourtPriceSlot`, viết lại availability, endpoint, service độ phủ và mã lỗi 422.
- Change này chỉ đặc tả phía sân: chọn bảng giá cùng cơ sở trong form sân, màn sân đọc và ghi đúng liên kết mà quản lý giá định nghĩa ("Court price table link"), không chốt cách lưu; sân không mở hoặc resume khi bảng giá chưa phủ kín lịch. Không thêm requirement bảng giá vào `reservations-and-pricing`.
- Phân việc cổng độ phủ:
  - Chọn: change này giữ requirement "Court opening conditions" và việc gọi service độ phủ trong `OpenCourt`, `ResumeCourt` và eligibility (task 3.3, 3.4, 3.5). Module 3 giữ service, endpoint và mã lỗi.
  - Phương án khác: module 3 làm cả wiring và delta `venue-court-operations` riêng. Loại: mở và resume sân là action của module 2; hai delta cùng ý trên một capability sẽ trùng header khi archive.
  - Module 3 đã nhận (D3 và task 1.4 của họ): bỏ delta "độ phủ giá là điều kiện mở sân" và task `OpenCourt`/resume, chỉ cung cấp `PriceCoverageService`, dạng kết quả (mỗi mục: sân, thứ hoặc ngày, giờ bắt đầu, giờ kết thúc) và mã `PRICE_COVERAGE_INCOMPLETE` cho công bố giá và đổi liên kết.
  - Mở và resume sân đưa khoảng thiếu vào `missing_items` của 422 gộp cùng các điều kiện khác, không trả `PRICE_COVERAGE_INCOMPLETE` riêng.
  - Pha 2 của module 3 giữ tên `CourtPriceRuleService` và đổi `isPriced` (trong `VerifyEffectiveBookingEligibility`) và `pricedCourtIds` (danh sách công khai trong `CourtController::index`) thành "sân có liên kết thường đang hiệu lực" (D28 của module 3). Task 3.5 thêm kiểm độ phủ trên cả hai; danh sách công khai gọi `PriceCoverageService::uncoveredCourtIds` một lần cho mọi sân.
  - Màn sân gọi cùng action áp liên kết của module 3 (task 2.5 của họ), nên đổi liên kết làm hở giá một sân `active` hoặc `paused` nhận 422 `PRICE_COVERAGE_INCOMPLETE` của họ.
- Ngày lễ (thoả thuận với module 3): MODIFIED "Catalog and operational calendar" ghi holidays là ngày nghỉ của lịch phục vụ, không đặt giá. Bảng giá ngày lễ (BR-51) thuộc delta `reservations-and-pricing` của module 3.
- Độ phủ kín là điều kiện nhận đặt (BR-41, BR-47, FR-43):
  - Chọn: "Unified public and new-booking eligibility" thêm điều kiện lịch phục vụ hiệu lực của sân được phủ giá kín, cùng service độ phủ. Một predicate fail-closed, giống D6; public dùng cùng eligibility.
  - Phương án khác: 422 mọi lần đổi giờ cơ sở hoặc lịch sân làm sân `active` bị hở giá. Loại: nhiều đường ghi phải kiểm, và không bắt được sân `active` đã migrate với giá hở.
  - Giữ quy tắc theo thời điểm: thời điểm không có giá áp dụng (ví dụ ngày ngoại lệ kéo dài giờ) vẫn bị bỏ khỏi availability và từ chối đặt.
- Thứ tự: task chọn bảng giá và cổng độ phủ trong `OpenCourt`, resume sân và eligibility làm sau khi Pha 2 của module 3 merge, gọi service độ phủ của module 3.
- Trước đó: Pha 2 mở và resume sân, và eligibility, kiểm mọi điều kiện trừ độ phủ kín, có ghi chú `ponytail:` ở đúng chỗ. An toàn vẫn giữ: availability bỏ slot không giá, quote từ chối slot không giá, sân không có giá nào bị ẩn khỏi public và trả 404 ở chi tiết (cutover Decision 9). Không thể thu sai tiền.
- Change không archive trước khi cổng độ phủ có mặt.
- Phương án khác: tạm tính độ phủ trên `PriceRule` / `CourtPriceSlot` hiện tại. Loại: nhân đôi service của module 3 rồi xoá ngay.

### Thứ tự archive với `direct-owner-cutover`
- Chọn: archive cutover trước, rồi change này.
- Thứ tự đã thống nhất với các session module 3 và 4: `direct-owner-cutover` → `align-module-02-venue-court` → change của module 3 → `align-module-04-booking`. Mỗi change archive sau dựng khối MODIFIED trùng header từ spec chính mà change trước để lại.
- Module 3 dựng khối MODIFIED "Role-based owner operations" trên text của change này (commit `92021735`). Sửa requirement đó thì báo module 3 để họ dựng lại.
- Thứ tự archive khác thứ tự code: code Pha 2 của module 3 (service độ phủ) phải merge trước task 3.3–3.5 của change này, còn delta của module 3 archive sau change này.
- Cutover chỉ còn task vận hành. Delta của nó MODIFY cùng header: "Catalog and operational calendar", "Unified public and new-booking eligibility", "Venue lifecycle and captured policy continuity", "Report-aligned draft venue readiness", "Verified owner collection connection", "Role-based owner operations", "On-site changes and incident handling", "Trigger real-time status hooks", "Versioned reservation API compatibility" (rebase sang "Unversioned reservation API"), "Policy-aware changes and truthful finance interfaces", "Truthful payment acceptance boundaries", "Persisted and authorized browser journeys" và "Legacy wallet conservation during booking changes".
- "Report-aligned draft venue readiness" đã có trên spec chính; cutover chỉ MODIFY nó. Khối REMOVED ở đây hợp lệ với cả hai thứ tự archive. Chỉ "Final bookings and released availability" là requirement cutover thêm mới, nên chỉ có trên spec chính sau khi cutover archive.
- Khối MODIFIED của change này trùng header thì dựng từ text delta của cutover.
- Nếu change này archive trước, khối của cutover sẽ ghi đè thay đổi ở đây.
- Cutover rebase "Versioned reservation API compatibility" sang header "Unversioned reservation API" của spec chính (ghi chú ở task 12.1 và 14.5 của cutover), giữ text cấm huỷ và dời của nó. Change này có khối MODIFIED "Unversioned reservation API" dựng từ text spec chính cộng lệnh cấm đó, rồi thêm ngoại lệ cho lệnh D8. Lệnh cấm khách giữ nguyên; module 4 thu hẹp khi rebase.
- Đồng bộ spec ngoài module 2: `api-alignment` "Policy-aware changes and truthful finance interfaces", `platform-readiness` "Persisted and authorized browser journeys", "Truthful payment acceptance boundaries" và `wallet-payment` "Legacy wallet conservation during booking changes" (text cutover) cấm mọi huỷ, dời, kể cả lệnh D8 của owner/staff. Change này có khối MODIFIED tối thiểu cho chúng, dựng từ text delta của cutover. `realtime-updates`, `localization/database-i18n`, `advanced-booking-marketplace` "Long-running recurring reservations" và `owner-hosted-drop-in-sessions` không có delta cutover trên header đó, nên dựng từ spec chính.
  - Phương án khác: để change sau sửa. Loại: sau khi archive, spec chính tự mâu thuẫn với D1, D5, D6, D8, D9, D12.
  - Không viết delta `financial-ledger-and-payouts`: ghi chú hoàn tiền D8 không thoả điều kiện "verified external owner refund evidence" của scenario "Refund is processed", nên không mâu thuẫn. Module 4 có delta trên capability này; viết thêm dễ trùng header.
- Module 4 cũng sửa "Final bookings and released availability" (khách huỷ đơn chờ thanh toán) và `on-site-attendance`. Change archive sau dựng khối MODIFIED từ spec chính change trước để lại.

### Quyết định khi làm Pha 0 (giữ nguyên)
- Pre-check eligibility trong `ReservationController` giữ lại vì nó ghi nhận gói hết hạn. Lần kiểm quyết định chạy dưới lock trong service.
- Thứ tự lock `platform_subscriptions` trước, rồi sân, rồi cơ sở, để tránh deadlock.
- Platform admin đọc cơ sở qua route partner → 403 (BR-23); trong `OrganizationController::show` thấy đầy đủ.
- Owner/staff đọc cơ sở ngoài tổ chức của route → 404; thành viên chỉ là customer, người ngoài, platform admin → 403 với mọi id.
- Guard `closed → archived` và cặp `published / temporarily_closed` của Pha 0 bị thay bằng `LifecycleGuard` (D3).

### Kiểm eligibility dưới lock và thứ tự lock
- Thứ tự lock toàn cục, khớp code thanh toán hiện có (cơ sở → kết nối nhận tiền → attempt → đơn) và module 4 D1 (attempt → đơn): `platform_subscriptions` → sân (id tăng dần) → cơ sở (id tăng dần) → `venue_suspensions` → kết nối nhận tiền → payment attempt → đơn → `affected_reservation_cases` → `suspension_remediations`. Lệnh nào bỏ qua bước nào thì vẫn giữ thứ tự các bước còn lại.
- Phương án khác: khoá đơn trước (bản nháp cũ). Loại: ngược chiều với khởi tạo thanh toán và xử lý biên nhận, tạo vòng chờ đơn ↔ cơ sở.
- Đường đặt (hold, checkout, recurring, phiên do owner tổ chức qua `MatchService`): `getEntitlement` khoá gói, khoá sân, khoá cơ sở, rồi `execute` với predicate mới:
  - cơ sở `active` và không có đình chỉ đang hiệu lực;
  - sân `active`;
  - không có stop của sân hoặc cơ sở chồng thời gian yêu cầu, kiểm trong `SlotSelectionPolicy` dưới cùng lock.
- Stop và đình chỉ đọc sau khi đã có lock cơ sở. Ở READ COMMITTED, câu đọc sau lock thấy dòng vừa commit.
- Lệnh stop sân khoá dòng sân; stop cơ sở, đình chỉ, gửi và duyệt khắc phục khoá dòng cơ sở trước. Không lệnh nào khoá sân sau cơ sở, nên không có vòng chờ với đường đặt.
- Lưu thay đổi D7 khoá theo thứ tự toàn cục trước khi ghi và tính lại danh sách ảnh hưởng: các sân bị ảnh hưởng trước (thay đổi cấp sân: sân đó; cấp cơ sở: mọi sân của cơ sở, id tăng dần), rồi cơ sở.
  - Trên main, `SaveVenueOperatingSchedule` và `SaveVenueScheduleException` khoá cơ sở `FOR UPDATE` rồi khoá ngầm sân khi ghi dòng có `court_id`, ngược đường đặt (D11 của module 3 cũng ghi nhận). Task 4.4 đổi sang thứ tự trên.
  - Phương án khác: giữ thứ tự cũ và để 40P01 về 409. Loại: lưu giờ hoạt động lúc đông khách sẽ bị huỷ ngẫu nhiên, trong khi hai action này đằng nào cũng được viết lại ở task 4.4.
- Lệnh D8 đọc đơn không lock để lấy sân gốc, rồi khoá sân gốc và sân đích theo id tăng dần, cơ sở, đình chỉ, attempt đang mở (khi huỷ đơn `held`), đơn, rồi các case. Đọc lại đơn dưới lock; sân, giờ hoặc trạng thái đổi → 409. Không khoá gói: lệnh không tạo đặt mới.
- Pre-check ở controller chỉ để ghi gói hết hạn, không quyết định.
- Mua lại slot (module 4 giao lại): người mua khoá listing là một đường nhận đặt mới. Đọc listing không lock để lấy sân, rồi khoá gói → sân → cơ sở → `venue_suspensions` → kết nối nhận tiền theo thứ tự trên, kiểm trạng thái cơ sở và sân, đình chỉ, stop chồng slot, đối tác, gói và kết nối, rồi mới khoá listing, entitlement và đơn. Không kiểm lại giá: slot giữ giá đã ghi. Hỏng → 409, không tạo lock người mua và không lộ tài khoản người bán.
  - Phương án khác: kiểm sau khi khoá listing (giữ thứ tự lock hiện có của resale). Loại: khoá đơn trước sân và cơ sở, ngược thứ tự toàn cục.
- Deadlock (module 4 giao lại): PostgreSQL vẫn có thể huỷ một transaction bằng SQLSTATE `40P01` khi lệnh khác giữ lock ngoài thứ tự. Đường đặt, mua lại slot và kiểm eligibility trả 409 kèm thông báo thử lại tiếng Việt thay cho 500. Transaction đã rollback nên không còn đơn, hold, attempt hay lock. Bắt ở một chỗ dùng chung, không lặp ở từng service.
  - Phương án khác: tự chạy lại transaction. Loại: chạy lại có thể tạo hiệu ứng trùng ở đường thanh toán; người dùng thử lại thì qua kiểm idempotent sẵn có.

## Risks / Trade-offs

- **[Ánh xạ trạng thái sai trên prod]** → Đếm trước theo từng giá trị cũ (read-only). Diễn tập trên bản restore dump mới. Test migration phủ đủ 9 giá trị cũ, kể cả `suspended` có và không có audit.
- **[Bỏ snapshot làm lộ sửa chưa duyệt]** Cơ sở `published` có cột đang lưu khác snapshot sẽ hiện bản chưa duyệt. → Pre-check liệt kê các cơ sở lệch; chủ hoặc user xem trước khi deploy. Report không còn duyệt nên đây là hành vi đúng sau cutover.
- **[URL slug sân cũ gãy]** → Không làm redirect. Đổi mọi link frontend sang id trong cùng lần push. Ghi vào ghi chú phát hành.
- **[Ảnh cũ không qua upload]** → Preset `/courts/*.jpg` được chấp nhận (D11). Pre-check đếm link ngoài; migration bỏ chúng khỏi mảng, backup giữ bản gốc.
- **[`lower()` phụ thuộc collation]** Mã có dấu như "SÂN A" và "sân a" có thể không trùng dưới collation C. → Test với chữ có dấu trên PostgreSQL thật; nếu DB dùng collation C thì dùng `lower(... COLLATE "und-x-icu")`.
- **[Mã sân trùng hoặc dài hơn 50]** → Pre-check; dedupe và cắt trong migration trước khi tạo unique index.
- **[Đơn tương lai trên đối tượng bị pause hoặc đóng khi migrate]** Không có case thì không dùng được lệnh D8. → Migration Pha 5 backfill case cho mọi stop đang mở và đình chỉ đang hiệu lực, idempotent nhờ unique của case.
- **[Không có Redis]** → Đúng sai không dựa vào broadcast. Màn hình poll; trạng thái đọc từ DB.
- **[Lệnh mỗi phút chạy trễ hoặc không chạy]** → Chặn bằng predicate (D6). Chỉ nhãn trạng thái trễ.
- **[Lệch frontend/backend sau push]** Push `main` deploy frontend ngay; backend chờ pipeline tay khoảng 7 phút. → Chạy `glab ci run -b main` ngay sau push, vào giờ đã xác nhận, không trong lúc demo.
- **[Hai pipeline `main` chạy cùng lúc]** → Kiểm không có pipeline nào đang chạy trước khi bấm.
- **[Phụ thuộc module 3]** → Q1: cổng độ phủ làm sau Pha 2 của module 3; change không archive trước đó. Kiểm module 3 đã bỏ delta và task `OpenCourt` trùng (task 6.10).
- **[Sân `active` đã migrate có giá hở bị khoá đặt]** Khi eligibility kiểm độ phủ kín, sân `active` có khoảng chưa giá sẽ không nhận đặt và ẩn khỏi public. → Pre-check đếm sân `active` hở giá trước khi bật cổng; chủ bổ sung giá hoặc sân chờ tới khi đủ.
- **[Chồng lấn module 4 ngoài "Final bookings"]** `releaseHeld`, `release_reason` và nhãn huỷ, form chính sách (`hold_ttl_minutes`, `require_continuity`). → Task 4.10, 2.16; kiểm lại khi task 5.5 của module 4 merge.
- **[Delta ngoài module 2 trùng header với change khác]** `api-alignment`, `platform-readiness`, `wallet-payment` dựng từ text cutover; module 4 có thể sửa cùng header khi mở lại khách huỷ đơn chờ thanh toán. → Change archive sau dựng khối MODIFIED từ spec chính change trước để lại (task 6.10).
- **[Chồng lấn module 4 trên "Final bookings and released availability"]** → Change archive sau rebase khối MODIFIED. Kiểm trùng header `on-site-attendance` trước khi archive.
- **[Tranh chấp lock giữa stop sự cố và đường đặt]** → Thứ tự lock toàn cục; transaction ngắn; `lock_timeout` trên SQL tay.
- **[File vượt giới hạn kiến trúc]** File ≤300 dòng, controller ≤7 method public, component ≤400 dòng. → Tách controller theo nhóm lệnh (mở/pause/resume/close, đơn bị ảnh hưởng, đình chỉ, khắc phục).
- **[`name → code` lan rộng ở frontend]** → Grep mọi chỗ đọc `court.name` và `court.slug`; vitest cho nhãn "Mã sân" và route id.
- **[Test dựa trên giá trị trạng thái cũ]** `DraftVenueCourtSetupTest`, `FinalMutationEligibilityAndCacheTest`, `InflightQuoteEligibilityTest`, `NewRepositoriesAndLocksIntegrationTest`, `VenueLifecycleAndPublicationPersistenceTest`, `VenueLifecycleTransitionsTest`, `VenuePublicSnapshotAndOperationalClosureTest`, `VenuePublicationReviewStatusGuardTest`, `VenuePublicationReviewTest`. → Viết lại theo 4 trạng thái hoặc xoá cùng tính năng duyệt.

## Migration Plan

1. **Backup prod trước.**
   - `pg_dump` DB `booking` vào `backend/storage/app/backups/`. Không in mật khẩu.
2. **Pre-check read-only** (`PGOPTIONS='-c default_transaction_read_only=on'`, `PGTZ=Asia/Ho_Chi_Minh`):
   - Số cơ sở theo từng giá trị `status`.
   - Cơ sở `suspended` cùng dòng audit `venue.lifecycle.transitioned` có `new_status='suspended'` gần nhất.
   - Sân có tên (sau trim) dài hơn 50; tên trùng trong cùng cơ sở theo `lower(btrim(...))`.
   - Ảnh không phải URL disk ảnh và không phải preset `/courts/`.
   - Cơ sở `published` có cột đang lưu khác `approved_public_snapshot`.
   - `court_blocks` còn `ends_at` trong tương lai; đơn tương lai `held`, `confirmed` hoặc đang sử dụng trên cơ sở hoặc sân không `active`.
   - Sân `active` có lịch phục vụ chưa phủ giá kín (sau Pha 2 của module 3).
3. **Diễn tập local.**
   - Restore dump mới vào DB local. `artisan migrate --pretend` để ra SQL. Chạy SQL đó trên bản restore.
   - Restore lần hai, chạy `migrate` thật. Diff `pg_dump -s` và dữ liệu giữa hai bản.
4. **Expand** (SQL tay, một transaction, `lock_timeout`, dòng `migrations` batch max+1):
   - CHECK `venues.status` thành hợp của 9 giá trị cũ và 4 giá trị mới.
   - Thêm `courts.status` (CHECK 4 giá trị, mặc định `inactive`), `courts.code`, `courts.code_normalized` (generated), `courts.images`.
   - Tạo `venue_suspensions`, `operational_stops`.
   - Đình chỉ, theo D4: với mỗi cơ sở `suspended`, tạo một dòng `venue_suspensions` với `reason = status_reason`, `suspended_by` = user của dòng audit, `suspended_at` = thời điểm audit. Không có audit thì `suspended_at = updated_at`, `suspended_by` null. Lý do cũ ngắn hơn 10 ký tự được giữ; giới hạn chỉ áp cho request.
   - Trạng thái: map theo D4. `courts.status` từ `is_active`.
   - Stop cho đối tượng đã dừng, để resume và close đi một đường:
     - cơ sở `paused` (từ `temporarily_closed` hoặc `suspended`) → một stop `business` đang mở;
     - cơ sở `closed` → một stop `closure`;
     - sân `paused` → một stop `business` đang mở; chỉ owner resume được.
     - `starts_at` = thời điểm migrate, `reason` = `status_reason` hoặc "Chuyển đổi dữ liệu", `created_by` null.
   - `court_blocks` → `operational_stops` với `kind = maintenance`, `expected_end_at = ended_at = ends_at`, `reason` rỗng thì "Bảo trì".
   - Mã sân: `code = btrim(name->>'vi')`, thiếu thì `en`. Trùng trong cơ sở theo `code_normalized`: giữ dòng id nhỏ nhất, các dòng sau thêm " (2)", " (3)" theo thứ tự id, cắt để tổng ≤50. Rồi tạo unique `(venue_id, code_normalized)`.
   - Ảnh: `courts.images = [image_url]` khi có. Bỏ link ngoài khỏi `courts.images` và `venues.images`.
   - Backend cũ chạy được trên schema này và fail closed: cơ sở không phải `active` không nhận đặt.
5. **Deploy**, vào giờ user đã xác nhận, không trong lúc demo:
   - Push `main`; frontend tự deploy qua Cloudflare Workers Builds.
   - Chạy ngay `glab ci run -b main`. Không chạy hai pipeline `main` cùng lúc.
   - Kiểm `build-info.json` của backend và frontend khớp commit.
   - Smoke test: mở cơ sở và sân, pause có hẹn giờ, xem trước đơn bị ảnh hưởng, trang giám sát admin.
6. **Contract** (transaction riêng, sau khi smoke test đạt):
   - Thu hẹp CHECK `venues.status` về 4 giá trị.
   - Xoá `courts.is_active`, `courts.image_url`, `courts.name`, `courts.slug`, `court_blocks`, `venues.approved_public_snapshot`, `venues.current_publication_revision_id`, `venue_publication_revisions`, `venues.status_reason`.
7. **Pha 5 và 6** chỉ thêm bảng và cột (`affected_reservation_cases`, `suspension_remediations`), deploy theo cùng quy trình bước 1, 4, 5. Migration Pha 5 backfill case `pending` cho mọi stop đang mở và đình chỉ đang hiệu lực theo predicate BR-38.
8. **Reseed** là phương án thay thế user có thể chọn (`bt-server-demo-reseed`): dump một DB đã migrate và seed mới rồi restore, theo cutover Decision 13. Khi đó bỏ bước 4 và 6 trên prod.

**Rollback:**
- Trước contract: revert merge, deploy lại image cũ qua pipeline, chạy down migration. Map ngược có mất mát: `inactive → draft`, `active → published`, `paused → temporarily_closed`, `closed → closed`; cơ sở có đình chỉ đang hiệu lực → `suspended`. `courts.is_active` vẫn còn nên sân không mất dữ liệu.
- Sau contract: restore backup bước 1. Mọi thay đổi sau thời điểm backup mất.
- Bảng thêm mới ở Pha 5 và 6 để nguyên khi rollback code; code cũ không đọc chúng.

## Open Questions

- **Huỷ một pause đã hẹn trước giờ bắt đầu.** Spec chỉ cho resume đối tượng đang `paused`, nên pause hẹn trước không huỷ được; chủ chờ tới giờ rồi resume. Có thể bổ sung sau: cho resume ghi `ended_at = now()` trước `starts_at`, khi đó khung chặn rỗng. Không chặn change này.
