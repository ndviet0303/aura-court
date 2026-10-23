## Purpose

Keeps point-of-sale stock separately for each venue of an organization, so that sales, holds, receipts, returns and transfers change only the stock physically held at the venue where they happen.

## Requirements

### Requirement: Venue-scoped stock balance
Each tracked product SHALL have a separate on-hand quantity for every venue of its organization. The product catalog (SKU, barcode, name, price, cost, VAT, unit, minimum stock and active flag) SHALL remain organization-wide. A product read for a venue SHALL report that venue's on-hand quantity, and a venue with no recorded stock for a product SHALL report 0. An on-hand quantity MUST NOT become negative. Untracked services and rentals SHALL NOT require or change stock at any venue.

#### Scenario: Sale at one venue
- **WHEN** venue A and venue B of the same organization each hold 10 units of a drink and a cashier at venue A sells 2
- **THEN** venue A SHALL report 8 and venue B SHALL still report 10

#### Scenario: New venue
- **WHEN** an owner opens a new venue in an organization whose catalog already has products
- **THEN** every tracked product SHALL report 0 at the new venue until stock is received there

#### Scenario: Untracked service
- **WHEN** a cashier sells a service or rental item that does not track stock
- **THEN** the sale SHALL succeed without a stock check or stock change at any venue

### Requirement: Stock events bound to one venue
Every stock change SHALL record exactly one venue, together with the product, change type, signed quantity, actor, time, source reference and the resulting on-hand quantity at that venue. A receipt, issue or adjustment SHALL change the venue chosen by the authorized user. A completed sale SHALL deduct at the order's venue. A settled service tab SHALL deduct at the venue of the tab's booking. A pending QR order confirmed later SHALL deduct at the order's venue. An accepted return SHALL re-enter stock at the venue of the original order. A request that does not identify a venue for a stock change SHALL be rejected without any change.

#### Scenario: Receipt at a venue
- **WHEN** an owner receives 24 units of a product at venue B
- **THEN** only venue B SHALL increase by 24 and one movement SHALL record venue B, the actor and the new venue B balance

#### Scenario: Return after sale
- **WHEN** an owner accepts the return of 1 unit from an order completed at venue A while their own shift is at venue B
- **THEN** the unit SHALL re-enter stock at venue A, not venue B

#### Scenario: Late QR confirmation
- **WHEN** a verified bank receipt confirms a pending QR order of venue A
- **THEN** stock SHALL be deducted once at venue A and venue B SHALL be unchanged

#### Scenario: Missing venue
- **WHEN** a stock receipt or adjustment is submitted without a venue
- **THEN** it SHALL be rejected with a validation error and no quantity or movement SHALL change

### Requirement: Venue-scoped stock holds
The quantity available to sell at a venue SHALL equal its on-hand quantity minus the units held by pending QR orders of that venue and by open service tabs whose booking is at that venue. Holds at one venue MUST NOT reduce what another venue can sell. Concurrent requests for the last available units at a venue SHALL let at most one succeed.

#### Scenario: Hold at another venue
- **WHEN** venue A has 1 unit held by a pending QR order and venue B holds 1 unit with no holds
- **THEN** a cashier at venue B SHALL be able to sell that unit

#### Scenario: Last unit at a venue
- **WHEN** two cashiers at venue A request the only available unit at the same time
- **THEN** exactly one order or hold SHALL succeed and the other SHALL fail with an insufficient-stock error that names the product

### Requirement: Inter-venue stock transfer
An organization owner SHALL move a positive quantity of a tracked product from one venue to a different venue of the same organization. The transfer SHALL commit atomically: the source decreases, the destination increases by the same quantity, and one transfer record keeps the product, both venues, quantity, note, actor and time, linked to one outgoing and one incoming movement. A transfer SHALL be rejected without any change when the venues are the same, either venue belongs to another organization, the product is untracked or belongs to another organization, the quantity is not a positive integer, or the quantity exceeds the source venue's available quantity. Staff MUST NOT create transfers. A retried request with the same idempotency key SHALL return the original result without moving stock again.

#### Scenario: Owner moves stock
- **WHEN** an owner transfers 6 units from venue A holding 20 to venue B holding 2
- **THEN** venue A SHALL report 14, venue B SHALL report 8, and one transfer record with its two linked movements SHALL exist

#### Scenario: Transfer exceeds available stock
- **WHEN** venue A holds 5 units of which 2 are held by a pending QR order and an owner requests a transfer of 4
- **THEN** the transfer SHALL fail with an insufficient-stock error and neither venue SHALL change

#### Scenario: Foreign venue
- **WHEN** an owner requests a transfer to a venue of another organization
- **THEN** the request SHALL be rejected and neither venue SHALL change

#### Scenario: Staff attempts a transfer
- **WHEN** a staff member requests a transfer between two venues assigned to them
- **THEN** the request SHALL be rejected as forbidden

#### Scenario: Retried transfer
- **WHEN** a client resends a completed transfer request with the same idempotency key
- **THEN** the original transfer SHALL be returned and stock SHALL move only once

### Requirement: Venue-scoped inventory visibility
Stock levels, stock movements, transfers and inventory indicators (total units, stock value, low-stock count, out-of-stock count) SHALL be reported for one selected venue. An owner SHALL read any venue of the organization. Staff SHALL read only venues assigned to them. A read for a venue outside the caller's scope SHALL be rejected as forbidden. Low-stock and out-of-stock counts SHALL compare the selected venue's quantity with the product's organization-wide minimum.

#### Scenario: Staff reads an assigned venue
- **WHEN** a staff member assigned to venue A opens the inventory of venue A
- **THEN** quantities, movements and indicators SHALL reflect venue A only

#### Scenario: Staff reads another venue
- **WHEN** a staff member assigned only to venue A requests the stock or movements of venue B
- **THEN** the request SHALL be rejected as forbidden

#### Scenario: Low stock at one venue
- **WHEN** a product's minimum is 5, venue A holds 3 and venue B holds 30
- **THEN** venue A's indicators SHALL count it as low stock and venue B's SHALL not

### Requirement: Stock cutover preserves quantities
Moving from organization-wide to venue-scoped stock SHALL preserve every product's existing quantity and movement history. Existing quantities and all existing movements of an organization SHALL be assigned to one venue of that organization chosen by a documented deterministic rule, so that each venue's quantity still equals the sum of its movements; these movements SHALL be marked as assigned by the cutover. If an organization holds stock or movements but has no venue, the cutover SHALL stop without changing any data and SHALL report the affected organizations.

#### Scenario: Organization with several venues
- **WHEN** the cutover runs for an organization with stock of 36 units of a product and two venues
- **THEN** the chosen venue SHALL report 36, the other venue 0, and the organization's total SHALL remain 36

#### Scenario: Stock without a venue
- **WHEN** an organization holds stock but has no venue
- **THEN** the cutover SHALL fail before changing data and name that organization
