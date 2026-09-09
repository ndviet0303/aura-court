## ADDED Requirements

### Requirement: Authoritative mutation outcomes
Business UI SHALL show success, clear drafts or change displayed persisted inventory only after the server confirms the operation. Rejected requests SHALL retain user input and display the error; ambiguous network failures SHALL allow lookup or same-key retry without duplicate effects. Business data placeholders SHALL be absent from operational paths.

#### Scenario: POS request fails
- **WHEN** the order API rejects a sale
- **THEN** no success receipt appears and the cart remains available

#### Scenario: Lost response
- **WHEN** the server commits but the response is lost
- **THEN** retrying the logical operation retrieves its existing outcome

### Requirement: Capability-aware interface
The interface SHALL reflect effective organization permissions and feature availability. Disabled capabilities SHALL be clearly unavailable, not replaced by synthetic successful behavior; direct API access SHALL remain guarded.

#### Scenario: Disabled POS
- **WHEN** POS is disabled for the selected organization
- **THEN** the UI explains unavailability and cannot simulate a completed sale

