## MODIFIED Requirements

### Requirement: Post-session cost capture
The platform SHALL let the host end a session, closing registrations, then enter and review the actual paying headcount, actual court duration, hourly court rate, shuttlecock count and price per shuttlecock. Duration SHALL accept fractional hours with at least minute precision. Headcount MUST be a positive integer; duration MUST be positive; shuttlecock count MUST be a nonnegative integer; unit prices MUST be nonnegative whole VND. Missing or invalid inputs SHALL prevent bill finalization. The default hourly court rate SHALL be the price per hour that the court's price tables give at the session's start, as defined in "Per-minute rental calculation"; when the court has no price at that time, no default SHALL be offered. The host SHALL be able to enter unit prices when no applicable default exists; any defaults MUST remain visible for confirmation.

#### Scenario: Fractional duration and individual shuttlecocks
- **WHEN** the host enters 1.5 hours at 200000 VND per hour and six shuttlecocks at 25000 VND each
- **THEN** the reviewed court cost SHALL be 300000 VND and shuttlecock cost SHALL be 150000 VND

#### Scenario: Default rate from the price tables
- **WHEN** a session starts at 17:00 on a court whose price table charges 140000 VND per hour from 16:00
- **THEN** the cost form SHALL offer 140000 VND per hour as the default court rate for the host to confirm or change

#### Scenario: No shuttlecocks were used
- **WHEN** the host enters zero shuttlecocks with otherwise valid inputs
- **THEN** the bill SHALL allow a zero shuttlecock subtotal

#### Scenario: Invalid input or session not ended
- **WHEN** finalization is requested before the session ends or with zero headcount, negative prices, nonpositive duration or fractional shuttlecock count
- **THEN** the platform SHALL reject finalization with a useful error and preserve existing saved data

### Requirement: Court and shuttlecock only equal calculation
The platform SHALL calculate the court subtotal from actual duration multiplied by the confirmed hourly rate and the shuttlecock subtotal from actual count multiplied by the confirmed per-shuttlecock rate. The total SHALL equal only those two subtotals; the equal share SHALL equal that total divided by the actual paying headcount. The server SHALL calculate authoritative totals; the court subtotal SHALL be ⌊minutes × hourly rate / 60⌋, dropping any fraction of a VND once and never rounding up, as for court bookings. For a non-integral share, the platform SHALL preserve the exact total and divisor and label any rounded per-person display as approximate; it MUST NOT silently increase the bill, charge a remainder to the host, or create payment allocations. Time-weighted, itemized and custom splits SHALL NOT be offered for this flow.

#### Scenario: Equal split
- **WHEN** two hours at 150000 VND per hour and eight shuttlecocks at 25000 VND each are divided among eight actual paying players
- **THEN** the court subtotal SHALL be 300000 VND, the shuttlecock subtotal 200000 VND, the total 500000 VND, and each equal share 62500 VND

#### Scenario: Fractional court subtotal
- **WHEN** the host enters 80 minutes at 100000 VND per hour
- **THEN** the court subtotal SHALL be 133333 VND

#### Scenario: Non-integral share
- **WHEN** a total of 500000 VND is divided among six paying players
- **THEN** the bill SHALL preserve 500000 VND and six players, display any rounded share as approximate, and SHALL NOT treat that display as six exact collected payments

#### Scenario: Client tampers with totals
- **WHEN** a request includes totals inconsistent with its valid cost inputs
- **THEN** the platform SHALL use server-calculated amounts rather than the supplied totals
