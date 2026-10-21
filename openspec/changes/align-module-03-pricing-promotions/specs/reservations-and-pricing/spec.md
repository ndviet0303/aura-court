## ADDED Requirements

### Requirement: Venue price tables
Each price table SHALL belong to exactly one venue and SHALL have a name, a kind and a status. The kind SHALL be normal ("Thông thường") or holiday ("Ngày lễ") and SHALL NOT change after creation. The status SHALL be active or retired. The prices of a table SHALL be held in published versions, as defined in "Price table versions and effective time". Each version SHALL list one or more price intervals, and each interval SHALL name its applicable days, a start time and an end time within the day, and a price per hour. A normal table's applicable days SHALL be days of the week; a holiday table's applicable days SHALL be specific calendar dates. Times SHALL be on the venue's local clock, the end SHALL be after the start, and an end entered as 23:59 SHALL mean the end of the day. Every interval SHALL cover [start, end): it includes its start and excludes its end, so two intervals that share a boundary SHALL NOT be treated as overlapping. Intervals of one version that apply to the same day SHALL NOT overlap. The price per hour SHALL be a whole number of VND greater than 0. A request that misses a required field or breaks one of these rules SHALL be rejected with 422 naming each problem, and nothing SHALL be saved. The owner SHALL list the price tables of each venue of their organization; the list of a venue that has no price table SHALL be empty. A table's detail SHALL show its name, kind, status, applicable days, intervals and prices, the effective time of each version and the courts that currently use it. Price tables SHALL NOT be deleted.

#### Scenario: Adjacent intervals
- **WHEN** the owner saves a normal table that charges 100,000 VND per hour for 10:00–16:00 and 140,000 VND per hour for 16:00–22:00 from Monday to Friday
- **THEN** the table SHALL be saved and play from 16:00 SHALL be priced at 140,000 VND per hour

#### Scenario: Overlapping intervals
- **WHEN** the owner saves intervals 10:00–16:00 and 15:00–18:00 for Monday in the same table
- **THEN** the request SHALL be rejected with 422 naming Monday and 15:00–16:00, and nothing SHALL be saved

#### Scenario: Invalid price
- **WHEN** the owner enters a price per hour of 0, a negative amount or an amount with a fraction
- **THEN** the request SHALL be rejected with 422 naming that interval

#### Scenario: Holiday table on specific dates
- **WHEN** the owner creates a holiday table for 01/01/2027 and 30/04/2027 that charges 180,000 VND per hour for 06:00–23:59
- **THEN** the table SHALL be saved, its prices SHALL apply on those two dates only, and play from 23:00 to midnight on those dates SHALL be priced

#### Scenario: Venue without price tables
- **WHEN** the owner opens price management for a venue that has no price table
- **THEN** the list SHALL be empty

### Requirement: Price table versions and effective time
Saving the content of a price table SHALL publish a new version of it; there SHALL be no unpublished draft. Each version SHALL record its publication time and its effective time, which is the play time from which the version prices courts. A version whose effective time is not in the future SHALL take effect at its publication. A published version SHALL NOT change and earlier versions SHALL remain readable. For a court time t of a booking confirmed at time c, the applicable version of a linked table SHALL be the most recently published version among those published at or before c whose effective time is at or before t. A newer version SHALL therefore replace earlier versions for play from its own effective time, including an earlier version that is scheduled but has not taken effect yet. Publishing SHALL require the organization's current business entitlement and SHALL first pass "Price coverage check" for every active or paused court that has a current or scheduled link to the table; a failing publication SHALL change nothing. A new price takes effect for a court time when it becomes the price that a booking confirmed from then on would pay. That SHALL happen only at a publication and at a change of a court's links, as defined in "Court price table links". At that moment the platform SHALL emit one price-effect signal naming the affected courts and the start of the affected play time, so that bookings still pending payment on those courts can be re-checked; the arrival of a version's effective time SHALL NOT emit another signal. Confirmed bookings SHALL keep their recorded unit prices and rental.

#### Scenario: Booking confirmed before the publication
- **WHEN** a booking for 26/10 is confirmed on 19/10, and on 20/10 the owner publishes a new price that takes effect at 00:00 on 25/10
- **THEN** the booking SHALL keep the price recorded when it was confirmed

#### Scenario: Booking confirmed after the publication for a later date
- **WHEN** after that publication a booking for 26/10 is confirmed
- **THEN** it SHALL be priced by the new version

#### Scenario: Booking confirmed after the publication for an earlier date
- **WHEN** after that publication a booking for 24/10 is confirmed
- **THEN** it SHALL be priced by the version in effect on 24/10

#### Scenario: Effective time in the past
- **WHEN** the owner publishes a version at 10:00 on 20/10 with an effective time of 08:00 on 20/10
- **THEN** the version SHALL be published, SHALL take effect at 10:00 on 20/10 and SHALL NOT price any booking confirmed before its publication

#### Scenario: A later version replaces a scheduled one
- **WHEN** one version is published on 20/10 to take effect on 25/10, and another is published on 22/10 to take effect on 23/10
- **THEN** a booking for 26/10 confirmed on 22/10 or later SHALL be priced by the version published on 22/10

#### Scenario: Publication would leave a gap
- **WHEN** a new version drops the price for 21:00–22:00 on Mondays while an active court that uses the table serves until 22:00
- **THEN** publication SHALL be rejected with 422 `PRICE_COVERAGE_INCOMPLETE` listing that court, Monday and 21:00–22:00, and no version SHALL be published

#### Scenario: Expired business entitlement
- **WHEN** an owner whose platform plan has expired publishes a version
- **THEN** publication SHALL fail without changing live prices

#### Scenario: One signal per publication
- **WHEN** the owner publishes a version, effective at 00:00 on 25/10, of a table that courts A and B use
- **THEN** one price-effect signal SHALL be emitted for courts A and B from 00:00 on 25/10, and no further signal SHALL be emitted when 25/10 begins

### Requirement: Court price table links
The platform SHALL store the price tables a court uses as dated links. A link SHALL name the court, the price table, the table's kind, a start time and an optional end time. At any time a court SHALL have at most one normal link and at most one holiday link. A linked table SHALL belong to the court's venue; a table of another venue or another organization SHALL be rejected with 422. The owner SHALL be able to apply one table to one or more courts of its venue at a switch time, which defaults to now and SHALL NOT be in the past. The newest change SHALL win: the court's link of the same kind in effect at the switch time SHALL end there, every link of that kind scheduled to start later SHALL be removed, and the new link SHALL start at the switch time, so no instant is priced by two tables of the same kind. The owner SHALL be able to end a court's link of one kind at a time without replacing it; links of that kind scheduled to start later SHALL be removed in the same way. Applying, replacing or ending a link SHALL first pass "Price coverage check" for each affected active or paused court from the switch time onward, on the links that the change would leave; on failure nothing SHALL change. The owner SHALL be able to retire a table at any time. A retired table SHALL NOT be linked to a court again and SHALL accept no new version; courts that already use it SHALL keep its prices until their link is replaced or ended. Past links SHALL remain readable. The court screens and price management SHALL read and write the same links. A court without a holiday link SHALL be priced by its normal link on every date.

#### Scenario: Apply a table to several courts
- **WHEN** the owner applies a normal table to courts 1 and 2 of its venue with a switch time of 00:00 on 25/10
- **THEN** each court's current normal link SHALL end at that time and a link to the new table SHALL start there

#### Scenario: A new link replaces a scheduled one
- **WHEN** court 1 uses normal table A, a link to normal table B is scheduled from 00:00 on 30/10, and the owner applies normal table C to court 1 from 00:00 on 25/10
- **THEN** the link to A SHALL end at 00:00 on 25/10, the scheduled link to B SHALL be removed, and table C SHALL price court 1 from 00:00 on 25/10 onward

#### Scenario: Court of another venue
- **WHEN** the owner applies a table to a court of another venue
- **THEN** the request SHALL be rejected with 422 and the court's links SHALL stay unchanged

#### Scenario: Retired table
- **WHEN** the owner applies a retired table to a court
- **THEN** the request SHALL be rejected with 422, while courts that already use that table SHALL keep being priced by it

#### Scenario: Ending a link would leave a gap
- **WHEN** the owner ends the normal link of an active court without replacing it
- **THEN** the request SHALL be rejected with 422 `PRICE_COVERAGE_INCOMPLETE` and the link SHALL stay unchanged

#### Scenario: Both screens share the link
- **WHEN** the owner changes a court's price table on the court screen
- **THEN** price management SHALL show the court under the new table and no longer under the old one

### Requirement: Price coverage check
The price coverage check SHALL verify that, from a given time onward, every bookable time of a court has exactly one price under its links and their applicable versions. A court's bookable time is its effective service schedule inside its venue's operating hours. On a date that the applicable version of the court's linked holiday table lists, only that table's intervals for that date SHALL count, and the normal table SHALL NOT fill any time of that date. The result SHALL list each court, each day of the week or date and each uncovered interval. The check SHALL run on publication and on applying, replacing or ending a link; there a failure SHALL return 422 with code `PRICE_COVERAGE_INCOMPLETE` and that list. Opening or resuming a court SHALL run the same check as one of the conditions in "Court opening conditions", and its uncovered intervals SHALL be reported among that requirement's missing items rather than in a separate response. The owner SHALL be able to read a court's current coverage with every uncovered interval marked.

#### Scenario: Gap at the end of the day
- **WHEN** a court serves 08:00–22:00 every day and its normal table has no price for 21:00–22:00
- **THEN** the check SHALL fail and list 21:00–22:00 for each day of the week

#### Scenario: Holiday table does not fall back to normal prices
- **WHEN** a court serves 06:00–22:00 and the owner links a holiday table whose prices for 01/01/2027 cover only 06:00–20:00
- **THEN** the link SHALL be rejected with 422 `PRICE_COVERAGE_INCOMPLETE` listing 01/01/2027 20:00–22:00, and normal prices SHALL NOT be used for that time

#### Scenario: Gap on an inactive court
- **WHEN** a publication leaves a gap only on a court that is inactive
- **THEN** the publication SHALL succeed and the gap SHALL be reported when the owner opens that court

### Requirement: Per-minute rental calculation
The platform SHALL price a booking interval [start, end) on one court with one calculation shared by quotes, availability, holds, checkout and the re-check of bookings pending payment. The calculation SHALL split the interval wherever the price per hour changes, including at interval boundaries, at a version's effective time and at a link's switch time, and SHALL take the applicable version for each part. The rental SHALL be T = ⌊Σ dᵢ × pᵢ / 60⌋, where dᵢ is the number of minutes of part i and pᵢ its price per hour; the exact amounts of all parts SHALL be added before the fraction is dropped once. On a date that the court's linked holiday table lists, the holiday prices SHALL apply. If any minute of the interval has no price, the request SHALL be rejected with 422 and nothing SHALL be sold; a base, default, neighbouring or first-found price MUST NOT be substituted. A quote SHALL return the rental, the discount, the amount payable and a snapshot that records each part with its table, version, minutes and price per hour, and the selected voucher with its program and end time, or no voucher. The customer SHALL see the rental before confirming a booking. A confirmed booking SHALL keep the snapshot and amounts it was confirmed with.

#### Scenario: Booking across two price intervals
- **WHEN** a court charges 100,000 VND per hour for 10:00–16:00 and 140,000 VND per hour for 16:00–22:00, and a customer quotes 15:30–17:00
- **THEN** the rental SHALL be 30 × 100,000 / 60 + 60 × 140,000 / 60 = 50,000 + 140,000 = 190,000 VND

#### Scenario: Fraction dropped once
- **WHEN** a customer quotes 15:30–16:30 on a court that charges 100,001 VND per hour before 16:00 and 140,001 VND per hour from 16:00
- **THEN** the rental SHALL be ⌊50,000.5 + 70,000.5⌋ = 120,001 VND

#### Scenario: Holiday prices apply
- **WHEN** a court's normal table charges 100,000 VND per hour and its holiday table charges 180,000 VND per hour on 01/01/2027, and a customer quotes 18:00–19:30 on 01/01/2027
- **THEN** the rental SHALL be 270,000 VND

#### Scenario: Unpriced minute
- **WHEN** a customer quotes 21:30–22:30 and 22:00–22:30 has no price
- **THEN** the quote SHALL be rejected with 422 and nothing SHALL be held

#### Scenario: Version change inside a booking
- **WHEN** a version published on 20/10 raises the price from 140,000 to 160,000 VND per hour from 18:00 on 26/10, and a booking for 17:00–19:00 on 26/10 is confirmed after that publication
- **THEN** the rental SHALL be 140,000 + 160,000 = 300,000 VND

### Requirement: Public price display
Availability SHALL offer only court times that "Per-minute rental calculation" can price and SHALL show the amounts it returns. Public venue and court listings SHALL show a "from" price ("Giá từ"): the lowest price per hour that a booking confirmed now could pay on the venue's or court's active courts. When no such price exists, no price SHALL be shown. Interfaces MUST NOT show an estimated, rounded-up or substitute price for a court time.

#### Scenario: From price of a venue
- **WHEN** a venue has two active courts whose lowest applicable price is 80,000 VND per hour
- **THEN** the venue listing SHALL show "Giá từ 80.000 ₫/giờ"

#### Scenario: Court without prices
- **WHEN** an active court has no price for 21:00–22:00 on a day
- **THEN** availability SHALL NOT offer that time and SHALL NOT show any amount for it

### Requirement: Venue promotion programs
Each promotion program SHALL belong to one venue and SHALL apply to a non-empty set of that venue's courts; a court of another venue SHALL be rejected with 422. A program SHALL have a name, a voucher kind, one discount type, a discount value, a start time, an end time after the start and its court set. The voucher kind SHALL be public ("Công khai") or private ("Riêng"). The discount type SHALL be a percentage or a fixed amount per booking. A public program SHALL have a maximum number of uses and a private program a number of codes, each a whole number greater than 0. A percentage SHALL be greater than 0 and at most 100, with at most two decimal places; a fixed amount SHALL be a whole number of VND greater than 0. A minimum rental and, for a percentage program only, a maximum discount MAY be set, and when set each SHALL be a whole number of VND greater than 0; a maximum discount on a fixed-amount program SHALL be rejected with 422. Times received with an offset SHALL be stored and shown on the application clock. Before creating a program the owner SHALL review its terms and confirm issuing it. A program SHALL be issued when it is created and SHALL be usable within its time window until it is stopped. Creating a private program SHALL generate that number of distinct codes, unique across the platform, each with a QR code that encodes a link to the booking page of one of the program's courts with the code filled in; on that page the customer SHALL be able to choose another court of the venue, and the code SHALL be rechecked for the chosen court.

The owner SHALL list the programs of each venue of their organization with name, voucher kind, discount type and value, minimum rental, maximum discount, time window, courts, status and usage numbers, and SHALL view and copy each private code and its QR. The status SHALL be upcoming ("Sắp diễn ra"), running ("Đang diễn ra"), ended ("Đã kết thúc") or stopped ("Đã dừng"). The owner SHALL be able to update a program's name, discount value, time window, court set, minimum rental, maximum discount and, for a public program, its maximum uses, which SHALL NOT go below the uses already held and used. The voucher kind, the discount type, the private codes and their number SHALL NOT change. Changed conditions SHALL apply to bookings that are not yet confirmed. The owner SHALL be able to stop a program after confirming the action; a stopped program SHALL NOT restart. Once a program is stopped, its public voucher SHALL leave the public list, and neither its unused private codes nor any of its vouchers selected or held for a booking not yet confirmed SHALL apply. When an update or a stop leaves a booking still pending payment without its voucher, with a different discount or with a payment deadline after the program's new end, that booking SHALL end as expired and its customer SHALL be notified, so that no payment is requested for an amount the customer did not agree to. Updating or stopping SHALL NOT change confirmed bookings, their discounts or the usage history, and programs SHALL NOT be deleted. Creating and updating a program SHALL require the organization's current business entitlement; stopping SHALL NOT.

#### Scenario: Public program
- **WHEN** the owner creates a public program of 20% off with a 50,000 VND maximum discount, for courts 1 and 2, with 10 uses
- **THEN** the program SHALL be issued and at most 10 confirmed bookings SHALL be able to use it

#### Scenario: Private program
- **WHEN** the owner creates a private program with 10 codes
- **THEN** 10 distinct codes SHALL be generated, each with a QR code linking to the booking page of one of the program's courts with that code filled in, and the owner SHALL be able to copy each code and QR

#### Scenario: Invalid program
- **WHEN** the owner submits a program whose end equals its start, with no court, with 0 uses, with a 120% or 12.345% discount, or with a maximum discount on a fixed amount
- **THEN** the request SHALL be rejected with 422 naming each problem and nothing SHALL be created

#### Scenario: Locked terms
- **WHEN** the owner changes the voucher kind or the discount type of an issued program
- **THEN** the request SHALL be rejected with 422, while a change of its name SHALL be saved

#### Scenario: Time with an offset
- **WHEN** the owner sends a start time of 2026-10-20T03:00:00Z
- **THEN** the program SHALL store and show 10:00 on 20/10 on the application clock, and saving it again unchanged SHALL NOT move it

#### Scenario: Stop while a booking is pending payment
- **WHEN** the owner confirms stopping a program while a booking pending payment holds one of its vouchers
- **THEN** the program SHALL be stopped, that booking SHALL end as expired with its voucher use released and its customer notified, and confirmed bookings that used the program SHALL keep their discounts

#### Scenario: Update raises the minimum rental
- **WHEN** the owner raises a program's minimum rental to 300,000 VND while a booking pending payment with a rental of 250,000 VND holds its voucher
- **THEN** that booking SHALL end as expired and its customer SHALL be notified

### Requirement: Voucher eligibility and discount
A voucher SHALL apply to a booking only when all of these hold: its program is not stopped; the booking's confirmation time is at or after the program's start and before its end, whatever the play date; the booking's court is in the program's court set; for a public program, a use is left after counting the uses already used and those held for other bookings; for a private code, the code is neither used nor held for another booking; and the rental before discount reaches the minimum rental when one is set. When a voucher is listed, entered or submitted, its time window SHALL be judged at the time of the request; at confirmation it SHALL be judged as defined in "Atomic voucher use". A booking SHALL use at most one voucher. Public and private vouchers SHALL NOT be combined, and choosing another voucher SHALL replace the current one. With rental T, the discount D SHALL be ⌊min(T × r / 100, C, T)⌋ for a percentage r with a maximum discount C, ⌊min(T × r / 100, T)⌋ for a percentage without a maximum, and min(F, T) for a fixed amount F. The amount payable SHALL be P = T − D and SHALL never be negative. Membership discounts and loyalty points SHALL NOT reduce the amount of a court booking.

#### Scenario: Percentage capped by the maximum
- **WHEN** the rental is 300,000 VND and the voucher gives 20% with a 50,000 VND maximum
- **THEN** the discount SHALL be 50,000 VND and the amount payable 250,000 VND

#### Scenario: Percentage below the maximum
- **WHEN** the rental is 190,000 VND and the voucher gives 10% with a 30,000 VND maximum
- **THEN** the discount SHALL be 19,000 VND and the amount payable 171,000 VND

#### Scenario: Percentage with decimals
- **WHEN** the rental is 190,000 VND and the voucher gives 12.5% without a maximum
- **THEN** the discount SHALL be 23,750 VND and the amount payable 166,250 VND

#### Scenario: Fixed amount above the rental
- **WHEN** the rental is 40,000 VND and the voucher gives 50,000 VND off
- **THEN** the discount SHALL be 40,000 VND and the amount payable 0 VND

#### Scenario: Minimum rental not reached
- **WHEN** the rental is 250,000 VND and the voucher requires a minimum rental of 300,000 VND
- **THEN** the voucher SHALL NOT apply

#### Scenario: Validity follows the confirmation time
- **WHEN** a voucher ends at 12:00 on 25/10 and a booking for 26/10 is confirmed at 11:59 on 25/10
- **THEN** the voucher SHALL apply, and it SHALL NOT apply to a booking confirmed at 12:00 on 25/10 or later

#### Scenario: Court outside the program
- **WHEN** a customer books a court that is not in the program's court set
- **THEN** the voucher SHALL NOT apply

### Requirement: Voucher choice during booking
For a court and interval, the platform SHALL list the public vouchers that apply to that booking at the time of the request, each with its computed discount, and SHALL NOT list private vouchers. By default the voucher with the largest discount after dropping fractions SHALL be selected; on a tie, the one that ends earlier; and if they end at the same time, the one created earlier. The customer SHALL be able to choose another listed voucher, enter a private code or open a private voucher's QR link; a valid private code SHALL replace the current voucher when the customer applies it. An invalid code SHALL be refused with its reason: not found, not started yet, expired, already used, held for another booking, program stopped, no use left, court not covered or minimum rental not reached. Selecting, entering or scanning a voucher SHALL NOT hold or consume it. When the customer changes the court or the time, the platform SHALL recalculate the rental and recheck the chosen voucher: a voucher that no longer applies SHALL be removed with a notice, and a voucher the customer chose or entered that still applies SHALL stay applied. When the voucher is removed, when none is applied, or when the applied one was the default, the default SHALL be selected again. The booking summary SHALL show the rental, the chosen voucher, the discount and the amount payable. A checkout SHALL apply exactly the voucher it names, or none. A named voucher that does not apply at submission SHALL be rejected with 422 `VOUCHER_INVALID`, giving the reason and the amounts without that voucher, and nothing SHALL be created. This check SHALL run before the recalculated amount is compared with the amount the customer was shown and before the payment deadline is set, so a voucher whose program ends at the moment of submission SHALL be rejected with the reason that it has expired.

#### Scenario: Default chosen by actual discount
- **WHEN** the rental is 190,000 VND, voucher A gives 10% without a maximum and voucher B gives 20,000 VND off
- **THEN** voucher B SHALL be selected by default because its discount of 20,000 VND exceeds A's 19,000 VND

#### Scenario: Tie on the discount
- **WHEN** two listed vouchers each give 20,000 VND off and one ends on 30/10 while the other ends on 31/10
- **THEN** the one ending on 30/10 SHALL be selected, and between vouchers that also end at the same time the one created earlier SHALL be selected

#### Scenario: Private code replaces the default
- **WHEN** a customer whose booking shows the default public voucher applies a valid private code
- **THEN** the private voucher SHALL replace the public one and the summary SHALL show its discount

#### Scenario: Used private code
- **WHEN** a customer enters a private code that another booking has already used
- **THEN** the code SHALL be refused with the reason "đã được sử dụng" and nothing SHALL be held

#### Scenario: Changed time drops the voucher
- **WHEN** a customer shortens 15:30–17:00 to 15:30–16:00 and the new rental falls below the chosen voucher's minimum rental
- **THEN** the voucher SHALL be removed with a notice, the amounts SHALL be recalculated, and the default voucher for the new interval, if any, SHALL be selected

#### Scenario: Changed time keeps an entered code
- **WHEN** a customer has applied a private code and moves 15:30–17:00 to 16:00–17:30 on the same court, and the code still applies
- **THEN** the code SHALL stay applied and SHALL NOT be replaced by the default public voucher

#### Scenario: Program stopped before submission
- **WHEN** a customer submits a booking with a voucher whose program was stopped after it was selected
- **THEN** the checkout SHALL be rejected with 422 `VOUCHER_INVALID` and the reason that the program is stopped, and no booking SHALL be created

#### Scenario: Voucher ends at the moment of submission
- **WHEN** a customer submits a booking at 12:00 with a voucher whose program ends at 12:00
- **THEN** the checkout SHALL be rejected with 422 `VOUCHER_INVALID` and the reason that the voucher has expired, before any price comparison or payment deadline, and no booking SHALL be created

### Requirement: Atomic voucher use
When a booking pending payment is created with a voucher, one use SHALL be held for that booking in the same transaction: a public program's held uses SHALL increase by one, or a private code SHALL be marked held for that booking. For a public program, held plus used uses SHALL never exceed its maximum, and a private code SHALL be held or used by at most one booking. When concurrent submissions compete for the last use or for the same code, only one SHALL succeed; the others SHALL receive 422 `VOUCHER_INVALID` and SHALL create nothing. At confirmation the platform SHALL recheck that the voucher still applies to the booking under "Voucher eligibility and discount", counting the use the booking already holds, and still gives the discount recorded on the booking, and SHALL turn the held use into a used one in the same transaction as the confirmation. The time window SHALL be judged at the time the verified transfer was received or, for a booking whose amount payable is zero or that is paid from a legacy wallet, at the transaction that confirms it. A booking SHALL NOT be confirmed with a voucher that no longer applies: when the recheck fails, the booking SHALL end as expired with its held use released once, every payment already received for it SHALL be recorded as a payment exception, nothing further SHALL be charged, and the customer SHALL be notified. If the confirmation does not commit, no use SHALL be recorded. A booking that ends without confirmation SHALL release its held use exactly once, and releasing SHALL NOT extend the voucher's time window. Repeating a hold, a confirmation or a release for the same booking SHALL NOT change the counts again. A booking that is confirmed in the transaction that creates it SHALL hold and use its voucher in that transaction. The use of a confirmed booking SHALL NOT be returned when the booking is later cancelled, moved or rescheduled: a public use SHALL NOT be restored and a used private code SHALL NOT be used again. Such changes SHALL NOT create additional uses either. Any refund SHALL be agreed and paid by the owner outside the platform.

#### Scenario: Two customers race for the last use
- **WHEN** a public program has one use left and two customers submit bookings with it at the same time
- **THEN** exactly one booking SHALL be created holding that use and the other submission SHALL receive 422 `VOUCHER_INVALID` without creating a booking

#### Scenario: Two customers race for one private code
- **WHEN** two customers submit bookings with the same private code at the same time
- **THEN** only one booking SHALL hold the code

#### Scenario: Confirmation delivered twice
- **WHEN** the payment confirmation for a booking holding a voucher is processed twice
- **THEN** the program SHALL record one used use for that booking

#### Scenario: Transfer arrives just after the program is stopped
- **WHEN** the owner stops a program and, before the booking pending payment that holds its voucher has been expired, that booking's verified transfer is processed
- **THEN** the booking SHALL NOT be confirmed, SHALL end as expired with its held use released once, the transfer SHALL be recorded as a payment exception and the customer SHALL be notified

#### Scenario: Wallet confirmation after the program is stopped
- **WHEN** a legacy wallet cohort customer confirms a booking pending payment after the owner stopped the program of its voucher and before the booking has been expired
- **THEN** the wallet SHALL NOT be charged, the booking SHALL end as expired with its held use released once, and the request SHALL be rejected with 422 `VOUCHER_INVALID`

#### Scenario: Transfer received before the program ends
- **WHEN** a booking's verified transfer was received before its voucher's program ended and is processed after that end
- **THEN** the booking SHALL be confirmed and its voucher use SHALL be recorded as used

#### Scenario: Pending booking expires
- **WHEN** a booking pending payment that holds a voucher use expires
- **THEN** the use SHALL be released once, the remaining uses SHALL increase by one, and the voucher's end time SHALL stay unchanged

#### Scenario: Cancellation after confirmation
- **WHEN** a confirmed booking that used a voucher is later cancelled
- **THEN** the use SHALL stay counted as used and a private code SHALL stay used

#### Scenario: Zero amount payable
- **WHEN** a checkout creates a booking whose voucher makes the amount payable 0 VND
- **THEN** the booking SHALL be confirmed in that transaction and its voucher use SHALL be recorded as used in the same transaction

### Requirement: Voucher usage tracking
For each program the owner SHALL see its usage. A public program SHALL show its maximum uses, the uses used, the uses held and the uses remaining, which equal the maximum minus used and held uses. A private program SHALL list its issued codes with their status (available, held or used) and, for a used code, the booking that used it; an available code of an ended or stopped program SHALL be shown as no longer usable. The owner SHALL be able to list the bookings that used a voucher of the program with the booking code, the confirmation time and the discount. A booking cancelled after confirmation SHALL stay counted as having used its voucher. Program details and usage history SHALL be kept for lookup.

#### Scenario: Public counts
- **WHEN** a public program with 10 uses has 3 confirmed bookings and 1 booking pending payment that use it
- **THEN** it SHALL show 3 used, 1 held and 6 remaining

#### Scenario: Cancelled booking stays counted
- **WHEN** one of those confirmed bookings is cancelled
- **THEN** the program SHALL still show 3 used and list that booking with its confirmation time and discount

#### Scenario: Private code list
- **WHEN** the owner opens a private program with 10 codes, 2 of them used
- **THEN** the owner SHALL see all 10 codes, 2 marked used with their bookings and 8 available

### Requirement: Voucher scope
Vouchers SHALL apply only to ordinary court bookings on courts in their program's court set. POS orders and recurring series SHALL NOT accept a voucher or coupon: a request that names one SHALL be rejected with 422 and SHALL change nothing, rather than silently ignoring it. A slot resale purchase SHALL NOT apply a voucher.

#### Scenario: POS order with a code
- **WHEN** a cashier submits a POS order with a voucher code
- **THEN** the request SHALL be rejected with 422 and no order SHALL be created

#### Scenario: Recurring series with a voucher
- **WHEN** a customer previews or accepts a recurring series with a voucher code
- **THEN** the request SHALL be rejected with 422 stating that vouchers do not apply to recurring series

### Requirement: Court quotes from price tables
The platform SHALL quote court time only from the court's price table links and their applicable versions, using "Per-minute rental calculation". Price rules scoped to the organization, a venue, a sport or a single court, precedence tiers and configured base prices SHALL NOT be used. A quote SHALL snapshot each price part with its table version, and the selected voucher with its program, terms and end time. A time without a price MUST NOT be sold with a fallback price. Later changes SHALL not alter a confirmed booking; a booking still pending payment SHALL follow "Pending bookings at a price change". An expired quote SHALL be recalculated and accepted again before payment.

#### Scenario: Holiday table replaces the normal table
- **WHEN** a court has both a normal link and a holiday link whose table lists the requested date
- **THEN** quotes and displayed availability SHALL both use the holiday amount

#### Scenario: No applicable price
- **WHEN** a requested open interval has no price under the court's links
- **THEN** it SHALL not be sold using an unrelated price

#### Scenario: Price rules change after quote acceptance
- **WHEN** a policy changes after quote acceptance
- **THEN** a confirmed booking's snapshot SHALL remain stable, a booking still pending payment SHALL be re-checked as defined in "Pending bookings at a price change", and an expired unaccepted quote requires renewed consent

#### Scenario: Prices survive the move to price tables
- **WHEN** existing price rules and hourly court prices are converted into venue price tables and links
- **THEN** every interval that the old rules priced in the next 14 days, and on every date listed in a converted holiday table, SHALL be quoted the same amount before and after, or the conversion SHALL stop without changing any data

### Requirement: Authoritative ordinary checkout and voucher result
Ordinary booking checkout SHALL use server-calculated amounts only: the rental from "Per-minute rental calculation", at most one voucher under "Voucher eligibility and discount", and the amount payable P = T − D. Membership discounts and loyalty points SHALL NOT apply to court bookings, and checkout SHALL NOT offer a points control. A booking pending payment SHALL hold its voucher use as defined in "Atomic voucher use" without consuming it before confirmation. Confirmation SHALL atomically finalize the allocation and consume the held use once; expiry or cancellation of an unpaid booking SHALL release it once. A booking whose voucher makes the amount payable zero SHALL be confirmed with a legitimate zero-payable record rather than a fabricated bank receipt. For a pre-cutover legacy wallet cohort, the wallet debit of the amount payable, booking confirmation and inventory allocation SHALL commit atomically with the price snapshot and related financial records. Bookings that recorded membership or point reductions before this change SHALL keep and show them unchanged.

#### Scenario: Pending payment expires
- **WHEN** an unpaid booking holds a voucher use
- **THEN** expiry SHALL release it exactly once, including after retries

#### Scenario: Voucher covers the rental
- **WHEN** a 50,000 VND voucher is applied to a 40,000 VND rental
- **THEN** the booking SHALL confirm atomically with a zero-payable record and one voucher use, without requesting a bank transfer or leaving a pending payment

#### Scenario: Tampered discount
- **WHEN** a client supplies an unearned discount, a membership or point reduction, or an edited total
- **THEN** the server SHALL reject or recalculate before acceptance without granting the benefit or holding a voucher use

#### Scenario: Legacy unpaid hold
- **WHEN** a pre-cutover unpaid hold is submitted for confirmation
- **THEN** it SHALL require payment evidence under its recorded model and SHALL not be silently converted or confirmed free

#### Scenario: Atomic wallet checkout
- **WHEN** an eligible legacy wallet cohort customer confirms a booking with a 200,000 VND rental and a voucher worth 50,000 VND
- **THEN** checkout SHALL charge 150,000 VND from the wallet, record the voucher use and confirm the booking and its slots as one transaction

#### Scenario: Failure after a partial checkout step
- **WHEN** insufficient funds, an inventory conflict or a persistence failure prevents checkout completion
- **THEN** wallet balances, voucher uses, booking records, inventory and financial postings SHALL remain unchanged by the failed operation

#### Scenario: No points or membership discount
- **WHEN** a customer with loyalty points and an active membership reviews a court booking before checkout
- **THEN** the interface SHALL show no points control and no membership discount, and the amount payable SHALL equal the rental minus the voucher discount

#### Scenario: Reload after successful checkout
- **WHEN** a customer reloads a confirmed booking that used a voucher
- **THEN** booking detail and payment history SHALL show the rental, the voucher, the discount and the net paid amount consistent with voucher, payment and financial records

#### Scenario: Booking with a recorded point reduction
- **WHEN** a customer opens a booking confirmed before this change with a point reduction
- **THEN** it SHALL show the recorded reduction and net paid amount unchanged

## MODIFIED Requirements

### Requirement: Home availability identifies its selected court and date
The home availability panel SHALL identify the selected court and selected local play date adjacent to the slots and provide a nearby court selector. Its title MUST NOT imply all-court aggregation or always claim today's date when another date is selected. Each slot SHALL be one 30-minute time of that court that starts on a whole or half hour of venue local time, and SHALL expose its start and end time and availability for that court only. Changing court/date SHALL refresh the matching slots, clear incompatible selections and prevent stale responses from presenting another court's availability as the selected court's. Labels SHALL support Vietnamese and English.

#### Scenario: One court is booked from 19 to 21
- **WHEN** court A is booked from 19:00 to 21:00
- **THEN** its 19:00–19:30, 19:30–20:00, 20:00–20:30 and 20:30–21:00 slots SHALL be unavailable under a heading identifying court A and the selected date

#### Scenario: Another court remains available
- **WHEN** the user changes from booked court A to court B that has prices and no conflicting booking or closure at 19:00–21:00
- **THEN** the panel SHALL identify court B and display all four of its corresponding future slots from 19:00 to 21:00 as available

#### Scenario: Different play date
- **WHEN** the user selects a date other than today
- **THEN** the heading and returned slots SHALL represent that date in the venue's applicable local timezone without a misleading today label

#### Scenario: Court switches while requests are pending
- **WHEN** an older availability request completes after the user has selected another court or date
- **THEN** its response SHALL NOT replace the currently selected court/date's displayed availability

## REMOVED Requirements

### Requirement: Immutable price quotes
**Reason**: Report Module 03 (BR-50..BR-57) prices courts only from venue price tables linked to each court, with holiday tables taking precedence on their dates and no fallback. Effective-dated rules scoped to the organization, a venue, a venue and sport or a court, peak and base tiers and their precedence no longer exist, so the scenarios about those rules no longer hold.
**Migration**: Replaced by "Court quotes from price tables", which keeps the rules that a confirmed booking is never repriced, that a booking still pending payment follows "Pending bookings at a price change", that an expired quote needs renewed consent and that no unrelated price is sold. Existing rules and court hourly prices are converted into price tables and links with an amount comparison, as stated in its scenario "Prices survive the move to price tables".

### Requirement: Authoritative ordinary checkout and promotion result
**Reason**: Report Module 03 (BR-64, BR-66) makes the amount payable of a court booking the rental minus at most one voucher discount. Coupons, membership discounts, loyalty points and their combination rules no longer apply to court bookings (decision Q2 = A, 2026-10-21).
**Migration**: Replaced by "Authoritative ordinary checkout and voucher result" and "Atomic voucher use". The booking points control and point reservation are removed; the legacy wallet cohort keeps paying the amount payable from its wallet. Bookings that recorded coupon, membership or point reductions keep and show them.

### Requirement: Complete tenant promotion lifecycle
**Reason**: Report Module 03 (BR-49, BR-58..BR-62, BR-71) replaces organization-scoped campaigns and coupons, customer eligibility groups, combination rules and delegated pricing staff with venue promotion programs that issue public or private vouchers and that only the owner manages. Membership benefits no longer apply to court bookings (decision Q2 = A, 2026-10-21).
**Migration**: Replaced by "Venue promotion programs", "Voucher eligibility and discount", "Voucher choice during booking", "Atomic voucher use", "Voucher usage tracking", "Voucher scope" and "Authoritative ordinary checkout and voucher result". The business entitlement gate moves to "Price table versions and effective time" and "Venue promotion programs". Existing campaigns and coupons are not converted: production holds demo data that is backed up and reseeded with new vouchers. Bookings keep the coupon amounts recorded in their snapshots.
