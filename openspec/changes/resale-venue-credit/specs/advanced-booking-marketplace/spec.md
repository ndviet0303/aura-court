## ADDED Requirements

### Requirement: Owner-collected marketplace transfer
Only the current holder of a confirmed, unused, undisputed eligible booked slot SHALL list it for transfer under a published venue policy, with a default minimum of two hours before play and an asking price no higher than that slot's paid price. A slot's paid price SHALL be the booking's amount payable after every discount recorded on it (voucher, coupon, membership or points), and for a booking of several slots, that amount's share in proportion to the slot's recorded rental, with any fraction of a VND dropped. An asking price above it SHALL be rejected with 422 without creating a listing. A listing SHALL cover exactly one booked slot; the holder of a multi-slot booking SHALL list each slot separately. A listing SHALL NOT ask for, store or show the seller's bank account.

One effective listing and at most one active buyer hold SHALL exist per slot entitlement, and a buyer SHALL hold at most one listing at a time. Only a buyer with a verified email SHALL place a hold. A hold SHALL last the published hold period, by default fifteen minutes and never past the listing cutoff. The buyer SHALL be able to cancel their own hold before payment is verified. An expired or cancelled hold SHALL return the listing to sale once. The seller SHALL NOT release a buyer's hold and SHALL NOT withdraw a listing while a hold is active.

For a paid listing, the buyer SHALL pay the venue owner through the venue's verified receiving connection. The QR SHALL name that owner's account, the exact asking price and a server-generated reference. A paid hold SHALL be refused when the venue has no verified receiving connection. The entitlement SHALL transfer only on a verified provider receipt that matches the receiver, the reference and the exact amount, arrives while the hold is active, and finds the slot still transferable. Transferable means that cutoff, dispute, account restriction, venue policy and entitlement status are rechecked at that moment. Neither a buyer report nor a seller confirmation SHALL exist as a way to transfer or to block a transfer. A receipt that arrives after the hold ended, carries a different amount, or finds the slot no longer transferable SHALL NOT transfer the slot or issue credit. It SHALL become an owner payment exception, and the listing state SHALL stay unchanged.

On transfer, holder and check-in access SHALL update atomically. The buyer SHALL receive one new entitlement and new check-in authority, and the prior holder's access SHALL be revoked. The original court, time and booking price-policy snapshot SHALL be preserved. The consideration SHALL be recorded as owner-collected with zero fee, and the seller SHALL be issued venue credit equal to the asking price at the slot's organization. A repeated receipt SHALL produce at most one transfer, one credit issue and one ledger posting. Aura Court SHALL NOT collect or hold transfer money. It SHALL NOT charge a marketplace fee or commission, including for an unsold listing that stores an older fee rate. A transfer purchase SHALL NOT apply a voucher. Free transfer SHALL require explicit acceptance and SHALL transfer without payment and without credit. Previously completed transfers and their monetary records MUST remain unchanged.

#### Scenario: Eligible slot sells
- **WHEN** the buyer holding an eligible 200,000 VND listing pays the owner's QR with the exact amount and reference before the hold ends
- **THEN** one entitlement SHALL transfer, new check-in authority SHALL be issued, prior holder access SHALL be revoked and the original price preserved, and a 200,000 VND owner-collected consideration with zero fee SHALL be recorded
- **AND** the seller SHALL receive 200,000 VND of venue credit at the slot's organization, and one owner receipt SHALL be posted

#### Scenario: Discounted booking
- **WHEN** the holder of a booking with a 200,000 VND rental and a 50,000 VND voucher discount lists it
- **THEN** an asking price above 150,000 VND SHALL be rejected with 422 and an asking price of 150,000 VND SHALL be accepted

#### Scenario: Paid share of a multi-slot booking
- **WHEN** a legacy booking of two slots with rentals of 100,000 and 200,000 VND paid 240,000 VND after discounts, and its holder lists the second slot
- **THEN** the highest accepted asking price SHALL be ⌊240,000 × 200,000 / 300,000⌋ = 160,000 VND

#### Scenario: One hour of a multi-hour booking is listed
- **WHEN** the holder of a legacy 19:00–21:00 booking recorded as two one-hour slots lists the 20:00–21:00 slot and a buyer completes the purchase
- **THEN** only that slot's entitlement SHALL transfer and the 19:00–20:00 slot SHALL stay with the holder

#### Scenario: Seller lists without a bank account
- **WHEN** a holder lists an eligible slot at a price above zero
- **THEN** the listing SHALL be created without asking for a receiving account, and no seller account SHALL appear in any listing response

#### Scenario: Buyer holds a listing
- **WHEN** a verified buyer holds an available paid listing at a venue with a verified receiving connection
- **THEN** the buyer SHALL see a QR naming the venue owner's account, the exact asking price and a server-generated reference, with the hold's end time
- **AND** other users SHALL see the listing as unavailable

#### Scenario: Two buyers race for a listing
- **WHEN** buyers concurrently try to hold the same listing
- **THEN** at most one hold SHALL succeed and the other buyer SHALL receive a conflict

#### Scenario: Buyer already holds a listing
- **WHEN** a buyer with an active hold tries to hold a second listing
- **THEN** the request SHALL be refused with a conflict and the second listing SHALL stay available

#### Scenario: Unverified buyer
- **WHEN** a buyer whose email is not verified tries to hold a listing
- **THEN** the request SHALL be refused with 403 and no hold SHALL be created

#### Scenario: Venue without a verified receiving connection
- **WHEN** a buyer tries to hold a paid listing at a venue with no verified receiving connection
- **THEN** the hold SHALL be refused and the listing SHALL stay available

#### Scenario: Hold expires or is cancelled
- **WHEN** the buyer cancels the hold, or the hold period passes with no verified receipt
- **THEN** the hold SHALL end once, the listing SHALL return to sale, and no transfer or credit SHALL occur

#### Scenario: Seller tries to release or withdraw during a hold
- **WHEN** the seller tries to release a buyer's active hold or withdraw the held listing
- **THEN** the request SHALL be refused and the hold SHALL stay with the buyer

#### Scenario: Payment arrives after the hold ended
- **WHEN** a transfer with the hold's reference and exact amount reaches the owner's connection after the hold expired or was cancelled
- **THEN** no transfer and no credit SHALL occur, the payment SHALL be recorded as an owner payment exception, and the listing SHALL keep its current state

#### Scenario: Wrong amount
- **WHEN** a transfer with the hold's reference arrives for an amount other than the asking price
- **THEN** no transfer and no credit SHALL occur and the payment SHALL be recorded as an owner payment exception

#### Scenario: Slot stops being transferable before payment
- **WHEN** the listing's booking becomes disputed, the buyer becomes restricted or the cutoff passes before a matching receipt arrives
- **THEN** the receipt SHALL NOT transfer the slot or issue credit and SHALL be recorded as an owner payment exception

#### Scenario: Repeated provider receipt
- **WHEN** the same verified receipt for a held listing is delivered twice
- **THEN** exactly one transfer, one credit issue and one ledger posting SHALL exist

#### Scenario: Free transfer
- **WHEN** a holder lists a slot for free and an eligible buyer accepts it
- **THEN** the slot SHALL transfer without a QR, without a payment record and without venue credit

#### Scenario: Old unsold listing with a stored fee
- **WHEN** a buyer holds an eligible unsold listing that stores a five-percent fee rate
- **THEN** the buyer SHALL be asked to pay the owner exactly the asking price, the seller SHALL be credited the asking price, and no marketplace fee SHALL be recorded

#### Scenario: Historical fee
- **WHEN** a completed historical transfer contains commission or a peer-to-peer settlement
- **THEN** history SHALL remain unchanged while new transfers use owner collection with zero platform fee; its recorded amounts and financial journal SHALL remain available without a retroactive refund, credit or recomputation
