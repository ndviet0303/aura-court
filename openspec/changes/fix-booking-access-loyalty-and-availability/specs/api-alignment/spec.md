## ADDED Requirements

### Requirement: Administrative venue lists use the supported API contract
Administrative pages that require the existing venue list SHALL obtain it from the supported `/api/v1/admin/venues` contract under its existing authorization. They MUST NOT call the undefined `/api/v2/admin/venues` route or silently substitute an empty successful result when loading fails.

#### Scenario: Venue and court management load
- **WHEN** an authorized administrator opens `/admin/venues` or `/admin/manage`
- **THEN** the venue list SHALL load through the supported route and render its returned data without a route-not-found error

#### Scenario: Shared venue consumers load
- **WHEN** an authorized user opens staff, schedule, organization, pricing or shift reconciliation functionality that uses the same administrative venue-list contract
- **THEN** that functionality SHALL use the supported route with the existing response shape and authorization

### Requirement: Bilingual actionable error dialogs
Blocking API failures in venue/court management, organization access, POS and customer loyalty checkout SHALL produce one actionable dialog in the selected Vietnamese or English locale. Capability-disabled, unauthorized, unauthenticated, validation/conflict, not-found, network and server failures SHALL be distinguishable through stable codes or explicit status mappings. All dialog titles, bodies, buttons, detail headings and accessibility labels SHALL be localized. Unknown failures MUST NOT expose stack traces, database messages or internal route names. Field validation SHALL retain its inline guidance where applicable; informational notices need not become dialogs.

#### Scenario: Organization capability is disabled
- **WHEN** a guarded request is rejected because the selected organization's capability is disabled
- **THEN** the dialog SHALL explain that the feature has not been enabled for this organization, rather than claiming the route is missing or the user's password is invalid

#### Scenario: Locale changes
- **WHEN** the user selects Vietnamese or English before a failure or while its dialog is open
- **THEN** the dialog's text and controls SHALL use that locale without untranslated keys or fixed text from the other locale

#### Scenario: Concurrent failures
- **WHEN** several requests from the same screen fail for the same underlying capability condition
- **THEN** the interface SHALL show one dismissible dialog rather than stacked duplicates and SHALL preserve entered data or the POS cart

#### Scenario: Keyboard interaction
- **WHEN** a blocking error dialog opens
- **THEN** focus SHALL move into the dialog, remain within it while open, allow keyboard dismissal, and return to a sensible originating control after closing

#### Scenario: Unknown service failure
- **WHEN** an API returns an unrecognized internal error
- **THEN** the interface SHALL show a localized safe fallback and a support/reference code when available without presenting raw infrastructure details

### Requirement: POS loading respects resolved organization capabilities
The POS interface SHALL distinguish loading, enabled, disabled and failed capability resolution. It SHALL resolve capabilities for the active organization before starting dependent POS requests. Capability state SHALL be invalidated on logout and organization changes; a fetch failure MUST NOT be presented as a definitive feature-disabled result or reuse another organization's permissions. Direct API requests SHALL remain guarded independently of the interface.

#### Scenario: Disabled POS on entry
- **WHEN** the selected organization has POS disabled
- **THEN** the screen SHALL explain the disabled capability, avoid dependent POS loads and prevent checkout without fabricating a successful sale

#### Scenario: Capability lookup fails
- **WHEN** the capabilities request fails due to network or server error
- **THEN** the screen SHALL show a localized retryable failure and SHALL NOT conclude that the organization has no POS entitlement

#### Scenario: Organization changes
- **WHEN** a user switches from a POS-enabled organization to a disabled organization
- **THEN** stale capabilities and POS data SHALL NOT authorize operations in the new organization and late responses from the old context SHALL NOT replace the current context
