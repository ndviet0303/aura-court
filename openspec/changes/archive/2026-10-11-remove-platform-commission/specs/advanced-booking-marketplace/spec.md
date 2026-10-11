## MODIFIED Requirements

### Requirement: Controlled marketplace transfer
The platform SHALL permit listing only a confirmed reservation slot that has not started, begins at least two hours in the future, and is priced no higher than its original price. A buyer checkout MUST transfer the entitlement once only after buyer payment and seller refund are accepted; the seller refund MUST return to the original payment source for the accepted resale price without a marketplace fee or platform commission. A new purchase after commission retirement SHALL use zero marketplace fee even if an unsold listing retains an older nonzero fee rate. Previously completed transfers and their monetary records MUST remain unchanged.

#### Scenario: Eligible slot sells
- **WHEN** a buyer completes checkout for an eligible unsold listing at 200,000 VND after retirement
- **THEN** the platform SHALL create one buyer entitlement, record a 200,000 VND seller refund and zero marketplace fee, and mark the listing sold

#### Scenario: Seller refund cannot be accepted
- **WHEN** buyer collection succeeds but the seller refund cannot be accepted
- **THEN** the platform SHALL compensate the buyer according to the payment policy and make the listing available only when no entitlement transfer occurred

#### Scenario: Two buyers race to purchase a listing
- **WHEN** two buyers attempt checkout for the same eligible listing
- **THEN** at most one buyer SHALL receive the entitlement and the other SHALL receive a conflict or compensated outcome

#### Scenario: Old unsold listing is purchased after retirement
- **WHEN** a buyer starts a new purchase of an eligible unsold listing with a stored five-percent fee
- **THEN** the purchase SHALL charge zero marketplace fee and return the accepted resale price to the seller without changing any prior completed transfer

#### Scenario: Completed historical transfer is inspected
- **WHEN** a transfer completed before retirement is inspected after retirement
- **THEN** its recorded buyer amount, seller refund, marketplace fee and financial journal SHALL remain available without a retroactive refund or recomputation
