# Advanced Booking Marketplace Specification

## Purpose

Extends confirmed reservations with long-running recurrence, shared payment, and
fair-price resale while maintaining inventory and payer-level financial integrity.

## Requirements

### Requirement: Long-running recurring reservations
The platform SHALL support weekly, biweekly and monthly series bounded by published venue policy and at most 12 months/104 occurrences. Client/server SHALL agree on weekdays, recurrence dates, per-session duration and count. A server preview SHALL price/check every full interval and expose conflicts; all-or-nothing or partial acceptance SHALL follow published policy and require acceptance of the final occurrence/price list. Full-series or per-period payment SHALL be disclosed; only verified paid or explicitly policy-guaranteed occurrences SHALL confirm. Unpaid holds and overdue future periods SHALL release according to captured policy, preserving paid/history and allowing scoped occurrence changes.

#### Scenario: Series is confirmed
- **WHEN** a customer accepts a multi-weekday two-hour biweekly preview
- **THEN** persisted dates, durations, prices and counts SHALL exactly match the accepted preview without truncating to twelve or reserving only one slot

#### Scenario: Partial conflict
- **WHEN** one occurrence is occupied under partial-acceptance policy
- **THEN** the platform SHALL show the excluded occurrence and revised total for explicit acceptance before holding

#### Scenario: All-or-nothing conflict
- **WHEN** one occurrence conflicts under all-or-nothing policy
- **THEN** no partial booking or payment allocation SHALL persist

#### Scenario: Period payment
- **WHEN** only one period has verified payment and no payment guarantee exists
- **THEN** only that period SHALL confirm; later occurrences remain under disclosed hold/payment rules

#### Scenario: Monthly invoice becomes overdue
- **WHEN** an unpaid period becomes overdue
- **THEN** only eligible unpaid future intervals SHALL release once, with notice and no corruption of completed sessions

### Requirement: Split payment collection and refund allocation
A host SHALL allocate the accepted booking total to two through eight real permitted participants using positive whole-VND shares with deterministic remainder allocation. Each share SHALL have a unique direct-owner payment reference and expiry no later than the inventory hold. Only verified receipts SHALL mark shares paid; host shortfall coverage SHALL itself require payment. Booking SHALL confirm once when verified allocations reach its exact payable total without an additional wallet debit. Expired groups SHALL release holds and retain owner-refund obligations for already received amounts. Refunds SHALL follow actual payer allocations.

#### Scenario: All split shares are paid
- **WHEN** the final verified share arrives before expiry
- **THEN** one booking SHALL confirm without charging the host again

#### Scenario: UI request failed
- **WHEN** share payment/status request fails
- **THEN** the UI SHALL show failure/pending, preserve real state and never mark a sample participant paid

#### Scenario: Split group expires with an unpaid share
- **WHEN** a group expires after two participants paid
- **THEN** the hold SHALL release once and those paid allocations SHALL remain traceable refund obligations

#### Scenario: Host covers shortfall
- **WHEN** the host elects to cover unpaid shares
- **THEN** a referenced payment obligation SHALL be created and coverage SHALL not complete until verified

### Requirement: Controlled marketplace transfer
Only the current holder of confirmed, unused, undisputed eligible court-time SHALL list a transfer under a published venue policy, with default minimum two hours before play and asking price no higher than original. One effective listing and one exclusive buyer attempt SHALL exist per entitlement. The new holder SHALL be valid and any payment obligations SHALL be independently verified before transfer. Baseline paid transfer SHALL collect directly to the verified venue owner and track owner-executed seller reimbursement under disclosed terms; free transfer SHALL require explicit acceptance without fabricated payment. Aura Court MUST NOT hold or pay between parties or accrue commission. The original court/time/booking price-policy snapshot SHALL remain, consideration recorded separately; holder/check-in access SHALL update atomically. Failed/late financial outcomes SHALL enter reconciliation before relisting.

#### Scenario: Eligible slot sells
- **WHEN** verified buyer payment and required seller reimbursement evidence satisfy the published transfer policy
- **THEN** one entitlement SHALL transfer, new check-in authority SHALL be issued, prior holder access revoked and original price preserved

#### Scenario: Dispute or restricted buyer
- **WHEN** a listing's booking becomes disputed or the buyer is restricted before commitment
- **THEN** transfer SHALL fail without changing ownership and any received money SHALL enter owner obligation tracking

#### Scenario: Two buyers race to purchase a listing
- **WHEN** buyers concurrently accept the same listing
- **THEN** at most one SHALL receive the entitlement; other receipts SHALL not be silently consumed

#### Scenario: Client refund flag
- **WHEN** a buyer submits a seller-refund-accepted flag without evidence
- **THEN** the platform SHALL not treat it as fulfilled financial obligation

#### Scenario: Historical fee
- **WHEN** a completed historical transfer contains commission
- **THEN** history SHALL remain unchanged while new transfers use zero platform fee

#### Scenario: Seller refund cannot be accepted
- **WHEN** buyer funds were received but required seller reimbursement cannot be evidenced
- **THEN** the transfer SHALL remain unresolved, no new holder SHALL be issued, the owner SHALL have a traceable refund/resolution obligation and relisting SHALL wait until competing financial obligations are cleared
