## Context

Xem proposal.md, mục Why. Hiện trạng liên quan tới thiết kế:

- **Bảng `sport_zones`.** Các cột: `venue_id`, `name`/`description` (JSON dịch), `sport_type`, `slot_duration`, `is_active`.
  - `courts.sport_zone_id` và `price_rules.sport_zone_id` là FK nullable, `nullOnDelete`.
  - Sân không có cột môn riêng. Môn, độ dài khung giờ (fallback 60) và mức giá theo zone đều lấy qua zone.
- **Ai đọc zone:**
  - Đọc `sportZone->slot_duration`: `PriceQuoteService`, `AvailabilityController`, `RecurringSeriesService`.
  - Chỉ eager-load `sportZone` mà không dùng: `SlotSelectionPolicy`, `LoyaltyPolicyService`.
  - `CourtPriceRuleService::appliesTo` và `PriceQuoteService::scopeRank` coi zone là phạm vi thứ 3: sân 4, zone 3, cơ sở 2, tổ chức 1.
- **Dữ liệu prod.** DB `booking` được reseed ngày 2026-10-19 từ `SportZoneCourtSeeder`: 6 venue, 21 zone, zone nào cũng có sân.
  - Seeder tạo zone bằng `updateOrCreate(['venue_id', 'sport_type'])`, nên mỗi cặp (cơ sở, môn) có đúng một zone.
  - Mọi zone có `slot_duration` 60 và `is_active` true.
  - Mọi `PriceRule` của seed là rule `base` theo **sân** (`court_id` có giá trị), kèm `sport_zone_id` trùng zone của sân. Seed không có rule nào chỉ theo zone.
  - Không đọc trực tiếp được prod trong phiên này. Vì vậy migration tự kiểm tra dữ liệu (xem D3) thay vì dựa vào giả định.
- **Frontend.**
  - `courtApi.getSportZones` công khai không có nơi gọi.
  - Endpoint chi tiết cơ sở (`/venues/{slug}`) trả zone lồng sân, nhưng `useVenueDirections` lấy sân từ store, không đọc phần lồng này.
  - Không có giao diện tạo hay sửa zone. `adminApi.createSportZone` và các hàm cùng nhóm không ai gọi.
  - Không form nào gửi `slot_duration`. Đây là Quyết định 10 của change `direct-owner-cutover`: khung giờ 1h nằm ở dữ liệu và câu chữ, không thêm ràng buộc mới.
- **Commit `679ccc90` (2026-10-15)** bỏ ô "Cơ sở" khỏi form sân và cho sửa địa chỉ cơ sở ngay trên form. Từ đó sân mới vào cơ sở đang lọc, hoặc cơ sở đang hoạt động đầu tiên.
- **Deploy.** Push `main` tự deploy frontend (Cloudflare Workers Builds). Backend chỉ deploy khi chạy `glab ci run -b main` bằng tay.
  - Migration prod chạy tay bằng SQL vì không SSH được vào container.
  - Cache công khai (`courts`, `venues`) sống 300 giây.

## Goals / Non-Goals

**Goals:**
- Không mã nào còn nhắc tới zone sau cutover. Không giữ bảng zone "để đó", không alias field `sport_zone` trong payload.
- Không sân nào đổi giá, môn hay độ dài khung giờ do migration. Nếu dữ liệu không cho phép điều đó, migration dừng mà không đổi gì.
- Không có khoảng thời gian code và schema lệch nhau gây lỗi 500.

**Non-Goals:**
- **Giao diện chỉnh `slot_duration`.** API sân nhận trường này như API zone từng nhận, nhưng form không hiện ô này. Giữ đúng Quyết định 10.
- **Bộ lọc `is_active` riêng cho nhóm môn.** Zone ngừng hoạt động hôm nay chỉ ẩn sân khỏi bộ lọc theo môn, không ẩn khỏi danh sách chung. Mỗi sân giữ `is_active` của chính nó.
- **Đổi cấu trúc trang chỉ đường, Google Maps hay tìm theo khu vực.**
- **Sửa `dacn_report.docx`.**

## Decisions

### D1. Sân mang `sport_type` và `slot_duration`
- **Cột.**
  - `courts.sport_type`: string, NOT NULL, default `'badminton'`.
  - `courts.slot_duration`: unsigned int, NOT NULL, default `60`.
  - Index `(venue_id, sport_type)`, thay index cùng tên cột của `sport_zones`.
  - Default trùng default cũ: `sport_zones.sport_type` mặc định là badminton, các service fallback 60 phút. Nhờ vậy sân không có zone hôm nay vẫn giữ được hành vi cũ, và fixture test chỉ cần ghi môn khi môn quan trọng với test đó.
- **Danh sách giá trị nằm một chỗ.** `Court::SPORT_TYPES` giữ 8 môn đang dùng ở `SportZoneController`. `Court::SLOT_DURATIONS = [30, 45, 60, 90, 120]` là đúng tập API zone đang nhận, nên đây không phải ràng buộc mới. `CourtController` và `PriceRuleController` dùng `Rule::in(...)` với hai hằng này.
- **Đọc độ dài khung giờ.** `PriceQuoteService`, `AvailabilityController` và `RecurringSeriesService` đọc `$court->slot_duration`, bỏ `?: 60` vì cột đã NOT NULL. Các chỗ eager-load `sportZone` bị xoá.
- **Phương án đã loại:**
  - *Enum hoặc CHECK ở DB.* Loại vì zone cũng không có ràng buộc này, và mỗi lần thêm môn sẽ phải thêm migration. Validate ở API là đủ, giống hôm nay.
  - *Không thêm `slot_duration`, coi mọi sân là 60.* Loại vì brief yêu cầu sân mang `slot_duration`, ba service đang đọc giá trị này, và Quyết định 10 đã hoãn việc bỏ setting này sang một change riêng.

### D2. Rule giá theo zone đổi sang "Cơ sở + môn"
- **Cột mới.** `price_rules.sport_type`, string nullable.
- **Phạm vi áp dụng.** Rule áp cho sân khi `(sport_type null hoặc trùng môn của sân)`, cộng các điều kiện tổ chức, cơ sở và sân như cũ.
- **Thứ tự hạng giữ nguyên:**
  - sân = 4;
  - `sport_type` trùng và cùng cơ sở = 3;
  - cùng cơ sở = 2;
  - tổ chức = 1.
- **Validate.** `sport_type` phải thuộc `Court::SPORT_TYPES`. Rule có môn thì phải có `venue_id`, vì zone luôn ngầm chỉ một cơ sở. `PriceRuleController::assertSportHasVenue` kiểm trạng thái sau khi gộp dữ liệu gửi lên với rule hiện có, thay cho `required_with:sport_type`: `required_with` bỏ lọt một PATCH chỉ gửi `venue_id: null` cho rule đã có môn, hoặc chỉ gửi `sport_type` cho rule toàn tổ chức. Nhờ đó không xuất hiện kiểu rule mới "một môn trên toàn tổ chức", và thứ hạng vẫn rõ ràng.
- **Đổi tên trong các lớp khác.**
  - `PriceRuleController::index` lọc theo `sport_type` thay cho `sport_zone_id`, và eager-load `venue` và `court`.
  - Frontend `PricingCalendarGrid` lặp lại đúng quy tắc áp dụng và thứ hạng này.
- **Vì sao an toàn nhất.** Zone thực chất là cặp (cơ sở, môn): seeder và `CreateVenue` đều tạo tối đa một zone mỗi cặp. Đổi sang cơ sở + môn giữ nguyên tập sân mà rule đang áp. Sân mới cùng môn vẫn tự nhận rule như khi thêm sân vào zone. Modal giá vẫn có ba cấp cơ sở → môn → sân. Trường hợp hiếm có hai zone cùng môn trong một cơ sở thì guard ở D3 phát hiện và dừng.
- **Phương án đã loại:**
  - *Chỉ theo sân.* Mỗi rule zone tách thành N rule theo sân. Cách này giữ đúng giá hiện tại, nhưng mất cấp giá "mọi sân pickleball của cơ sở X", sân thêm sau không nhận rule, và modal giá mất một cấp.
  - *Gộp vào theo cơ sở.* Không an toàn: cơ sở có nhiều môn sẽ bị đổi giá.

### D3. Migration hai bước, có guard
Postgres chạy mỗi migration trong một transaction. DDL cũng nằm trong transaction đó, nên guard ném lỗi thì không có gì thay đổi. Backfill dùng `UPDATE … FROM`. SQL riêng của Postgres đã có tiền lệ trong repo (`2026_08_30_144835`), và test cũng chạy trên pgsql.

**M1 `2026_10_20_000001_add_sport_fields_to_courts_and_price_rules`.** Bước này chỉ thêm, nên chạy trước deploy được.

1. Thêm `courts.sport_type`, `courts.slot_duration`, index `(venue_id, sport_type)` và `price_rules.sport_type`.
2. Backfill sân từ zone:
   ```sql
   UPDATE courts SET sport_type = z.sport_type, slot_duration = z.slot_duration
   FROM sport_zones z WHERE z.id = courts.sport_zone_id;
   ```
3. Backfill rule chỉ theo zone. Chỉ rule có cơ sở trống hoặc trùng cơ sở của zone mới được map. Rule có cơ sở lệch hôm nay không áp cho sân nào, nên giữ nguyên để guard (b) chặn.
   ```sql
   UPDATE price_rules SET sport_type = z.sport_type, venue_id = z.venue_id
   FROM sport_zones z
   WHERE z.id = price_rules.sport_zone_id AND price_rules.court_id IS NULL
     AND (price_rules.venue_id IS NULL OR price_rules.venue_id = z.venue_id);
   ```
4. `down()`: xoá ba cột mới và index.

Code cũ vẫn chạy với schema sau M1. Payload sân lúc đó đã có thêm `sport_type` và `slot_duration`, vì Eloquent trả mọi cột.

**M2 `2026_10_20_000002_drop_sport_zones`.** Chạy sau khi backend mới đã live. Guard chạy trước. Nếu một truy vấn trả về hàng nào, migration ném `RuntimeException` kèm id và không xoá gì.

```sql
-- (a) rule theo sân mà zone khác zone của sân: hôm nay không áp, sau khi bỏ zone sẽ áp
SELECT r.id FROM price_rules r JOIN courts c ON c.id = r.court_id
WHERE r.sport_zone_id IS NOT NULL AND r.sport_zone_id IS DISTINCT FROM c.sport_zone_id;
-- (b) rule chỉ theo zone chưa được M1 map đúng sang cơ sở + môn của zone
SELECT r.id FROM price_rules r JOIN sport_zones z ON z.id = r.sport_zone_id
WHERE r.court_id IS NULL
  AND (r.sport_type IS DISTINCT FROM z.sport_type OR r.venue_id IS DISTINCT FROM z.venue_id);
-- (c) zone có rule chỉ theo zone, nhưng tập sân của zone khác tập sân cùng cơ sở + môn
SELECT DISTINCT z.id FROM sport_zones z
JOIN courts c ON c.venue_id = z.venue_id OR c.sport_zone_id = z.id
WHERE EXISTS (SELECT 1 FROM price_rules r WHERE r.sport_zone_id = z.id AND r.court_id IS NULL)
  AND (c.sport_zone_id IS NOT DISTINCT FROM z.id)
      IS DISTINCT FROM (c.venue_id = z.venue_id AND c.sport_type = z.sport_type);
-- (d) môn hoặc độ dài khung giờ của sân lệch với zone (ghi chép giữa M1 và M2)
SELECT c.id FROM courts c JOIN sport_zones z ON z.id = c.sport_zone_id
WHERE c.sport_type <> z.sport_type OR c.slot_duration <> z.slot_duration;
```

Khi guard qua hết:
- `dropConstrainedForeignId('sport_zone_id')` trên `price_rules`, rồi trên `courts`. Postgres xoá luôn index của cột.
- Xoá bảng `sport_zones`.

`down()` của M2 dựng lại cấu trúc cũ:
- Tạo lại `sport_zones` (name và description kiểu JSON) cùng hai FK.
- Tạo một zone cho mỗi cặp `(venue_id, sport_type)` của sân, tên `{"vi": <tên môn>, "en": <tên môn>}`.
- Gắn lại `courts.sport_zone_id`. Gắn `price_rules.sport_zone_id` cho rule có `sport_type`.
- Từ chối nếu một cặp (cơ sở, môn) có nhiều `slot_duration`, theo tiền lệ `2026_10_11_000011`. Tên zone không khôi phục được. Rollback thật trên prod dùng bản `pg_dump`.

**Migration cũ `2026_08_28_000003`** chuyển sang `DB::table`, giữ nguyên hành vi. Lý do: trên DB mới, migration `2026_08_14_000001` chèn sẵn một venue. Vòng lặp vì thế chạy ít nhất một lần, và sẽ gọi model `SportZone` lúc này đã bị xoá. Prod đã chạy migration này rồi nên không bị ảnh hưởng.

**Phương án đã loại:**
- *Một migration duy nhất.* Chạy trước deploy thì code cũ lỗi vì thiếu bảng zone. Chạy sau deploy thì code mới lỗi vì thiếu `courts.sport_type`. Kiểu nào cũng có vài phút lỗi 500.
- *Để bảng zone lại, không dùng nữa.* Đó là shim.
- *Guard bằng vòng lặp PHP so tập sân trước và sau.* Bốn truy vấn SQL ở trên dùng lại được nguyên văn trong runbook prod chạy tay.

### D4. Endpoint và bộ lọc
- **Xoá.** Route `sport-zones` công khai và admin, `SportZoneController`, model `SportZone`, `Venue::sportZones()`, `Court::sportZone()`, `PriceRule::sportZone()`. Router trả 404 cho các đường dẫn đã xoá.
- **`CourtController`:**
  - `index` và `adminIndex` lọc `where('sport_type', …)`.
  - Bỏ tham số `sport_zone_id` khỏi truy vấn và khỏi khoá cache.
  - `store` và `update` validate `sport_type` (bắt buộc khi tạo) và `slot_duration` (không bắt buộc).
- **`EloquentVenueRepository`:**
  - `getActiveVenuesWithCourts` lọc `whereHas('courts', active + sport_type)`.
  - `findBySlug` eager-load `courts` đang hoạt động kèm `priceSlots`, thay cho `sportZones.courts.priceSlots`. Vẫn đủ thông tin sân, chỉ bỏ một tầng lồng.
- **`AdminController::venues` (admin).** Eager-load `courts:id,venue_id,sport_type` của sân đang hoạt động, để thẻ Cơ sở hiện các môn.
- **`CreateVenue`.** Không tạo zone và trả về `$venue`. `UpdateVenue` trả `fresh()`. `EloquentVenueLifecycleRepository` bỏ `sportZones` khỏi eager-load.

### D5. Form sân admin: chỉ thay ô zone bằng ô "Môn thể thao"
- **Một ô thay cho ô zone.** "Môn thể thao": 8 môn, nhãn từ i18n `sportTypes`, mặc định `badminton` như default ở DB.
- **Cơ sở vẫn ngầm như commit `679ccc90`.** Form không có ô "Cơ sở". Sân mới vào cơ sở đang lọc, hoặc cơ sở đang hoạt động đầu tiên khi bộ lọc là "Tất cả". Sửa sân thì giữ cơ sở của sân. Ô địa chỉ cơ sở ngay trên form giữ nguyên hành vi: chỉ ghi vào cơ sở khi người dùng đã sửa.
- **Đã duyệt.** Người dùng duyệt ngày 2026-10-19: "form sân chỉ thay zone bằng ô Môn thể thao, giữ cơ sở ngầm như commit 679ccc90".
- **Phương án đã loại:** *Thêm lại ô "Cơ sở".* Bản nháp đầu đề xuất vì chủ có nhiều cơ sở có thể đặt nhầm sân khi bộ lọc là "Tất cả". Người dùng chọn giữ form gọn như `679ccc90`.
- **Giới hạn số dòng.** Ô môn thay chỗ ô zone nên `CourtFormModal.vue` gần như không dài thêm. Nếu vẫn vượt trần trong `frontend/src/__tests__/architecture-allowlist.json` thì tách component, không nâng trần.

### D6. Giá, thẻ Cơ sở, bảng sân (admin)
- **`PricingRuleModal`.**
  - Ô "Môn thể thao" chỉ bật khi đã chọn Cơ sở. Lựa chọn là các môn có trong sân của cơ sở đó.
  - Danh sách sân lọc theo cơ sở và môn đã chọn.
  - Payload gửi `sport_type`.
- **`usePricingCalendar` và `useAdminManageCourts`.** Không gọi `getSportZones` nữa. `useAdminVenues` cũng vậy.
- **Thẻ Cơ sở và bảng sân.**
  - `VenueCard` hiện chip theo các `sport_type` khác nhau trong `venue.courts`.
  - `AdminCourtsTable` hiện chip tên môn.
- **i18n.**
  - Thêm khối `sportTypes` ở cấp gốc trong `vi.ts`/`en.ts`. Đây là nguồn nhãn môn duy nhất cho cả admin lẫn trang khách.
  - Xoá các key zone: `admin.manage.sportZoneLabel`, `admin.manage.defaultSportZone`, `admin.pricing.sportZoneLabel`, `admin.pricing.allSportZones` và các key zone còn lại.

### D7. Trang khách
- **Thay nguồn dữ liệu.**
  - `sport_zone?.sport_type` thành `sport_type` ở icon, ảnh mặc định (`courtImage`), `CourtSpecsGrid` và lịch sử đặt sân.
  - `sport_zone?.name` thành nhãn `sportTypes.<sport_type>`, ở thẻ sân, bộ chọn sân trang lịch và lưới sân trang chỉ đường.
- **`courtMatchesSport`.** So khớp trực tiếp `sport_type`; `football` khớp cả `football_5v5` và `football_7v7`.
  - Bỏ các nhánh đoán môn theo tên, mô tả hay tên zone, vì giờ sân nào cũng có môn.
  - Tương tự, `getCourtIcon` bỏ nhánh đoán theo tên.
- **Ô tìm trên trang chủ (`courtArea`).** Thay tên zone bằng nhãn môn theo ngôn ngữ đang chọn, để gõ "cầu lông" vẫn ra sân cầu lông.
- **Type.** Bỏ type `SportZone`. `Court` thêm `sport_type: SportType` và `slot_duration: number`. `Venue` bỏ `sport_zones`.

### D8. Seeder và test
- **Seeder.**
  - `SportZoneCourtSeeder` đổi tên thành `CourtSeeder`. Mỗi định nghĩa sân đã có sẵn `sport_type`, nên chỉ cần bỏ khối dữ liệu zone và `zone_key`.
  - Rule `base` theo sân bỏ `sport_zone_id`.
  - Các seeder khác đọc `$court->sport_type` và `$court->slot_duration`.
  - `DatabaseSeeder` gọi `CourtSeeder`.
- **Test có sẵn.** Fixture tạo zone đổi thành `Court::create([... 'sport_type' => …])`.
- **Test mới:**
  - **Migration.** Gọi `down()` của M2 rồi M1 để dựng lại zone. Seed sân, rule theo zone và rule theo sân. Gọi `up()` và kiểm tra backfill và giá. Thêm một case guard (b) hoặc (c) ném lỗi và DB giữ nguyên. DDL của Postgres nằm trong transaction nên `RefreshDatabase` rollback được hết.
  - **Rule giá cơ sở + môn.** Kiểm tra thứ hạng sân > cơ sở+môn > cơ sở, và lỗi validate khi có môn mà thiếu cơ sở.
  - **Bộ lọc.** `/courts?sport_type=`, `/venues?sport_type=`, và `/sport-zones` trả 404.
  - **Sân.** Tạo sân có môn, môn không hợp lệ trả 422, `slot_duration` mặc định 60, lịch trống dùng `slot_duration` của sân.
  - **Frontend.** Spec của `CourtFormModal`/`useAdminManageCourts` (payload có `sport_type` và cơ sở ngầm) và `PricingRuleModal`.

### D9. Change `direct-owner-cutover`
Delta "One-hour booking slots" của change này còn viết "venue setup SHALL create zones with a one-hour slot length" và "every seeded zone SHALL have a 60-minute slot length". Sửa thành sân: "new courts SHALL default to a one-hour slot length" và "every seeded court…". Ý nghĩa không đổi; change đó vẫn archive được trước hay sau change này.

## Risks / Trade-offs

- **[Frontend deploy trước backend]** Push `main` tự deploy frontend, còn backend chờ chạy pipeline bằng tay.
  - Đọc: sau M1, backend cũ đã trả `sport_type` và `slot_duration`, nên trang khách đọc đúng — nhưng chỉ khi cache 300 giây của `/courts` và `/venues` đã hết hạn. Khoá cache không đổi khi chạy M1 bằng `psql`, nên một bản cache từ trước M1 vẫn thiếu `sport_type`, và frontend mới sẽ vỡ thẻ sân. → Chờ ít nhất 5 phút sau khi commit M1 rồi mới push (bước 5).
  - Ghi: backend cũ bỏ qua trường `sport_type`. Rule giá tạo trong khoảng này sẽ thành rule theo cơ sở, sân mới sẽ thành cầu lông.
  - → Chạy M1 trước khi push. Chạy pipeline backend ngay sau push. Không sửa giá hay sân trong vài phút đó (hệ thống demo, người dùng là chủ duy nhất).
- **[Ghi chép giữa M1 và M2]** Zone hay sân tạo bằng code cũ sau M1 sẽ lệch dữ liệu. → Guard (b), (c), (d) chặn M2, báo id để xử lý tay. Không có gì bị xoá.
- **[Hai zone cùng môn trong một cơ sở, có rule chỉ theo zone]** → Guard (c) chặn. Seed không tạo trường hợp này.
- **[Payload đổi hình dạng (BREAKING)]** Chỉ frontend của repo dùng các endpoint này. Không có app di động hay client ngoài. → Đổi cả hai phía trong cùng change.
- **[Mất tên và mô tả zone]** Tên zone kiểu "Khu Cầu Lông Cầu Giấy" chỉ hiện như nhãn phụ. → Thay bằng tên môn. `down()` không khôi phục được tên; bản `pg_dump` thì có.
- **[Zone ngừng hoạt động]** Sân thuộc zone ngừng hoạt động sẽ hiện lại dưới bộ lọc môn. Seed không có zone nào như vậy. → Ghi nhận; mỗi sân dùng `is_active` của chính nó.
- **[Trần số dòng file]** Backend: các file phần lớn ngắn đi. Frontend: `CourtFormModal.vue` dài thêm. → Chạy `php vendor/bin/phpunit tests/Unit/Architecture` và test kiến trúc frontend. Tách component nếu cần. Không nâng trần.

## Migration Plan

Chỉ chạy khi người dùng đã duyệt và chốt thời điểm. Không chạy artisan vào prod; dùng `psql` với thông số DB trong `.env` của checkout chính, theo memory `bt-server-demo-reseed`.

1. Merge vào `main` local theo memory `git-finish-flow`. Chưa push.
2. `pg_dump -Fc --no-owner --no-privileges` DB `booking`. Lưu vào `backend/storage/app/backups/booking-bt-server-<YYYYMMDD-HHMM>-before-remove-sport-zones.dump`.
3. Kiểm tra chỉ đọc (`PGOPTIONS='-c default_transaction_read_only=on'`): số rule chỉ theo zone, và guard (a). Kỳ vọng 0 và 0, theo dữ liệu seed.
4. Chạy SQL của M1 trong một transaction với `SET lock_timeout = '30s'`. Thêm dòng `migrations` cho M1 với batch = max + 1.
5. Chờ ít nhất 5 phút sau bước 4 để cache `/courts` và `/venues` (300 giây) hết hạn. Sau đó push `main` (frontend tự deploy) và chạy ngay `glab ci run -b main` để deploy backend. Đợi image mới live.
6. Chạy bốn truy vấn guard của M2. Nếu tất cả rỗng, chạy SQL drop của M2 trong một transaction và thêm dòng `migrations` cho M2. Nếu có hàng nào, dừng lại và báo người dùng.
7. Smoke test: `/api/courts?sport_type=pickleball`, một trang chi tiết sân, một báo giá, và form "Thêm sân mới" ở admin.

**Rollback:**
- Trước bước 6: trước khi revert code, liệt kê các hàng code mới đã ghi mà không có zone: `SELECT id FROM price_rules WHERE sport_type IS NOT NULL AND sport_zone_id IS NULL` và `SELECT id FROM courts WHERE sport_zone_id IS NULL`. Code cũ sẽ coi rule như vậy là rule theo cả cơ sở, và đưa sân như vậy về cầu lông 60 phút. Gắn từng hàng vào zone cùng cơ sở và môn bằng tay (kiểm tra cả `slot_duration`), hoặc restore bản dump ở bước 2. Sau đó mới revert code, deploy lại và xoá ba cột của M1. `down()` của M1 từ chối chạy khi còn hàng như vậy.
- Sau bước 6: restore bản dump ở bước 2, theo quy trình `pg_restore` trong memory `bt-server-demo-reseed`, và deploy lại commit trước change.
