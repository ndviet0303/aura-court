## ADDED Requirements

### Requirement: Owner transaction reconciliation
The organization owner SHALL list the booking payment transactions received for the venues of their organization, filtered by venue and period. Each transaction SHALL show:
- the related booking, when it is identified;
- the booking's amount payable Pᵢ, read from the recorded rental minus the recorded discount and never recalculated from current prices, also after a court or time change;
- the amount received Aⱼ;
- the transaction time;
- the reconciliation outcome;
- the difference Δⱼ = Aⱼ − Pᵢ, where Δⱼ = 0 is a match, Δⱼ < 0 is a shortfall and Δⱼ > 0 is an excess.

The list SHALL reuse the receipts, allocations and payment attempts of booking payment processing and SHALL NOT create transactions of its own. A transaction SHALL NOT be assigned to a booking because the amounts are equal, and the amount received SHALL NOT be altered to match a booking. A matching amount SHALL NOT replace the code, deadline and no-duplicate conditions of booking payment processing. A transaction without an identified booking SHALL show no Δ.

#### Scenario: Matched payment
- **WHEN** a booking with rental 300,000 VND and voucher discount 50,000 VND receives a transfer of 250,000 VND that was allocated to it
- **THEN** the transaction SHALL show the booking, Pᵢ = 250,000, Aⱼ = 250,000 and Δ = 0

#### Scenario: Short transfer
- **WHEN** a transfer of 200,000 VND carries the payment code of a booking whose amount payable is 250,000 VND
- **THEN** the transaction SHALL show the booking, its exception outcome and Δ = −50,000, and the booking SHALL not be confirmed by it

#### Scenario: Transfer without a code
- **WHEN** a transfer of exactly 250,000 VND arrives without any booking payment code
- **THEN** it SHALL show no booking and no Δ, even if a pending booking has the same amount

### Requirement: Owner financial report metrics
The organization owner SHALL view a financial report for the venues of their organization, filtered by period, venue and court. The report SHALL show five separate metrics, each with its amount and the number of distinct booking codes, plus the bookings behind each metric. Pᵢ SHALL be the booking's recorded amount payable.

The five metrics:
- **Received payments (R):** the sum of the valid booking payment transactions whose recorded payment time is in the period.
- **Completed revenue (H):** the sum of Pᵢ over bookings whose attendance was completed, with the completion time in the period.
- **Unfinished paid bookings (U):** the sum of Pᵢ over bookings whose valid payment was confirmed in the period and that are confirmed or in use at the time of viewing. The value MAY change on a later view as statuses change.
- **No-show bookings (N):** the sum of Pᵢ over bookings recorded as no-show, with the recording time in the period.
- **Bookings ended by an incident (S):** the sum of Pᵢ over paid bookings that the venue ended because of an operational incident, with the ending time in the period.

Rules across metrics:
- S SHALL exclude bookings still under incident handling, bookings moved to another court or time and still served, and bookings ended before payment.
- N and S SHALL NOT be added to H. S SHALL NOT be presented as money to refund or refunded.
- A booking of 0 VND SHALL be counted and SHALL add 0 VND.
- The report SHALL NOT add the metrics into one total revenue.
- The report SHALL NOT include point-of-sale sales, refunds, operating costs, revenue sharing or platform fees, and it SHALL NOT record attendance outcomes itself.

#### Scenario: Metrics kept separate
- **WHEN** a booking paid on 30 September is completed on 2 October and the owner views October
- **THEN** it SHALL count in H for October and not in R or U for October, and no total revenue SHALL be shown

#### Scenario: Unfinished booking completes later
- **WHEN** a booking paid in October is still confirmed when the owner views October, and is completed before the next view
- **THEN** the first view SHALL count it in U, and the next view SHALL count it in H and no longer in U

#### Scenario: No-show and incident
- **WHEN** one paid booking is recorded as no-show and another paid booking is ended by the venue because of an incident in the period
- **THEN** the first SHALL count only in N, the second only in S, and neither in H

#### Scenario: Free booking
- **WHEN** a 0 VND booking is completed in the period
- **THEN** H SHALL count one more booking and its amount SHALL not change

#### Scenario: Court filter
- **WHEN** the owner filters one court of a venue
- **THEN** every metric and every detail row SHALL include only bookings of that court

### Requirement: Financial reporting period
Financial reports SHALL interpret periods in Vietnam time. A record SHALL fall in a period when start ≤ its time < end. A period chosen by calendar day, month, quarter or year SHALL follow the calendar and SHALL NOT use the 30-, 90-, 180- or 360-day package terms. Times SHALL be stored in UTC and converted only for the period bounds and display.

#### Scenario: Midnight boundary
- **WHEN** a payment is recorded at 00:00 Vietnam time on 1 November and the owner views October
- **THEN** it SHALL NOT appear in October and SHALL appear in November

#### Scenario: Calendar month
- **WHEN** the owner selects February
- **THEN** the period SHALL run from 1 February 00:00 to 1 March 00:00 Vietnam time, not 30 days

### Requirement: Owner-only finance access and export
Only the organization owner SHALL view, reconcile and export financial transactions and reports, and only for the venues of their organization. Staff, owners of other organizations and platform administrators without an owner membership SHALL receive 403.

The owner SHALL export the financial report to an Excel file:
- The file SHALL match the report and its filters at export time.
- It SHALL state the report name, the venue and court scope, the period, the export time, the five metrics with their counts and the detail rows.
- It SHALL contain only data within the owner's scope.

#### Scenario: Export matches the screen
- **WHEN** the owner exports the October report filtered to one venue
- **THEN** the file SHALL contain the same five metrics, counts and detail rows as the screen, with the venue, the period and the export time

#### Scenario: Staff access
- **WHEN** staff assigned to a venue open the financial transactions, the financial report or its export
- **THEN** the platform SHALL answer 403 and the staff interface SHALL NOT offer these screens

#### Scenario: Another organization
- **WHEN** an owner requests the report of a venue of another organization
- **THEN** the platform SHALL answer 403 without revealing its data
