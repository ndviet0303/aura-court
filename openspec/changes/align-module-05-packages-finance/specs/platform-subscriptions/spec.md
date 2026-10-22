## ADDED Requirements

### Requirement: Venue package terms and prices
The platform SHALL sell platform usage as a package attached to exactly one venue. A payment or renewal for one venue SHALL NOT change the package of any other venue, including venues of the same owner. There SHALL be exactly four terms, 30, 90, 180 and 360 days, where a day is 24 hours, and all terms SHALL grant the same functions.

Platform administrators SHALL set prices:
- Each term SHALL have its own price in whole VND, together with the time it takes effect. A price SHALL NOT be derived from another term's price.
- A price whose effective time is not in the future SHALL take effect when it is set.
- A new price for a term SHALL replace that term's prices that are scheduled and not yet effective. Prices that already took effect SHALL remain recorded.
- The applicable price of a term at time t SHALL be the latest price of that term, among those not replaced by t, whose effective time is at or before t.
- Only platform administrators SHALL set prices.

When a price is set, every active organization owner SHALL receive an in-app notification and an email that name the term, the new price and its effective time. No fixed notice period SHALL apply. Owners and platform administrators SHALL see, for each term, its number of days, its applicable price and its next scheduled price, if any. Rental vouchers SHALL NOT discount a package.

#### Scenario: Price scheduled for a later date
- **WHEN** an administrator sets 300,000 VND for the 30-day term effective on 1 December
- **THEN** requests created before 1 December SHALL use the earlier applicable price, requests created from then SHALL use 300,000 VND, and every active owner SHALL receive an in-app notification and an email naming the term, the price and 1 December

#### Scenario: Scheduled price replaced before it takes effect
- **WHEN** an administrator sets a new 90-day price before the scheduled 90-day price takes effect
- **THEN** the replaced price SHALL never apply, the new price SHALL apply from its own effective time, and the replaced price SHALL remain in the price history

#### Scenario: Effective time in the past
- **WHEN** an administrator sets a price with an effective time earlier than now
- **THEN** the price SHALL take effect when it is set

#### Scenario: Price set by someone other than a platform administrator
- **WHEN** an organization owner or staff member tries to set a package price
- **THEN** the platform SHALL answer 403 and no price SHALL change

### Requirement: Package payment requests
The owner SHALL register or renew the package of a venue of their organization by creating a payment request for one term. Before creation, the interface SHALL show the term's number of days and the amount payable.

The request kind SHALL follow the venue's package at creation:
- It SHALL be a purchase when the venue has no package, or its package has expired or was terminated. A purchase MAY use any term.
- It SHALL be a renewal when the package is active. A renewal SHALL use the term of the active package; another term SHALL be rejected with 409 `PACKAGE_TERM_LOCKED`.

The request SHALL record the term, the price applicable at its creation and the amount, none of which SHALL change afterwards. It SHALL expire 10 minutes after creation. It SHALL show a request code, a payment code, a QR code, the amount, AuraCourt's receiving bank account and the deadline.

A venue SHALL have at most one unexpired pending request:
- Creating a request for the same term SHALL return the existing request unchanged.
- Creating a request for another term SHALL be rejected with 409 `PACKAGE_REQUEST_PENDING`.
- Reopening a request SHALL NOT extend its deadline or change its amount.

A term without an applicable price SHALL be rejected with 422 `PACKAGE_PRICE_UNAVAILABLE`. A permanently closed venue SHALL NOT receive a request.

A request not paid successfully by its deadline SHALL be shown as expired ("Hết hạn thanh toán") from that moment, even before any scheduled job runs, and SHALL NOT grant usage time. There SHALL be no action to cancel a request. Pending and expired requests SHALL NOT count as collected fees or as receivables. The owner interface SHALL explain each rejection code in Vietnamese or English.

#### Scenario: Purchase for a venue without a package
- **WHEN** the owner creates a 90-day request for a venue that has never had a package
- **THEN** a purchase request SHALL be created with the applicable 90-day price, a payment code, a QR code for AuraCourt's account and a deadline 10 minutes later

#### Scenario: Reopening a pending request
- **WHEN** the owner opens the payment of a pending request again 6 minutes after creating it, or asks again for the same term
- **THEN** the same request SHALL be shown with the same codes, amount and deadline, and no new request SHALL be created

#### Scenario: Another term while a request is pending
- **WHEN** the owner asks for a 30-day request while a 90-day request of the same venue is still pending
- **THEN** the platform SHALL answer 409 `PACKAGE_REQUEST_PENDING` with the pending request and create nothing

#### Scenario: Renewal with another term
- **WHEN** the owner of a venue with an active 30-day package asks for a 180-day request
- **THEN** the platform SHALL answer 409 `PACKAGE_TERM_LOCKED` and create nothing

#### Scenario: Price change after creation
- **WHEN** a new price takes effect while a request created at the old price is still pending
- **THEN** the request SHALL keep its amount, and a request created after it expires SHALL use the new price

#### Scenario: Deadline passes without payment
- **WHEN** 10 minutes pass after creation without a successful payment
- **THEN** the request SHALL be shown as expired immediately, the package SHALL not change, and the owner SHALL be able to create a new request

### Requirement: Verified package activation and renewal
Package fees SHALL be paid by bank transfer to AuraCourt's receiving account. They SHALL be confirmed only through the authenticated notification of AuraCourt's payment provider.

Every incoming transaction whose content carries a package payment code SHALL be stored once, with its provider transaction identity, amount, content, transaction time and outcome. It SHALL NOT be treated as a wallet top-up or a booking payment.

A transaction SHALL activate or renew a package only when all of these hold:
- its payment code matches a pending request;
- its amount equals the request amount exactly;
- its transaction time is before the request deadline.

Under a lock on the venue:
- If the venue's package is active with the same term, its end SHALL move forward by the term's days from the current end.
- If the venue has no package, or its package has expired or was terminated, a new package SHALL start at the transaction time and end the term's days later.
- A transaction for another term while a package is active SHALL NOT change the package.

The request SHALL become paid, with its payment time and the resulting period.

A transaction that is late, has a different amount, carries an unknown code, targets a request already paid or conflicts with the active term SHALL be stored with that outcome for reconciliation. It SHALL NOT activate or extend a package and SHALL NOT count as collected. A repeated notification of the same transaction SHALL NOT create a package, extend it, store it again or count it again.

An applied payment SHALL be posted once to AuraCourt's ledger as platform revenue, separately from booking money, and the owner SHALL be notified in-app. Activation or renewal SHALL NOT reopen a paused venue or lift a suspension. Pausing or suspending a venue SHALL NOT stop or extend its package.

#### Scenario: First purchase paid on time
- **WHEN** a transfer with the request's payment code and exact amount is made 4 minutes after a 30-day purchase request was created
- **THEN** a package SHALL start at the transaction time and end 30 × 24 hours later, the request SHALL be paid and the fee SHALL be posted once

#### Scenario: Renewal paid before the end
- **WHEN** a 30-day package ends on 10 November at 10:00 and a 30-day renewal is paid on 5 November
- **THEN** the package SHALL end on 10 December at 10:00

#### Scenario: Renewal paid after the end
- **WHEN** a renewal request created before the package end is paid on time but after the package ended
- **THEN** the package SHALL restart at the transaction time and end the term's days later, and the gap SHALL not be charged

#### Scenario: Late transfer
- **WHEN** a transfer with the right code and amount has a transaction time at or after the request deadline
- **THEN** it SHALL be stored as late, the request SHALL be expired and no package SHALL start or extend

#### Scenario: Different amount
- **WHEN** a transfer carries the right code but an amount different from the request amount
- **THEN** it SHALL be stored as an amount mismatch, the request SHALL stay pending until its deadline and no package SHALL change

#### Scenario: Repeated notification
- **WHEN** the provider sends the notification of an applied transaction again
- **THEN** no package, period, stored transaction, ledger entry or collected-fee count SHALL be added

#### Scenario: Transfer with an unknown code
- **WHEN** a transfer carries a package payment code that matches no request
- **THEN** it SHALL be stored as an unknown code, and it SHALL NOT be recorded as a wallet top-up

#### Scenario: Paused venue renews
- **WHEN** the owner of a paused venue pays a renewal
- **THEN** the package SHALL be extended and the venue SHALL stay paused

### Requirement: Early package termination
The owner SHALL terminate the active package of a venue of their organization only after confirming the consequences: the remaining time is lost, nothing is refunded and nothing moves to a new package. A request without that confirmation SHALL be rejected with 422 `TERMINATION_NOT_CONFIRMED`. Terminating a package that is not active SHALL be rejected with 409 `PACKAGE_NOT_ACTIVE`.

Termination SHALL take effect immediately, record its time and actor, and stop new bookings for the venue. It SHALL NOT:
- delete purchase, renewal or transaction history;
- reduce collected fees;
- end confirmed bookings.

After termination, the owner SHALL be able to register any term as a new purchase.

#### Scenario: Confirmed termination
- **WHEN** the owner confirms the consequences and terminates an active 360-day package with 200 days left
- **THEN** the package SHALL be terminated at once with its time and actor recorded, new bookings SHALL be rejected, confirmed bookings SHALL stay confirmed and the collected fee SHALL be unchanged

#### Scenario: Termination without confirmation
- **WHEN** the owner sends a termination without confirming the consequences
- **THEN** the platform SHALL answer 422 `TERMINATION_NOT_CONFIRMED` and the package SHALL stay active

#### Scenario: New term after termination
- **WHEN** the owner terminates a 30-day package and then pays a 90-day purchase on time
- **THEN** a new 90-day package SHALL start at the transaction time

#### Scenario: Pending renewal paid after termination
- **WHEN** a renewal request created before termination is paid on time after the termination
- **THEN** a new package of that term SHALL start at the transaction time, and the terminated package SHALL keep its termination

### Requirement: Package gates new bookings only
As part of "Unified public and new-booking eligibility", an active package of the court's venue at the moment of the request SHALL be a condition of every new-booking entry point: quotes, holds, checkout, recurring series, owner-hosted sessions and slot resale purchases. The check SHALL always run, with no switch that disables or shadows it. It SHALL be serialized with the creation of the booking. A venue without an active package SHALL reject new bookings, and the other eligibility conditions SHALL still apply.

Bookings around the package end:
- A booking held before the package ended SHALL remain payable until its original payment deadline, which SHALL NOT be extended.
- Confirmed bookings SHALL keep being served.
- While a package is active, bookings for play after the package end SHALL be accepted.

These SHALL NOT require an active package: managing venues, courts, prices, promotions and staff; operating confirmed bookings; viewing finance; and buying, renewing or terminating packages. No venue, court or staff quota SHALL apply, and there SHALL be no trial.

#### Scenario: Hold after the package ended
- **WHEN** a customer tries to hold a court of a venue whose package ended one minute earlier, before any expiry job runs
- **THEN** the hold SHALL be rejected and nothing SHALL be reserved

#### Scenario: Hold placed before the package ended
- **WHEN** a booking held at 09:55 for a package that ends at 10:00 is paid at 10:03, before its original deadline
- **THEN** the booking SHALL be confirmed with its original deadline

#### Scenario: Play after the package end
- **WHEN** a customer books, while the package is active, a court time that starts after the package end
- **THEN** the booking SHALL be accepted under the other eligibility conditions

#### Scenario: Owner works with an expired package
- **WHEN** the owner of a venue with an expired package publishes a price table version, updates a promotion program or invites staff
- **THEN** the operation SHALL follow its own rules without a package check

### Requirement: Package expiry reminders
The active owners of a venue's organization SHALL receive an in-app notification and an email at four points: 7 days, 3 days and 1 day before the package end, and at the end. Each reminder SHALL name the venue, the term, the end time in Vietnam time and how to renew.

- Each reminder SHALL be sent at most once for a given package end.
- When several reminders are due at once, only the latest due one SHALL be sent.
- A renewal SHALL replace the pending reminders of the old end with the reminders of the new end.
- A terminated package SHALL receive no further reminders.
- A package of a permanently closed venue SHALL receive no reminders, because that venue can no longer renew.

#### Scenario: Seven days before the end
- **WHEN** a package reaches 7 days before its end
- **THEN** each active owner SHALL receive one in-app notification and one email naming the venue, the term, the end time and how to renew

#### Scenario: Renewal moves the reminders
- **WHEN** a package due to end in 2 days is renewed for 30 days
- **THEN** no 1-day or end reminder SHALL be sent for the old end, and reminders SHALL follow the new end

#### Scenario: Terminated package
- **WHEN** a package is terminated 5 days before its end
- **THEN** no 3-day, 1-day or end reminder SHALL be sent

#### Scenario: Delayed scheduler
- **WHEN** the reminder job resumes after both the 3-day and the 1-day points have passed
- **THEN** only the 1-day reminder SHALL be sent, and running the job again SHALL send nothing more

### Requirement: Owner view of venue packages
The owner SHALL see, for each venue of their organization:
- the package term, start, end and status (no package, active, expired or terminated), determined at the time of viewing;
- the termination time when the package was terminated;
- any pending request with its deadline;
- the history of its requests with amounts, statuses and payment times.

The owner SHALL be able to buy or renew from this view, also after the package has expired or was terminated. Staff SHALL NOT see or manage packages.

#### Scenario: Expired package view
- **WHEN** the owner opens the packages of an organization with one active, one expired and one terminated venue package
- **THEN** each venue SHALL show its own status, dates and history, and the expired and terminated venues SHALL offer a purchase

#### Scenario: Pending request shown
- **WHEN** a venue has a pending request
- **THEN** the view SHALL show it with its remaining time and reopen the same payment information

### Requirement: Platform fee administration and reports
Only platform administrators SHALL access these functions.

Payment requests:
- Administrators SHALL list and view package payment requests with venue, owner, term, amount, creation time, deadline and status (pending, expired or paid), together with the stored transactions and their outcomes.
- The list SHALL filter by status, venue, term and period.

Venue package status:
- Administrators SHALL list venues with their package status determined at the time of viewing: no package, active, expired or terminated.
- They SHALL filter venues whose non-terminated package ends within 7, 3 or 1 days. A venue SHALL belong to the n-day group when 0 < end − now ≤ n days. Groups MAY overlap, and their totals SHALL count distinct venues.

Fee report:
- Administrators SHALL view collected fees and the number of successful purchases and renewals by period, venue and term.
- The report SHALL use the amount paid on each request and its payment time, without recalculating from current prices.
- It SHALL exclude anomalous transactions and booking money, and SHALL keep the fees of terminated packages.
- Periods SHALL use Vietnam calendar days, with the start included and the end excluded.

Exports:
- Administrators SHALL export the fee report, the request list and the venue status list to Excel files that match the screen's filters at export time.
- Each file SHALL state the report name, its scope, the period when applicable, the export time, the totals and the details.

#### Scenario: Collected fees by term
- **WHEN** an administrator views the fee report for October with two paid 30-day requests, one paid 360-day request later terminated, one expired request and one late transfer
- **THEN** the report SHALL show the three paid amounts as collected, split by term, and SHALL exclude the expired request and the late transfer

#### Scenario: Expiring venues
- **WHEN** one venue's package ends in 2 days and another's in 6 days
- **THEN** the 7-day group SHALL count 2 distinct venues, the 3-day group 1 and the 1-day group 0

#### Scenario: Request detail with an anomalous transfer
- **WHEN** an administrator opens a request that received a transfer with a different amount
- **THEN** the detail SHALL show the transfer with its amount, time and amount-mismatch outcome

#### Scenario: Export matches the screen
- **WHEN** an administrator exports the request list filtered to expired requests of one venue
- **THEN** the Excel file SHALL contain exactly those requests and state the scope, the period and the export time

#### Scenario: Non-administrator access
- **WHEN** an organization owner or staff member calls a platform fee administration endpoint
- **THEN** the platform SHALL answer 403

## REMOVED Requirements

### Requirement: Versioned platform plan and fee consent
**Reason**: Report Module 05 (BR-91..BR-94) sells one package per venue, with four fixed terms of 30, 90, 180 and 360 days of 24 hours. Each term has its own price and effective time. Versioned plans, calendar-month terms, quotas, features and plan changes are not part of it.
**Migration**: Replaced by "Venue package terms and prices" and "Package payment requests". The `platform_plans` table, plan CRUD and the plan catalog are removed. Production holds demo data that is backed up and reseeded with venue packages.

### Requirement: Verified and idempotent paid activation
**Reason**: The report activates a package only for a transfer with the right code, the exact amount and a transaction time before the 10-minute deadline (BR-96). A late payment is stored without activating. The removed requirement reconciled late payments into an active period.
**Migration**: Replaced by "Verified package activation and renewal". Package fees are confirmed through AuraCourt's SePay notification. The PayOS subscription adapter and the platform branch of the provider event handler are removed.

### Requirement: Trial eligibility and entitlement enforcement
**Reason**: The report has no trial and no venue, court or staff quotas. The package is only a condition for receiving new bookings (BR-103).
**Migration**: Replaced by "Package gates new bookings only". Trial activation and quota checks are removed. Staff quotas also leave "Real staff invitation and permission lifecycle".

### Requirement: Expiry and outstanding obligations
**Reason**: The report derives the package status (no package, active, expired, terminated) at the time of viewing, sends reminders at 7, 3 and 1 days and at the end, and lets the owner terminate immediately (BR-98, BR-101, BR-105). Payment failure, past-due and suspended subscription states do not exist.
**Migration**: Replaced by "Package gates new bookings only", "Package expiry reminders", "Early package termination" and "Owner view of venue packages". Cancel-renewal and auto-renewal are removed.

### Requirement: Subscription contract and enforcement completeness
**Reason**: The plan selection, trial, quota and feature-gating contract it describes no longer exists. The report adds platform fee administration and reports (FR-97..FR-100).
**Migration**: Replaced by "Package payment requests", "Verified package activation and renewal", "Owner view of venue packages" and "Platform fee administration and reports". The routes `partner/organizations/{id}/subscription/*`, `partner/subscription/confirm-payment`, `platform/plans` and `admin/platform-plans*` are removed.
