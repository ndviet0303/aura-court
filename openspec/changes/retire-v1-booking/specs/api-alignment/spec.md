## MODIFIED Requirements

### Requirement: Administrative venue lists use the supported API contract
Administrative pages that require the existing venue list SHALL obtain it from the supported `/api/admin/venues` contract under its existing authorization. They MUST NOT silently substitute an empty successful result when loading fails.

#### Scenario: Venue and court management load
- **WHEN** an authorized administrator opens `/admin/venues` or `/admin/manage`
- **THEN** the venue list SHALL load through the supported route and render its returned data without a route-not-found error

#### Scenario: Shared venue consumers load
- **WHEN** an authorized user opens staff, schedule, organization, pricing or shift reconciliation functionality that uses the same administrative venue-list contract
- **THEN** that functionality SHALL use the supported route with the existing response shape and authorization
