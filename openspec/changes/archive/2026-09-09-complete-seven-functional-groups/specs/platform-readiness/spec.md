## ADDED Requirements

### Requirement: Seven functional area acceptance evidence
Completion SHALL require a traceable acceptance matrix for account/access, venue/court, pricing/promotion, booking, payment/finance, on-site operations and customer/community. Each area SHALL include persisted success after reload, invalid input, authorization/tenant isolation and relevant retry/conflict evidence. Provider-dependent capabilities SHALL retain existing go-no-go gates and SHALL NOT be declared operational without provider evidence.

#### Scenario: Full acceptance
- **WHEN** all seven areas pass against real test APIs and an isolated database
- **THEN** the release records results, environment and links to tests

#### Scenario: Mock-only flow
- **WHEN** a required flow passes only with business API mocks or lacks provider evidence
- **THEN** the area remains incomplete or explicitly blocked

### Requirement: Independent package rollout
Each delivery package SHALL have independently reversible activation and recorded entry/exit evidence. Rollback SHALL preserve completed payments, attendance and historical records and SHALL NOT restore false-success paths.

#### Scenario: Package rollback
- **WHEN** a POS integrity failure is found in pilot
- **THEN** new affected operations are disabled without deleting committed receipts or journals

