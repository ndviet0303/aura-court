## Purpose

Venue credit is a balance in VND that a venue owner's organization owes one customer. It is issued when that customer's slot resells through the marketplace, and it can be spent on the organization's bookings and POS sales or cashed out at its counter, so resale proceeds never need an owner bank transfer or platform custody.

## ADDED Requirements

### Requirement: Owner-issued venue credit
Venue credit SHALL be held per organization and customer as a whole number of VND that never goes negative and never expires. It SHALL be issued only by a completed paid marketplace transfer, for exactly the asking price, at the organization of the transferred slot. Credit SHALL NOT be topped up, bought, transferred to another user, converted to loyalty points or wallet balance, or spent at another organization. Every change SHALL be recorded as an immutable entry with its amount, the balance after it, its source and a reference. Concurrent spends SHALL NOT overdraw the balance.

Each issue, spend and cash-out SHALL post one balanced journal on the organization's owner ledger. Issue SHALL record a liability to the customer instead of revenue. Spending SHALL turn that liability into revenue. Cash-out SHALL settle it against counter cash. Credit balances, plus credit reserved for held bookings, MUST reconcile to that liability. Venue credit SHALL be separate from the legacy tenant wallet and from loyalty points. It SHALL NOT re-enable wallet top-up, wallet pages or wallet tender for direct-owner organizations.

#### Scenario: Credit issued on a sale
- **WHEN** a customer's 150,000 VND listing at organization A sells
- **THEN** the customer's credit at organization A SHALL rise by 150,000 VND with an entry referencing the listing, and organization A's owner ledger SHALL record a 150,000 VND liability to the customer

#### Scenario: Spend at another organization
- **WHEN** a customer with credit only at organization A tries to spend it on a booking or POS sale at organization B
- **THEN** the spend SHALL be refused and no balance SHALL change

#### Scenario: Top-up or transfer attempt
- **WHEN** a customer tries to deposit money into venue credit or send credit to another user
- **THEN** no such action SHALL be offered, and a direct request SHALL be refused without changing any balance

#### Scenario: Credit never expires
- **WHEN** a customer's credit has been unused for a year
- **THEN** the full balance SHALL remain spendable

#### Scenario: Ledger reconciliation
- **WHEN** an organization's credit balances and the credit reserved for its held bookings are summed
- **THEN** the total SHALL equal the owner ledger's credit liability for that organization

### Requirement: Venue credit at booking checkout
A customer SHALL be able to apply venue credit to an ordinary booking at any venue of the issuing organization. The amount SHALL be any part of the amount payable after vouchers, coupons, memberships and points, up to the available balance. Applied credit SHALL be reserved when the booking is held. It SHALL be consumed once when the booking is confirmed by a verified payment, and released once when the hold expires or the customer cancels it. When credit covers the whole amount payable, the booking SHALL be confirmed at once without a payment QR. Otherwise the QR SHALL ask for only the remainder. Recurring series, split payments, tournament registrations and marketplace purchases SHALL NOT accept venue credit.

#### Scenario: Partial credit
- **WHEN** a customer with 150,000 VND of credit books a 200,000 VND slot at the issuing organization and applies all their credit
- **THEN** the booking SHALL be held with a QR for 50,000 VND and 150,000 VND of credit SHALL be reserved
- **AND** when the 50,000 VND payment is verified, the booking SHALL be confirmed and the reserved credit consumed

#### Scenario: Full credit
- **WHEN** a customer's applied credit covers the whole amount payable
- **THEN** the booking SHALL be confirmed immediately, no payment QR SHALL be issued, and the credit SHALL be consumed

#### Scenario: Hold expires
- **WHEN** a held booking with reserved credit expires unpaid or is cancelled by the customer
- **THEN** the reserved credit SHALL return to the customer's balance exactly once

#### Scenario: Concurrent checkouts
- **WHEN** a customer with 100,000 VND of credit applies 100,000 VND in two checkouts at the same time
- **THEN** at most one checkout SHALL reserve the credit and the other SHALL be refused with 422

#### Scenario: Unsupported booking type
- **WHEN** a customer tries to apply credit to a recurring series, a split share, a tournament registration or a marketplace purchase
- **THEN** the request SHALL be refused without reserving credit

### Requirement: Venue credit as POS tender
A cashier with an authorized open shift SHALL be able to accept venue credit for an identified customer of the same organization. Credit MAY be the only tender or one tender of a mixed payment, up to the customer's balance. The credit SHALL be consumed when the order completes. A POS return of a credit-paid order SHALL put the credit portion back as credit. A credit tender without an identified customer, above the balance, or at another organization SHALL be refused without changing balances, stock or the cart.

#### Scenario: Credit pays the whole order
- **WHEN** a cashier with an open shift completes a 60,000 VND order for a customer with 150,000 VND of credit, using credit tender
- **THEN** the order SHALL complete, the customer's credit SHALL fall to 90,000 VND, and the shift's expected cash SHALL not change

#### Scenario: Mixed credit and cash
- **WHEN** a 100,000 VND order is paid with 40,000 VND of credit and 60,000 VND in cash
- **THEN** the order SHALL complete, 40,000 VND of credit SHALL be consumed, and the shift's expected cash SHALL rise by 60,000 VND

#### Scenario: Insufficient credit or no customer
- **WHEN** a cashier submits a credit tender above the customer's balance or without an identified customer
- **THEN** the sale SHALL be refused with 422 and the cart, stock and balance SHALL stay unchanged

#### Scenario: Return of a credit-paid order
- **WHEN** a cashier returns an order paid partly with 40,000 VND of credit
- **THEN** 40,000 VND SHALL go back to the customer's credit and the cash portion SHALL be handled as an ordinary cash return

### Requirement: Counter cash-out of venue credit
A customer SHALL be able to create a one-time cash-out code in the app for a chosen amount, up to their balance at one organization. A code SHALL be valid for ten minutes, usable once, and bound to the customer, the organization and the amount. A cashier of that organization with an authorized open shift SHALL be able to enter the code and see the customer's name and the amount. The cashier SHALL confirm after handing over the cash. Confirmation SHALL debit the credit once, record a cash-out movement with the code's reference on the shift, and lower the shift's expected cash by that amount. An expired, used or foreign code, a balance that has since fallen below the amount, or a cashier without an open shift SHALL be refused without changing any balance or shift.

#### Scenario: Cash-out at the counter
- **WHEN** a customer with 150,000 VND of credit creates a code for 100,000 VND and a cashier with an open shift at that organization confirms it
- **THEN** the customer's credit SHALL fall to 50,000 VND, the shift SHALL record a 100,000 VND cash-out with the code's reference, and its expected cash SHALL fall by 100,000 VND

#### Scenario: Code used twice
- **WHEN** two cashiers confirm the same code, at the same time or one after the other
- **THEN** exactly one cash-out SHALL occur and the other SHALL be refused

#### Scenario: Expired code
- **WHEN** a cashier enters a code more than ten minutes after it was created
- **THEN** the code SHALL be refused and no balance or shift SHALL change

#### Scenario: Code from another organization
- **WHEN** a cashier of organization B enters a code created for organization A
- **THEN** the code SHALL be refused without revealing the customer or the amount

#### Scenario: Balance spent after the code was created
- **WHEN** a customer creates a 100,000 VND code and then spends credit so that less than 100,000 VND remains before the cashier confirms
- **THEN** the cash-out SHALL be refused and the balance and shift SHALL stay unchanged

### Requirement: Venue credit visibility
A customer SHALL see their credit balance at each organization and the entries behind it: issue, reservation, release, spend and cash-out, each with its date, amount and source. A seller SHALL be notified when a sale issues credit, with the amount and the organization. The owner of an organization SHALL see that organization's outstanding credit total and its credit entries. A cashier SHALL see a customer's balance only while serving that identified customer at POS. Customers SHALL NOT see other customers' credit, and staff and owners of other organizations SHALL be refused with 403.

#### Scenario: Customer views credit
- **WHEN** a customer with credit at two organizations opens their credit page
- **THEN** they SHALL see each organization's balance and its entries with dates, amounts and sources

#### Scenario: Seller notified of a sale
- **WHEN** a seller's listing sells
- **THEN** the seller SHALL receive a notification naming the slot, the credit amount and the organization where it can be used

#### Scenario: Owner views outstanding credit
- **WHEN** an organization owner opens the credit list in finance
- **THEN** they SHALL see the organization's outstanding credit total and its entries, and nothing from other organizations

#### Scenario: Foreign reader
- **WHEN** an owner or staff member of another organization requests an organization's credit entries
- **THEN** the request SHALL be refused with 403
