## MODIFIED Requirements

### Requirement: Immutable price quotes
The platform SHALL calculate a price quote from active tenant and sport zone rules using this precedence: holiday or date exception, peak rule, weekly time rule, then base price. The accepted quote MUST snapshot the applied prices and rule version so later rule changes cannot change the checkout amount. Pricing rules MUST be resolved against the specific Sport Zone containing the reserved court, not a venue-wide default.

#### Scenario: Exception overrides a weekly price
- **WHEN** a requested slot matches both a date exception and a weekly time rule within the sport zone
- **THEN** the returned quote SHALL use the date exception price

#### Scenario: Price rules change after quote acceptance
- **WHEN** an administrator changes a pricing rule for a sport zone after checkout accepts a quote
- **THEN** the reservation's recorded amount SHALL remain unchanged

#### Scenario: Different sport zones have different prices
- **WHEN** a user requests a quote for a Football court and a Badminton court at the same venue for the same time
- **THEN** the returned quotes SHALL apply the respective sport zone's pricing rules, resulting in different amounts if configured differently
