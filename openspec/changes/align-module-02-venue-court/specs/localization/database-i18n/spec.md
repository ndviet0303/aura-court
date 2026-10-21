## MODIFIED Requirements

### Requirement: Bilingual Data Validation
The API MUST validate that inputs for translatable fields submitted as per-locale values provide structurally valid data for the supported locales. A venue name or court description submitted as a single text value SHALL be validated only by "Venue information validation" or "Court code identity".

#### Scenario: Invalid translation payload
- **WHEN** an API client submits a per-locale translation payload missing the default language (e.g., English)
- **THEN** the API returns a `422 Unprocessable Entity` validation error indicating the default language is required
