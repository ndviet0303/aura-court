## Context

The platform currently relies on static translation files (`en.ts`, `vi.ts`) in the frontend for static text. However, dynamic user-generated content or system data (Venues, Courts, Sport Zones) are stored as single-language strings in PostgreSQL. See proposal.md for motivation.

## Goals / Non-Goals

**Goals:**
- Implement a robust solution for storing multilingual strings in the database.
- Ensure the API can handle locale-aware serialization.
- Provide a clear migration path for existing single-language data.

**Non-Goals:**
- Automatic machine translation of user content.
- Frontend UI development for the admin panel (this focuses on the backend implementation and API contracts).
- User-generated content translation for chat messages or dispute cases (we will focus on master data like venues, courts, and sport zones first).

## Decisions

### 1. Database Storage Format
**Decision**: Use PostgreSQL `json` (or `jsonb`) columns to store translations instead of a separate translations table per entity.
- **Rationale**: The `spatie/laravel-translatable` package uses JSON columns which perfectly fit PostgreSQL's native JSONB support. It avoids complex JOINs and minimizes the number of new tables.
- **Alternatives**:
  - *Entity-Attribute-Value (EAV)*: Too complex and slow to query.
  - *Separate Translation Tables (e.g. `venue_translations`)*: Normalizes data perfectly but requires many new tables and complicated relationships. JSONB provides the best balance of performance and developer experience in modern Laravel.

### 2. Package Selection
**Decision**: Use `spatie/laravel-translatable`.
- **Rationale**: It is the industry standard for Laravel JSON-based translations. It integrates seamlessly with Eloquent models using a `Translatable` trait, reducing boilerplate.

### 3. API Payload Format
**Decision**: When creating/updating, the API will accept structural arrays: `{"name": {"en": "...", "vi": "..."}}`.
- **Rationale**: This allows admins to save multiple translations in a single request.
- **Alternatives**: Accept plain strings `{"name": "..."}` and infer the locale from a header. This is harder for admin interfaces where users edit multiple languages simultaneously.

## Risks / Trade-offs

- **Risk**: Existing data migration could result in data loss if not carefully handled.
  - **Mitigation**: Create a multi-step migration: 1. Rename old column to `name_old`. 2. Add new JSON `name` column. 3. Backfill `name` JSON with `name_old` as the default locale (`{"en": "value"}`). 4. Drop `name_old`.
- **Risk**: Searching/filtering by name becomes more complex with JSONB columns.
  - **Mitigation**: Use PostgreSQL JSONB querying operators (e.g., `name->>'en' ILIKE '%term%'`) and add appropriate GIN indexes if search performance degrades.

## Migration Plan

1. Install `spatie/laravel-translatable`.
2. Generate migrations to convert target columns (`name`, `description` in `venues`, `courts`, `sport_zones`) to JSON.
3. Write an Artisan command or migration logic to convert existing string data into `{ "en": "string", "vi": "string" }` format.
4. Update Models to include the `Translatable` trait.
5. Update API Requests and Resources to support structured translation objects.
