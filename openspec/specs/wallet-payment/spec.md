# Capability: Wallet Payment

## Purpose

Preserves immutable historical wallet balances, deposits, and legacy transaction records without automatic conversion or deductions, while ensuring new bookings, tournaments, and booking changes follow recipient-bound verified collection and owner-executed obligations.

## Requirements

### Requirement: Persisted tournament registration checkout
Tournament registration SHALL verify eligibility, deadlines, capacity and published fees server-side. Paid direct entry SHALL reserve capacity for a bounded interval and confirm one participant only from a verified receipt; free entry SHALL persist without debit or invented receipt. Retries SHALL return existing results; all administrative admission paths SHALL enforce capacity and fee or record an explicitly authorized fee waiver.

#### Scenario: Paid registration
- **WHEN** a matching receipt is verified before the registration deadline
- **THEN** one participant and recipient-scoped financial event SHALL persist

#### Scenario: Last place race
- **WHEN** two players compete for one place
- **THEN** at most one confirmed or exclusive pending place SHALL exist and excess receipts enter reconciliation

#### Scenario: Insufficient funds
- **WHEN** only a client success claim exists
- **THEN** no paid participant SHALL be confirmed

### Requirement: Verified wallet deposit journey
Historical tenant wallet deposits and balances SHALL remain readable and reconcile to source receipts. New pooled-platform top-ups SHALL be disabled for a cohort before direct-owner cutover. In-flight verified legacy deposits SHALL be processed or held in an explicit reconciliation queue, never discarded. Unknown redirect status SHALL not grant credit; no automated wallet-to-owner transfer SHALL be introduced.

#### Scenario: Duplicate confirmation
- **WHEN** a verified pre-cutover deposit event repeats
- **THEN** exactly one original credit SHALL remain

#### Scenario: New pooled deposit
- **WHEN** a cutover-cohort user attempts to create a new platform-collected top-up
- **THEN** the operation SHALL be unavailable with direct-payment guidance

#### Scenario: Unverified return
- **WHEN** a browser claims a legacy deposit succeeded without receipt
- **THEN** no wallet credit SHALL be granted

### Requirement: Legacy wallet conservation during booking changes
For historical wallet records, the platform SHALL preserve original journals/balances and reject unauthorized replay of retired operations. Restored direct-owner booking changes SHALL create owner-executed obligations, not wallet credits/debits. Independently authorized legacy compensation SHALL reference original allocations, never retroactively recalculate or convert direct receipts.

#### Scenario: Direct cancellation
- **WHEN** a post-cutover direct booking is cancelled
- **THEN** no wallet entry SHALL be created

#### Scenario: Historical refund viewed
- **WHEN** old wallet history includes a refund or pending legacy obligation
- **THEN** it SHALL remain accurately visible without automatic repayment or deletion

#### Scenario: Unpaid expiry
- **WHEN** an unpaid direct hold expires
- **THEN** inventory SHALL release without wallet movement

### Requirement: Legacy tournament wallet payment history
Recorded legacy wallet tournament payments SHALL remain immutable and replay-safe. New tournament registrations in a direct-owner cohort SHALL use the declared verified recipient's collection flow rather than creating new platform wallet custody. Any continued legacy closed-loop spend SHALL be explicitly restricted to its existing issuer and reconciled obligations.

#### Scenario: Old tournament receipt
- **WHEN** a historical wallet registration is read or retried
- **THEN** its original amount/reference SHALL remain and no new debit SHALL occur

#### Scenario: Direct tournament entry
- **WHEN** a new paid direct-owner tournament registration is initiated
- **THEN** it SHALL remain pending until verified recipient-bound payment and available capacity commit
