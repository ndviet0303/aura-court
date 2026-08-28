## Context

See `proposal.md` for the motivation of supporting multiple sports. The current system models inventory via a `Court` that is tied directly to a `Venue` with venue-wide default pricing and an implicit 60-minute duration slot.

## Goals / Non-Goals

**Goals:**
- Migrate data model from Venue->Court to Venue->SportZone->Court
- Support sport-specific pricing rules
- Support sport-specific slot generation (e.g. 60 vs 90 minutes)

**Non-Goals:**
- Supporting "Multi-purpose courts" (where one physical court switches between badminton and futsal dynamically). We only support dedicated courts grouped by SportZone.

## Decisions

### 1. Data Model: SportZone
**Decision:** Introduce a `SportZone` model (`sport_zones` table) which acts as an intermediate entity between `Venue` and `Court`. 
**Rationale:** Venue owners need to apply pricing and operational hours to a group of courts of the same sport type. The `SportZone` represents this group.
**Alternatives considered:** 
- Adding `sport_id` and `slot_duration` directly to `Court`. Rejected because venue owners usually configure pricing for the whole badminton area, not for each court individually.

### 2. Migration Strategy
**Decision:** All existing `courts` will be migrated to belong to a default "Badminton" `SportZone` created for their respective `Venue`.
**Rationale:** Ensures backward compatibility. The v1 API can continue to read from the default `SportZone` to serve legacy clients without disruption.

## Risks / Trade-offs

- **Risk:** Existing legacy bookings might not have a `SportZone` context if we decouple heavily.
  **Mitigation:** `Court` will retain a direct foreign key to `Venue` (or it can be accessed via `SportZone->Venue`), and legacy data structures will be shimmed to return default badminton values if queried by legacy APIs.
