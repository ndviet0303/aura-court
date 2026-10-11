## MODIFIED Requirements

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
