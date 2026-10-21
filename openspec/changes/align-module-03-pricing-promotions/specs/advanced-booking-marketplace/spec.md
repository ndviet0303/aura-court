## MODIFIED Requirements

### Requirement: Peer-to-peer marketplace transfer
Only the current holder of a confirmed, unused, undisputed eligible booked slot SHALL list it for transfer under a published venue policy, with a default minimum of two hours before play and an asking price no higher than that slot's paid price. A slot's paid price SHALL be the booking's amount payable after every discount recorded on it (voucher, coupon, membership or points), and for a booking of several slots, that amount's share in proportion to the slot's recorded rental, with any fraction of a VND dropped. An asking price above it SHALL be rejected with 422 without creating a listing. A listing SHALL cover exactly one booked slot; the holder of a multi-slot booking SHALL list each slot separately. A paid listing SHALL record the seller's own receiving account. That account SHALL be disclosed only to the seller and to the buyer holding the active purchase lock; it MUST NOT appear in listing lists, logs or responses to other users. One effective listing and at most one active buyer lock SHALL exist per slot entitlement. A lock SHALL expire automatically when its buyer neither reports payment nor cancels within the published lock period. A reported payment SHALL keep the lock until the seller resolves it or the listing cutoff passes.

The buyer SHALL pay the seller directly using the seller's displayed payment instructions. Aura Court and the venue owner MUST NOT collect, hold, route or reimburse transfer money, and MUST NOT charge a marketplace fee or commission, including for an unsold listing that stores an older fee rate. A transfer purchase SHALL NOT apply a voucher. The entitlement SHALL transfer only when the seller confirms receipt; a buyer's report of payment alone MUST NOT transfer it. On confirmation, holder and check-in access SHALL update atomically: one new entitlement for the buyer, prior holder access revoked, the original court/time/booking price-policy snapshot preserved, and the accepted consideration recorded separately with zero fee. A seller who reports non-receipt SHALL release the lock. Payment disagreements between buyer and seller SHALL be handled through support cases, and interfaces SHALL present a buyer's report as unconfirmed rather than as completed payment. Free transfer SHALL require explicit acceptance without fabricated payment. Previously completed transfers and their monetary records MUST remain unchanged.

#### Scenario: Eligible slot sells
- **WHEN** the buyer holding the lock on an eligible 200,000 VND listing pays the seller and the seller confirms receipt before cutoff
- **THEN** one entitlement SHALL transfer, new check-in authority SHALL be issued, prior holder access revoked, the original price preserved, and a 200,000 VND consideration with zero marketplace fee recorded, with no owner receipt, reimbursement obligation or ledger posting

#### Scenario: Discounted booking
- **WHEN** the holder of a booking with a 200,000 VND rental and a 50,000 VND voucher discount lists it
- **THEN** an asking price above 150,000 VND SHALL be rejected with 422 and an asking price of 150,000 VND SHALL be accepted

#### Scenario: Paid share of a multi-slot booking
- **WHEN** a legacy booking of two slots with rentals of 100,000 and 200,000 VND paid 240,000 VND after discounts, and its holder lists the second slot
- **THEN** the highest accepted asking price SHALL be ⌊240,000 × 200,000 / 300,000⌋ = 160,000 VND

#### Scenario: One hour of a multi-hour booking is listed
- **WHEN** the holder of a legacy 19:00–21:00 booking recorded as two one-hour slots lists the 20:00–21:00 slot and a buyer completes the purchase
- **THEN** only that slot's entitlement SHALL transfer and the 19:00–20:00 slot SHALL stay with the holder

#### Scenario: Buyer locks a listing
- **WHEN** an eligible buyer locks an available paid listing
- **THEN** only that buyer and the seller SHALL see the seller's receiving account and a payment QR for the exact asking price, and other users SHALL see the listing as unavailable

#### Scenario: Two buyers race to purchase a listing
- **WHEN** buyers concurrently try to lock the same listing
- **THEN** at most one lock SHALL succeed and the other buyer SHALL receive a conflict without seeing the seller's account

#### Scenario: Lock expires without a payment report
- **WHEN** the buyer neither reports payment nor cancels within the lock period
- **THEN** the lock SHALL expire once and the listing SHALL return to sale

#### Scenario: Buyer reports payment
- **WHEN** a buyer reports payment and the seller has not yet confirmed receipt
- **THEN** the entitlement SHALL remain with the seller and the listing SHALL stay locked for that buyer until the seller confirms, reports non-receipt or the cutoff passes

#### Scenario: Seller reports non-receipt or cutoff passes
- **WHEN** the seller reports non-receipt, or the cutoff passes without seller confirmation
- **THEN** no transfer SHALL occur, the buyer SHALL be notified, and the buyer SHALL be able to open a support case referencing the listing

#### Scenario: Dispute or restricted buyer
- **WHEN** a listing's booking becomes disputed or the buyer is restricted before seller confirmation
- **THEN** transfer SHALL fail without changing ownership

#### Scenario: Old unsold listing with a stored fee
- **WHEN** a buyer locks an eligible unsold listing that stores a five-percent fee rate
- **THEN** the buyer SHALL be asked to pay the seller exactly the asking price and no marketplace fee SHALL be recorded

#### Scenario: Historical fee
- **WHEN** a completed historical transfer contains commission
- **THEN** history SHALL remain unchanged while new transfers use zero platform fee; its recorded buyer amount, seller refund, marketplace fee and financial journal SHALL remain available without a retroactive refund or recomputation
