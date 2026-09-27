## MODIFIED Requirements

### Requirement: Player profiles and match discovery
Players SHALL persist sports, skill, approximate location, availability and visibility and discover public/invite-only activities by sport/location/time/skill/registration status. Generic customer-hosted events and owner-hosted drop-ins SHALL remain distinct. A customer with a verified booking entitlement SHALL be allowed to organize an activity using that right without needing facility ownership. Publication SHALL require valid time/deadline/capacity/eligibility and court usage rights; all court commitments SHALL participate in shared inventory. Profile/privacy choices SHALL control disclosure.

#### Scenario: Player searches suitable matches
- **WHEN** a player filters by sport, area, date, skill and availability
- **THEN** only permitted matching activities SHALL appear

#### Scenario: Invite-only match is viewed by a non-invitee
- **WHEN** an uninvited reader requests a protected activity
- **THEN** details SHALL not be disclosed

#### Scenario: Customer with court rights
- **WHEN** a customer publishes an activity against their valid booked court-time
- **THEN** the activity SHALL be permitted within that entitlement and SHALL not double-allocate inventory

#### Scenario: Invalid timing
- **WHEN** an ordinary activity ends before it starts or exceeds its court right
- **THEN** publication SHALL fail without reserving inventory

### Requirement: Event-based reputation and disputes
Reputation SHALL derive from up to the 20 most recent verified qualifying events, initially 60, bounded to 0–100: attendance +2, late cancellation -8, no-show -15 and an upheld violation -10 against the responsible party. Source existence, organization, participant and actual outcome SHALL be verified. Operators MUST NOT directly edit scores or assert arbitrary source identifiers. A successful appeal SHALL reverse or correct the challenged effect; a proven violation penalty SHALL apply only to the identified responsible party through a separate reasoned decision, never automatically punish the appellant. Audit and notification SHALL preserve provenance.

#### Scenario: Verified attendance is recorded
- **WHEN** a real eligible attendance event is processed twice
- **THEN** one score effect SHALL result

#### Scenario: Invented source
- **WHEN** a staff member submits a nonexistent or foreign source event
- **THEN** no qualifying activity or score change SHALL occur

#### Scenario: Successful appeal
- **WHEN** a no-show penalty is overturned
- **THEN** the incorrect deduction SHALL be reversed/recomputed and no automatic additional penalty SHALL target the appellant

#### Scenario: Venue operator tries to alter a score
- **WHEN** an operator attempts a direct reputation edit without a verified source event
- **THEN** the platform SHALL reject it without changing reputation

### Requirement: Supported tournament formats and score integrity
Single-elimination and round-robin tournaments SHALL provide explicit publication/registration deadlines, participant/fee eligibility, per-event organizer/official grants, check-in/presence/absence/withdrawal states, deterministic seeding and correct byes. Admitted participants SHALL satisfy capacity and verified fees or authorized waivers on every path. Score corrections SHALL require reason and expected version, recompute standings from accepted results and propagate affected rounds safely. Complete tournaments SHALL validate required results, publish a versioned final result and lock core data; controlled reopening SHALL preserve decisions/history. Double-elimination remains excluded.

#### Scenario: Tournament bracket is generated
- **WHEN** a seeded bracket has a non-power-of-two participant count
- **THEN** byes SHALL advance eligible participants to their correct next position without phantom matches

#### Scenario: Stale score update is submitted
- **WHEN** an official posts an obsolete tournament version
- **THEN** the update SHALL fail with authoritative state

#### Scenario: Correct completed round robin result
- **WHEN** an authorized official corrects a completed match with reason
- **THEN** standings SHALL be recomputed once without incrementing played/wins twice

#### Scenario: Correction affects next round
- **WHEN** a correction would change a player in an already-played next round
- **THEN** automatic overwrite SHALL stop pending a documented full affected-round resolution

#### Scenario: Finalize and reopen
- **WHEN** all required results are accepted and final publication occurs
- **THEN** core results SHALL lock and any later change SHALL require an authorized versioned reopening

#### Scenario: Delegated customer official
- **WHEN** a customer explicitly assigned to one tournament manages allowed results
- **THEN** the action SHALL succeed only in that tournament without organization-wide staff privileges

## ADDED Requirements

### Requirement: Activity registration roster and attendance
Activity registration SHALL validate open/deadline state, skills/conditions, invitation or approval, actual capacity and duplicates under concurrency. Approval-required requests SHALL not consume confirmed places before approval. Organizers SHALL finalize rosters at the deadline or explicit close; assigned attendance operators SHALL record present/late/absent/withdrawn statuses with reasons. Notifications SHALL reflect committed outcomes only.

#### Scenario: Unqualified join
- **WHEN** a user outside accepted skill/registration conditions requests a place
- **THEN** registration SHALL be refused without changing capacity

#### Scenario: Roster closes
- **WHEN** the organizer closes a valid activity roster
- **THEN** subsequent joins SHALL fail and existing members SHALL see the persisted roster

#### Scenario: Attendance correction
- **WHEN** an authorized person corrects an attendance outcome
- **THEN** reason/history SHALL persist and any reputation change SHALL reference the corrected evidence
