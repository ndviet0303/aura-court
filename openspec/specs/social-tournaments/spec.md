# Social Tournaments Specification

## Purpose

Enables verified player discovery, accountable community reputation, and deterministic
tournament administration with timely realtime state updates.

## Requirements

### Requirement: Player profiles and match discovery
The platform SHALL let players maintain skill level from one through five, approximate location, availability, preferred positions, and reputation. It SHALL support public and invite-only match posts filtered by city or radius, skill range, availability, and required participant count.

#### Scenario: Player searches suitable matches
- **WHEN** a player searches using location, skill range, and availability filters
- **THEN** the platform SHALL return only public match posts satisfying those filters

#### Scenario: Invite-only match is viewed by a non-invitee
- **WHEN** a user who was not invited requests an invite-only match post
- **THEN** the platform SHALL not disclose the protected post details

### Requirement: Event-based reputation and disputes
The platform SHALL calculate reputation from the 20 most recent qualifying activities with an initial score of 60: attendance +2, late cancellation -8, no-show -15, and upheld dispute -10. Venue operators MUST NOT directly edit reputation; every dispute decision SHALL retain an audit record.

#### Scenario: Verified attendance is recorded
- **WHEN** a player's attendance is verified for a qualifying activity
- **THEN** the platform SHALL recalculate reputation using the last 20 qualifying activities

#### Scenario: Venue operator tries to alter a score
- **WHEN** a venue operator attempts to directly change a player's reputation
- **THEN** the platform SHALL deny the change

### Requirement: Supported tournament formats and score integrity
The platform SHALL support single-elimination and round-robin tournaments with seeded bracket/fixture generation, check-in, score entry, and standings. Given the same seeds and format, generation MUST be deterministic; conflicting score updates MUST be rejected rather than silently overwriting one another. Double-elimination is not included in this capability.

#### Scenario: Tournament bracket is generated
- **WHEN** an administrator generates a single-elimination bracket from an ordered seed list
- **THEN** the platform SHALL produce the same bracket for the same input

#### Scenario: Stale score update is submitted
- **WHEN** an official submits a score change based on an older tournament version
- **THEN** the platform SHALL reject it and return the current state for resynchronization

### Requirement: Realtime tournament state
The platform SHALL deliver committed tournament updates to authorized subscribers within two seconds at the 95th percentile and SHALL expose a versioned resynchronization path for clients that miss events.

#### Scenario: Score update commits
- **WHEN** an authorized score update is committed
- **THEN** authorized subscribers SHALL receive the new tournament version through the realtime channel

#### Scenario: Client misses an event
- **WHEN** a client detects a gap in tournament event versions
- **THEN** it SHALL be able to retrieve the authoritative current version and resume from it
