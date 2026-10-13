## MODIFIED Requirements

### Requirement: Verified owner collection connection
An authorized owner SHALL register the receiving bank account and manually configure a SePay connection using platform-provided instructions and a connection-specific webhook credential. The webhook credential SHALL be presented in an authentication form that the provider supports for its bank-transaction callbacks; for SePay this is its API-key authorization header, and the setup instructions SHALL name that form. The platform MUST verify control/authorization and a successful authenticated test before enabling direct online collection. Secrets SHALL remain server-side, encrypted, scoped, rotatable and absent from responses/logs. Bank login passwords MUST NOT be requested by Aura Court.

#### Scenario: Incomplete connection
- **WHEN** an owner has entered only a bank account number
- **THEN** the account can be shown as draft but online collection and publication readiness SHALL fail with setup guidance

#### Scenario: Verified connection and rotation
- **WHEN** an authorized owner completes connection verification or rotates the webhook secret
- **THEN** the connection SHALL retain verification/audit history and reject messages authenticated for another or revoked connection

#### Scenario: Receiving account changes
- **WHEN** a verified receiver is changed while payment attempts remain open
- **THEN** existing attempts SHALL retain their receiver snapshot; new attempts SHALL require verified replacement configuration, without silently redirecting old QR payments

#### Scenario: Provider callback uses its supported credential form
- **WHEN** SePay delivers a bank-transaction callback carrying the connection's credential in its API-key authorization header
- **THEN** the callback SHALL be authenticated for that connection, while a missing, wrong, rotated or another connection's credential SHALL be rejected without creating a receipt

### Requirement: Receiver-bound payment evidence
Each payable obligation SHALL have an immutable amount, currency, receiver, purpose, reference and expiry snapshot. QR presentation and browser callbacks MUST NOT establish payment. Authenticated provider events or independently verified server queries SHALL be matched to the correct connection, receiver, incoming direction, amount, currency where available, reference and admissible time. Provider fields unavailable in a bank feed SHALL be resolved by verified connection configuration, not client assertions. Duplicate events MUST be idempotent within provider/connection identity and one receipt MUST NOT fund multiple obligations. Received money that cannot fund a live obligation SHALL remain a traceable finance exception that the owner resolves outside the platform; the platform SHALL NOT create a refund obligation for it or claim that it was returned.

#### Scenario: Correct payment
- **WHEN** a verified incoming transfer matches one live booking obligation
- **THEN** one immutable receipt SHALL be allocated and sufficient payment SHALL confirm its still-exclusive inventory exactly once

#### Scenario: Wrong or late payment
- **WHEN** a transfer has a foreign receiver, wrong amount, ambiguous reference, invalid authentication or arrives after release
- **THEN** no booking SHALL be silently confirmed; authenticated unmatched or late transfers SHALL enter reconciliation as traceable finance exceptions without a refund obligation

#### Scenario: Concurrent expiry and callback
- **WHEN** expiry and verified payment compete for a hold
- **THEN** one serialized outcome SHALL win; no court overlap, duplicate receipt allocation or lost-money obligation SHALL occur

#### Scenario: Client claim
- **WHEN** a customer or cashier posts a success flag, reference or screenshot alone
- **THEN** the operation SHALL remain unverified and SHALL NOT create paid financial effects

## REMOVED Requirements

### Requirement: Owner-executed refund obligations
**Reason**: The thesis report no longer includes booking cancellation, refunds or rescheduling. Customers pay the owner directly and the platform offers no refund workflow. A customer who cannot attend can resell the slot.
**Migration**: Remove the refund request, quote, approval, execution-report and acknowledgement endpoints and their interfaces. Existing refund obligation, allocation and evidence records stay as read-only history; they are not deleted or recalculated. Money that arrives without a live obligation becomes a finance exception that the owner resolves outside the platform.
