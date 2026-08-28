## 1. Database and Models

- [x] 1.1 Create `SportZone` model and `sport_zones` database migration
- [x] 1.2 Add `sport_zone_id` to `courts` table
- [x] 1.3 Write data migration to create default Badminton sport zones for existing venues and reassign their courts

## 2. Backend Pricing and Time Slots

- [x] 2.1 Refactor time slot generator to read `slot_duration` from `SportZone` instead of hardcoded 60 minutes
- [x] 2.2 Refactor pricing calculator to resolve rules against `SportZone` instead of `Venue`
- [x] 2.3 Update API endpoints to accept `sport_type` parameter for searching venues

## 3. Frontend Implementation

- [x] 3.1 Update Venue Admin UI to allow creating and managing Sport Zones
- [x] 3.2 Update Venue Admin UI to assign courts to specific Sport Zones
- [x] 3.3 Add Sport Type selector to the customer-facing home page search
- [x] 3.4 Group courts by Sport Zone on the venue details page UI

