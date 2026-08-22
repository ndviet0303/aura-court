## Purpose

Extends confirmed reservations with long-running recurrence, shared payment, and
fair-price resale while maintaining inventory and payer-level financial integrity.

## ADDED Requirements

### Requirement: Long-running recurring reservations
The platform SHALL support weekly and monthly series of up to 12 months or 104 occurrences. On confirmation it MUST reserve every occurrence's inventory, invoice each month, notify the payer at least seven days before the next invoice, and release only future occurrences when a payment becomes overdue.

#### Scenario: Series is confirmed
- **WHEN** a customer confirms a valid recurring series
- **THEN** every occurrence within its stated horizon SHALL be reserved and associated with the series

#### Scenario: Monthly invoice becomes overdue
- **WHEN** an unpaid recurring invoice passes its due policy
- **THEN** the platform SHALL release only the series occurrences after the overdue period and retain historical occurrences

### Requirement: Split payment collection and refund allocation
The platform SHALL let a host divide a reservation amount into two through eight shares whose sum exactly equals the booking amount. Each share MUST have its own payment reference and 15-minute expiry; the reservation SHALL confirm only when paid shares equal the expected total, and refunds SHALL be allocated by the amount each payer actually paid.

#### Scenario: All split shares are paid
- **WHEN** paid split shares reach the exact expected total before expiry
- **THEN** the platform SHALL confirm the reservation exactly once

#### Scenario: Split group expires with an unpaid share
- **WHEN** one or more split shares remain unpaid at expiry and the host does not cover the shortfall
- **THEN** the platform SHALL release the held reservation according to the expiry policy

### Requirement: Controlled marketplace transfer
The platform SHALL permit listing only a confirmed reservation slot that has not started, begins at least two hours in the future, and is priced no higher than its original price. A buyer checkout MUST transfer the entitlement once only after buyer payment and seller refund are accepted; the seller refund MUST return to the original payment source less the configured marketplace fee, defaulting to five percent.

#### Scenario: Eligible slot sells
- **WHEN** a buyer completes checkout for an eligible unsold listing
- **THEN** the platform SHALL create one buyer entitlement, record the seller refund and marketplace fee, and mark the listing sold

#### Scenario: Seller refund cannot be accepted
- **WHEN** buyer collection succeeds but the seller refund cannot be accepted
- **THEN** the platform SHALL compensate the buyer according to the payment policy and make the listing available only when no entitlement transfer occurred

#### Scenario: Two buyers race to purchase a listing
- **WHEN** two buyers attempt checkout for the same eligible listing
- **THEN** at most one buyer SHALL receive the entitlement and the other SHALL receive a conflict or compensated outcome
