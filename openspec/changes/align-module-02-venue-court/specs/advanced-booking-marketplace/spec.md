## MODIFIED Requirements

### Requirement: Long-running recurring reservations
The platform SHALL support weekly, biweekly and monthly series bounded by published venue policy and at most 12 months/104 occurrences. Client/server SHALL agree on weekdays, recurrence dates, per-session duration and count. A server preview SHALL price/check every full interval and expose conflicts; all-or-nothing or partial acceptance SHALL follow published policy and require acceptance of the final occurrence/price list. Full-series or per-period payment SHALL be disclosed; only verified paid or explicitly policy-guaranteed occurrences SHALL confirm. Unpaid holds and overdue future periods SHALL release according to captured policy, preserving paid/history; an occurrence's court or time SHALL change only through the operator commands of "Operator handling of affected bookings".

#### Scenario: Series is confirmed
- **WHEN** a customer accepts a multi-weekday two-hour biweekly preview
- **THEN** persisted dates, durations, prices and counts SHALL exactly match the accepted preview without truncating to twelve or reserving only one slot

#### Scenario: Partial conflict
- **WHEN** one occurrence is occupied under partial-acceptance policy
- **THEN** the platform SHALL show the excluded occurrence and revised total for explicit acceptance before holding

#### Scenario: All-or-nothing conflict
- **WHEN** one occurrence conflicts under all-or-nothing policy
- **THEN** no partial booking or payment allocation SHALL persist

#### Scenario: Period payment
- **WHEN** only one period has verified payment and no payment guarantee exists
- **THEN** only that period SHALL confirm; later occurrences remain under disclosed hold/payment rules

#### Scenario: Monthly invoice becomes overdue
- **WHEN** an unpaid period becomes overdue
- **THEN** only eligible unpaid future intervals SHALL release once, with notice and no corruption of completed sessions
