## ADDED Requirements

### Requirement: Thirty-minute booking interval
A customer booking SHALL cover exactly one court and one continuous half-open interval `[start, end)` within one play date in the venue's timezone. Start and end SHALL fall on a whole or half hour of venue local time, for example 15:00, 15:30 or 16:00; the end SHALL be later than the start, the duration SHALL be at least 30 minutes, the end MAY be the midnight that closes the play date, and the start SHALL NOT be in the past. Quotes, holds, checkout and the availability offered for booking SHALL apply these rules identically and independently of the court's interval length, and SHALL re-check them under the allocation lock. A selection that breaks them SHALL be rejected with 422 and error code `INVALID_BOOKING_TIME`, without creating any quote, booking, hold, voucher hold or payment record. A request that lists several start times instead of one interval SHALL be rejected the same way. A new booking SHALL be stored and priced as that one interval and SHALL be resold only as a whole. Existing bookings SHALL keep their recorded slots. Vietnamese interfaces, error messages and notifications SHALL name a booking by its time range or duration ("15:30–17:00", "1 giờ 30 phút") and SHALL NOT show the word "slot".

#### Scenario: Half-hour boundaries
- **WHEN** a customer books 15:30–17:00 on a free, priced court
- **THEN** one booking covering 15:30–17:00 SHALL be created and priced as one interval

#### Scenario: Thirty-minute minimum
- **WHEN** a customer requests 15:00–15:30, then 15:00–15:00, then 16:00–15:00
- **THEN** the first SHALL be accepted, and the other two SHALL be rejected with 422 `INVALID_BOOKING_TIME` without creating any record

#### Scenario: Off-grid time
- **WHEN** a customer requests a start at 15:25 or an end at 16:10
- **THEN** the request SHALL be rejected with 422 `INVALID_BOOKING_TIME` without creating any record

#### Scenario: Gapped selection
- **WHEN** a customer wants 15:00–16:00 and 17:00–18:00 on the same court, or a client sends a list of start times
- **THEN** one request SHALL NOT combine them; a list of start times SHALL be rejected with 422, and each interval SHALL need its own booking

#### Scenario: Past start
- **WHEN** a customer requests an interval whose start has already passed in venue time
- **THEN** the request SHALL be rejected with 422 `INVALID_BOOKING_TIME`

#### Scenario: Court interval length does not apply
- **WHEN** a court has a 60-minute interval length and a customer books 16:30–17:30 on it
- **THEN** the booking SHALL be accepted when the court-time is free and priced

#### Scenario: Resale of a new booking
- **WHEN** the holder of a new 15:30–17:00 booking lists it for resale
- **THEN** the listing SHALL cover the whole 15:30–17:00 interval, and no part of it SHALL be listed on its own

#### Scenario: Time wording
- **WHEN** a Vietnamese-language user sees a booking selection, a booking error, a waitlist notification or a resale action
- **THEN** the text SHALL show the time range or the duration, such as "Pass lại đơn 15:30–17:00", and SHALL NOT show the word "slot"

### Requirement: Payment deadline for pending bookings
When a booking with a positive amount payable is created, its payment deadline SHALL be the earliest of: the hold time plus 10 minutes, the booking's start time, and the end of validity of its applied voucher. The deadline SHALL be stored with the booking when it is created, and its payment request, with payment code and QR, SHALL expire at exactly that deadline. If the deadline would not be later than the hold time, the request SHALL be rejected with 422 `PAYMENT_WINDOW_CLOSED` without creating any record. Reopening the payment information SHALL return the same payment code, amount, receiver and deadline, and SHALL NOT extend the hold or issue another code. Only a verified transfer with the right payment code and the full amount, received before the deadline, SHALL confirm the booking; a transfer after the deadline SHALL be recorded as a payment exception. Venue booking policy SHALL NOT change the deadline.

#### Scenario: Start time caps the deadline
- **WHEN** a customer holds a 15:00 booking at 14:58 with no voucher
- **THEN** its payment deadline SHALL be 15:00, and a matching transfer received at 15:01 SHALL NOT confirm it and SHALL be recorded as a transfer after expiry

#### Scenario: Voucher end caps the deadline
- **WHEN** a customer holds a 16:00 booking at 14:50 with a voucher valid until 14:59
- **THEN** its payment deadline SHALL be 14:59

#### Scenario: Ten-minute cap
- **WHEN** a customer holds an 18:00 booking at 09:00 with no voucher, at a venue whose policy states a 30-minute hold
- **THEN** its payment deadline SHALL be 09:10

#### Scenario: Reopening the payment information
- **WHEN** the customer reopens the payment information of that booking at 09:05
- **THEN** the same payment code, amount and 09:10 deadline SHALL be returned, and no new payment request SHALL be created

#### Scenario: No payment window left
- **WHEN** a payable booking is requested at the exact start time of its interval, or with a voucher that ends at that moment
- **THEN** the request SHALL be rejected with 422 `PAYMENT_WINDOW_CLOSED` without creating any record

### Requirement: Booker contact snapshot
Creating a booking SHALL record the booker's full name, phone number and email, as held on the account at that moment, as the booking's booker contact. Before submitting, the customer SHALL be shown this contact. A customer whose account has no valid Vietnamese phone number SHALL be refused with 422 `PHONE_REQUIRED` before any hold, voucher hold or payment request is created, and the interface SHALL direct them to add a phone number. Later profile changes SHALL NOT change a booking's recorded contact. The customer's booking detail and the operators' booking views SHALL show the recorded contact.

#### Scenario: Missing phone number
- **WHEN** a customer whose account has no phone number submits a booking
- **THEN** the request SHALL be rejected with 422 `PHONE_REQUIRED`, nothing SHALL be held or reserved, and the interface SHALL link to updating the phone number

#### Scenario: Profile edited after booking
- **WHEN** a customer changes their name and phone number after creating a booking
- **THEN** the booking detail and the owner's booking view SHALL still show the name and phone number recorded when it was created

### Requirement: Customer-agreed amount
Before submitting a booking, the customer SHALL be shown the venue, court code, play interval, booker contact, rental amount, applied voucher, discount and amount payable. The checkout request SHALL carry the amount payable the customer was shown, and a request without it SHALL be rejected with 422. At submission the server SHALL recalculate rental, voucher and discount. If the recalculated amount payable is higher than the amount the customer was shown, the request SHALL be rejected with 409 `PRICE_CHANGED`, returning the new amounts, without creating a booking, hold, voucher hold or payment request. If it is equal or lower, the booking SHALL be created with the recalculated amount.

#### Scenario: Price rose before submission
- **WHEN** a customer was shown 200,000 VND payable and the recalculated amount at submission is 220,000 VND
- **THEN** the request SHALL be rejected with 409 `PRICE_CHANGED` carrying 220,000 VND, and no booking, hold, voucher hold or payment request SHALL exist for it

#### Scenario: Price fell before submission
- **WHEN** a customer was shown 200,000 VND payable and the recalculated amount at submission is 180,000 VND
- **THEN** the booking SHALL be created with 180,000 VND payable

#### Scenario: Amount not sent
- **WHEN** a checkout request omits the amount payable the customer was shown
- **THEN** the request SHALL be rejected with 422 without creating any record

### Requirement: Customer cancels a pending-payment booking
A customer SHALL be able to cancel their own booking while it is pending payment. The cancellation SHALL re-check the booking under the same locks as payment confirmation. A pending booking SHALL become cancelled, recorded as a customer payment cancellation; its court-time SHALL be released, its voucher hold SHALL be released without being recorded as used, and its payment request SHALL expire, each exactly once. Releasing a voucher SHALL NOT extend its validity. Repeating the cancellation on a booking that is already cancelled or expired SHALL succeed without releasing anything again. A confirmed booking SHALL NOT be cancelled this way: the request SHALL be rejected with 409 without changing anything. A request from anyone other than the booking's customer SHALL be refused without changing anything. When a cancellation and a payment race, only the first to commit SHALL take effect: after a confirmation the cancellation SHALL be rejected, and after a cancellation the transfer SHALL be recorded as a payment exception and SHALL NOT restore the booking. Expiry, this cancellation, the release of an overdue hold and a price-change release SHALL leave the same released state. The interface SHALL ask for confirmation before cancelling, and the booking SHALL then show as "Đã hủy thanh toán". To book again the customer SHALL submit a new request, which is re-checked for availability, price and voucher.

#### Scenario: Cancel a pending booking
- **WHEN** a customer confirms cancelling their pending booking that holds a voucher use
- **THEN** the booking SHALL become cancelled, its court-time SHALL become available, and the voucher use SHALL be released once and not recorded as used

#### Scenario: Repeated cancellation
- **WHEN** the customer repeats the cancellation, or cancels a booking that has already expired
- **THEN** the request SHALL succeed and no court-time or voucher use SHALL be released a second time

#### Scenario: Confirmed booking
- **WHEN** a customer tries to cancel a booking whose payment has already confirmed it
- **THEN** the request SHALL be rejected with 409 and the booking SHALL stay confirmed

#### Scenario: Another customer's booking
- **WHEN** a customer tries to cancel a booking made by someone else
- **THEN** the request SHALL be refused and the booking SHALL not change

#### Scenario: Transfer arrives after cancellation
- **WHEN** a matching transfer arrives after the customer cancelled the booking
- **THEN** the booking SHALL stay cancelled, and the transfer SHALL be recorded as a payment exception that the owner can look up

### Requirement: Pending bookings at a price change
When a new price takes effect for a court-time, every booking pending payment whose interval it covers SHALL be re-checked. If the recalculated amount payable differs from the booking's amount payable, the booking SHALL expire, recorded as a price change: its payment request SHALL stop being valid, its court-time and voucher hold SHALL be released once, and the customer SHALL be notified to review the new price and submit a new request. If the amount payable is unchanged, the booking and its payment deadline SHALL stay as they were. Confirmed bookings SHALL NOT be repriced. A transfer for an invalidated payment request SHALL be recorded as a payment exception.

#### Scenario: Amount payable changes
- **WHEN** a new price takes effect while a booking covered by it is pending payment, and its amount payable would change
- **THEN** the booking SHALL expire, its court-time and voucher use SHALL be released once, and the customer SHALL be notified to submit a new request

#### Scenario: Amount payable unchanged
- **WHEN** a new price takes effect but the pending booking's amount payable stays the same
- **THEN** the booking, its payment code and its deadline SHALL stay unchanged

#### Scenario: Confirmed booking
- **WHEN** a new price takes effect for the interval of a confirmed booking
- **THEN** its recorded amounts SHALL not change

#### Scenario: Transfer for an invalidated request
- **WHEN** a customer transfers money for a payment request invalidated by a price change
- **THEN** the booking SHALL not be confirmed or restored, and the transfer SHALL be recorded as a payment exception

### Requirement: Customer booking list and detail
A customer SHALL see only the bookings they made, in a paginated list where every page is reachable and counts reflect the full result. List and detail SHALL show the status in the report's terms: "Chờ thanh toán" while pending payment, "Đã xác nhận", "Đã hủy thanh toán" when the customer cancelled it while pending, "Hết hạn thanh toán", and, for a confirmed booking, the operational status recorded by on-site operations: "Đang sử dụng", "Hoàn thành" or "Không đến". The detail SHALL show the booking code, venue, court code, play date, start and end, booker contact, rental amount, voucher, discount, amount payable and payment result; the payment deadline while pending; check-in and check-out times when recorded; and incident handling when the booking has an affected-booking case. A pending booking's detail SHALL let the customer reopen its payment information and cancel it. The booking QR SHALL be shown only while the booking is confirmed or in use; it SHALL encode a reference that the on-site lookup resolves to exactly that booking, SHALL be visibly distinct from the payment QR, and SHALL be drawn on the device without sending the booking code to a third party. The customer interface SHALL NOT offer check-in or check-out actions.

#### Scenario: Long history
- **WHEN** a customer with 45 bookings opens their booking history
- **THEN** all 45 SHALL be reachable by loading further pages, and the counts SHALL include all 45

#### Scenario: Completed and expired bookings
- **WHEN** the history contains a confirmed booking that on-site operations marked completed and a booking that expired unpaid
- **THEN** they SHALL show "Hoàn thành" and "Hết hạn thanh toán", and neither SHALL show a booking QR

#### Scenario: Pending booking detail
- **WHEN** a customer opens a booking pending payment
- **THEN** the detail SHALL show the payment deadline and offer reopening the payment information and cancelling

#### Scenario: Booking QR
- **WHEN** a customer opens the QR of a confirmed booking
- **THEN** the QR SHALL be drawn without any request to a third-party service, and the on-site lookup SHALL resolve its content to that booking

## MODIFIED Requirements

### Requirement: Active inventory exclusivity
No overlapping active entitlement or exclusive hold SHALL exist for the same court-time across V1/V2 bookings, recurring occurrences, owner-hosted sessions and tournaments using that court. Interval duration, the 30-minute booking grid, venue calendar, book-ahead horizon, continuity and final eligibility SHALL be checked atomically at allocation and replacement. Continuity SHALL always apply: one booking covers one continuous interval, and no venue setting SHALL turn this off. Confirmed bookings, and held bookings whose payment deadline has not passed, SHALL both exclude competitors. A held booking whose payment deadline has passed SHALL NOT exclude competitors, whether or not the expiry job has run: availability SHALL show its interval as free, and a new allocation on that court SHALL first expire it, releasing it exactly as expiry does. The data store itself SHALL reject two held or confirmed reservation slots on the same court whose half-open time intervals overlap, independent of application-level checks. A store-level rejection SHALL surface to the client as the same availability conflict as an application-level rejection.

#### Scenario: Concurrent reservation attempts
- **WHEN** 100 eligible requests target one available court-time
- **THEN** exactly one hold SHALL succeed and 99 SHALL receive valid conflicts without financial side effects

#### Scenario: Overlapping duration
- **WHEN** different start times overlap a reserved interval or hosted session
- **THEN** the later allocation SHALL fail, not merely compare identical start timestamps

#### Scenario: Configured booking window
- **WHEN** a selection violates a venue's book-ahead rule or is not one continuous interval
- **THEN** every booking path SHALL reject it consistently

#### Scenario: Writer bypasses the application check
- **WHEN** a held or confirmed slot from 10:00 to 12:00 exists on a court, and a different reservation writes a held slot from 11:00 to 12:00 on that court without passing the application availability check
- **THEN** the data store SHALL reject the write, and no part of that reservation, its entitlements or its payment records SHALL persist

#### Scenario: Adjacent intervals do not conflict
- **WHEN** one reservation holds 10:00 to 11:00 and another reservation requests 11:00 to 12:00 on the same court
- **THEN** both SHALL be accepted, because half-open intervals that only touch do not overlap

#### Scenario: Replacement held while its original stays protected
- **WHEN** a confirmed reservation is rescheduled to a different court-time and the replacement is held pending an extra payment
- **THEN** both the original and the replacement SHALL exclude competitors at the data store until the swap commits

#### Scenario: Released slots free the interval
- **WHEN** a slot becomes cancelled or expired
- **THEN** its interval SHALL no longer block new holds on that court

#### Scenario: Overdue hold does not block
- **WHEN** a held 15:00–16:00 booking has passed its payment deadline, the expiry job has not run yet, and another customer requests 15:00–16:00 on that court
- **THEN** availability SHALL show the interval as free, the new hold SHALL succeed, and the overdue booking SHALL become expired with its voucher use released once

#### Scenario: Live hold blocks
- **WHEN** a held booking's payment deadline has not passed and another customer requests an overlapping interval
- **THEN** the request SHALL receive the availability conflict

### Requirement: Immutable price quotes
The platform SHALL calculate quotes from effective-dated rules scoped to the organization, a venue, a venue and one sport, or a single court, using holiday/date exception, peak, weekly then explicitly configured base precedence. Within one precedence level, the narrower scope SHALL win (court, then venue and sport, then venue, then organization), followed by priority, version and identifier as deterministic tie-breakers. A sport-scoped rule SHALL name its venue and SHALL apply only to that venue's courts of that sport. Prices and allowed coupon, membership and loyalty benefits SHALL snapshot versions and validity. A random unmatched slot price MUST NOT be a fallback. Later changes SHALL not alter a confirmed booking; a booking still pending payment SHALL follow "Pending bookings at a price change". An expired quote SHALL be recalculated and accepted again before payment.

#### Scenario: Exception overrides a weekly price
- **WHEN** both a holiday exception and weekly rule match
- **THEN** both API versions and displayed availability SHALL use the same exception amount

#### Scenario: No applicable price
- **WHEN** a requested open interval has no applicable rule or configured base
- **THEN** it SHALL not be sold using the first unrelated price

#### Scenario: Price rules change after quote acceptance
- **WHEN** a policy changes after quote acceptance
- **THEN** a confirmed booking's snapshot SHALL remain stable, a booking still pending payment SHALL be re-checked as defined in "Pending bookings at a price change", and an expired unaccepted quote requires renewed consent

#### Scenario: Venue and sport rule
- **WHEN** a venue has a weekly venue-wide rule and a weekly pickleball rule for the same hour, and a customer quotes that hour on a pickleball court and on a badminton court of the venue
- **THEN** the pickleball court SHALL be priced by the pickleball rule, the badminton court by the venue-wide rule, and a court-specific rule for that hour SHALL override both

#### Scenario: Sport rule without a venue
- **WHEN** an operator saves a sport-scoped price rule without naming a venue
- **THEN** the request SHALL be rejected with a validation error

#### Scenario: Prices survive the grouping removal
- **WHEN** existing grouping-scoped rules are migrated to venue-and-sport scope
- **THEN** every court SHALL be quoted the same amount for the same interval before and after, or the migration SHALL stop without changing any data

### Requirement: Final bookings and released availability
A confirmed booking SHALL be final for its customer. Customers SHALL NOT be offered cancellation of a confirmed booking, refunds or rescheduling, and no endpoint or interface SHALL create a cancellation request, refund quote, refund obligation, reschedule preview or reschedule request. The only cancellation offered to customers SHALL be cancelling their own booking while it is still pending payment, as defined in "Customer cancels a pending-payment booking", which SHALL involve no refund. Staff and owners SHALL NOT cancel, move or change the time of a confirmed booking, whether or not it is checked in, except through the move, time-change and cancel commands for a booking that has an open affected-booking case, as defined in "Operator handling of affected bookings", and other than ending a recurring contract as below; those commands SHALL NOT create a refund quote, refund obligation or any money movement. A holder who cannot attend SHALL be directed to slot resale under the venue's transfer policy. Ending a recurring contract SHALL release only future occurrences that are unpaid; paid occurrences SHALL remain confirmed. Suspended venues SHALL retain authorized servicing of existing bookings, including the handling of affected bookings; legacy terms SHALL not be silently rewritten. Waitlist notices SHALL never promise automatic booking.

#### Scenario: Cancellation, refund or reschedule request
- **WHEN** a client calls a removed cancellation, refund or reschedule endpoint, or a customer looks for a way to cancel or move a paid booking
- **THEN** the request SHALL be rejected without changing any booking, inventory, payment, refund or notification record, and the interface SHALL offer slot resale instead

#### Scenario: Customer cancels a booking still pending payment
- **WHEN** a customer cancels their own booking that is still pending payment
- **THEN** it SHALL be cancelled as defined in "Customer cancels a pending-payment booking", and no refund quote or refund obligation SHALL be created

#### Scenario: Operator change without an open affected-booking case
- **WHEN** an owner or staff member tries to cancel, move or change the time of a confirmed booking that has no open affected-booking case
- **THEN** the request SHALL be rejected with 409 without changing any booking, inventory, payment or refund record

#### Scenario: Operator cancels an affected booking
- **WHEN** an owner cancels a paid booking that has an open affected-booking case, with the customer's recorded agreement
- **THEN** its inventory SHALL be released once, no refund quote or refund obligation SHALL be created, the recorded payment SHALL stay as history, and any refund SHALL be settled by the owner outside the platform

#### Scenario: Recurring contract ends
- **WHEN** an authorized operator ends a recurring contract that has both paid and unpaid future occurrences
- **THEN** only the unpaid future occurrences SHALL release, exactly once, and the paid occurrences SHALL remain confirmed without refund obligations

#### Scenario: Expired unpaid hold
- **WHEN** expiry processes paid and unpaid bookings together
- **THEN** only eligible unpaid holds SHALL release, without refunding unpaid value

#### Scenario: Legacy policy
- **WHEN** a historical booking carries older cancellation, refund or change terms
- **THEN** it SHALL retain them as historical evidence without making cancellation, refunds or rescheduling available again

## REMOVED Requirements

### Requirement: One-hour booking slots
**Reason**: Report Module 04 BR-73 and BR-75 define a booking as one court and one continuous interval on a 30-minute grid, with a 30-minute minimum, and Module 03 BR-55 prices the whole interval by the minute. One-hour slots that are priced and resold one by one cannot express 15:30–17:00 or a 30-minute booking. The user chose the report grid on 2026-10-21.
**Migration**: Replaced by "Thirty-minute booking interval". Quote, hold and checkout take `starts_at` and `ends_at` instead of a list of slot starts. Existing bookings keep their recorded one-hour slots and stay resellable as recorded. New bookings are resold as a whole, and the wording rule moves to the new requirement.
