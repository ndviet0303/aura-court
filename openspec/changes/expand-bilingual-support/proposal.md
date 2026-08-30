## Why

The current frontend has full internationalization (i18n) support with synchronized `en.ts` and `vi.ts` locales, but the backend database schema only stores single-language text. To fully support multiple languages across the platform (Aura Court), we need to expand bilingual support to the database layer so dynamic content (like venue names, descriptions, court details) can be served in the user's preferred language.

## What Changes

- Integrate a multilingual package (e.g., `spatie/laravel-translatable`) into the Laravel backend to manage translations using JSON columns.
- Update core database tables (e.g., venues, courts, sport zones, tournaments) to migrate string columns (`name`, `description`) to JSON columns for translation support.
- Update corresponding Eloquent models to use the `Translatable` trait and define translatable attributes.
- Update API resources and controllers to handle locale-aware data retrieval and saving.

## Capabilities

### New Capabilities
- `localization/database-i18n`: Support for storing and retrieving multilingual data from the database using JSON columns.

### Modified Capabilities

## Impact

- **Database**: Migration required to change existing `string` or `text` columns to `json` columns for translatable fields.
- **Backend APIs**: Eloquent models, validation rules, and API Resources will need to handle array/JSON data for translated fields instead of plain strings.
- **Frontend**: API payloads for creating/updating entities and displaying dynamic text will need to respect the current active locale or send structural translation objects.
