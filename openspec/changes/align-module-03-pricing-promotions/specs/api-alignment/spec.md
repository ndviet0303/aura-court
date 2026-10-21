## MODIFIED Requirements

### Requirement: Bilingual actionable error dialogs
Blocking API failures in venue/court management, organization access, POS, price and promotion management and customer booking checkout SHALL produce one actionable dialog in the selected Vietnamese or English locale. Capability-disabled, unauthorized, unauthenticated, validation/conflict, not-found, network and server failures SHALL be distinguishable through stable codes or explicit status mappings. All dialog titles, bodies, buttons, detail headings and accessibility labels SHALL be localized. Unknown failures MUST NOT expose stack traces, database messages or internal route names. Field validation SHALL retain its inline guidance where applicable; informational notices need not become dialogs.

#### Scenario: Organization capability is disabled
- **WHEN** a guarded request is rejected because the selected organization's capability is disabled
- **THEN** the dialog SHALL explain that the feature has not been enabled for this organization, rather than claiming the route is missing or the user's password is invalid

#### Scenario: Price coverage gap
- **WHEN** publishing a price table version or changing a court's link fails with `PRICE_COVERAGE_INCOMPLETE`
- **THEN** the dialog SHALL list each court, day of the week or date and uncovered interval returned, in the selected locale, and the entered prices SHALL stay in the form

#### Scenario: Voucher no longer applies at checkout
- **WHEN** a booking checkout fails with `VOUCHER_INVALID`
- **THEN** the dialog SHALL state the returned reason and the amounts without the voucher, and the customer SHALL be able to continue without it or choose another voucher

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
