## 1. Setup and Package Installation

- [x] 1.1 Install `spatie/laravel-translatable` via Composer
- [x] 1.2 Publish the configuration file for `laravel-translatable`

## 2. Database Migrations

- [x] 2.1 Create a migration to change `name` and `description` in `venues` table to JSON format, including data migration logic
- [x] 2.2 Create a migration to change `name` and `description` in `courts` table to JSON format, including data migration logic
- [x] 2.3 Create a migration to change `name` and `description` in `sport_zones` table to JSON format, including data migration logic

## 3. Core Implementation

- [x] 3.1 Update `Venue` model to use `Translatable` trait and define translatable attributes
- [x] 3.2 Update `Court` model to use `Translatable` trait and define translatable attributes
- [x] 3.3 Update `SportZone` model to use `Translatable` trait and define translatable attributes
- [x] 3.4 Update generic CRUD requests/validations to handle translation JSON payloads instead of flat strings

## 4. API & Resource Updates

- [x] 4.1 Update API Resources (e.g., `VenueResource`, `CourtResource`) to ensure translatable fields are properly serialized based on current locale
- [x] 4.2 Add locale header middleware (or verify existing) to capture `Accept-Language` and set the Laravel application locale

## 5. Verification

- [x] 5.1 Run migrations and verify no data loss occurred
- [x] 5.2 Test creating and fetching a Venue with bilingual strings via API endpoints
