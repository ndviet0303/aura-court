## 1. Migration và model (design D1, D3)

- [x] 1.1 Chuyển migration cũ `2026_08_28_000003_migrate_existing_courts_to_default_sport_zones` sang `DB::table`, giữ nguyên hành vi, bỏ import `SportZone`.
- [x] 1.2 Viết M1 `2026_10_20_000001_add_sport_fields_to_courts_and_price_rules`:
  - Thêm `courts.sport_type` (default `badminton`), `courts.slot_duration` (default 60), index `(venue_id, sport_type)` và `price_rules.sport_type`.
  - Backfill bằng hai câu `UPDATE … FROM` trong design D3.
  - `down()` xoá các cột và index đó.
- [x] 1.3 Viết M2 `2026_10_20_000002_drop_sport_zones`:
  - Chạy guard (a)–(d). Nếu có hàng, ném `RuntimeException` kèm id.
  - Xoá `price_rules.sport_zone_id`, `courts.sport_zone_id` và bảng `sport_zones`.
  - `down()` dựng lại zone theo `(venue_id, sport_type)`, và từ chối nếu `slot_duration` lẫn lộn.
- [x] 1.4 Model:
  - `Court`: thêm `sport_type` và `slot_duration` vào fillable và casts, thêm hằng `SPORT_TYPES` và `SLOT_DURATIONS`, bỏ `sportZone()`.
  - `PriceRule`: thay `sport_zone_id` bằng `sport_type`, bỏ `sportZone()`.
  - `Venue`: bỏ `sportZones()`.
  - Xoá `app/Models/SportZone.php`.

## 2. Backend: giá và khung giờ (design D1, D2)

- [x] 2.1 `CourtPriceRuleService::appliesTo`: thay điều kiện zone bằng `sport_type` (null hoặc trùng môn của sân).
- [x] 2.2 `PriceQuoteService`:
  - `scopeRank` hạng 3 khi `sport_type` trùng và cùng cơ sở.
  - Đọc `$court->slot_duration`.
  - Bỏ eager-load `sportZone` và `court.sportZone`.
- [x] 2.3 Đọc `$court->slot_duration` và bỏ eager-load `sportZone` ở `AvailabilityController`, `RecurringSeriesService`, `SlotSelectionPolicy` và `LoyaltyPolicyService`.
- [x] 2.4 `PriceRuleController`:
  - Validate `sport_type` bằng `Rule::in(Court::SPORT_TYPES)`. Rule có môn phải có `venue_id`, kiểm trên trạng thái sau khi cập nhật (`assertSportHasVenue`).
  - `index` lọc theo `sport_type`.
  - Eager-load `venue` và `court`.

## 3. Backend: catalog và endpoint (design D4)

- [x] 3.1 `CourtController`:
  - `index` và `adminIndex` lọc `sport_type` trên cột của sân.
  - Bỏ `sport_zone_id` khỏi truy vấn và khoá cache.
  - `store` yêu cầu `sport_type`. `update` nhận `sport_type` và `slot_duration` theo hằng của `Court`.
  - Bỏ load `sportZone`.
- [x] 3.2 `EloquentVenueRepository`:
  - `getActiveVenuesWithCourts` lọc `whereHas('courts')` theo sân đang hoạt động và môn.
  - `findBySlug` eager-load `courts` đang hoạt động kèm `priceSlots`.
- [x] 3.3 `AdminController::venues` (admin) eager-load `courts:id,venue_id,sport_type` của sân đang hoạt động. Bỏ `fresh('sportZones')`.
- [x] 3.4 `CreateVenue` không tạo zone. `UpdateVenue` trả `fresh()`. `EloquentVenueLifecycleRepository` bỏ `sportZones`.
- [x] 3.5 Xoá các route `sport-zones` trong `routes/api.php` (công khai và admin) và xoá `SportZoneController`.
- [x] 3.6 `grep -rn "sport_zone\|sportZone\|SportZone" backend/app backend/routes` không còn kết quả. Chỉ migration được phép còn nhắc tới zone.

## 4. Seeder (design D8)

- [x] 4.1 Đổi tên `SportZoneCourtSeeder` thành `CourtSeeder`:
  - Bỏ khối zone và `zone_key`.
  - Sân lấy `sport_type` từ định nghĩa của nó.
  - Rule `base` bỏ `sport_zone_id`.
- [x] 4.2 `DatabaseSeeder` gọi `CourtSeeder`. Sửa `BookingPaymentSeeder`, `DisputeFinanceSeeder`, `MarketplaceSeeder`, `MatchmakingSeeder`, `RecurringSeriesSeeder` và `Concerns/FindsFreeCourtTime` để đọc `sport_type` và `slot_duration` của sân.

## 5. Test backend (design D8)

- [x] 5.1 Sửa 17 test đang tạo zone sang `Court::create([... 'sport_type' => …])`, gồm cả `DatabaseSeederTest` và `BookingPaymentSeederMappingTest`.
- [x] 5.2 Test migration:
  - Dựng lại zone bằng `down()` của M2, rồi M1.
  - Seed sân, rule chỉ theo zone và rule theo sân.
  - Chạy `up()` và kiểm tra: môn và khung giờ được chép, rule có `venue_id` + `sport_type`, báo giá không đổi.
  - Thêm case guard (c) ném lỗi và dữ liệu giữ nguyên.
- [x] 5.3 Test rule giá:
  - Sân pickleball nhận rule cơ sở + môn, sân cầu lông nhận rule theo cơ sở, rule theo sân thắng cả hai.
  - Rule có `sport_type` mà thiếu `venue_id` trả 422.
- [x] 5.4 Test catalog:
  - `/courts?sport_type=` và `/venues?sport_type=` lọc theo môn của sân.
  - `/sport-zones` và `/admin/sport-zones` trả 404.
  - Tạo sân thiếu môn hoặc sai môn trả 422.
  - `slot_duration` mặc định 60, và lịch trống dùng `slot_duration` của sân.
  - Tạo cơ sở không sinh dữ liệu phụ.

## 6. Frontend admin (design D5, D6)

- [x] 6.1 Type:
  - Bỏ `SportZone`.
  - `Court` thêm `sport_type` và `slot_duration`, bỏ `sport_zone_id` và `sport_zone`.
  - `Venue` bỏ `sport_zones` và thêm `courts?` (gồm id và `sport_type`).
  - `pricing.ts` dùng `sport_type`.
  - `admin.ts` bỏ `sport_zone_id`.
- [x] 6.2 `adminApi`: xoá `getSportZones`, `createSportZone`, `updateSportZone` và `deleteSportZone`.
- [x] 6.3 `useAdminManageCourts`:
  - `CourtForm` thay `sport_zone_id` bằng `sport_type`.
  - Không tải zone.
- [x] 6.4 `CourtFormModal` thay ô zone bằng ô "Môn thể thao"; cơ sở vẫn ngầm như `679ccc90`. Sửa `AdminManageView` cho khớp.
- [x] 6.5 `PricingRuleModal`:
  - Ô "Môn thể thao" chỉ bật khi đã chọn Cơ sở; lựa chọn là các môn có trong sân của cơ sở đó.
  - Danh sách sân lọc theo cơ sở và môn.
  - Payload gửi `sport_type`.
- [x] 6.6 `PricingCalendarGrid` áp dụng và xếp hạng rule theo `sport_type`. `usePricingCalendar`, `PricingCalendarView` và `useAdminVenues` bỏ zone.
- [x] 6.7 `VenueCard` hiện chip theo môn từ `venue.courts`. `AdminCourtsTable` hiện chip tên môn.
- [x] 6.8 i18n `vi.ts`/`en.ts`:
  - Thêm khối `sportTypes` cho 8 môn.
  - Thêm nhãn "Môn thể thao" ở form sân và ở modal giá, nếu chưa có.
  - Xoá các key zone.

## 7. Frontend phía khách (design D7)

- [x] 7.1 `courtApi` xoá `getSportZones` và `getSportZone`.
- [x] 7.2 `useCourtsList.courtMatchesSport` so khớp `sport_type`; `football` khớp cả 5v5 và 7v7. Ô tìm kiếm dùng nhãn môn.
- [x] 7.3 `CourtItemCard`, `ScheduleCourtSelector`, `VenueCourtsGrid`, `CourtDetailView` và `UserBookingCard` lấy môn và nhãn từ `sport_type`.
- [x] 7.4 `homeAvailability.getCourtIcon`, `courtArea` (ô tìm kiếm dùng nhãn môn) và `lib/courtImage` dùng `sport_type`. Bỏ các nhánh đoán môn theo tên.
- [x] 7.5 `grep -rn "sport_zone\|sportZone\|SportZone" frontend/src` không còn kết quả.

## 8. Test frontend

- [x] 8.1 Sửa các spec đang dùng zone: `AdminVenueRoutesAndViews`, `PricingRuleModal`, `BookingDetailLoyalty`, `BookingView`, `CourtsView`, `ScheduleView` và `VenueDirectionsView`.
- [x] 8.2 Thêm spec form sân:
  - Payload gửi `sport_type` và `venue_id` của cơ sở ngầm.
  - Lưu mà không sửa địa chỉ thì không gọi `updateVenue`.
- [x] 8.3 Thêm spec `PricingRuleModal`: ô môn bị khoá khi chưa chọn cơ sở, và payload có `sport_type`.

## 9. Spec của change khác (design D9)

- [x] 9.1 Sửa câu chữ delta "One-hour booking slots" trong `openspec/changes/direct-owner-cutover/specs/reservations-and-pricing/spec.md` từ zone sang sân, giữ nguyên ý nghĩa.
- [ ] 9.2 `openspec validate remove-sport-zones --strict` và `openspec validate direct-owner-cutover --strict` đều qua.

## 10. Cổng kiểm tra local (memory `local-test-gates`, không bao giờ chạy vào prod)

- [x] 10.1 PHPUnit toàn bộ, trên DB `*_test` local, dùng config scratch có `force="true"`.
- [x] 10.2 `php vendor/bin/phpunit tests/Unit/Architecture`, `vendor/bin/pint --test`, và PHPStan level 0 và level 5 với `.env` tạm.
- [x] 10.3 `npm run type-check`, rồi `vitest --maxWorkers=1`. Không chạy cùng lúc với PHPUnit.
- [ ] 10.4 Mở form "Thêm sân mới" trên dev server local và chụp ảnh ô "Môn thể thao".

## 11. Rollout prod (chỉ làm khi người dùng đồng ý rõ ràng và chốt thời điểm)

- [ ] 11.1 Merge `--no-ff` vào `main` local theo memory `git-finish-flow`. Chưa push.
- [ ] 11.2 `pg_dump` DB `booking` trên bt-server vào `backend/storage/app/backups/`.
- [ ] 11.3 Chạy kiểm tra chỉ đọc (số rule chỉ theo zone, guard (a)), rồi chạy SQL M1 và thêm dòng `migrations`.
- [ ] 11.4 Chờ ít nhất 5 phút sau M1 (cache `/courts` 300 giây), rồi push `main` và chạy ngay `glab ci run -b main`. Đợi backend mới live.
- [ ] 11.5 Chạy guard M2. Nếu tất cả rỗng, chạy SQL drop M2 và thêm dòng `migrations`. Nếu có hàng nào, dừng lại và báo người dùng.
- [ ] 11.6 Smoke test: lọc theo môn, chi tiết sân, báo giá, form "Thêm sân mới".
