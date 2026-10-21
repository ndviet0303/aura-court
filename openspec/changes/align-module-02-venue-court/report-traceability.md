# Truy vết report Module 02

## Nguồn và cách đọc

- Nguồn: `.claude/plans/module-02-report.md`, bản markitdown của `sub-documents.pdf`. Gồm FR-25..FR-52 và BR-25..BR-48, tổng 52 mã.
- Bằng chứng hiện trạng: `.claude/plans/module-02-gap-audit.md`, đối chiếu code tại `311790f3`. Bảng trạng thái lấy theo mục 1 của `.claude/plans/module-02-report-alignment.plan.md`.
- Trạng thái là kết quả đọc code, không phải kết quả chạy test.
  - **khớp**: code đã làm đúng report.
  - **khớp một phần**: có làm, còn thiếu hoặc sai một phần.
  - **thiếu**: chưa có.
  - **ngược**: code làm trái report.
  - **lệch có chủ đích**: code theo quyết định cũ (report bản trước hoặc `direct-owner-cutover`); report này thay quyết định đó.
- Cột "Pha 0" ghi mục sửa trong commit `53c880df` (merge `9dddc773`), nếu có:
  - 0.1: chặn đọc chéo tổ chức, `OrganizationController::show` theo vai trò, kênh `venue.{id}` từ chối tài khoản bị hạn chế.
  - 0.2: kiểm lại eligibility dưới lock ở hold, checkout và `MatchService::createPost`.
  - 0.3: guard tạm chặn tự gỡ đình chỉ và mở lại cơ sở đã đóng.
  - 0.4: tạo được sân trên cơ sở chưa đóng.
  - 0.5: bỏ giá giả `120000` trong `PricingCalendarGrid`.
  - 0.6: API công khai không trả dòng organization. Mục này không gắn với FR/BR nào. Nó thuộc requirement "Catalog and publication contract completeness".
- Cột "Task" trỏ tới `tasks.md`. Cột "Test dự kiến" là test sẽ viết hoặc viết lại. Mọi dòng đang ở trạng thái **chưa nghiệm thu** cho tới task 6.9.
- Tên capability viết tắt trong bảng:
  - `vco`: `venue-court-operations`.
  - `rp`: `reservations-and-pricing`.
  - `oa`: `organization-access`.
  - `osa`: `on-site-attendance`.
  - `dop`: `direct-owner-payments`.
  - `aa`: `api-alignment`.
  - `wp`: `wallet-payment`.
  - `ru`: `realtime-updates`.
  - `i18n`: `localization/database-i18n`.

## Functional requirements (FR-25..FR-52)

| ID | Report yêu cầu | Trạng thái tại `311790f3` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| FR-25 | Xem danh sách và chi tiết cơ sở. Owner xem cơ sở của tổ chức; staff chỉ xem cơ sở được phân công. | khớp một phần | 0.1 | `vco`: Read access to venues and courts; `oa`: Organization-scoped venue and court reads; `oa`: Role-scoped organization detail | 2.1, 2.12, 2.13 | `PartnerVenueReadAccessTest`; vitest trang chi tiết cơ sở |
| FR-26 | Thêm cơ sở với tên, địa chỉ, SĐT, email, mô tả, ảnh, giờ, tiện ích. Chỉ rõ trường sai. Gắn với tổ chức, chưa tự mở. | khớp một phần | – | `vco`: Single organization-scoped venue write path; `vco`: Venue information validation (thêm giới hạn tiện ích 20 mục, 1–50 ký tự, không có trong report, design D12); `vco`: Venue and court images; `vco`: Venue operating hours and court service schedules | 2.2, 2.3, 2.5, 2.6, 2.7, 2.11, 2.14 | `SingleVenueWritePathTest`; `VenueInformationValidationTest`; `VenueCourtImageUploadTest`; vitest modal cơ sở |
| FR-27 | Sửa thông tin cơ sở. Dữ liệu sai thì giữ nguyên bản đã lưu và chỉ rõ trường cần sửa. | khớp một phần | – | `vco`: Venue information validation; `vco`: Venue and court images; `vco`: Catalog and operational calendar; `vco`: Catalog and publication contract completeness | 1.13, 2.2, 2.5, 2.7, 2.14, 2.15 | `VenueInformationValidationTest`; `VenueProfilePersistenceTest`; `VenuePublicSnapshotAndOperationalClosureTest` |
| FR-28 | Đặt giờ hoạt động theo ngày và ngày đóng cửa. Đổi giờ thì kiểm đơn bị ảnh hưởng để xử lý. | lệch có chủ đích | – | `vco`: Venue operating hours and court service schedules; `vco`: Affected bookings from operational changes; `vco`: Catalog and operational calendar | 2.11, 4.1, 4.3, 4.4 | `CourtScheduleWithinVenueHoursTest`; viết lại `VenueOperatingCalendarTest:306-333`; `AffectedReservationsListTest` |
| FR-29 | Xem danh sách và chi tiết sân: mã, môn, mô tả, ảnh, lịch phục vụ, tình trạng. Phạm vi theo tổ chức hoặc cơ sở được phân công. | khớp một phần | 0.1 | `vco`: Read access to venues and courts; `oa`: Organization-scoped venue and court reads | 2.1, 2.12, 2.13 | mở rộng `PartnerVenueReadAccessTest`; vitest trang chi tiết sân |
| FR-30 | Thêm sân với mã không trùng trong cơ sở, môn, mô tả, ảnh. Sân có ID riêng, chưa tự mở nhận đặt. | ngược | 0.4 | `vco`: Courts carry their own sport and booking interval; `vco`: Court code identity; `vco`: Venue and court images; `vco`: Venue lifecycle and captured policy continuity | 1.16, 2.7, 2.8, 2.9 | viết lại `DraftVenueCourtSetupTest`; `CourtCodeIdentityTest`; `VenueCourtImageUploadTest` |
| FR-31 | Sửa mã, mô tả, ảnh sân. Mã không trùng. Đổi mã thì mọi đơn hiện mã mới, giữ ID, liên kết, giờ, tiền, trạng thái. | khớp một phần | – | `vco`: Court code identity; `vco`: Courts carry their own sport and booking interval; `vco`: Venue and court images | 2.6, 2.7, 2.9, 2.10 | `CourtCodeIdentityTest`; `VenueCourtCrudAndPreservationTest`; `VenueCourtImageUploadTest` |
| FR-32 | Đặt lịch phục vụ sân, nằm trong giờ cơ sở. Đổi lịch thì kiểm đơn bị ảnh hưởng. | khớp một phần | – | `vco`: Venue operating hours and court service schedules; `vco`: Affected bookings from operational changes | 2.11, 4.3, 4.4 | `CourtScheduleWithinVenueHoursTest`; viết lại `CalendarClosureAndUnifiedAvailabilityTest:68-150` |
| FR-33 | Chọn bảng giá đã cấu hình cho sân, hiện giá theo lịch, lưu liên kết, kiểm độ phủ. Đổi giá không đổi đơn đã xác nhận. | khớp một phần | 0.5 | `vco`: Court price table link (chỉ phía sân); `vco`: Court opening conditions; `vco`: Unified public and new-booking eligibility | 1.1, 3.1, 3.2, 3.3, 3.4, 3.5 | `CourtPriceTableLinkTest`; `CourtOpeningConditionsTest`; `PricingCalendarGrid.spec.ts` |
| FR-34 | Mở cơ sở khi đủ thông tin bắt buộc, có giờ hoạt động, không bị đình chỉ. Thiếu thì chỉ rõ. Không tự mở sân. | lệch có chủ đích | – | `vco`: Venue opening conditions; `vco` REMOVED: Report-aligned draft venue readiness, Independent publication review; `dop`: Verified owner collection connection | 1.5, 1.11, 1.13, 1.18, 2.4 | `VenueOpeningConditionsTest` |
| FR-35 | Mở từng sân khi đủ thông tin, có ít nhất một khoảng phục vụ, giá phủ kín, cơ sở hoạt động và không đình chỉ. Không dùng để khôi phục. | ngược | – | `vco`: Court opening conditions | 1.6, 1.18, 3.3 | `CourtOpeningConditionsTest` |
| FR-36 | Tạm ngừng sân do bảo trì hoặc sự cố, ngay hoặc theo lịch. Chặn đặt ngay, đổi trạng thái khi tới giờ. Không tự huỷ đơn, không tự mở lại. | khớp một phần | – | `vco`: Operational stops for venues and courts; `vco`: Stop and resume authority; `vco`: Affected bookings from operational changes; `osa`: On-site changes and incident handling; `oa`: Role-based owner operations; `ru`: Broadcast court status updates | 1.7, 1.9, 1.10, 1.12, 1.19, 4.1, 4.3, 4.4, 4.12 | `OperationalStopTest`; `LifecycleRoleSplitTest`; `AffectedReservationsListTest` |
| FR-37 | Xem đơn chưa dùng hoặc đang dùng bị ảnh hưởng: mã đơn, khách, sân, giờ, thanh toán. Bỏ đơn đã huỷ hoặc hoàn tất. | thiếu | – | `vco`: Affected bookings from operational changes | 4.1, 4.2, 4.5, 4.14 | `AffectedReservationsListTest` |
| FR-38 | Đổi sân, đổi giờ hoặc huỷ đơn theo phương án đã thống nhất với khách. Kiểm lại lịch trống. Chưa liên hệ được khách thì không tự xử lý. | lệch có chủ đích | – | `rp`: Operator handling of affected bookings; `rp`: Final bookings and released availability; `rp`: Unversioned reservation API; `rp`: Trigger real-time status hooks; `vco`: Affected bookings from operational changes; `osa`: On-site changes and incident handling; `aa`: Policy-aware changes and truthful finance interfaces | 4.6, 4.7, 4.8, 4.9, 4.10, 4.12, 4.14, 4.16, 4.17 | `AffectedBookingCommandsTest`; vitest trang đơn owner/staff |
| FR-39 | Khôi phục sân sau bảo trì khi cơ sở hoạt động, không đình chỉ, sân đủ lịch và giá. Kết thúc tại lúc xác nhận. Không phục hồi đơn. | khớp một phần | – | `vco`: Resuming paused venues and courts; `vco`: Stop and resume authority; `vco`: Court opening conditions | 1.8, 1.9, 3.4, 4.13 | `CourtOpeningConditionsTest`; `LifecycleRoleSplitTest`; `AffectedBookingCommandsTest` |
| FR-40 | Tạm ngừng kinh doanh cơ sở hoặc sân. Chặn đặt ngay, đổi trạng thái khi hiệu lực. Cơ sở dừng thì sân giữ trạng thái riêng. | khớp một phần | – | `vco`: Operational stops for venues and courts; `vco`: Stop and resume authority; `vco`: Affected bookings from operational changes | 1.7, 1.9, 1.10, 1.12, 1.18, 4.1, 4.3, 4.4 | `OperationalStopTest`; `LifecycleRoleSplitTest`; `AffectedReservationsListTest` |
| FR-41 | Ngừng kinh doanh cơ sở hoặc sân, kiểm đơn bị ảnh hưởng. Giữ dữ liệu. Không mở lại bằng khôi phục. | ngược | 0.3 | `vco`: Operational stops for venues and courts; `vco`: Court lifecycle preservation; `vco`: Venue lifecycle and captured policy continuity; `vco`: Affected bookings from operational changes | 1.4, 1.7, 1.15, 4.3, 4.4 | `ClosedIsFinalTest`; `OperationalStopTest`; `VenueCourtCrudAndPreservationTest` |
| FR-42 | Chỉ owner khôi phục sân tạm ngừng vì kinh doanh, sau khi kiểm cơ sở và lịch, giá của sân. | khớp một phần | – | `vco`: Resuming paused venues and courts; `vco`: Stop and resume authority; `oa`: Role-based owner operations | 1.8, 1.9, 3.4 | `LifecycleRoleSplitTest`; `CourtOpeningConditionsTest` |
| FR-43 | Khôi phục cơ sở tạm ngừng sau khi kiểm thông tin, giờ và đình chỉ. Giữ trạng thái riêng của sân. | ngược | – | `vco`: Resuming paused venues and courts; `vco`: Unified public and new-booking eligibility | 1.8, 2.4, 3.5 | `VenueOpeningConditionsTest`; `CourtOpeningConditionsTest`; `UnifiedBookingEligibilityTest` |
| FR-44 | Admin giám sát cơ sở toàn nền tảng: tổ chức, chủ, sân, lịch, trạng thái, đình chỉ. Không sửa thay chủ. | khớp một phần | – | `vco`: Platform venue monitoring; `vco`: Venue lifecycle and captured policy continuity | 1.13, 1.14, 5.9, 5.10 | `PlatformVenueMonitoringTest` |
| FR-45 | Admin đình chỉ cơ sở với lý do, hiệu lực ngay. Chặn đặt mới mọi sân, giữ trạng thái sân, không huỷ đơn. Lưu ai, lúc nào, lý do. | khớp một phần | 0.2 | `vco`: Venue suspension and remediation; `vco`: Unified public and new-booking eligibility | 1.10, 1.11a, 5.1, 5.10 | `VenueSuspensionTest`; `InflightQuoteEligibilityTest`; `AdvancedBookingMarketplaceTest` (mua lại slot) |
| FR-46 | Xem quyết định và lịch sử đình chỉ. Owner xem theo tổ chức; admin xem theo quyền giám sát. | khớp một phần | – | `vco`: Venue suspension and remediation; `vco`: Platform venue monitoring | 5.3, 5.9, 5.11 | `VenueSuspensionTest`; `PlatformVenueMonitoringTest` |
| FR-47 | Owner gửi kết quả khắc phục, có ít nhất một tệp. Yêu cầu sang "Chờ xem xét". Không sửa hay gửi thêm khi đang chờ. Vẫn đình chỉ. | thiếu | – | `vco`: Venue suspension and remediation | 5.4, 5.5, 5.11 | `SuspensionRemediationTest` |
| FR-48 | Owner theo dõi yêu cầu khắc phục: nội dung, minh chứng, trạng thái, phản hồi, lịch sử. | thiếu | – | `vco`: Venue suspension and remediation | 5.3, 5.5, 5.11 | `SuspensionRemediationTest`; `VenueSuspensionTest` |
| FR-49 | Bổ sung và gửi lại khi yêu cầu ở "Yêu cầu bổ sung" hoặc "Từ chối". Giữ các lần gửi trước. | thiếu | – | `vco`: Venue suspension and remediation | 5.5, 5.11 | `SuspensionRemediationTest` |
| FR-50 | Admin xem danh sách và chi tiết yêu cầu khắc phục cùng quyết định, minh chứng, lịch sử. | thiếu | – | `vco`: Venue suspension and remediation | 5.6, 5.10 | `SuspensionRemediationTest` |
| FR-51 | Admin yêu cầu bổ sung, từ chối hoặc chấp thuận. Phản hồi bắt buộc. Chấp thuận gỡ đình chỉ, không tự mở lại cơ sở hay sân. | thiếu | – | `vco`: Venue suspension and remediation | 5.7, 5.10 | `SuspensionRemediationTest`; `VenueSuspensionTest` |
| FR-52 | Thông báo cho owner khi bị đình chỉ và khi có kết quả duyệt; cho admin khi owner gửi. Mở được nội dung từ thông báo. | thiếu | – | `vco`: Venue suspension and remediation | 5.8 | `SuspensionRemediationTest` |

## Business rules (BR-25..BR-48)

| ID | Report yêu cầu | Trạng thái tại `311790f3` | Pha 0 | Requirement | Task | Test dự kiến |
| --- | --- | --- | --- | --- | --- | --- |
| BR-25 | Owner chỉ quản lý cơ sở của tổ chức. Staff chỉ xem và vận hành cơ sở được phân công. Admin giám sát, đình chỉ, duyệt khắc phục, không sửa thay chủ. | khớp một phần | 0.1 | `oa`: Role-based owner operations; `oa`: Organization-scoped venue and court reads; `oa`: Venue realtime channel authorization; `vco`: Read access to venues and courts; `vco`: Stop and resume authority; `vco`: Platform venue monitoring | 1.9, 1.14, 2.1, 2.12, 5.9 | `PartnerVenueReadAccessTest`; `VenueChannelAuthorizationTest`; `LifecycleRoleSplitTest`; `PlatformVenueMonitoringTest` |
| BR-26 | Cơ sở bắt buộc tên, địa chỉ, SĐT, email. Giới hạn độ dài, định dạng SĐT và email. Trim trước khi kiểm. | khớp một phần | – | `vco`: Venue information validation (thêm giới hạn tiện ích không có trong report, design D12); `i18n`: Bilingual Data Validation | 2.2, 2.4, 2.17 | `VenueInformationValidationTest` (data-provider BR-26) |
| BR-27 | Sân thuộc một cơ sở, ID không đổi. Mã sân là tên hiển thị, 1–50 ký tự, không trùng trong cơ sở (trim, không phân biệt hoa thường). Mô tả tối đa 2000. | khớp một phần | – | `vco`: Court code identity; `i18n`: Bilingual Data Validation | 2.8, 2.9, 2.17 | `CourtCodeIdentityTest` (data-provider BR-27); test migration mã sân |
| BR-28 | Sửa sân chỉ đổi mã, mô tả, ảnh. Đổi mã hiện trên mọi đơn. Không chuyển cơ sở, không đổi môn. | lệch có chủ đích | – | `vco`: Courts carry their own sport and booking interval; `vco`: Court code identity | 2.9, 2.10 | `VenueCourtCrudAndPreservationTest`; `CourtCodeIdentityTest` |
| BR-29 | Tối đa 10 ảnh mỗi cơ sở hoặc sân, JPG/JPEG/PNG, tối đa 5 MB. Tệp sai không được lưu. | ngược | – | `vco`: Venue and court images | 2.6, 2.7 | `VenueCourtImageUploadTest` |
| BR-30 | Cơ sở và sân mới ở "Chưa hoạt động". Bốn trạng thái. Đình chỉ quản lý riêng ở cấp cơ sở, không ghi đè trạng thái, không có đình chỉ sân. | khớp một phần | 0.3 | `vco`: Venue lifecycle and captured policy continuity; `vco`: Venue suspension and remediation | 1.2, 1.3, 1.4, 1.16, 1.18 | `VenueCourtStatusMigrationTest`; `ClosedIsFinalTest`; `DraftVenueCourtSetupTest` |
| BR-31 | Lịch sân nằm trọn trong giờ cơ sở. Các khoảng không chồng nhau; chạm nhau thì được. | ngược | – | `vco`: Venue operating hours and court service schedules | 2.11 | `CourtScheduleWithinVenueHoursTest` |
| BR-32 | Sân chỉ liên kết bảng giá hợp lệ. Giá lấy từ module giá, không nhập tại màn sân. Giá phủ kín thời gian mở nhận đặt. Đổi giá không tính lại đơn đã xác nhận. | ngược | 0.5 | `vco`: Court price table link (chỉ phía sân); `vco`: Court opening conditions; `vco`: Resuming paused venues and courts; `vco`: Unified public and new-booking eligibility | 1.1, 3.1, 3.2, 3.3, 3.4, 3.5 | `CourtPriceTableLinkTest`; `CourtOpeningConditionsTest` |
| BR-33 | Điều kiện mở cơ sở và mở sân. Mở cơ sở không tự mở sân. | ngược | – | `vco`: Venue opening conditions; `vco`: Court opening conditions; `vco`: Unified public and new-booking eligibility; `dop`: Verified owner collection connection | 1.5, 1.6, 1.11, 2.4, 3.3 | `VenueOpeningConditionsTest`; `CourtOpeningConditionsTest` |
| BR-34 | Owner và staff tạm ngừng, khôi phục sân vì bảo trì trong phạm vi. Chỉ owner tạm ngừng kinh doanh, khôi phục sau đó và ngừng kinh doanh. | khớp một phần | – | `vco`: Stop and resume authority; `oa`: Role-based owner operations | 1.9, 1.18 | `LifecycleRoleSplitTest` |
| BR-35 | Tạm ngừng có lý do 10–1000 ký tự và thời điểm bắt đầu không ở quá khứ. Kết thúc dự kiến tuỳ chọn, sau bắt đầu, chỉ để theo dõi. | ngược | – | `vco`: Operational stops for venues and courts | 1.7 | `OperationalStopTest` |
| BR-36 | Chặn ngay đặt mới giao khoảng từ lúc bắt đầu ngừng. Đơn kết thúc đúng lúc bắt đầu không giao. Trạng thái giữ nguyên tới khi hiệu lực. | khớp một phần | 0.2 | `vco`: Operational stops for venues and courts; `vco`: Unified public and new-booking eligibility; `ru`: Broadcast court status updates | 1.10, 1.11a, 1.12, 1.19 | `OperationalStopTest`; `InflightQuoteEligibilityTest`; `AdvancedBookingMarketplaceTest` (mua lại slot) |
| BR-37 | Ngừng sân chỉ ảnh hưởng sân đó. Ngừng cơ sở chặn mọi sân nhưng không ghi đè trạng thái sân. Không xoá dữ liệu. | khớp một phần | – | `vco`: Operational stops for venues and courts; `vco`: Court lifecycle preservation | 1.7, 1.10, 1.15 | `OperationalStopTest`; `VenueCourtCrudAndPreservationTest` |
| BR-38 | Định nghĩa đơn cần xử lý và phép giao khoảng. Không dùng kết thúc dự kiến làm mốc chặn. | khớp một phần | – | `vco`: Affected bookings from operational changes; `vco`: Operational stops for venues and courts | 4.1, 4.3 | `AffectedReservationsListTest` |
| BR-39 | Không tự huỷ đơn. Sự cố được ngừng ngay dù còn đơn. Thống nhất với khách trước khi đổi, kiểm lịch trống. Chưa liên hệ được thì theo dõi, không đánh vắng mặt. | lệch có chủ đích | – | `vco`: Affected bookings from operational changes; `rp`: Operator handling of affected bookings; `osa`: On-site changes and incident handling; `osa`: Booking attendance lifecycle | 4.4, 4.6, 4.7, 4.11, 4.12 | `AffectedBookingCommandsTest`; `AffectedReservationsListTest`; `OperationalStopTest` |
| BR-40 | Chỉ owner hoàn tiền, ngoài Aura Court. Staff không hoàn tiền. Huỷ hay cập nhật đơn không chuyển tiền và không là bằng chứng đã hoàn. | khớp | – | `rp`: Operator handling of affected bookings; `rp`: Final bookings and released availability; `vco`: Affected bookings from operational changes; `oa`: Role-based owner operations; `aa`: Policy-aware changes and truthful finance interfaces; `wp`: Legacy wallet conservation during booking changes | 4.6, 4.10, 4.15, 4.17, 4.18 | `AffectedBookingCommandsTest`; `AffectedReservationsListTest`; `RetiredRefundAndRescheduleRoutesTest`; vitest trang đơn owner/staff |
| BR-41 | Khôi phục có hiệu lực lúc xác nhận. Điều kiện khôi phục sân. Khôi phục cơ sở giữ trạng thái sân. Không phục hồi đơn. Không áp cho đối tượng đã ngừng kinh doanh. | ngược | 0.3 | `vco`: Resuming paused venues and courts; `vco`: Venue lifecycle and captured policy continuity; `vco`: Unified public and new-booking eligibility | 1.4, 1.8, 3.4, 3.5, 4.13 | `ClosedIsFinalTest`; `CourtOpeningConditionsTest`; `VenueOpeningConditionsTest`; `AffectedBookingCommandsTest` |
| BR-42 | Chỉ admin đình chỉ, lý do 10–1000 ký tự, hiệu lực ngay, chặn mọi sân. Owner và staff không tự gỡ. Owner vẫn truy cập để khắc phục và xử lý đơn. | ngược | 0.2, 0.3 | `vco`: Venue suspension and remediation; `vco`: Unified public and new-booking eligibility; `vco`: Venue lifecycle and captured policy continuity | 1.10, 5.1, 5.2 | `VenueSuspensionTest`; `InflightQuoteEligibilityTest` |
| BR-43 | Chỉ chủ sở hữu gửi khắc phục, có nội dung và ít nhất một tệp. Tối đa một yêu cầu đang chờ. Không sửa. Gửi không tự gỡ đình chỉ. | thiếu | – | `vco`: Venue suspension and remediation | 5.5 | `SuspensionRemediationTest` |
| BR-44 | Nội dung 10–5000 ký tự. 1–10 tệp PDF/JPG/JPEG/PNG, tối đa 10 MB mỗi tệp. | thiếu | – | `vco`: Venue suspension and remediation | 5.4, 5.5 | `SuspensionRemediationTest` (biên BR-44) |
| BR-45 | Bốn trạng thái của yêu cầu. Chỉ gửi lại khi "Yêu cầu bổ sung" hoặc "Từ chối". Giữ nội dung và phản hồi các lần trước. | thiếu | – | `vco`: Venue suspension and remediation | 5.5, 5.7 | `SuspensionRemediationTest` (ma trận BR-45) |
| BR-46 | Chỉ admin duyệt yêu cầu đang chờ. Phản hồi bắt buộc. Chấp thuận gỡ đình chỉ. Không ghi đè kết quả đã xử lý. | thiếu | – | `vco`: Venue suspension and remediation | 5.7 | `SuspensionRemediationTest` |
| BR-47 | Gỡ đình chỉ không đổi trạng thái. Cơ sở tạm ngừng hoặc đã ngừng vẫn không nhận đặt. Cơ sở hoạt động thì chỉ sân hoạt động, đủ lịch và giá nhận đặt. | ngược | – | `vco`: Venue suspension and remediation; `vco`: Venue lifecycle and captured policy continuity; `vco`: Unified public and new-booking eligibility | 1.10, 3.4, 3.5, 5.7 | `SuspensionRemediationTest`; `VenueSuspensionTest`; `UnifiedBookingEligibilityTest` |
| BR-48 | Lưu quyết định và kết quả duyệt với đối tượng, nội dung, thời điểm, người. Thông báo đúng người nhận. Giữ lịch sử để tra cứu. | khớp một phần | – | `vco`: Venue suspension and remediation | 5.1, 5.5, 5.7, 5.8 | `SuspensionRemediationTest`; `VenueSuspensionTest` |

## Đồng bộ spec ngoài module

Các delta dưới đây không hiện thực FR/BR nào của report module 2. Chúng chỉ sửa requirement của capability khác đang mâu thuẫn với change này sau khi `direct-owner-cutover` archive (proposal mục "Đồng bộ spec ngoài module 2"). Delta `aa`, `wp`, `ru`, `i18n` có hiện thực FR/BR nên đã ghi ở các bảng trên.

| Capability | Requirement | Lý do sửa | Task | Test dự kiến |
| --- | --- | --- | --- | --- |
| `platform-readiness` | Persisted and authorized browser journeys | Bỏ nghiệm thu duyệt công khai (D1); kiểm lệnh D8 bị từ chối khi đơn không có case mở (D8) | 6.4a | Playwright `p1-venue-lifecycle.spec.ts` |
| `platform-readiness` | Truthful payment acceptance boundaries | Scenario "Direct booking refund" không còn cấm lệnh D8 trên đơn có case mở | 6.4a | Playwright |
| `advanced-booking-marketplace` | Long-running recurring reservations | Bỏ "scoped occurrence changes"; một buổi chỉ đổi sân, giờ qua lệnh D8 | 4.19 | `AffectedBookingCommandsTest`; `RecurringDirectOwnerSeriesTest` |
| `owner-hosted-drop-in-sessions` | Preserve existing booking and payment behavior | Bỏ hợp đồng "hoàn tiền theo chính sách"; không thêm huỷ, dời cho khách hay hoàn tiền | 4.20 | `OwnerHostedDropInSessionTest` |

Việc module 4 giao lại, ghi thành scenario trong `vco` "Unified public and new-booking eligibility":
- "Resale purchase after a stop or suspension" (task 1.11a), đã gắn vào FR-45 và BR-36.
- "Database deadlock while locking" (task 1.11b). Không thuộc FR/BR nào; test giả lập SQLSTATE `40P01`.

Không viết delta `financial-ledger-and-payouts`: ghi chú hoàn tiền của D8 không thoả điều kiện của scenario "Refund is processed", nên không mâu thuẫn.

## Phần thuộc module 3 (bảng giá)

Change này chỉ đặc tả phía sân của FR-33 và BR-32:
- requirement "Court price table link";
- cổng độ phủ trong "Court opening conditions", "Resuming paused venues and courts" và "Unified public and new-booking eligibility".

Các mục dưới đây thuộc change Pha 2 (bảng giá và độ phủ) của module 3, gồm Pha 4 cũ của module 2. Change này chỉ chờ rồi tích hợp, qua nhóm 3 của `tasks.md`.

- Report module 2 ghi bảng giá "cùng tổ chức". Report module 3 (BR-50) ghi bảng giá thuộc một cơ sở. Change này theo quy tắc chặt hơn: sân chỉ liên kết bảng giá cùng cơ sở (Q1 trong `design.md`).
- Bảng giá thuộc một cơ sở (`price_tables.venue_id`), liên kết sân–bảng giá (tên bảng/cột do design module 3 chốt), và migrate `PriceRule` hiện có vào bảng giá. Change này không chốt cách lưu liên kết.
- CRUD bảng giá, thời điểm hiệu lực, bảng giá ngày lễ, cách resolve giá.
- Bỏ `CourtPriceSlot`, route `/admin/courts/{id}/prices*`, `ManageCourtPrices`, `CourtPricingModal`, `useAdminPricingMatrix`.
- Viết lại `AvailabilityController`: sinh mốc giờ từ lịch phục vụ theo `slot_duration`, định giá bằng `priceSnapshots`. Sửa các consumer frontend đang đọc `price_slots`.
- Service, endpoint và mã lỗi 422 của độ phủ giá. Module 2 chỉ gọi service này ở `OpenCourt`, `ResumeCourt` và eligibility (task 3.3, 3.4, 3.5). Module 3 không viết delta `venue-court-operations` trùng ý (design Q1).
- Dừng một bảng giá không được để lại khoảng hở (BR-57 của module 3).
- Test do module 3 sở hữu:
  - sân chỉ có `PriceRule` vẫn có availability;
  - đổi giá không đổi đơn đã xác nhận, ở phía tính giá.
- Change này không archive trước khi task 3.3, 3.4 và 3.5 xong.
