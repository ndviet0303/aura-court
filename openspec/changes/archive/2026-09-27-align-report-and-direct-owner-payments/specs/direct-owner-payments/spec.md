## Purpose

Record verified payments directly to each facility operator without treating the platform as custodian of booking proceeds, and support auditable owner-executed refunds.

## ADDED Requirements

### Requirement: Verified owner collection connection
An authorized owner SHALL register the receiving bank account and manually configure a SePay connection using platform-provided instructions and a connection-specific webhook credential. The platform MUST verify control/authorization and a successful authenticated test before enabling direct online collection. Secrets SHALL remain server-side, encrypted, scoped, rotatable and absent from responses/logs. Bank login passwords MUST NOT be requested by Aura Court.

#### Scenario: Incomplete connection
- **WHEN** an owner has entered only a bank account number
- **THEN** the account can be shown as draft but online collection and publication readiness SHALL fail with setup guidance

#### Scenario: Verified connection and rotation
- **WHEN** an authorized owner completes connection verification or rotates the webhook secret
- **THEN** the connection SHALL retain verification/audit history and reject messages authenticated for another or revoked connection

#### Scenario: Receiving account changes
- **WHEN** a verified receiver is changed while payment attempts remain open
- **THEN** existing attempts SHALL retain their receiver snapshot; new attempts SHALL require verified replacement configuration, without silently redirecting old QR payments

### Requirement: Receiver-bound payment evidence
Each payable obligation SHALL have an immutable amount, currency, receiver, purpose, reference and expiry snapshot. QR presentation and browser callbacks MUST NOT establish payment. Authenticated provider events or independently verified server queries SHALL be matched to the correct connection, receiver, incoming direction, amount, currency where available, reference and admissible time. Provider fields unavailable in a bank feed SHALL be resolved by verified connection configuration, not client assertions. Duplicate events MUST be idempotent within provider/connection identity and one receipt MUST NOT fund multiple obligations.

#### Scenario: Correct payment
- **WHEN** a verified incoming transfer matches one live booking obligation
- **THEN** one immutable receipt SHALL be allocated and sufficient payment SHALL confirm its still-exclusive inventory exactly once

#### Scenario: Wrong or late payment
- **WHEN** a transfer has a foreign receiver, wrong amount, ambiguous reference, invalid authentication or arrives after release
- **THEN** no booking SHALL be silently confirmed; authenticated unmatched transfers SHALL enter reconciliation, and late paid funds SHALL remain a traceable owner refund/reallocation obligation

#### Scenario: Concurrent expiry and callback
- **WHEN** expiry and verified payment compete for a hold
- **THEN** one serialized outcome SHALL win; no court overlap, duplicate receipt allocation or lost-money obligation SHALL occur

#### Scenario: Client claim
- **WHEN** a customer or cashier posts a success flag, reference or screenshot alone
- **THEN** the operation SHALL remain unverified and SHALL NOT create paid financial effects

### Requirement: Owner-executed refund obligations
Whole/partial booking cancellation, price reductions and supported compensations SHALL create versioned refund obligations bounded by original verified paid amounts minus previous refunds and unresolved allocations. The owner SHALL execute the bank transfer; Aura Court SHALL track requester, policy calculation, recipient, due date, evidence and actor. An owner report of transfer SHALL be distinguished from independently verified completion or recipient acknowledgement. Dispute decisions MUST NOT themselves mark money as returned. No automatic bank debit or wallet credit SHALL be implied.

#### Scenario: Partial refund
- **WHEN** an approved change reduces the customer's paid obligation
- **THEN** the platform SHALL preserve original receipts and record the exact refundable allocation once without transferring money

#### Scenario: Owner reports refund
- **WHEN** the owner submits transfer evidence
- **THEN** status SHALL reflect reported execution until evidence is verified or the recipient acknowledges, with audit and recipient notification

#### Scenario: Over-refund or repeat
- **WHEN** a repeated or concurrent instruction exceeds remaining refundable value
- **THEN** the platform SHALL reject excess and preserve the existing obligation/outcome

#### Scenario: Overdue refund
- **WHEN** a refund passes its recorded deadline without completion
- **THEN** the owner SHALL be reminded and the case SHALL be eligible for escalation without claiming a completed refund

### Requirement: Separate platform subscription receiver
Subscription fees SHALL be collected for Aura Court using a platform-specific receiving connection and purpose namespace. Venue revenue and subscription receipts MUST NOT be interchangeable, netted, or routed through each other's settlement accounts.

#### Scenario: Receiver confusion
- **WHEN** a valid owner-bank receipt is submitted as a subscription payment
- **THEN** the subscription SHALL remain inactive and the receipt SHALL be reconciled only in its actual receiving context

