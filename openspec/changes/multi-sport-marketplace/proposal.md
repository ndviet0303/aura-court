## Why

The current booking system hardcodes the concept of a "Court" to a Badminton court, with fixed 60-minute blocks and venue-wide pricing. To scale the marketplace to other sports (e.g., Football 5v5/7v7, Pickleball, Tennis), the system must support diverse sports within a single venue. We need a flexible architecture that supports sport-specific configurations (pricing, slot durations) without overcomplicating inventory management for venue owners.

## What Changes

- Introduce `SportZone` (or Court Group) as a logical grouping of courts for a specific sport within a venue.
- Courts will belong to a `SportZone` instead of directly to a `Venue` (or practically, they belong to a Venue via a SportZone).
- Move pricing rules and slot durations from the `Venue` level to the `SportZone` level.
- **BREAKING**: API endpoints for fetching time slots and pricing will now require resolving the `SportZone` context, as slot durations may vary (e.g., 90 mins for football vs 60 mins for badminton).
- Update search and filter capabilities to allow users to search by Sport Type across venues.

## Capabilities

### New Capabilities
- `venue-sport-zones`: The ability for venue owners to configure distinct zones for different sports within a venue, each with its own pricing rules and slot duration.

### Modified Capabilities
- `reservations-and-pricing`: The reservation system must calculate quotes and time-grids based on the `SportZone` configuration rather than a venue-wide default.

## Impact

- **Database**: Introduction of `sport_zones` table; migration of existing courts to default badminton sport zones.
- **Backend API**: Modifications to time slot generation logic and reservation pricing calculators to read from `sport_zones`.
- **Frontend UI**: Search by sport type on the home page; venue details page will group courts and prices by sport zone.
