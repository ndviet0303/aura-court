## Purpose

Provides mechanisms to store and retrieve database content in multiple languages, allowing users to see dynamic platform text in their preferred locale.

## ADDED Requirements

### Requirement: Multilingual Content Storage
The system SHALL support storing translated text for specific entities (e.g., Venues, Courts, Sport Zones) using structured JSON rather than plain strings.

#### Scenario: Saving translated content
- **WHEN** an admin creates or updates an entity with multilingual fields
- **THEN** the system saves the translations in a JSON structure containing supported locales (e.g., `{"en": "Name", "vi": "Tên"}`)

### Requirement: Locale-aware Data Retrieval
The system SHALL return the translated text matching the current active locale, or fallback to a default locale if the translation for the current locale is missing.

#### Scenario: Fetching data in user's locale
- **WHEN** a user requests data with the `Accept-Language` header or a locale parameter set to `vi`
- **THEN** the API returns the Vietnamese translation of the entity's multilingual fields
- **WHEN** the requested locale's translation is missing
- **THEN** the API returns the English (default) translation

### Requirement: Bilingual Data Validation
The API MUST validate that inputs for translatable fields provide structurally valid data for the supported locales.

#### Scenario: Invalid translation payload
- **WHEN** an API client submits a translation payload missing the default language (e.g., English)
- **THEN** the API returns a `422 Unprocessable Entity` validation error indicating the default language is required
