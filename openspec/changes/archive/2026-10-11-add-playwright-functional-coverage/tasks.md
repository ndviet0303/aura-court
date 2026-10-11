## 1. Baseline và ma trận chức năng

- [x] 1.1 Đối chiếu 12 test hiện có với router, API caller, main specs và report-traceability; ghi kỳ vọng lỗi thời, phụ thuộc demo và khoảng trống UI, không kế thừa trạng thái passed lịch sử.
- [x] 1.2 Tạo `docs/testing/playwright-coverage.md` đủ 126 FR riêng biệt, đúng ID/tên; điền spec, UI/API, actor/scope, P0/P1/P2, test/step dự kiến và trạng thái planned/API-only/blocked có bằng chứng.
- [x] 1.3 Thêm các dòng supplementary cho waitlist, thông báo, friends/feed, service tabs, drop-in bills, ví lịch sử, chỉ đường, trang công khai/legal/404, i18n/theme/accessibility/realtime; liên kết D01–D71 liên quan mà không thay đổi báo cáo cũ.

## 2. Môi trường E2E cô lập

- [x] 2.1 Viết runner tối thiểu sở hữu run ID, database PostgreSQL riêng, test credentials, thư mục tạm và tiến trình frontend/backend; dùng session bền qua HTTP, namespace storage/cache riêng và không đọc cấu hình production.
- [x] 2.2 Thêm preflight kiểm tra môi trường, database thực tế/URL override/cached config, API origin/runtime config và server ownership trước reset/seed/mutation; thêm runnable checks chứng minh từ chối target sai mà không thay đổi dữ liệu.
- [x] 2.3 Thêm teardown theo manifest chỉ thu hồi tài nguyên của run; kiểm tra cả kết thúc bình thường và lỗi giữa chừng, không ảnh hưởng database/tiến trình bên ngoài.
- [x] 2.4 Tạo fixture không-demo cho hai tổ chức và hai cơ sở cùng tổ chức, đủ customer/owner/manager/cashier/staff/platform admin và tài khoản chưa xác thực/bị hạn chế; đáp ứng approval/subscription/publication/calendar/policy thật.
- [x] 2.5 Tạo dữ liệu riêng từng test cho sân/giá/coupon/points/stock; ngày tương lai theo Asia/Ho_Chi_Minh, manifest ID động và thiết lập expiry qua CLI test-only; kiểm tra test chạy độc lập không phụ thuộc thứ tự.
- [x] 2.6 Chuẩn bị receiver/secret giả lập, gửi sự kiện qua webhook và receipt processor thật; chặn external egress, cấu hình mail sink/OAuth adapter test-only, không mở endpoint reset/paid phục vụ production.
- [x] 2.7 Tái sử dụng phiên đăng nhập theo actor bằng fixture nhỏ; giữ auth state/manifest bí mật ngoài Git và artifacts; kiểm tra response demo-simulated không được tính persistence.

## 3. Baseline mới và P0 — truy cập, lịch sân

- [x] 3.1 Sửa/thay thế test owner redirect/quyền manage/venues lỗi thời theo main specs và router; bỏ credential demo, tên/ID/số dư cố định, chờ thời gian và assertion tùy chọn che thiếu UI.
- [x] 3.2 Viết browser journey login → return path → reload → logout, guest-only redirect và sai thông tin; kiểm tra customer chưa xác thực/bị hạn chế không thực hiện booking trái phép.
- [x] 3.3 Viết ma trận owner/manager/cashier/staff/customer/platform admin: truy cập đúng UI, platform denial, foreign tenant và sibling venue denial qua URL và request liên quan.
- [x] 3.4 Viết discovery/chi tiết/lịch trống/chọn slot, đổi sân/ngày và response đến muộn; chứng minh sân A kín không khóa B và lựa chọn cũ không bị giữ sai.

## 4. P0 — đặt sân, thanh toán và thay đổi

- [x] 4.1 Viết hành trình đặt sân từ UI → hold → đúng QR/receiver/amount → webhook test hợp lệ → booking confirmed; reload và đọc authoritative state chứng minh đúng một booking/payment allocation.
- [x] 4.2 Kiểm tra callback thành công chỉ từ browser, event sai receiver/amount/reference/authentication và event trùng; không xác nhận giả hoặc cấp quyền/trừ tiền/điểm hai lần.
- [x] 4.3 Dùng hai browser context/customer tranh cùng court-time, xác minh chỉ một hold thành công và UI khách thua hiển thị conflict đúng; giữ high-load race proof trong backend suite.
- [x] 4.4 Kiểm tra expiry qua cơ chế thật giải phóng slot, sự kiện tiền đến muộn không hồi sinh booking và giữ exception/refund obligation theo contract.
- [x] 4.5 Kiểm tra coupon/member/loyalty preview không ghi tiêu điểm, số tiền cuối và snapshot sau reload; stale/invalid points/coupon, thay policy và checkout thất bại không gây double consumption; kiểm tra control ở các entry point dùng chung.
- [x] 4.6 Viết hủy toàn bộ/một phần và refund quote/request → owner báo chuyển → recipient acknowledgement; assert giới hạn hoàn và không tự ghi credit ví hoặc giả bank transfer.
- [x] 4.7 Viết đổi lịch sang giá cao hơn với trả chênh lệch, timeout giữ booking cũ và giá thấp hơn tạo nghĩa vụ hoàn; kiểm tra slot và lịch sử sau reload.
- [x] 4.8 Kiểm tra retry sau mất response của thao tác đã commit dùng cùng business operation, trả đúng kết quả cũ không tạo booking/receipt/benefit thứ hai.

## 5. P0 — POS, ca và phục vụ sân

- [x] 5.1 Viết mở ca → chọn khách/hàng → quote → bán tiền mặt → hóa đơn; reload xác minh một đơn, tồn kho và tổng tiền ca chính xác.
- [x] 5.2 Viết bán QR pending → receipt test xác thực → paid, cùng các trường hợp hủy/hết hạn và retry; không đánh dấu paid theo xác nhận client đơn thuần hoặc trừ kho hai lần.
- [x] 5.3 Viết đóng ca/đối chiếu/review, chênh lệch tiền mặt và đơn pending chưa xử lý; xác minh tổng, trạng thái và quyền sau reload.
- [x] 5.4 Viết tra cứu/check-in → thêm/xóa dịch vụ trên tab → settlement/check-out; kiểm tra booking không hợp lệ, thiếu tồn và lặp thao tác không thu hai lần.

## 6. Cổng CI P0 và báo cáo nền

- [x] 6.1 Thêm selectors/tags P0/P1/P2 và report HTML/JUnit, trace/ảnh lỗi; xuất metadata revision/environment/browser/timezone/capability, loại auth state và secrets khỏi artifacts.
- [x] 6.2 Xác minh executor của runner và chọn runtime Playwright được hỗ trợ; ghi rõ prerequisite bên ngoài nếu thiếu, không dùng giả định image Docker cho shell executor hoặc nhét browser vào job Alpine deploy.
- [x] 6.3 Thêm job P0 bắt buộc cho merge request/deployable branches và dependency trước build/push/deploy; tách scheduled/manual regression khỏi mọi job có thể deploy.
- [x] 6.4 Kiểm tra pipeline với P0 fail, thiếu backend, zero-test selection, skip và retry-only pass: tất cả không được tạo gate xanh; diagnostics vẫn được lưu có quyền truy cập giới hạn và retention 7 ngày.
- [x] 6.5 Chạy từng P0 độc lập và ba lần toàn bộ P0 liên tiếp không retry; ghi thời gian/kết quả thật, giữ task chưa hoàn thành nếu còn lỗi hoặc gap P0.

## 7. P1 — tài khoản, đối tác và cấu hình cơ sở

- [x] 7.1 Bao phủ đăng ký, hồ sơ cá nhân/thể thao, verification/resend, forgot/reset password và OAuth contract qua dịch vụ ngoài giả lập được gắn nhãn; kiểm tra link sai/hết hạn và trạng thái sau reload.
- [x] 7.2 Viết partner draft/save/upload/submit → yêu cầu bổ sung → resubmit → review/provision; kiểm tra tài liệu private, submit thiếu và không được tự duyệt/cấp platform admin.
- [x] 7.3 Viết plan catalog/trial/select/pay/activate/renew/cancel-renewal/expiry; kiểm tra quota và receipt owner không kích hoạt gói platform; ghi rõ thao tác quản trị chỉ có API nếu không có UI.
- [x] 7.4 Viết draft venue/profile/images/amenities/courts → lịch hoạt động/nghỉ/bảo trì/khóa → readiness → gửi duyệt → công khai; kiểm tra xuất hiện/mất khả dụng đúng và lịch sử được giữ.
- [x] 7.5 Bao phủ UI cấu hình receiver/verification/rotation nếu có; kiểm tra thiếu kết nối được hướng dẫn và existing payment attempt giữ receiver snapshot, ghi API-only/gap nếu không có user surface tương ứng.
- [x] 7.6 Viết staff invite/accept/revoke và thay role/venue grants, đổi tổ chức; chứng minh quyền thu hồi có hiệu lực, bảo vệ owner cuối cùng và quota.
- [x] 7.7 Viết quản lý giá/effective dates/priority, coupon/campaign lifecycle, membership/loyalty version; kiểm tra điều kiện phạm vi, hết hạn, không-combinable và giá booking cũ không đổi.
## 8. P1 — booking và vận hành mở rộng

- [x] 8.1 Viết recurrence preview nhiều ngày/thời lượng → all-or-nothing hoặc partial conflict → chấp nhận → thanh toán từng kỳ; kiểm tra count/dates/totals và overdue chỉ giải phóng buổi hợp lệ.
- [x] 8.2 Viết split participants/shares → đóng góp đã xác thực → đủ tiền confirm, thiếu tiền/hết hạn và host cover; kiểm tra nghĩa vụ hoàn theo người thực trả, không debit host lần nữa.
- [x] 8.3 Viết resale list/withdraw/buy/transfer với eligibility, giới hạn giá, xác thực thanh toán/bằng chứng hoàn người bán và buyer race; kiểm tra quyền check-in đổi một lần, không commission mới.
- [x] 8.4 Bao phủ danh mục hàng, điều chỉnh stock/movements, hết hàng/last-item, POS return/adjustment và print/send receipt; kiểm tra dữ liệu hóa đơn/stock/cash và trạng thái sau reload.
- [x] 8.5 Bao phủ walk-in, đến sớm/muộn/no-show và điều kiện check-in; waitlist → giải phóng slot → thông báo, kiểm tra thông báo read/unread và dữ liệu còn sau reload.
- [x] 8.6 Kiểm tra lịch sử ví legacy và direct-cohort top-up bị chặn, không chuyển đổi/tự tiêu số dư hoặc phát sinh credit do direct refund; không coi history-only là checkout ví mới.

## 9. P1 — cộng đồng, giải đấu và tài chính

- [x] 9.1 Viết friendship/invitation response và activity create/publish/search/join/roster/attendance; kiểm tra tenant/audience, capacity và reputation theo sự kiện hợp lệ.
- [x] 9.2 Viết feed post/comment/reaction/report/moderation; kiểm tra quyền, dữ liệu sau reload và thiếu UI phải được ghi rõ thay vì giả browser pass.
- [x] 9.3 Viết owner-hosted drop-in end session → draft bill → finalize; kiểm tra chia chi phí sân/cầu, loại chi phí phụ và không biến hóa đơn thông tin thành split-payment collection.
- [x] 9.4 Viết tournament create/publish/register/payment/capacity/check-in/bracket/score/results; kiểm tra score permissions, last place và không sửa kết quả đã chốt.
- [x] 9.5 Viết dispute submit/evidence/classification/decision/remedy/reconsideration/close; kiểm tra foreign-party denial và chưa hoàn nghĩa vụ thì không báo xử lý tài chính hoàn tất.
- [x] 9.6 Viết revenue/reconciliation/exception/report/CSV journeys: xác minh nội dung tải xuống, lọc đúng tenant/venue/kỳ và tách tiền chủ sân với subscription; không fake export hoặc resolve bằng note không đủ bằng chứng.

## 10. P2 và nghiệm thu đầy đủ

- [x] 10.1 Bao phủ lỗi mạng/session hết hạn/capability tắt/quyền bị thu hồi, giữ input/focus và chống thông báo thành công giả; gắn nhãn fault-injection, không thay thế backend success suite.
- [x] 10.2 Chạy mẫu đại diện auth/booking/payment/POS/modal trên Việt–Anh, light/dark/mobile; kiểm tra bàn phím, focus entry/trap/restore, dismiss và nội dung không tràn màn hình.
- [x] 10.3 Chạy smoke Chromium/Firefox/WebKit và mobile với selection riêng; public/about/legal/404/directions/navigation đúng, không nhân toàn bộ ma trận locale/theme/browser.
- [x] 10.4 Kiểm tra hai phiên nhận cập nhật availability/tournament bằng realtime process cô lập và kiểm tra polling fallback riêng; thêm timezone-boundary case, không giả cập nhật DOM làm bằng chứng realtime.
- [x] 10.5 Thêm full-regression scheduled-pipeline/manual entry points và runbook chạy local/CI, prerequisites, seed/cleanup, đọc trace/report và xử lý lỗi; ghi rõ việc bật lịch CI thực hoặc đổi runner cần quyền riêng.
- [x] 10.6 Chạy các suite đã bổ sung cùng type-check và regression backend/frontend bị ảnh hưởng; cập nhật từng FR/supplementary row bằng kết quả thật, test step, environment/revision và artifact link.
- [x] 10.7 Rà soát đủ 126 ID không thiếu/trùng, không còn P0 gap, mọi gap P1/P2 và provider gate đều hiển thị; không đánh dấu change nghiệm thu toàn bộ nếu còn required journey chưa chạy/failed/skipped/blocked, không tự sửa nghiệp vụ ngoài phạm vi.
