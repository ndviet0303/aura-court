## MODIFIED Requirements

### Requirement: Versioned reservation API compatibility
V1 and V2 SHALL remain supported projections of one authoritative reservation/inventory identity during migration. New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions; confirmation SHALL require verified payment, or a documented zero-payable outcome. The former cancellation/rescheduling retirement SHALL be superseded by policy-governed requests. Historical wallet bookings SHALL retain recorded amounts, payment model and terms; old idempotency records MUST NOT authorize a different new command.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a client retrieves a migrated historical wallet booking
- **THEN** the original code, status, financial references and ownership history SHALL remain readable

#### Scenario: New client creates a reservation
- **WHEN** an eligible client creates the same direct-payment booking through either supported version
- **THEN** the result SHALL reference one pending obligation and inventory hold without debiting a wallet

#### Scenario: Insufficient payment
- **WHEN** only partial or unverified funds are received
- **THEN** the booking SHALL remain unconfirmed and unpaid inventory SHALL follow its captured expiry policy

#### Scenario: Stale client invokes a retired command
- **WHEN** a stale old key is used against the new cancellation/change workflow
- **THEN** the server SHALL reject incompatible replay or return only its original outcome, never mutate the new model by replaying an old response

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** a direct-cohort customer has insufficient legacy wallet balance but requests a valid direct-owner booking
- **THEN** the server SHALL create the policy-bound pending direct payment without a wallet debit; confirmation SHALL still require verified owner payment or a legitimate zero-payable result

### Requirement: Immutable price quotes
The platform SHALL calculate quotes from effective-dated tenant/venue/court rules using holiday/date exception, peak, weekly then explicitly configured base precedence, with deterministic scope/priority tie-breaking. Prices and allowed coupon, membership and loyalty benefits SHALL snapshot versions and validity. A random unmatched slot price MUST NOT be a fallback. Later changes SHALL not alter an accepted booking. An expired quote SHALL be recalculated and accepted again before payment.

#### Scenario: Exception overrides a weekly price
- **WHEN** both a holiday exception and weekly rule match
- **THEN** both API versions and displayed availability SHALL use the same exception amount

#### Scenario: No applicable price
- **WHEN** a requested open interval has no applicable rule or configured base
- **THEN** it SHALL not be sold using the first unrelated price

#### Scenario: Price rules change after quote acceptance
- **WHEN** a policy changes after quote acceptance
- **THEN** the accepted snapshot SHALL remain stable; an expired unaccepted quote requires renewed consent

### Requirement: Active inventory exclusivity
No overlapping active entitlement or exclusive hold SHALL exist for the same court-time across V1/V2 bookings, recurring occurrences, owner-hosted sessions and tournaments using that court. Interval duration, venue calendar, book-ahead horizon, continuity policy and final eligibility SHALL be checked atomically at allocation and replacement. Held and confirmed states SHALL both exclude competitors.

#### Scenario: Concurrent reservation attempts
- **WHEN** 100 eligible requests target one available court-time
- **THEN** exactly one hold SHALL succeed and 99 SHALL receive valid conflicts without financial side effects

#### Scenario: Overlapping duration
- **WHEN** different start times overlap a reserved interval or hosted session
- **THEN** the later allocation SHALL fail, not merely compare identical start timestamps

#### Scenario: Configured booking window
- **WHEN** slots violate a venue's book-ahead or continuity rule
- **THEN** all versions SHALL reject the selection consistently

### Requirement: Trigger real-time status hooks
Committed hold, confirmation, expiry, cancellation, reschedule and operational readiness changes SHALL publish scoped inventory updates after commit. Rejected or rolled-back actions SHALL not publish success or released availability.

#### Scenario: Successful reservation triggers hook
- **WHEN** a reservation commits
- **THEN** authorized viewers SHALL receive its actual court-time state

#### Scenario: Unpaid inventory expires
- **WHEN** an unpaid hold expires durably
- **THEN** one committed release SHALL publish availability and notify eligible waitlisted users

#### Scenario: Retired operation does not publish availability
- **WHEN** rescheduling fails because the replacement is occupied
- **THEN** no replacement/release event SHALL be emitted

### Requirement: Authoritative ordinary checkout and promotion result
V1/V2 direct checkout SHALL share server-calculated prices, same-tenant coupons and versioned membership entitlements, permitted combination rules and loyalty conversion. A hold SHALL reserve scarce coupon uses and points without irrevocably spending them before successful payment. Confirmation SHALL atomically finalize allocation/discounts and consume reserved benefits once; expiry/cancellation SHALL release unconsumed reservations. Fully discounted bookings SHALL record legitimate zero payment rather than fabricated bank receipts. Point preview SHALL be read-only and reflect actual eligibility.

#### Scenario: Prohibited combination
- **WHEN** coupon/membership/points policy prohibits the requested combination
- **THEN** preview/checkout SHALL reject or present a permitted choice consistently without consuming benefits

#### Scenario: Pending payment expires
- **WHEN** an unpaid booking reserved points and a coupon use
- **THEN** expiry SHALL release them exactly once, including after retries

#### Scenario: Loyalty covers total
- **WHEN** accepted benefits make the amount payable zero
- **THEN** booking SHALL confirm atomically with a zero-payable record and one point consumption without requesting bank transfer

#### Scenario: Concurrent points
- **WHEN** two holds compete for the same point balance
- **THEN** only reservations within the available unexpired balance SHALL succeed


#### Scenario: Tampered discount
- **WHEN** a client supplies an unearned coupon/membership/point reduction or edited total
- **THEN** the server SHALL reject or recalculate before acceptance without granting the benefit or consuming points

#### Scenario: Legacy unpaid hold
- **WHEN** a pre-cutover unpaid hold is submitted for confirmation
- **THEN** it SHALL require payment evidence under its recorded model and SHALL not be silently converted or confirmed free

## ADDED Requirements

### Requirement: Complete tenant promotion lifecycle
Authorized owners or delegated pricing staff SHALL create, revise, activate, suspend and retire scoped campaigns/coupons with customer eligibility, effective dates, limits, minimum spend and combination rules. Updates SHALL require current business entitlement, preserve audit/version history and active earned membership terms. Customer booking SHALL apply the same organization-scoped membership catalog managed by that owner.

#### Scenario: Foreign coupon
- **WHEN** a customer uses another venue's restricted coupon or membership
- **THEN** checkout SHALL refuse the benefit without changing usage counts

#### Scenario: Campaign retired
- **WHEN** a campaign or new membership sale is retired
- **THEN** new benefits SHALL stop while previously earned unexpired membership entitlements remain honored

#### Scenario: Expired business entitlement
- **WHEN** a pricing administrator with an expired platform plan activates a new rule
- **THEN** activation SHALL fail without changing live policy

## REMOVED Requirements

### Requirement: Booking changes and released availability
**Reason**: The no-cancellation/no-rescheduling policy is intentionally superseded by the report-aligned policy request workflow.
**Migration**: Preserve historical terms and balances, reject incompatible old-command replay, and implement new versioned previews/requests/approvals rather than blindly reactivating old wallet-refund commands.

## ADDED Requirements

### Requirement: Policy-governed booking changes and released availability
Customers SHALL request whole/partial cancellation and court/date/time changes; venue-authorized staff/owners SHALL review against the accepted policy. The server SHALL show deadline, fee, refund/extra-payment amount and replacement availability before commitment. Approved cancellation SHALL release eligible inventory once and create owner refund obligations for paid allocations. Rescheduling SHALL secure replacement, collect any verified difference and atomically swap inventory, retaining original reservation if replacement/payment fails. Suspended venues SHALL retain authorized obligation handling; legacy terms SHALL not be silently rewritten. Waitlist notices SHALL never promise automatic booking.

#### Scenario: Approved paid cancellation
- **WHEN** an eligible paid cancellation is approved and retried
- **THEN** inventory SHALL release once and one bounded owner-refund obligation SHALL be recorded without automated bank debit

#### Scenario: More expensive replacement
- **WHEN** an accepted reschedule requires extra payment
- **THEN** the original slot SHALL remain protected until verified difference and replacement commit; failure preserves original booking

#### Scenario: Cheaper or equal replacement
- **WHEN** an approved replacement costs less or the same
- **THEN** the swap SHALL preserve audit/history and create only the legitimate refund difference, if any

#### Scenario: Expired unpaid hold
- **WHEN** expiry processes paid and unpaid bookings together
- **THEN** only eligible unpaid holds SHALL release, without refunding unpaid value

#### Scenario: Legacy policy
- **WHEN** a historical booking has older terms or no sufficient change terms
- **THEN** it SHALL retain historical evidence and require an explicit supported resolution rather than fabricated consent

