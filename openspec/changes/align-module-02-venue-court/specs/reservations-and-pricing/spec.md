## ADDED Requirements

### Requirement: Operator handling of affected bookings
An organization owner, or staff assigned to the booking's venue, SHALL be able to move a booking to another court, change its time, or cancel it, but only while the booking has an open affected-booking case, meaning a case that has no final outcome yet. Customers SHALL NOT be offered any of these commands. Each command SHALL:
- require an explicit confirmation that the customer agreed to the outcome and the contact channel used (phone, Zalo or chat); a request missing either SHALL be rejected with 422 and change nothing;
- apply only to a booking that is held (awaiting payment), confirmed or in use (checked in), and not cancelled, expired, completed or no-show, and change only its slots that have not ended; any other booking SHALL be rejected with 409;
- lock the booking and re-validate it under that lock, rejecting with 409 a booking whose court, time or status changed while the command waited for the lock, and, for a move or time change, re-check the target court-time under that lock: it SHALL satisfy the court, venue, suspension, operational-stop and effective-service-time conditions of "Unified public and new-booking eligibility", excluding the partner, plan or trial, price, booking-policy and collection-connection conditions, SHALL follow the booking window and slot rules of the booking's captured policy, with the exceptions below, and SHALL NOT overlap any held or confirmed inventory other than the booking's own; a failed check SHALL return 409 and leave the booking, its slots and its case unchanged;
- keep the booking's recorded amounts, price snapshot, discounts and payment records unchanged, and SHALL NOT create a quote, payment request, wallet transaction, journal entry, refund quote or refund obligation;
- record the actor, contact channel, customer agreement, time, the source of the affected-booking case as the reason, and the previous and new court-time in the booking history, and close every open case of the booking with the matching outcome: moved, time changed or cancelled;
- return 403 when the actor is neither the owner of the booking's organization nor staff assigned to the booking's venue, or when the target court lies outside the actor's scope;
- publish scoped inventory updates for the released and the newly occupied court-times only after commit.

A move SHALL keep the slot times and SHALL target a court of the same sport in the same organization; a court of another sport SHALL be rejected with 422. A time change SHALL keep the court and the booking's total duration; a different duration SHALL be rejected with 422. A move or time change of a held booking that holds a voucher use SHALL be rejected with 409 and SHALL leave the booking, its voucher use and its case unchanged, because the voucher is checked again against the booking's court and time when the booking is confirmed; the operator MAY cancel it instead. Because both commands keep the duration of the slots they change, the minimum and maximum duration rules SHALL NOT apply to them, even when the unended part of a booking is shorter than the policy minimum. A move SHALL accept a slot already in progress at its original start, so a booking in use can move mid-slot; a time change SHALL place every changed slot at or after the current time and within the booking horizon of the captured policy. During a move or time change the original court-time SHALL stay protected until the swap commits, and any failure SHALL leave the booking on its original court and time. A cancellation SHALL set the booking to cancelled and release its unended slots exactly once; for a booking still awaiting payment it SHALL also end the open payment obligation and release a held voucher use exactly once, and a transfer that arrives later SHALL become a finance exception. A cancelled booking SHALL be shown to its customer as cancelled by the venue for an operational change, never as the customer's own payment cancellation. Only the owner MAY attach a refund note stating that a refund was arranged outside the platform; a refund note from staff SHALL be rejected with 403. A refund note SHALL NOT mark the booking as paid or refunded and SHALL NOT count as evidence that the customer received money; any refund SHALL be agreed and paid by the owner outside Aura Court.

#### Scenario: Owner moves an affected booking to another court
- **WHEN** an owner moves a paid booking with an open affected-booking case from court A to a free pickleball court B of the same organization, recording that the customer agreed by Zalo
- **THEN** the booking's slots SHALL move to court B at the same times, court A's interval SHALL be released, and the case SHALL close as moved
- **AND** the recorded amounts and payment records SHALL stay unchanged, and no quote, payment request, wallet transaction, journal entry or refund obligation SHALL be created

#### Scenario: Move a booking in use mid-slot
- **WHEN** at 20:30 staff move a checked-in 19:00–21:00 booking with 60-minute slots, whose captured booking policy requires at least two hours, to a court of the same sport that is free from 20:00 to 21:00, with the customer's recorded agreement
- **THEN** the move SHALL succeed although the 20:00–21:00 slot has already started and is shorter than the policy minimum, that slot SHALL move to the new court, the ended 19:00–20:00 slot SHALL stay on the original court, and the booking SHALL stay in use

#### Scenario: Customer agreement or contact channel missing
- **WHEN** an owner or staff member sends a move, time-change or cancel command without the customer-agreement confirmation or without a contact channel
- **THEN** the command SHALL be rejected with 422 and the booking, its slots and its case SHALL stay unchanged

#### Scenario: Target court-time is not available
- **WHEN** the target court-time overlaps another held or confirmed booking or an operational stop, lies outside the court's effective service time, or belongs to a court that is not active or a venue that is paused, closed or suspended
- **THEN** the command SHALL be rejected with 409, the booking SHALL keep its original court and time, its case SHALL stay open, and no inventory update SHALL be published

#### Scenario: Move to a court of another sport
- **WHEN** an owner tries to move a pickleball booking to a badminton court
- **THEN** the command SHALL be rejected with 422 and nothing SHALL change

#### Scenario: Time change on the same court
- **WHEN** staff assigned to the venue change an affected booking from 19:00–21:00 to 20:00–22:00 on the same court, 21:00–22:00 is free, and the customer agreed by phone
- **THEN** the change SHALL succeed, because the booking's own 20:00–21:00 slot does not count as a conflict, and the case SHALL close as time changed with the amounts unchanged

#### Scenario: Time change with a different duration
- **WHEN** an operator changes a two-hour affected booking to a one-hour or three-hour interval
- **THEN** the command SHALL be rejected with 422 and nothing SHALL change

#### Scenario: Held booking with a voucher
- **WHEN** an operator moves or changes the time of a held booking that holds a voucher use and has an open affected-booking case
- **THEN** the command SHALL be rejected with 409 and the booking, its voucher use and its case SHALL stay unchanged
- **AND** the operator SHALL still be able to cancel the booking, which releases the voucher use exactly once

#### Scenario: Cancel a paid affected booking
- **WHEN** an owner cancels a paid booking with an open affected-booking case after the customer agreed by phone, and the request is retried
- **THEN** the booking SHALL become cancelled, its unended slots SHALL be released exactly once, the case SHALL close as cancelled, and the retry SHALL be rejected with 409 without a second release
- **AND** no wallet transaction, journal entry, refund quote or refund obligation SHALL be created, and the booking SHALL be shown as cancelled, never as refunded

#### Scenario: Cancel an affected booking that is still awaiting payment
- **WHEN** an operator cancels a held booking with an open affected-booking case and the customer's transfer arrives afterwards
- **THEN** the open payment obligation SHALL end, a held voucher use SHALL be released exactly once, and the late transfer SHALL become a finance exception without confirming the booking

#### Scenario: Refund note
- **WHEN** an owner cancels an affected booking with a note that the refund was transferred outside the platform, and staff send the same command with a refund note
- **THEN** the owner's note SHALL be stored without changing any payment or refund record, and the staff request SHALL be rejected with 403 without changing anything

#### Scenario: Actor outside the venue scope
- **WHEN** staff assigned only to venue A send a command for a booking at venue B, or an owner targets a court of another organization
- **THEN** the command SHALL be rejected with 403 and nothing SHALL change

#### Scenario: Booking without an open affected-booking case
- **WHEN** an owner or staff member sends a move, time-change or cancel command for a booking that has no affected-booking case, or whose case already has a final outcome
- **THEN** the command SHALL be rejected with 409 and nothing SHALL change

#### Scenario: Customer calls an operator command
- **WHEN** a customer calls the move, time-change or cancel command for their own booking
- **THEN** the request SHALL be rejected with 403 and nothing SHALL change

#### Scenario: Concurrent moves into the same court-time
- **WHEN** two operators move two different affected bookings into the same free court-time at the same moment
- **THEN** exactly one move SHALL succeed and the other SHALL be rejected with 409, leaving its booking and case unchanged

#### Scenario: Booking with two open cases
- **WHEN** a booking has one open case from a venue business pause and another from a court maintenance stop, and the owner moves it to another court with the customer's agreement
- **THEN** both cases SHALL close as moved and the booking history SHALL record the move once with both case sources

#### Scenario: Booking changed before the lock
- **WHEN** an operator cancels a held booking while the customer's payment for it is being confirmed
- **THEN** exactly one of the two SHALL take effect first: either the payment confirms the booking and the cancel is rejected with 409 without changing it, or the cancel takes effect and the transfer becomes a finance exception without confirming the booking
- **AND** neither request SHALL wait on the other indefinitely

## MODIFIED Requirements

### Requirement: Final bookings and released availability
A confirmed booking SHALL be final for its customer. Customers SHALL NOT be offered cancellation, refunds or rescheduling, and no endpoint or interface SHALL create a cancellation request, refund quote, refund obligation, reschedule preview or reschedule request. Staff and owners SHALL NOT cancel, move or change the time of a confirmed booking, whether or not it is checked in, except through the move, time-change and cancel commands for a booking that has an open affected-booking case, as defined in "Operator handling of affected bookings", and other than ending a recurring contract as below; those commands SHALL NOT create a refund quote, refund obligation or any money movement. A holder who cannot attend SHALL be directed to slot resale under the venue's transfer policy. Ending a recurring contract SHALL release only future occurrences that are unpaid; paid occurrences SHALL remain confirmed. Suspended venues SHALL retain authorized servicing of existing bookings, including the handling of affected bookings; legacy terms SHALL not be silently rewritten. Waitlist notices SHALL never promise automatic booking.

#### Scenario: Cancellation, refund or reschedule request
- **WHEN** a client calls a removed cancellation, refund or reschedule endpoint, or a customer looks for a way to cancel or move a paid booking
- **THEN** the request SHALL be rejected without changing any booking, inventory, payment, refund or notification record, and the interface SHALL offer slot resale instead

#### Scenario: Operator change without an open affected-booking case
- **WHEN** an owner or staff member tries to cancel, move or change the time of a confirmed booking that has no open affected-booking case
- **THEN** the request SHALL be rejected with 409 without changing any booking, inventory, payment or refund record

#### Scenario: Operator cancels an affected booking
- **WHEN** an owner cancels a paid booking that has an open affected-booking case, with the customer's recorded agreement
- **THEN** its inventory SHALL be released once, no refund quote or refund obligation SHALL be created, the recorded payment SHALL stay as history, and any refund SHALL be settled by the owner outside the platform

#### Scenario: Recurring contract ends
- **WHEN** an authorized operator ends a recurring contract that has both paid and unpaid future occurrences
- **THEN** only the unpaid future occurrences SHALL release, exactly once, and the paid occurrences SHALL remain confirmed without refund obligations

#### Scenario: Expired unpaid hold
- **WHEN** expiry processes paid and unpaid bookings together
- **THEN** only eligible unpaid holds SHALL release, without refunding unpaid value

#### Scenario: Legacy policy
- **WHEN** a historical booking carries older cancellation, refund or change terms
- **THEN** it SHALL retain them as historical evidence without making cancellation, refunds or rescheduling available again

### Requirement: Unversioned reservation API
The platform SHALL serve one reservation command and query API under `/api`, with no version segment in any path. Third-party callbacks registered in external consoles (Google and Facebook OAuth callbacks, SePay platform and per-connection webhooks) SHALL keep their registered `/api/v1/...` paths. The V1 booking API SHALL be retired only when all of the following hold:
- every historical booking is mapped to exactly one reservation;
- every active booking slot at a future time has an active mapped reservation slot;
- every first-party client uses the reservation API for checkout, payment, history, reviews and disputes;
- the retiring change records the project owner's approval and the evidence it relied on.

After retirement, every V1 booking-specific endpoint SHALL reject requests without creating, reading or changing any record. Historical bookings SHALL stay unchanged as read-only history, and their live state SHALL be kept on the mapped reservation.

New direct-owner checkout SHALL create a pending-payment booking with exclusive expiring holds and receiver-bound payment instructions. Confirmation SHALL require verified payment, or a documented zero-payable outcome. The API SHALL NOT offer customers cancellation, refunds or rescheduling of a booking; a holder who cannot use a booking can pass it on only through slot resale. Owners and assigned staff SHALL move, change the time of or cancel a booking only through the commands of "Operator handling of affected bookings", which SHALL NOT create a refund or any money movement. Historical wallet bookings SHALL retain recorded amounts, payment model and terms, and SHALL remain readable through their mapped reservation. Old idempotency records MUST NOT authorize a different new command.

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

#### Scenario: Operator commands through the reservation API
- **WHEN** an owner moves a booking that has an open affected-booking case, and then tries to cancel another confirmed booking that has none
- **THEN** the move SHALL follow "Operator handling of affected bookings" without creating any money movement, and the cancel SHALL be rejected with 409 without changing anything

### Requirement: Trigger real-time status hooks
Committed hold, confirmation, expiry, release, operator move, time-change and cancel commands, saved operational stops and resumes, venue suspensions and their lifts, and operational readiness changes SHALL publish scoped inventory updates after commit. Rejected or rolled-back actions SHALL not publish success or released availability.

#### Scenario: Successful reservation triggers hook
- **WHEN** a reservation commits
- **THEN** authorized viewers SHALL receive its actual court-time state

#### Scenario: Unpaid inventory expires
- **WHEN** an unpaid hold expires durably
- **THEN** one committed release SHALL publish availability and notify eligible waitlisted users

#### Scenario: Retired operation does not publish availability
- **WHEN** a client calls a removed cancellation or reschedule command
- **THEN** the request SHALL be rejected and no release or replacement event SHALL be emitted
