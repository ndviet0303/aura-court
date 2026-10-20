## MODIFIED Requirements

### Requirement: Multilingual Content Storage
The system SHALL support storing translated text for specific entities (e.g., Venues, Courts) using structured JSON rather than plain strings.

#### Scenario: Saving translated content
- **WHEN** an admin creates or updates an entity with multilingual fields
- **THEN** the system saves the translations in a JSON structure containing supported locales (e.g., `{"en": "Name", "vi": "Tên"}`)
