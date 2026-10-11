## MODIFIED Requirements

### Requirement: Authoritative ordinary checkout and promotion result
Ordinary V1 and V2 checkout SHALL produce equivalent wallet-funded confirmed outcomes under the existing wallet specification. Server-calculated coupon and membership eligibility and permitted combination SHALL be reflected in the accepted price snapshot; frontend totals SHALL NOT authorize discounts. Customers SHALL be able to preview and select same-tenant loyalty points before confirming a new ordinary booking. Accepted loyalty reduction, point debit, remaining wallet payment, booking confirmation and inventory allocation SHALL commit atomically with the policy snapshot and related financial records. Checkout without loyalty selection SHALL preserve existing behavior. Legacy held records SHALL NOT be confirmed without a recorded valid payment; this change SHALL NOT introduce a new pending-payment lifecycle or retroactive discounts on existing bookings. Specialized split and recurring invoice lifecycles retain their existing explicit rules.

#### Scenario: Tampered discount
- **WHEN** a client submits an unearned discount
- **THEN** checkout rejects or recalculates it without undercharging

#### Scenario: Legacy unpaid hold
- **WHEN** an old client confirms a held ordinary reservation without funds
- **THEN** confirmation fails without granting an active entitlement

#### Scenario: Customer discovers the loyalty control before payment
- **WHEN** a customer reviews selected court-times before the action that creates and pays for an ordinary booking
- **THEN** the interface SHALL offer a localized points control, explain the applicable organization and show the authoritative points, discount and remaining wallet amount before confirmation

#### Scenario: Atomic points and wallet checkout
- **WHEN** an eligible customer confirms a booking with 200,000 VND payable before loyalty, sufficient points, and a permitted 5,000 VND point reduction
- **THEN** checkout SHALL record the point debit and 5,000 VND reduction, charge 195,000 VND from the wallet and confirm the booking and its slots as one transaction

#### Scenario: Failure after a partial checkout step
- **WHEN** insufficient funds, an inventory conflict or a persistence failure prevents checkout completion
- **THEN** point and wallet balances, coupon usage, booking records, inventory and financial postings SHALL remain unchanged by the failed operation

#### Scenario: Loyalty covers the payable amount
- **WHEN** permitted loyalty redemption reduces the amount payable to zero
- **THEN** checkout SHALL confirm and record the fully discounted booking and point use without creating a fabricated positive wallet debit or a pending payment

#### Scenario: Reload after successful checkout
- **WHEN** a customer reloads a booking paid using points
- **THEN** booking detail and payment history SHALL show the persisted loyalty reduction and net paid amount consistent with point, wallet and financial records

## ADDED Requirements

### Requirement: Home availability identifies its selected court and date
The home availability panel SHALL identify the selected court and selected local play date adjacent to the slots and provide a nearby court selector. Its title MUST NOT imply all-court aggregation or always claim today's date when another date is selected. Each slot SHALL expose its start and end time and availability for that court only. Changing court/date SHALL refresh the matching slots, clear incompatible selections and prevent stale responses from presenting another court's availability as the selected court's. Labels SHALL support Vietnamese and English.

#### Scenario: One court is booked from 19 to 21
- **WHEN** court A is booked from 19:00 to 21:00 using hourly slots
- **THEN** its 19:00–20:00 and 20:00–21:00 slots SHALL be unavailable under a heading identifying court A and the selected date

#### Scenario: Another court remains available
- **WHEN** the user changes from booked court A to court B that has no conflicting booking or closure at 19:00–21:00
- **THEN** the panel SHALL identify court B and display both of its corresponding future slots as available

#### Scenario: Different play date
- **WHEN** the user selects a date other than today
- **THEN** the heading and returned slots SHALL represent that date in the venue's applicable local timezone without a misleading today label

#### Scenario: Court switches while requests are pending
- **WHEN** an older availability request completes after the user has selected another court or date
- **THEN** its response SHALL NOT replace the currently selected court/date's displayed availability
