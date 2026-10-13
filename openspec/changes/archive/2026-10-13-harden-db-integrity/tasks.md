## 1. Preflight

- [x] 1.1 Chạy migration còn treo trên bt-server (`2026_10_07_000001_change_avatar_url_to_text_in_users_table`). Xác nhận `migrate:status` không còn migration nào treo trên mọi môi trường đích. (bt-server: xong 2026-10-11, batch 6.) Production chính là bt-server (stack Portainer `booking`), không có môi trường riêng. Kiểm tra read-only ngày 2026-10-13: không còn migration nào treo, `btree_gist` 1.8, có `reservation_slots_no_overlap`, 72 CHECK, ba bảng chết đã bị xoá.
- [x] 1.2 Xác nhận role ứng dụng tạo được `btree_gist` trên mọi môi trường đích. bt-server: `ziet_dev` là chủ database nên đủ quyền. Production: kiểm tra quyền CREATE trên database. Nếu thiếu thì nhờ DBA chạy `CREATE EXTENSION btree_gist` trước khi deploy. (bt-server: đã cài 1.8.) Production chính là bt-server (stack Portainer `booking`), không có môi trường riêng. Kiểm tra read-only ngày 2026-10-13: không còn migration nào treo, `btree_gist` 1.8, có `reservation_slots_no_overlap`, 72 CHECK, ba bảng chết đã bị xoá.
- [x] 1.3 Viết các query preflight (lưu trong `docs/` hoặc trong command) và chạy trên bt-server và một snapshot production. Ghi kết quả vào change. Các query gồm:
  - số cặp slot `held`/`confirmed` cùng sân, khác reservation, chồng khoảng `[)`. Phải bằng 0.
  - số slot active dưới reservation đã `cancelled`/`expired`. Phải bằng 0.
  - với mỗi cột status trong phạm vi (design §5): danh sách giá trị khác nhau trong data.
  - số dòng trong `reserves`, `memberships`, `membership_plans`. Phải bằng 0.

  bt-server: xong, kết quả ở design (Context). Không có production riêng; schema bt-server sau khi apply đã kiểm tra lại ngày 2026-10-13.

## 2. Index cho foreign key

- [x] 2.1 Sinh danh sách FK thiếu index phủ cột đầu bằng query audit, chạy trên bt-server và trên `booking_test` sau `migrate:fresh`. Hai danh sách phải khớp nhau, bỏ các FK của bảng sẽ xoá ở nhóm 4.
- [x] 2.2 Viết migration `CREATE INDEX IF NOT EXISTS {table}_{column}_index` cho cả danh sách. `down()` dùng `DROP INDEX IF EXISTS` tương ứng.

## 3. RESTRICT thay cho CASCADE vào bảng tài chính

- [x] 3.1 Viết migration đổi 45 FK trong design §3 sang `ON DELETE RESTRICT`: drop rồi add lại với cùng tên constraint. `down()` trả lại CASCADE cho đúng 45 FK đó.
- [x] 3.2 Sửa `ReconcileLegacyPartnersCommandTest` nếu test fail vì RESTRICT. Cách sửa: setup không tạo dòng tài chính, hoặc xoá bảng con một cách tường minh. Không được nới RESTRICT. (Kết quả: test vẫn pass với RESTRICT, không cần sửa vì lý do này.)
- [x] 3.3 Thêm feature test cho spec "Financial history blocks owner deletion":
  - xoá organization có POS order bị từ chối;
  - xoá user có wallet hoặc point ledger bị từ chối;
  - xoá organization có journal bị từ chối do FK chứ không phải do trigger;
  - trong cả ba trường hợp, data giữ nguyên.

## 4. Xoá bảng chết

- [x] 4.1 Grep xác nhận `Reserve`, `Membership`, `MembershipPlan` và các bảng `reserves`, `memberships`, `membership_plans` chỉ còn được dùng ở những chỗ ghi trong design §6. Phải tính cả test, factory, seeder và frontend. (Tìm thêm: `MembershipPlanSeeder` seed một dòng `membership_plans`, và `TenantMembershipCheckoutBenefitsTest` tạo một membership legacy làm mồi nhử. Cả hai đã bỏ phần dùng bảng cũ.)
- [x] 4.2 Bỏ route `GET/POST api/v1/admin/membership-plans` và `POST api/v1/admin/memberships`, bỏ `PromotionController::plans`, `storePlan`, `assignMembership`, bỏ `User::memberships()`. Xoá ba model.
  - Xoá các test chỉ phục vụ 3 route này. `AdminMembershipTest` đang test route v2 nên phải giữ nguyên và vẫn pass.
  - Cập nhật allowlist kiến trúc và baseline PHPStan nếu entry tương ứng không còn khớp.
- [x] 4.3 Viết migration xoá `memberships`, `membership_plans`, `reserves` theo thứ tự đó.
  - `up()` throw `RuntimeException` nêu tên bảng nếu bảng còn dòng.
  - `down()` tạo lại bảng rỗng với cột và FK như migration gốc.

## 5. CHECK constraint cho status

- [x] 5.1 Với mỗi cột trong phạm vi, lập tập giá trị hợp lệ theo quy tắc ở design §5 (code ghi, code đọc, default, constant, data ở 1.3; seeder/test/e2e chỉ bù chỗ trống, trái từ vựng thì sửa writer — design D3).
  - Giá trị nào chỉ có trong data thì điều tra.
  - Hợp lệ: thêm vào tập, kèm comment.
  - Sai: sửa data trong một bước riêng có review, trước khi chạy migration. Migration CHECK không được sửa data.
- [x] 5.2 Migration nhóm inventory/booking: `reservations`, `reservation_slots`, `reservation_entitlements`, `bookings`, `booking_slots`, `recurring_series`, `recurring_occurrences`, `waitlist_entries`, `price_quotes`, `booking_attendances`.
- [x] 5.3 Migration nhóm payments/finance: `payments`, `refunds`, `owner_payment_attempts`, `owner_payment_receipts`, `owner_refund_obligations`, `payment_intents`, `platform_payment_attempts`, `platform_subscriptions`, `platform_plans`, `monthly_invoices`, `settlements`, `beneficiaries`, `accounts`, `wallet_accounts`, `reconciliation_runs`, `finance_exceptions`, `outbox_messages`. `payouts`, `provider_events`, `provider_operations` được miễn vì lưu status provider gửi về (design D2).
- [x] 5.4 Migration nhóm POS/on-site: `pos_orders.status`, `pos_orders.handover_status`, `pos_payments`, `pos_carts`, `cash_shifts`, `shift_reconciliations`, `service_tabs`, `service_tab_items`.
- [x] 5.5 Migration nhóm organization/partner/venue:
  - `organizations.status`, `organizations.partner_status`;
  - `organization_members`, `organization_invitations`, `users`;
  - `venues`, `venue_publication_revisions`, `receiving_connections`, `partner_documents`;
  - 3 cột status của `host_kycs` (`status`, `investigation_status`, `napas_match_status`); `tax_status` và `lease_contract_status` là text tự do nên được miễn (design D2);
  - `coupons`, `promotion_campaigns`, `loyalty_rules`;
  - `tenant_customers`, `tenant_membership_plans`, `tenant_memberships`.
- [x] 5.6 Migration nhóm community/tournament/disputes: `match_posts`, `match_participations.status`, `match_participations.attendance_status`, `match_invitations`, `match_bills`, `split_groups`, `split_shares`, `marketplace_listings`, `entitlement_transfers`, `friendships`, `feed_reports`, `dispute_cases`, `reputation_disputes`, `tournaments`, `tournament_fixtures`, `tournament_participants`.
- [x] 5.7 Migration nhóm rollout runs: `acceptance_runs`, `operational_drill_runs`, `pilot_migration_runs`.
- [x] 5.8 Đếm lại: tổng số CHECK phải bằng 72. Mỗi migration có `down()` drop đúng các constraint của nó.

## 6. Exclusion constraint chống trùng lịch

- [x] 6.1 Viết migration theo design §1:
  - `CREATE EXTENSION IF NOT EXISTS btree_gist`;
  - constraint `reservation_slots_no_overlap` trên slot `held`/`confirmed`, không có cột lineage (design D1).

  `down()` drop constraint nhưng giữ extension.
- [x] 6.2 Xác nhận `RescheduleService` không cần gán lineage: `PriceQuoteService::create` đã từ chối lịch thay thế chồng lên lịch gốc, nên không có cột lineage và không sửa `ReservationSlot` (design D1).
- [x] 6.3 Thêm `reservation_slots_no_overlap` vào chỗ match tên constraint ở 4 site `catch (QueryException)`: `OrdinaryCheckoutService` (khoảng dòng 436 và 703), `ReservationService` (khoảng dòng 276), `RescheduleService` (khoảng dòng 201). Thông báo 409 và metric giữ như cũ.
- [x] 6.4 Feature test cho từng scenario mới của "Active inventory exclusivity":
  - chèn trực tiếp slot 11:00–12:00 của reservation khác chồng lên slot 10:00–12:00 thì bị DB từ chối;
  - qua API, hai lưới giờ khác nhau chồng nhau thì trả 409 và không để lại reservation, entitlement hay payment attempt nào;
  - hai slot kề nhau 10–11 và 11–12 đều được nhận;
  - dời lịch sang giờ khác cần thu thêm tiền: trong lúc chờ thanh toán, cả lịch gốc lẫn hold thay thế đều chặn reservation khác ở DB; trả tiền chênh lệch thì swap thành công;
  - slot `cancelled`/`expired` không chặn hold mới.
- [x] 6.5 Các bộ test hiện có phải pass không cần sửa: `RescheduleAtomicSwapTest`, `ConcurrentSlotAndWalletCheckoutTest`, `CalendarClosureAndUnifiedAvailabilityTest`, `LegacyBookingAuditAndBackfillTest`.

## 7. Test schema conformance

- [x] 7.1 Viết `tests/Feature/Infrastructure/SchemaConformanceTest.php` theo design §7, gồm 3 kiểm tra:
  - FK thiếu index;
  - cột status thiếu CHECK, có map miễn trừ `table.column → lý do`;
  - FK CASCADE vào tập bảng tài chính.

  Message lỗi phải nêu tên bảng, cột và constraint.
- [x] 7.2 Chứng minh test bắt được lỗi: tạm thêm một migration có FK không index hoặc cột status không CHECK, xác nhận test fail đúng thông báo, rồi bỏ migration tạm đó đi.

## 8. Xác minh và rollout

- [x] 8.1 Trên `booking_test`: `migrate:fresh` pass, cả bộ PHPUnit pass, `migrate:rollback` từng bước về trước change thành công, rồi `migrate` lại.
- [x] 8.2 Static gates pass: Pint `--test`, PHPStan level 0 và level 5 (không regenerate baseline để nuốt lỗi mới), test kiến trúc.
- [x] 8.3 Apply lên bt-server. Chạy lại query audit và ghi kết quả vào change:
  - FK thiếu index = 0;
  - CHECK = 72;
  - CASCADE vào bảng tài chính = 0;
  - exclusion constraint có mặt.
- [ ] 8.4 Sau khi deploy production, theo dõi log Laravel 48 giờ, tìm `check constraint` và `reservation_slots_no_overlap`. Nếu có giá trị status bị từ chối ngoài dự kiến thì ghi lại và xử lý bằng migration bổ sung tập giá trị. Không được gỡ constraint.
  (Còn mở lúc archive ngày 2026-10-13: SSH tới bt-server bị timeout nên không đọc được log Laravel. Cần xem lại khi có quyền truy cập.)
