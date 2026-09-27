# Implementation checklist

Mọi ô đang chưa làm. Việc tạo proposal không thực thi task. Đọc design.md và report-traceability.md trước khi apply. Mỗi task phải có kiểm chứng thực; D nào bị bác bỏ phải giữ bằng chứng và không tự xóa khỏi ma trận.

Thứ tự: 1 → 2/3 → 4 → 5/6 → 7 → 8/9/10 → 11/12 → 13. Khóa truy cập D71 thực hiện sớm ở 2 trước khi mở rộng case. Phần contract/UI ở 3 được xây trước, nghiệm thu thanh toán thật sau 5; readiness receiver D19 nằm ở 5 để không tạo phụ thuộc vòng với catalog. Không bật tính năng tài chính trước gate 5 và 13.

## 1. Baseline và tiêu chí (trước mọi sửa source)

- [x] 1.1 Chụp baseline commit/hash báo cáo và tái kiểm D01–D71 qua route/caller thật; ghi confirmed/partial/disproved kèm bằng chứng, không coi audit tĩnh là test đã chạy.
- [x] 1.2 Đối chiếu main specs với hai change completed chưa archive; lập thứ tự sync/rebase để giữ zero-commission/loyalty/navigation và áp dụng policy mới sau cùng, không viết lại lịch sử change cũ.
- [x] 1.3 Chốt contract inventory cho 126 FR: endpoint, principal, receiver, state, schema/resource, error và nơi nghiệm thu; duy trì report-traceability.md với test/evidence thực khi làm.
- [x] 1.4 Chuẩn bị DB kiểm thử cô lập và fixture nhiều org/nhiều venue cùng org; bảo đảm không chạy destructive tests trên DB người dùng hoặc production.
- [x] 1.5 Lập inventory read-only và thiết kế additive migration/payment_model cho legacy funds/booking/identity/benefits; checksum và số dư phải giữ nguyên trên bản sao trước cutover.

## 2. Tài khoản và ranh giới quyền

- [x] 2.1 [D01] Chặn tài khoản hạn chế ở password/OAuth và session đang tồn tại; test không tạo booking/tiền/điểm.
- [x] 2.2 [D02] Áp dụng xác thực email cho mọi đường customer V1/V2/định kỳ; test gọi trực tiếp không bypass.
- [x] 2.3 [D03] Thay kiểm tra phone-present bằng evidence xác thực hoặc email đã verified; test chưa verified bị từ chối.
- [x] 2.4 [D07] Tách màn hình/API owner-staff khỏi quyền global admin; test owner role customer vận hành đúng cơ sở và bị chặn tenant khác.
- [x] 2.5 [D08] Lưu venue/function grants, dùng active org/venue thay org 1; test chuyển tổ chức, thu hồi quyền và sibling venue.
- [x] 2.6 [D09] Thêm lời mời pending/delivery/accept/expire/revoke; ngăn mời owner tạo global admin; test accept idempotent.
- [x] 2.7 [D13] Gắn quota vào mọi create/update/import có thể tăng tài nguyên; test race court/staff và downgrade vượt quota.
- [x] 2.8 [D71] Scope mọi query/detail/export/evidence/mutation theo party/venue/org hoặc elevated admin; test ID tenant khác không đọc/ghi được.

## 3. Hợp đồng đối tác và gói dịch vụ

- [x] 3.1 [D04] Nối đúng API current/start/save/submit/correction/history; test hành trình trên API thật không 404.
- [x] 3.2 [D05] Thống nhất upload field/type, business type, trạng thái, consent, lịch sử và resource; test upload/nộp/sửa/reload.
- [x] 3.3 [D06] Sửa phân giải completeness handler và kiểm tra quyền; test checklist thiếu tài liệu trả lỗi nghiệp vụ đúng.
- [x] 3.4 [D10] Hoàn thiện CRUD/version/publish/retire PlatformPlan cho platform admin; test không nhầm customer membership.
- [x] 3.5 [D11] Nối paths/select/trial/current/payment/return/renew và payload/response; test UI thật thấy QR/status server.
- [x] 3.6 [D12] Tính kỳ 1/6/12 calendar months, cuối tháng và renewal; test không rơi về 30 ngày.
- [x] 3.7 [D14] Sửa request type/DTO của endpoint tạo venue; test HTTP owner tạo draft đúng quota không lỗi phân giải lớp.

## 4. Catalog, lịch và policy lưu server

- [x] 4.1 Thêm versioned venue policy và form cho hold/book-ahead/continuity/cancel/change/refund/arrival/recurring/transfer; bắt buộc cấu hình trước readiness, không tự cấp miễn phí hay giả consent.
- [x] 4.2 [D15] Persist contact/description/images/location và update/review/public snapshot; test round-trip không rơi field.
- [x] 4.3 [D16] Thêm/chuẩn hóa dữ liệu surface/capacity và form/readback; test validation và bảo toàn booking history.
- [x] 4.4 [D17] Đưa lịch/dated exceptions lên server theo venue/court/timezone; test thiết bị khác reload và API đặt ngoài giờ bị chặn.
- [x] 4.5 [D18] Dùng chung conflict check cho lịch tuần/ngày nghỉ/closure; test giữ nguyên confirmed commitments và trả references.
- [x] 4.6 [D20] Gắn same eligibility vào venue/court/zone list-detail-cache-quote-checkout; test draft/suspended/expired và stale cache.

## 5. Nền tảng nhận tiền trực tiếp (phụ thuộc 1–4)

- [x] 5.1 Xác minh tài liệu chính thức SePay bằng Context7 và sandbox cho đúng sản phẩm/headers/payload; ghi bằng chứng test tenant receiver thay vì mặc định tin HMAC cũ.
- [x] 5.2 Triển khai receiving connection/schema, encrypted credential rotation, private verification evidence và hướng dẫn onboarding SePay thủ công; test secret không lộ ở API/log/export.
- [x] 5.3 Triển khai payment attempt/receipt/allocation receiver-purpose snapshots và khóa chống replay; test duplicate/foreign receiver/tamper/wrong amount/time/currency.
- [x] 5.4 Thiết kế và kiểm chứng owner/platform accounting entries theo entity/model; kiểm tra balanced journals, zero new commission và không chuyển số dư lịch sử.
- [x] 5.5 Thêm callback/query inbox, after-commit processing và bounded recovery; test lost response/out-of-order/late/partial/excess/unmatched transfer không mất nghĩa vụ tiền.
- [x] 5.6 Thêm UI owner connection/readiness và customer QR/status thật; test thay receiver không đổi QR/attempt snapshot cũ.
- [x] 5.7 [D39] Tạo obligation/QR owner receiver và ledger entity-model; disable pooled top-up mới theo cohort, giữ legacy funds; test không tenant payable mới.
- [x] 5.8 [D45] Chỉ verified platform-receiver event/query được activation; nối webhook/attempt; test body đúng tiền nhưng không evidence vẫn pending.
- [x] 5.9 [D40] Thêm refund request/calculation/evidence/owner execution/ack/status; test partial/refund-limit/idempotency và không tự chuyển tiền.
- [x] 5.10 [D19] Cấu hình receiver SePay thủ công, test xác thực/quyền sử dụng tài khoản và readiness từ chối receiver chưa verified.

## 6. Giá, khuyến mãi, thành viên và điểm

- [x] 6.1 [D21] Dùng một calculator cho availability/V1/V2/định kỳ; test cùng input cho cùng giá holiday/peak/week.
- [x] 6.2 [D22] Thêm hiệu lực theo ngày/version và conflict priority; test biên thời gian, không sửa giá booking đã accepted.
- [x] 6.3 [D23] Bỏ fallback giá đầu tiên không khớp; test chỉ explicit base hợp lệ hoặc từ chối chưa mở bán.
- [x] 6.4 [D24] Hoàn thiện create/update/activate/suspend/retire campaign/coupon và nhóm áp dụng; test lifetime/limits/audit.
- [x] 6.5 [D25] Scope coupon/customer eligibility theo org/venue/court; backfill chỉ khi đủ provenance; test foreign benefit bị từ chối.
- [x] 6.6 [D26] Dùng chung combination policy booking/POS/preview/loyalty; test coupon cấm kết hợp không bị cộng lén.
- [x] 6.7 [D27] Nối tenant memberships/earned entitlements vào V1/V2/POS; test hạng owner tạo có hiệu lực đúng tenant sau reload.
- [x] 6.8 [D28] Áp dụng book-ahead, earn multiplier, điểm hết hạn và reversal append-only; test balance unexpired và version đã earned.
- [x] 6.9 [D29] Chặn activation/update business policy khi entitlement không còn hợp lệ; test role đủ nhưng plan hết hạn.

## 7. Booking, expiry và các luồng nâng cao (phụ thuộc 4–6)

- [x] 7.1 Chuẩn hóa V1 adapter và V2 canonical payment/inventory identity; test cùng booking đọc hai version không tạo hai allocation/receipts.
- [x] 7.2 Thêm point/coupon reservation khi hold và consume/release khi payment/expiry; test mất response, stale policy, zero-payable và concurrent hold.
- [x] 7.3 Triển khai atomic reschedule swap với replacement hold và extra-payment timeout; test original được giữ đến commit và late receipt thành exception.
- [x] 7.4 Triển khai cancellation refund quote/approval liên kết payer allocations; test partial paid, points/coupon reversal theo snapshot và không hoàn quá số tiền thực.
- [x] 7.5 [D30] Đổi direct cohort sang pending hold/receipt confirmation V1/V2; test không trừ ví, TTL và zero-payable.
- [x] 7.6 [D31] Khôi phục preview/request/review hủy-partial-reschedule với snapshot và UI VI/EN; test giá tăng/giảm/bằng, conflict và repeat.
- [x] 7.7 [D32] Sửa service resolution và sweeps V1/V2/split/invoice/POS; test expiry tự chạy/commit durable và race callback. (POS/QR scheduled auto-expiry — incl. pos_orders expires_at/TTL — deferred to 8.11 [D58]; scheduler + V1/V2 + split + recurring-invoice + subscription sweeps delivered.)
- [x] 7.8 [D33] Enforce venue horizon/duration/continuity cùng nhau V1/V2; test nonidentical starts nhưng overlap và past/over-horizon.
- [x] 7.9 [D34] Dùng preview server với weekdays/frequency/count/until, không cap 12 trái preview; test weekly/biweekly/monthly.
- [x] 7.10 [D35] Tách series end và session end, reserve mọi interval của buổi; test buổi hai giờ không chỉ chiếm giờ đầu.
- [x] 7.11 [D36] Chốt danh sách/giá trước hold; hỗ trợ toàn bộ/một phần và per-period verified payment/guarantee; test unpaid không confirmed vô điều kiện.
- [x] 7.12 [D43] Ràng paid invoice vào verified receipts, bỏ reference client tự tạo như bằng chứng; test giả/late/duplicate invoice event.
- [x] 7.13 [D41] Xác nhận shares/host coverage bằng receipt, thống nhất states; test đủ shares confirm một lần không debit ví lần hai.
- [x] 7.14 [D42] Load đúng group/shares/receipts, bỏ sample/fallback thành công; test API lỗi không đổi paid và reload khớp server.
- [x] 7.15 [D37] Recheck dispute/account/venue policy/cutoff ở list và commit; test chuyển trạng thái trong lúc checkout.
- [x] 7.16 [D44] Nối buyer direct receipt và owner-reported/verified seller reimbursement; test flags giả không đổi entitlement.
- [x] 7.17 [D38] Đổi entitlement holder và check-in authority atomically, giá gốc bất biến; test người mua vào được, người bán mất quyền.

## 8. Attendance, POS, ca và tồn kho

- [x] 8.1 Hoàn thiện print/send receipt thật và cấu hình mixed tender có allocation chính xác nếu venue bật; test không in paid receipt trước verified payment.
- [x] 8.2 [D49] Kiểm holder/representative, venue, arrival window/payment/court readiness; test wrong holder/time/occupied/maintenance.
- [x] 8.3 [D50] Project in_use/completed/no_show nhất quán V1/V2, tách payment; test repeated scan/check-out không thêm event.
- [x] 8.4 [D51] Cho checkout và giữ tab receivable độc lập; test unpaid không thành paid và vẫn collect được sau checkout.
- [x] 8.5 [D52] Thêm awaiting-inspection và confirm ready theo quyền; test không bàn giao trước kiểm tra, không mở slot đã booked.
- [x] 8.6 [D53] Nối no-show policy/actor vào V2, incident/reassignment/extension chung workflow; test không kéo sang lượt kế tiếp.
- [x] 8.7 [D54] Giới hạn confirm QR vào verified provenance/receiver/reference/amount; test staff body tùy ý không hoàn tất order.
- [x] 8.8 [D55] Persist venue/register/shift links và actor, migrate chỉ có evidence; test hai ca sát giờ không nhập nhằng totals.
- [x] 8.9 [D56] Chặn/hand-over pending order có disposition, late receipt không sửa closed shift; test race close/payment.
- [x] 8.10 [D57] Thêm linked returns/refunds/cash-in/out và variance reason/review; test expected cash và original receipt bất biến.
- [x] 8.11 [D58] Phân biệt tracked/untracked, reserve stock QR và tabs cùng nguồn; test last unit/race/expiry/cancel release. (Includes the POS/QR scheduled auto-expiry sweep + pos_orders expires_at/TTL + host schedule + observability, deferred here from 7.7/D32.)

## 9. Hoạt động cộng đồng và uy tín

- [x] 9.1 Regression hồ sơ thể thao/privacy, tìm kiếm, friendship/invitation, feed report/hide/restore và durable notifications bằng real API; giữ bill court/shuttle-only là informational.
- [x] 9.2 [D59] Cho generic customer host dùng quyền booking hợp lệ, không giả owner; test hoạt động trong entitlement không double allocation.
- [x] 9.3 [D60] Validate ordinary activity timing/deadline/skill/conditions ở publish và join; test hết hạn và sai skill không chiếm suất.
- [x] 9.4 [D61] Gắn hosted sessions vào canonical inventory; test cả hai thứ tự tạo và race booking/session.
- [x] 9.5 [D62] Thêm approval-required, close roster, present/late/absent/withdrawn và notification; test quota/duplicate/authority.
- [x] 9.6 [D68] Verify nguồn/participant/actor và đảo penalty khi appeal thành công; test không phạt appellant tự động.

## 10. Giải đấu

- [x] 10.1 [D63] Thêm event-scoped organizer/official grants cho khách; test đúng giải được phép, giải khác bị chặn.
- [x] 10.2 [D64] Dùng admission chung cho self/admin: deadline/capacity/verified fee hoặc waiver; thêm attendance states và test race.
- [x] 10.3 [D65] Sửa deterministic bye propagation; test 3/5/7 người và không tạo phantom winner.
- [x] 10.4 [D66] Recompute standings từ accepted results, require reason/version và xử lý downstream rounds; test correction/replay.
- [x] 10.5 [D67] Validate/finalize/publish/lock final results, controlled reopening; test không regenerate xóa fixtures đã finalized.

## 11. Hỗ trợ, tranh chấp và kiểm duyệt

- [x] 11.1 Thêm reasoned moderation keep/hide/restore, private evidence retention, conflict-of-interest reassignment và party notifications vào case; test quyết định không tự sửa tiền/kết quả ngoài execution workflow.
- [x] 11.2 [D69] Thêm party intake/classification/deadline/duplicates/private evidence/explanations; test upload-scope và bổ sung hồ sơ.
- [x] 11.3 [D70] Nối decision versions/execution/refund obligations/notices/reopen/close gates; test phán quyết không tự paid.

## 12. Đối soát, báo cáo và xuất dữ liệu (sau các nguồn giao dịch)

- [x] 12.1 [D46] Đối soát receipt/allocations của tất cả nguồn và legacy theo receiver/entity; test từng mismatch không bị net mất.
- [x] 12.2 [D47] Bắt evidence/correction và rerun trước matched; test note-only không zero mismatch hay tuyên bố bù trừ.
- [x] 12.3 [D48] Hợp nhất canonical V1/V2-POS-refund-platform fees, filter venue/date/access; test CSV/totals không đếm đôi và không lẫn receiver.

## 13. Nghiệm thu, migration rehearsal và bàn giao

- [x] 13.1 Chạy contract/E2E thật cho 7 module với reload, invalid input, 401/403/404/409/422 và lỗi mạng; loại mock-only bằng chứng, false success và export mô phỏng khỏi operational paths.
- [x] 13.2 Chạy matrix account-role-venue-org-case/attachment/channel/export, gồm revoked grant, suspended account và owner không global admin; lưu bằng chứng D01–D71 liên quan.
- [x] 13.3 Chạy concurrency/retry cho court overlap, points/coupon use, final stock, split/transfer, invoice, subscription, refund limit và last tournament place; xác minh không double effects.
- [x] 13.4 Rehearse additive migration/rollback trên bản sao: count/checksum/totals/legacy response, no policy/balance rewrite; giữ ingestion/reconciliation khi pause new commands.
- [x] 13.5 Chạy provider sandbox end-to-end cho owner receipt và platform subscription, manual setup/secret rotation/late receipt/refund evidence; thiếu capability phải ghi blocked riêng không đánh dấu production-ready.
- [x] 13.6 Kiểm tra reliability/backup-restore/realtime/host scheduler theo main specs hiện hành; NFR trống trong báo cáo không được tự gán đạt.
- [x] 13.7 Hoàn thiện 126 FR và 71 D dispositions bằng tên test, môi trường, revision, kết quả; cập nhật runbook/copy VI-EN và rà residual wallet-only/no-cancel/mocked success.
- [x] 13.8 Chỉ chuẩn bị activation sau khi chủ vận hành phê duyệt legacy-liability disposition và cohort; không tự chuyển tiền/rollout. Trước archive được yêu cầu riêng, reconcile older deltas và sửa purpose/spec references lỗi thời.
