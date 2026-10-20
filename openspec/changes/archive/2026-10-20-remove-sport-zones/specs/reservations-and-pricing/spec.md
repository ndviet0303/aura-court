## MODIFIED Requirements

### Requirement: Immutable price quotes
The platform SHALL calculate quotes from effective-dated rules scoped to the organization, a venue, a venue and one sport, or a single court, using holiday/date exception, peak, weekly then explicitly configured base precedence. Within one precedence level, the narrower scope SHALL win (court, then venue and sport, then venue, then organization), followed by priority, version and identifier as deterministic tie-breakers. A sport-scoped rule SHALL name its venue and SHALL apply only to that venue's courts of that sport. Prices and allowed coupon, membership and loyalty benefits SHALL snapshot versions and validity. A random unmatched slot price MUST NOT be a fallback. Later changes SHALL not alter an accepted booking. An expired quote SHALL be recalculated and accepted again before payment.

#### Scenario: Exception overrides a weekly price
- **WHEN** both a holiday exception and weekly rule match
- **THEN** both API versions and displayed availability SHALL use the same exception amount

#### Scenario: No applicable price
- **WHEN** a requested open interval has no applicable rule or configured base
- **THEN** it SHALL not be sold using the first unrelated price

#### Scenario: Price rules change after quote acceptance
- **WHEN** a policy changes after quote acceptance
- **THEN** the accepted snapshot SHALL remain stable; an expired unaccepted quote requires renewed consent

#### Scenario: Venue and sport rule
- **WHEN** a venue has a weekly venue-wide rule and a weekly pickleball rule for the same hour, and a customer quotes that hour on a pickleball court and on a badminton court of the venue
- **THEN** the pickleball court SHALL be priced by the pickleball rule, the badminton court by the venue-wide rule, and a court-specific rule for that hour SHALL override both

#### Scenario: Sport rule without a venue
- **WHEN** an operator saves a sport-scoped price rule without naming a venue
- **THEN** the request SHALL be rejected with a validation error

#### Scenario: Prices survive the grouping removal
- **WHEN** existing grouping-scoped rules are migrated to venue-and-sport scope
- **THEN** every court SHALL be quoted the same amount for the same interval before and after, or the migration SHALL stop without changing any data
