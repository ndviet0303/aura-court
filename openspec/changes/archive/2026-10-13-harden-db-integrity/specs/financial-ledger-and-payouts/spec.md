## ADDED Requirements

### Requirement: Financial history blocks owner deletion
Deleting a record that owns financial history SHALL be refused while that history exists; it MUST NOT cascade. An owner is any record that financial history references, for example an organization, user, booking, reservation, POS order or product, cash shift, wallet account, subscription plan, subscription, payment attempt, refund obligation, recurring series or match post. Financial history includes ledger accounts, journals and journal entries; payments, refunds and refund obligations with their allocations and evidence; owner and platform payment attempts and receipts; subscriptions and monthly invoices; settlements, payouts and beneficiaries; wallet accounts and transactions; point ledgers; POS orders, items and payments; cash shifts and shift reconciliations; service tabs and their items; split groups and shares; match bills; stock movements; entitlement transfers; and elevated audit logs. A refused deletion SHALL leave every record unchanged. Records with no financial history SHALL keep their existing deletion behavior.

#### Scenario: Organization with POS history is deleted
- **WHEN** an attempt is made to delete an organization that has at least one POS order
- **THEN** the deletion SHALL be refused, and the organization, its POS orders, items and payments SHALL remain unchanged

#### Scenario: User with a wallet is deleted
- **WHEN** an attempt is made to delete a user who has a wallet account or a point ledger entry
- **THEN** the deletion SHALL be refused and the wallet transactions and point history SHALL remain reportable

#### Scenario: Organization with journals is deleted
- **WHEN** an attempt is made to delete an organization that has posted journals
- **THEN** the deletion SHALL be refused through the referential rule itself, not only through the append-only journal guard

#### Scenario: Owner without financial history
- **WHEN** a record with no dependent financial history is deleted through a supported workflow
- **THEN** the deletion SHALL behave as before this requirement
