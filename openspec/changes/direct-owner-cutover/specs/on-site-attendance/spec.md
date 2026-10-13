## MODIFIED Requirements

### Requirement: On-site changes and incident handling
Authorized staff SHALL record operational incidents and move a booking to an available equivalent court under the same full-interval conflict rules as new bookings, preserving its price and payment history. Extra play time SHALL be booked as a new walk-in booking of the next free slot, with its own price and payment; staff SHALL NOT lengthen an existing slot or extend play without payment. Current play SHALL not extend into another reservation; equipment issues SHALL support evidence and scoped maintenance.

#### Scenario: Extension overlaps next booking
- **WHEN** staff try to add an hour on a court whose next slot is already held or booked
- **THEN** no booking SHALL be created and neither existing booking SHALL change

#### Scenario: Extra hour on a free court
- **WHEN** a playing customer asks for another hour and the next slot on that court is free
- **THEN** staff SHALL create a walk-in booking for that slot with its own payment, and the original booking's slots SHALL keep their recorded times

#### Scenario: Incident reassignment
- **WHEN** an incident requires moving a player to an available equivalent court
- **THEN** the authorized swap SHALL preserve price/payment history and record actor, reason and affected court intervals
