## MODIFIED Requirements

### Requirement: Rehearsed data migration and rollback
The platform SHALL provide a rehearsed migration plan that backfills each legacy venue into an organization and preserves legacy booking history. It SHALL preserve `/api/v1` booking responses until the V1 retirement gate defined by reservation API compatibility passes. After that gate passes, legacy booking history SHALL remain readable through the mapped reservations. The plan MUST include counts/checksums, a compatibility period that ends only through that gate, and a tested rollback path before pilot data is migrated.

#### Scenario: Migration rehearsal succeeds
- **WHEN** a representative production-like data copy is migrated in rehearsal
- **THEN** the recorded source and target counts/checksums SHALL reconcile and legacy booking history SHALL remain readable

#### Scenario: Rehearsal detects a mismatch
- **WHEN** reconciliation detects a missing or altered legacy record
- **THEN** pilot migration SHALL be blocked and the rollback procedure SHALL restore the prior serving path

#### Scenario: Compatibility period ends
- **WHEN** the V1 retirement gate passes and V1 booking endpoints are withdrawn
- **THEN** the count of historical bookings SHALL equal the count of their reservation mappings, and every historical booking SHALL remain readable with its original code, amounts and status

#### Scenario: Rollback after V1 retirement
- **WHEN** the release that withdrew the V1 booking endpoints is rolled back
- **THEN** the prior release SHALL serve V1 again from the unchanged historical tables, without data restoration, and reservations created in the meantime SHALL remain valid
