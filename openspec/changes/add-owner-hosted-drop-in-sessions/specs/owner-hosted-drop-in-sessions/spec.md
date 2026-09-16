## Purpose

Enable facility owners to host badminton sessions for individual drop-in players of compatible skill levels and calculate an equal post-session share of actual court and shuttlecock costs only.

## ADDED Requirements

### Requirement: Facility-owner hosted sessions
The platform SHALL let the owner of a facility open a badminton session for a court belonging to that facility. A session MUST include its court, scheduled start and end, positive player capacity, category (`giao lưu` or `đánh vãng lai`), and one or more accepted skill labels. Only the hosting owner with current authority over the facility SHALL manage the session and its bill. Opening a session MUST NOT imply a paid booking or bypass existing court-inventory protections.

#### Scenario: Owner opens a session
- **WHEN** a facility owner submits valid session details for their own court
- **THEN** the platform SHALL persist a discoverable session identifying the facility and owner as host without automatically adding the owner as a player

#### Scenario: Unauthorized management
- **WHEN** a customer, staff member without ownership, or owner of another facility attempts to create or manage a session for that court
- **THEN** the platform SHALL reject the operation without changing session or bill data

### Requirement: Drop-in discovery by skill and category
The platform SHALL let individual players find sessions by location or court, scheduled time, category, and accepted skill labels `newbie`, `Y`, `TBY`, `TB`, and `K`. These labels SHALL be specific to this badminton session flow and MUST NOT reinterpret existing generic player skill scores. A team or club membership SHALL NOT be required to find or join a session. A skill filter SHALL match sessions that explicitly accept that label.

#### Scenario: Individual finds a suitable session
- **WHEN** a player without a team searches for an upcoming `đánh vãng lai` session accepting `TBY` at a selected location
- **THEN** the results SHALL contain only sessions matching those filters and display their accepted levels, time, court, host and available places

#### Scenario: Labels do not match
- **WHEN** a player filters for `newbie` and a session accepts only `TB` and `K`
- **THEN** that session SHALL NOT appear in the filtered results

### Requirement: Participation is separate from hosting and billing headcount
The platform SHALL accept individual registrations only while a session is open and has capacity, without duplicate registrations or overfilling under concurrent requests. Host status alone SHALL NOT consume a place or add a person to the bill. The host-confirmed actual paying headcount after play SHALL be independent of registration count, allowing actual walk-in players and excluding absent registrants. An owner who actually plays SHALL be included only when the host counts that owner among paying players.

#### Scenario: Host does not play
- **WHEN** an owner opens a session and six customers register
- **THEN** the registered player count SHALL be six, not seven

#### Scenario: Registrations differ from actual attendance
- **WHEN** eight people registered but the host confirms seven actual paying players after absences and walk-ins
- **THEN** the bill SHALL use seven as its divisor without requiring seven registered user accounts

#### Scenario: Last place is requested concurrently
- **WHEN** two different players request the last available place
- **THEN** at most one registration SHALL succeed and the other SHALL receive a capacity conflict

### Requirement: Post-session cost capture
The platform SHALL let the host end a session, closing registrations, then enter and review the actual paying headcount, actual court duration, hourly court rate, shuttlecock count and price per shuttlecock. Duration SHALL accept fractional hours with at least minute precision. Headcount MUST be a positive integer; duration MUST be positive; shuttlecock count MUST be a nonnegative integer; unit prices MUST be nonnegative whole VND. Missing or invalid inputs SHALL prevent bill finalization. The host SHALL be able to enter unit prices when no applicable default exists; any defaults MUST remain visible for confirmation.

#### Scenario: Fractional duration and individual shuttlecocks
- **WHEN** the host enters 1.5 hours at 200000 VND per hour and six shuttlecocks at 25000 VND each
- **THEN** the reviewed court cost SHALL be 300000 VND and shuttlecock cost SHALL be 150000 VND

#### Scenario: No shuttlecocks were used
- **WHEN** the host enters zero shuttlecocks with otherwise valid inputs
- **THEN** the bill SHALL allow a zero shuttlecock subtotal

#### Scenario: Invalid input or session not ended
- **WHEN** finalization is requested before the session ends or with zero headcount, negative prices, nonpositive duration or fractional shuttlecock count
- **THEN** the platform SHALL reject finalization with a useful error and preserve existing saved data

### Requirement: Court and shuttlecock only equal calculation
The platform SHALL calculate the court subtotal from actual duration multiplied by the confirmed hourly rate and the shuttlecock subtotal from actual count multiplied by the confirmed per-shuttlecock rate. The total SHALL equal only those two subtotals; the equal share SHALL equal that total divided by the actual paying headcount. The server SHALL calculate authoritative totals, rounding the court subtotal to the nearest whole VND with halves rounded up when necessary. For a non-integral share, the platform SHALL preserve the exact total and divisor and label any rounded per-person display as approximate; it MUST NOT silently increase the bill, charge a remainder to the host, or create payment allocations. Time-weighted, itemized and custom splits SHALL NOT be offered for this flow.

#### Scenario: Equal split
- **WHEN** two hours at 150000 VND per hour and eight shuttlecocks at 25000 VND each are divided among eight actual paying players
- **THEN** the court subtotal SHALL be 300000 VND, the shuttlecock subtotal 200000 VND, the total 500000 VND, and each equal share 62500 VND

#### Scenario: Non-integral share
- **WHEN** a total of 500000 VND is divided among six paying players
- **THEN** the bill SHALL preserve 500000 VND and six players, display any rounded share as approximate, and SHALL NOT treat that display as six exact collected payments

#### Scenario: Client tampers with totals
- **WHEN** a request includes totals inconsistent with its valid cost inputs
- **THEN** the platform SHALL use server-calculated amounts rather than the supplied totals

### Requirement: Exclude all auxiliary purchases
The session bill SHALL contain only court and shuttlecock costs. It MUST NOT accept, import or display charges for water, grips, ice or other auxiliary goods or services. Customers SHALL purchase and pay for those separately through existing retail arrangements; existing retail operations SHALL remain available.

#### Scenario: Auxiliary charge is submitted
- **WHEN** a caller attempts to add a drink, accessory or arbitrary service charge to the session bill
- **THEN** the platform SHALL reject that charge and leave the two-cost total unchanged

#### Scenario: Separate retail purchase exists
- **WHEN** a player buys water separately during a session
- **THEN** the purchase SHALL NOT be added to the session bill or equal-share calculation

### Requirement: Persist and finalize a reviewable bill
The host SHALL be able to save and revise draft cost inputs after ending the session, then explicitly finalize the bill. Finalization SHALL persist one authoritative snapshot of inputs, confirmed prices, subtotals, total, divisor, host and finalization time. Host and registered participants SHALL be able to reload and view the finalized breakdown; unrelated users SHALL NOT gain bill-management access. A finalized bill SHALL be read-only in this release, retries SHALL NOT create duplicate finalizations, and conflicting edits SHALL NOT silently replace newer data. Later changes to default prices SHALL NOT change a finalized bill. Finalization SHALL NOT claim that money has been collected.

#### Scenario: Host reviews then finalizes
- **WHEN** the host saves a draft, reviews it and finalizes after the session ends
- **THEN** reloads by the host and registered participants SHALL show the same finalized inputs and calculated breakdown

#### Scenario: Retry or stale edit
- **WHEN** the host retries a successful finalization or submits an edit based on an older bill version
- **THEN** the platform SHALL return the existing finalized result for an identical retry or a conflict for an incompatible edit, without overwriting the snapshot

#### Scenario: Prices change later
- **WHEN** default hourly or shuttlecock prices change after finalization
- **THEN** the finalized bill SHALL retain its originally confirmed rates and amounts

### Requirement: Preserve existing booking and payment behavior
This flow SHALL be separate from advance split-payment booking confirmation and existing generic matches. It SHALL NOT introduce deposits, automatic payment collection, wallet deductions, QR payment confirmation, debt tracking, refunds or host shortfall guarantees. Any pre-session per-person estimate SHALL be explicitly identified as an estimate rather than the final amount. Existing booking confirmation, split expiry, refund and retail contracts SHALL remain unchanged.

#### Scenario: Existing advance split booking
- **WHEN** a user follows the existing advance split-payment booking flow
- **THEN** its existing confirmation and expiry rules SHALL remain in force without using post-session headcount or shuttlecock costs

#### Scenario: Final bill is not payment
- **WHEN** the host finalizes a drop-in session bill
- **THEN** the platform SHALL record the bill without marking it paid, debiting wallets or creating payment-provider transactions
