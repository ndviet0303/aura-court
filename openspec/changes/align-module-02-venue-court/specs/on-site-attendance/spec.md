## MODIFIED Requirements

### Requirement: On-site changes and incident handling
Authorized staff SHALL record operational incidents. When an incident leaves a court unable to serve, staff SHALL pause that court for maintenance or an incident, immediately if needed and even while bookings on it remain unhandled. The pause SHALL NOT cancel or move any booking; the bookings it affects, including one already in play, SHALL be handled as affected bookings. A booking SHALL move to another court only through the operator move-court command of "Operator handling of affected bookings", under that command's rules: the customer's recorded agreement, a re-check of the target court-time under the same full-interval conflict rules as new bookings, and preservation of the booking's price and payment history. The move SHALL record the actor, the source of the affected-booking case as the reason, and the court intervals moved. There SHALL be no separate on-site court reassignment operation. Extra play time SHALL be booked as a new walk-in booking of the next free slot, with its own price and payment; staff SHALL NOT lengthen an existing slot or extend play without payment. Current play SHALL not extend into another reservation; equipment issues SHALL support evidence and scoped maintenance.

#### Scenario: Extension overlaps next booking
- **WHEN** staff try to add an hour on a court whose next slot is already held or booked
- **THEN** no booking SHALL be created and neither existing booking SHALL change

#### Scenario: Extra hour on a free court
- **WHEN** a playing customer asks for another hour and the next slot on that court is free
- **THEN** staff SHALL create a walk-in booking for that slot with its own payment, and the original booking's slots SHALL keep their recorded times

#### Scenario: Incident reassignment
- **WHEN** an incident makes a court unusable while a customer is playing on it and staff pause that court immediately
- **THEN** the booking SHALL keep its court, time and status as an affected booking until staff apply the move-court command with the customer's agreement
- **AND** the move SHALL succeed only when the target court is free for the whole moved interval, keeping the booking's price and payment history and recording the actor, the case source as the reason and the court intervals moved

#### Scenario: Retired on-site reassignment
- **WHEN** a client calls the former on-site court reassignment operation
- **THEN** the platform SHALL answer 404 without changing any booking, court or attendance record

### Requirement: Booking attendance lifecycle
Assigned staff with attendance permission SHALL verify current effective holder or authorized representative, correct venue, payment eligibility, time window and operational court readiness before handover. Payment SHALL not imply attendance. Booking/reservation operational states SHALL consistently project confirmed, in_use, completed and no_show while payment remains separate. Duplicate check-in/out SHALL return prior outcomes. No-show SHALL use captured lateness policy and actor evidence. A booking with an open affected-booking case SHALL NOT be marked no-show; such a request SHALL be rejected with 409, as defined in "Affected bookings from operational changes". Checkout SHALL record actual end independently of unpaid ancillary obligations and move the court to awaiting readiness inspection, not automatically available.

#### Scenario: Repeated scan
- **WHEN** staff repeat a valid holder's check-in
- **THEN** one attendance SHALL persist without extending play time

#### Scenario: Invalid scan
- **WHEN** the scan is outside allowed time, uses a former transferred holder, targets another venue, or the court is occupied/blocked
- **THEN** no handover SHALL occur and protected details SHALL remain hidden

#### Scenario: Outstanding ancillary amount
- **WHEN** a playing customer checks out with an unpaid tab
- **THEN** attendance SHALL close while the tab remains a separately tracked receivable

#### Scenario: Readiness inspection
- **WHEN** staff complete post-play inspection successfully
- **THEN** the court SHALL become operationally ready without making reserved future intervals available

#### Scenario: No-show
- **WHEN** the allowed arrival window expires and authorized staff record absence
- **THEN** one policy-bound no-show state and audit SHALL persist across V1/V2 views
