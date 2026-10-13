# Reservations and Pricing Specification

## Purpose

Defines tenant-aware reservation and quote behaviour for V1 and V2 APIs with direct-owner payments, authoritative pricing, exclusive expiring holds, and policy-governed cancellations and reschedules while preserving historical bookings.

## Requirements

### Requirement: Unversioned reservation API
The platform SHALL serve one reservation command and query API under `/api`, with no version segment in any path. Third-party callbacks registered in external consoles (Google and Facebook OAuth callbacks, SePay platform and per-connection webhooks) SHALL keep their registered `/api/v1/...` paths. The V1 booking API SHALL be retired only when all of the following hold:
- every historical booking is mapped to exactly one reservation;
- every active booking slot at a future time has an active mapped reservation slot;
- every first-party client uses the reservation API for checkout, payment, history, reviews and disputes;
- the retiring change records the project owner's approval and the evidence it relied on.

After retirement, every V1 booking-specific endpoint SHALL reject requests without creating, reading or changing any record. Historical bookings SHALL stay unchanged as read-only history, and their live state SHALL be kept on the mapped reservation.

New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions. Confirmation SHALL require verified payment, or a documented zero-payable outcome. Historical wallet bookings SHALL retain recorded amounts, payment model and terms, and SHALL remain readable through their mapped reservation. Old idempotency records MUST NOT authorize a different new command.

#### Scenario: Legacy client retrieves a historical booking
- **WHEN** a customer or administrator views a migrated historical wallet booking
- **THEN** its original code, financial references and ownership history SHALL remain readable through its mapped reservation, which SHALL show its current status

#### Scenario: New client creates a reservation
- **WHEN** an eligible client creates a direct-payment booking
- **THEN** it SHALL do so through the reservation API, and the result SHALL reference one pending obligation and inventory hold without debiting a wallet

#### Scenario: Insufficient payment
- **WHEN** only partial or unverified funds are received
- **THEN** the booking SHALL remain unconfirmed and unpaid inventory SHALL follow its captured expiry policy

#### Scenario: Stale client invokes a retired command
- **WHEN** a client calls a removed cancellation, refund or reschedule command, or replays an old idempotency key against a different command
- **THEN** the server SHALL reject it or return only its original outcome, never mutate the new model by replaying an old response

#### Scenario: New client creates a reservation with insufficient balance
- **WHEN** a direct-cohort customer has insufficient legacy wallet balance but requests a valid direct-owner booking
- **THEN** the server SHALL create the policy-bound pending direct payment without a wallet debit; confirmation SHALL still require verified owner payment or a legitimate zero-payable result

#### Scenario: Retirement preconditions not met
- **WHEN** any booking lacks a reservation mapping, an active future booking slot lacks an active mapped reservation slot, or the owner's approval is not recorded
- **THEN** the V1 booking API SHALL keep serving and V1 retirement SHALL NOT proceed

#### Scenario: Stale client calls a retired V1 booking endpoint
- **WHEN** after retirement a client calls a V1 booking-specific endpoint to list, create, pay for, change or export a booking
- **THEN** the request SHALL be rejected, and no booking, slot, payment, reservation, idempotency or notification record SHALL be created or changed

#### Scenario: Stale client calls a versioned path
- **WHEN** a client calls a path under `/api/v1` or `/api/v2`
- **THEN** the server SHALL answer not found without creating or changing any record

#### Scenario: Historical booking without a mapping
- **WHEN** the backfill cannot map a historical booking to a reservation
- **THEN** the booking SHALL be reported with its reason, SHALL remain unchanged, and SHALL block retirement until it is resolved

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
No overlapping active entitlement or exclusive hold SHALL exist for the same court-time across V1/V2 bookings, recurring occurrences, owner-hosted sessions and tournaments using that court. Interval duration, venue calendar, book-ahead horizon, continuity policy and final eligibility SHALL be checked atomically at allocation and replacement. Held and confirmed states SHALL both exclude competitors. The data store itself SHALL reject two held or confirmed reservation slots on the same court whose half-open time intervals overlap, independent of application-level checks. A store-level rejection SHALL surface to the client as the same availability conflict as an application-level rejection.

#### Scenario: Concurrent reservation attempts
- **WHEN** 100 eligible requests target one available court-time
- **THEN** exactly one hold SHALL succeed and 99 SHALL receive valid conflicts without financial side effects

#### Scenario: Overlapping duration
- **WHEN** different start times overlap a reserved interval or hosted session
- **THEN** the later allocation SHALL fail, not merely compare identical start timestamps

#### Scenario: Configured booking window
- **WHEN** slots violate a venue's book-ahead or continuity rule
- **THEN** all versions SHALL reject the selection consistently

#### Scenario: Writer bypasses the application check
- **WHEN** a held or confirmed slot from 10:00 to 12:00 exists on a court, and a different reservation writes a held slot from 11:00 to 12:00 on that court without passing the application availability check
- **THEN** the data store SHALL reject the write, and no part of that reservation, its entitlements or its payment records SHALL persist

#### Scenario: Adjacent intervals do not conflict
- **WHEN** one reservation holds 10:00 to 11:00 and another reservation requests 11:00 to 12:00 on the same court
- **THEN** both SHALL be accepted, because half-open intervals that only touch do not overlap

#### Scenario: Replacement held while its original stays protected
- **WHEN** a confirmed reservation is rescheduled to a different court-time and the replacement is held pending an extra payment
- **THEN** both the original and the replacement SHALL exclude competitors at the data store until the swap commits

#### Scenario: Released slots free the interval
- **WHEN** a slot becomes cancelled or expired
- **THEN** its interval SHALL no longer block new holds on that court

### Requirement: Idempotent v2 business commands
Every v2 command that creates or changes business state SHALL require an `Idempotency-Key`. Retrying the same key with the same authenticated principal and request content SHALL return the original result without repeating the state change; reuse with different content SHALL be rejected.

#### Scenario: Client retries a reservation request
- **WHEN** a client repeats an accepted v2 reservation command with the same idempotency key and content
- **THEN** the platform SHALL return the original reservation result and SHALL not reserve additional inventory

#### Scenario: Idempotency key is reused for another request
- **WHEN** a client sends different command content using an existing idempotency key
- **THEN** the platform SHALL reject the request without applying either additional state change

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
V1/V2 direct checkout SHALL share server-calculated prices, same-tenant coupons and versioned membership entitlements, permitted combination rules and loyalty conversion. Customers SHALL be able to preview and select same-tenant loyalty points before confirming a new ordinary booking. A hold SHALL reserve scarce coupon uses and points without irrevocably spending them before successful payment. Confirmation SHALL atomically finalize allocation/discounts and consume reserved benefits once; expiry/cancellation SHALL release unconsumed reservations. For a pre-cutover legacy wallet cohort, accepted loyalty reduction, point debit, remaining wallet payment, booking confirmation and inventory allocation SHALL commit atomically with the policy snapshot and related financial records. Checkout without loyalty selection SHALL preserve existing behavior for its payment model. Fully discounted bookings SHALL record legitimate zero payment rather than fabricated bank receipts. Point preview SHALL be read-only and reflect actual eligibility. Loyalty redemption SHALL NOT apply retroactive discounts to existing bookings.

#### Scenario: Prohibited combination
- **WHEN** coupon/membership/points policy prohibits the requested combination
- **THEN** preview/checkout SHALL reject or present a permitted choice consistently without consuming benefits

#### Scenario: Pending payment expires
- **WHEN** an unpaid booking reserved points and a coupon use
- **THEN** expiry SHALL release them exactly once, including after retries

#### Scenario: Loyalty covers total
- **WHEN** accepted benefits make the amount payable zero
- **THEN** booking SHALL confirm atomically with a zero-payable record and one point consumption without requesting bank transfer, creating a fabricated positive wallet debit or leaving a pending payment

#### Scenario: Concurrent points
- **WHEN** two holds compete for the same point balance
- **THEN** only reservations within the available unexpired balance SHALL succeed

#### Scenario: Tampered discount
- **WHEN** a client supplies an unearned coupon/membership/point reduction or edited total
- **THEN** the server SHALL reject or recalculate before acceptance without granting the benefit or consuming points

#### Scenario: Legacy unpaid hold
- **WHEN** a pre-cutover unpaid hold is submitted for confirmation
- **THEN** it SHALL require payment evidence under its recorded model and SHALL not be silently converted or confirmed free

#### Scenario: Customer discovers the loyalty control before payment
- **WHEN** a customer reviews selected court-times before the checkout action that creates an ordinary booking
- **THEN** the interface SHALL offer a localized points control, explain the applicable organization and show the authoritative points, discount and remaining amount payable before confirmation

#### Scenario: Atomic points and wallet checkout
- **WHEN** an eligible legacy wallet cohort customer confirms a booking with 200,000 VND payable before loyalty, sufficient points, and a permitted 5,000 VND point reduction
- **THEN** checkout SHALL record the point debit and 5,000 VND reduction, charge 195,000 VND from the wallet and confirm the booking and its slots as one transaction

#### Scenario: Failure after a partial checkout step
- **WHEN** insufficient funds, an inventory conflict or a persistence failure prevents checkout completion
- **THEN** point and wallet balances, coupon usage, booking records, inventory and financial postings SHALL remain unchanged by the failed operation

#### Scenario: Reload after successful checkout
- **WHEN** a customer reloads a confirmed booking that used points
- **THEN** booking detail and payment history SHALL show the persisted loyalty reduction and net paid amount consistent with point, payment and financial records

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

### Requirement: Home availability identifies its selected court and date
The home availability panel SHALL identify the selected court and selected local play date adjacent to the slots and provide a nearby court selector. Its title MUST NOT imply all-court aggregation or always claim today's date when another date is selected. Each slot SHALL expose its start and end time and availability for that court only. Changing court/date SHALL refresh the matching slots, clear incompatible selections and prevent stale responses from presenting another court's availability as the selected court's. Labels SHALL support Vietnamese and English.

#### Scenario: One court is booked from 19 to 21
- **WHEN** court A is booked from 19:00 to 21:00 using hourly slots
- **THEN** its 19:00–20:00 and 20:00–21:00 slots SHALL be unavailable under a heading identifying court A and the selected date

#### Scenario: Another court remains available
- **WHEN** the user changes from booked court A to court B that has no conflicting booking or closure at 19:00–21:00
- **THEN** the panel SHALL identify court B and display both of its corresponding future slots as available

#### Scenario: Different play date
- **WHEN** the user selects a date other than today
- **THEN** the heading and returned slots SHALL represent that date in the venue's applicable local timezone without a misleading today label

#### Scenario: Court switches while requests are pending
- **WHEN** an older availability request completes after the user has selected another court or date
- **THEN** its response SHALL NOT replace the currently selected court/date's displayed availability
