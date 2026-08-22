## Purpose

Provides tenant-scoped point-of-sale, membership, loyalty, and closed-loop credit
features that reconcile operational activity with the financial ledger.

## ADDED Requirements

### Requirement: POS order, inventory, and booking attachment
The platform SHALL let authorized tenant staff manage products, SKU/barcodes, stock movements, carts, discounts, and cash/QR/wallet payments. A POS order MAY attach to a reservation and MUST record its stock and payment effects. When connectivity is unavailable, staff MAY save a local draft but MUST NOT finalize payment or inventory movement.

#### Scenario: Cashier completes a POS order
- **WHEN** a cashier finalizes a connected POS order with sufficient stock and valid payment
- **THEN** the platform SHALL create the order, stock movement, and associated financial records

#### Scenario: POS loses connectivity
- **WHEN** a cashier is offline while preparing a cart
- **THEN** the platform SHALL permit saving a draft but SHALL block finalizing payment and inventory movement

### Requirement: Shift close and cash reconciliation
The platform SHALL require cashiers to open and close shifts and SHALL compare the declared cash amount with the expected recorded cash activity. A difference MUST create a reconciled adjustment or an auditable exception; closing a shift MUST NOT rewrite completed POS or ledger records.

#### Scenario: Shift balances
- **WHEN** a cashier closes a shift whose declared cash equals expected cash
- **THEN** the platform SHALL record a successful cash reconciliation

#### Scenario: Shift has a cash difference
- **WHEN** a cashier closes a shift with a declared cash difference
- **THEN** the platform SHALL record the variance and create an auditable reconciliation outcome

### Requirement: Versioned tenant memberships and loyalty
The platform SHALL provide tenant-scoped memberships whose benefits are versioned and preserved for already-earned entitlement periods. It SHALL award and redeem loyalty points only under the tenant's active rules and SHALL record the source and use of every point change.

#### Scenario: Membership plan changes
- **WHEN** a tenant publishes a new version of a membership benefit
- **THEN** existing active membership entitlements SHALL retain the benefit version assigned to them

#### Scenario: Customer redeems loyalty points
- **WHEN** a customer meets a tenant rule for loyalty redemption
- **THEN** the platform SHALL apply the permitted benefit and record the point debit with its source transaction

### Requirement: Closed-loop tenant wallet
The platform SHALL provide wallet credit that is scoped to its issuing tenant, cannot become negative, and cannot be withdrawn, transferred to another user, or spent at another tenant. Wallet balances and liability MUST reconcile to their recorded financial entries.

#### Scenario: Customer pays with tenant wallet credit
- **WHEN** a customer has sufficient wallet credit at its issuing tenant
- **THEN** the platform SHALL debit the wallet and record the corresponding payment and liability effects

#### Scenario: Customer attempts cross-tenant transfer
- **WHEN** a customer requests a wallet withdrawal, user transfer, or payment at another tenant
- **THEN** the platform SHALL reject the request without changing wallet credit
