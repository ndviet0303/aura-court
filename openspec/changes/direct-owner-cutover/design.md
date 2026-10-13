## Context

See `proposal.md` (Why) for motivation and the specs in this change for the required behavior. This section records only the current state that shapes the approach.

- **Flag plumbing.** `config/features.php` reads `FEATURE_*` and `PILOT_ORGANIZATION_IDS`. The prod image bakes them from build args (`devops/build/booking.prod.Dockerfile`, with `FEATURE_MARKETPLACE_PAYOUTS=false` hardcoded). CI passes `FEATURE_DIRECT_OWNER_PAYMENTS=false` and `PILOT_ORGANIZATION_IDS=1,2` (`.gitlab-ci.yml:148-153`). Every flag this change needs is pilot-only, and `FeatureFlagService` already accepts `*` as "every organization". Prod has exactly organizations 1 and 2 today, but ids follow seed insert order, and `StartPartnerApplication` creates new organizations at runtime. The repo stack anchor (`devops/portainer/booking_app.stack.yml:39-45`) redeclares every flag with a `:-false` default, so a redeploy from that file would switch them all off. The running stack (Portainer id 77) does not pass them.
- **The owner-collection pipeline already exists for bookings.** A per-venue `ReceivingConnection` with a routing id feeds `OwnerPaymentAttempt` (reference `AC` + 18 chars), then `OwnerPaymentReceipt`, then the allocation. The allocation posts either `postDirectOwnerReceipt` (debit `owner_bank_cash`, credit `owner_direct_revenue`) or a `FinanceException`. `ReservationPaymentService::instructions()` renders the VietQR payload, and the booking page polls. `pos_order` and `tournament_registration` are accepted purposes, but neither has a receipt branch.
- **Real deliveries cannot authenticate today.** The owner webhook requires `X-SePay-Timestamp` plus `X-SePay-Signature` (HMAC). SePay's only webhook auth options are No_Authen, OAuth2.0 and `Authorization: Apikey <key>`. SePay sends `code` only when a company payment-code format is configured: a prefix of 2–3 letters and a suffix of 1–30 characters, NumberOnly or NumberAndLetter. It waits at most 8 s for a response.
- **The platform webhook still serves the old model.** `SepayWebhookController` → `ProcessQrPayment` handles wallet deposits and `POS-…` references against the platform account (`config('services.sepay.*')`). POS QR images are built from that same platform account (`PosService.php:534`). Service-tab settlement accepts `qr` on the cashier's word, without evidence.
- **Prod runtime.** The facts below were read from prod on 2026-10-13 with read-only queries.
  - There is no Redis, so every payment screen must poll.
  - The host cron runs `schedule:run`: the 01:00 tasks ran after the 2026-10-12 reseed.
  - Jobs execute in-process: the `jobs` table is empty.
  - The image has no migrate step. Schema reaches prod either by manual SQL or through the dump/restore reseed.
  - The junk courts "ABC" and "ABCD" were created by hand at Cầu Giấy at 21:38 and 21:46 on 2026-10-12. They are not seed data.
- **Tournaments have no venue relation.** `settings['venue']` is free display text.
- **Rescheduling is a second payment flow.** `RescheduleService` holds a replacement reservation. When the replacement costs more, it collects the difference through a `booking_change` attempt, which has its own receipt branch and expiry entry. A cheaper replacement produces a refund difference. Prod has no `booking_change` attempt.
- **Slot length is defined in several places that disagree.**
  - `PriceQuoteService::resolveSlots` (V2 quote and hold, and recurring quotes) accepts any half-hour start.
  - `OrdinaryCheckoutService` (V1 and walk-in checkout) and `WaitlistService` require the top of the hour.
  - `SlotSelectionPolicy` deliberately checks no alignment.
  - Availability and recurring sessions take the slot length from the zone's `slot_duration` (`in:30,45,60,90,120`), while ordinary checkout always prices 60-minute slots.
  - Policy durations accept any minute count from 15.
  - Price slots created one at a time accept any `H:i`; the bulk form takes whole hours.
  - Staff `extendSession` stretches a booking's last slot without payment.
- **Prod data already fits a one-hour grid.** Every zone has `slot_duration` 60. Every reservation slot, legacy booking slot, entitlement and price slot starts on the hour and lasts 60 minutes. All six policies use whole hours.
- **One real bank account is available**: the TPBank account already connected to SePay.

## Goals / Non-Goals

**Goals:**
- One source of truth for feature flags.
- One money path for everything the owner collects: the owner payment attempt covers bookings, recurring periods, split shares, POS QR and tournament fees.
- Slots read as one hour wherever a user looks: seeded data, the report and Vietnamese text. Staff can no longer lengthen a booking without payment.
- A real SePay delivery reaches the receipt boundary and settles exactly one obligation, with no cashier or organizer action.
- Remove the cancellation, refund, rescheduling and platform-held-money surfaces with no destructive migration. History stays in the database, and the data model stays the same apart from two additive nullable columns: the seller account on listings and the venue on tournaments.

**Non-Goals:**
- A separate physical bank account per seeded receiver in the demo. See Risks.
- Push updates. Payment screens poll.
- OAuth2.0 webhook auth, and polling the SePay transaction API.
- Rewriting historical refund, payout, wallet, commission or transfer records.
- Reclassifying POS cash postings (`pos.sale` → `tenant_payable`) for direct cohorts. Payouts stay disabled, so these postings create no payout.
- POS goods returns (`pos/orders/{order}/refund`). This is listed in the proposal's non-goals.
- A seller uploading a QR image for resale. The seller enters bank, account number and holder name, and the platform renders the VietQR.

## Decisions

### 1. The image is the only source of feature flags
- CI builds with `FEATURE_DIRECT_OWNER_PAYMENTS=true` and `PILOT_ORGANIZATION_IDS=*`, quoted so the shell cannot glob it.
  - With `1,2`, a partner organization approved during the demo would run the legacy wallet model, and `wallet_enabled` (Decision 8) would bring "Ví Aura" back for every user.
  - `*` enables no payouts: `FEATURE_MARKETPLACE_PAYOUTS=false` stays hardcoded in the Dockerfile.
- The other flags stay as they are.
- Delete the `FEATURE_*` and `PILOT_ORGANIZATION_IDS` lines from the stack anchor. The stack has no `env_file`, so Portainer stack variables only feed compose interpolation. The stale Portainer value `PILOT_ORGANIZATION_IDS=73,74` therefore becomes inert, and the user removes it.
- Known limit: the manual commands `PilotMigrate`, `ReviewPhaseGate` and `RecordMoneyFlowUat` default to the pilot list and cannot parse `*`. They must be given `--organizations`. None of them is scheduled.
- *Alternatives:*
  - Keep `PILOT_ORGANIZATION_IDS=1,2`. Rejected: ids are not stable across reseeds, and new partner organizations would fall back to the wallet model.
  - Keep the stack lines with `:-true` defaults. Rejected: that leaves two sources that can drift, and a Portainer value silently wins.
  - Use runtime env only. Rejected: every flip would need a Portainer edit, and the flags would lose their link to the code revision that needs them.

### 2. SePay API-key authentication replaces the timestamped HMAC on the owner endpoint
- `SePayReceivingConnectionService::verifyInboundWebhook` authenticates `Authorization: Apikey <credential>`. It compares the value with the connection's decrypted credential using `hash_equals`.
  - A missing or wrong header returns 401 and records no evidence.
  - The timestamp window and the HMAC code are deleted.
- These checks stay as they are:
  - receiver match (`accountNumber`)
  - payload validation
  - per-connection dedupe on the provider event id (`receiveVerifiedEvent` returns the existing receipt)
  - reference, amount and expiry matching in `processReceipt`
  - auto-verification on the first matching inbound event
- The evidence label changes from `webhook_hmac` to `webhook_api_key`.
- The connection screen and API name exactly what the owner pastes into SePay:
  - URL `…/api/v1/webhooks/sepay/connections/{routingId}`
  - auth Api_Key
  - event In_only
  - payment code format `AC`, suffix 18–18, NumberAndLetter
- Unit and feature tests, Playwright synthetic events and readiness evidence all switch to the header form.
- *Alternatives:*
  - Support both HMAC and API key. Rejected: a second auth path with no real producer adds surface and test cost (no shims).
  - OAuth2.0. Rejected: we would host a token endpoint and gain nothing over a TLS-carried per-connection secret.
  - Poll the SePay API. Rejected: it needs a real API token on prod, adds latency and creates a second ingestion path.

### 3. One real receiver for every seeded account, taken from env
- `UserSeeder::receivingConnection` reads four env values:
  - `SEED_DEMO_RECEIVER_BANK` (VietQR bank code, e.g. `TPB`)
  - `SEED_DEMO_RECEIVER_ACCOUNT`
  - `SEED_DEMO_RECEIVER_NAME`
  - `SEED_DEMO_RECEIVER_WEBHOOK_KEY`
- Outside `local`/`testing`, a missing value aborts the seed. This follows the existing `hashedPassword` pattern; there is no committed fallback.
- Every seeded venue connection and every seeded resale seller account uses these values. A QR in the demo can therefore never send money to a stranger.
- Only `aura-court` (Cầu Giấy) gets the env webhook key. The other connections get random keys and are not registered in SePay.
- The seeder prints the Cầu Giấy webhook URL. The routing id is uuid5 `auracourt-demo/aura-court`, which is deterministic and not secret. The seeder never prints the key.
- *Alternatives:*
  - The owner rotates the credential in the UI after each reseed. Rejected: a manual step that must be repeated before the defense.
  - Keep fake MBBank accounts. Rejected: real transfers could reach third parties.

### 4. POS QR uses the owner-attempt path
- **QR tender requires the venue's current verified connection.** Without one, the server refuses QR with setup guidance, and the POS config tells the UI to hide QR.
- **Creating a QR order.**
  - It keeps today's `pending_payment` order and stock reservations.
  - It creates an attempt: `purpose=pos_order`, `purpose_id` = order id, amount = QR portion, expiry = order expiry, server `AC` reference.
  - It stores that reference as `payment_reference`. Any client-sent reference is ignored.
  - It returns `ReservationPaymentService::instructions($attempt)`.
- **`processReceipt` gets a `pos_order` branch.**
  - It locks the order. If the order is still `pending_payment` and the attempt is live, it runs the existing completion routine: QR payment row, stock movements, loyalty.
  - It does **not** call `postPosSale` for the QR portion. That posting credits `tenant_payable`, a platform liability for money the platform never held. Revenue is recorded once, by `postDirectOwnerReceipt`.
  - A receipt for an order that is cancelled or expired becomes the exception `pos_order_unavailable`.
- **Removed:**
  - the employee `confirm-qr` endpoint and `PosService::confirmQrPayment`
  - the `POS-` branch of `ProcessQrPayment`
  - the platform-account QR builder

  The POS screen polls the order until it completes. `cancel-qr` stays: it cancels the order and expires the attempt.
- **Wallet tender is refused for direct cohorts** before any mutation, in both `PosService` checkout and `ServiceTabService` settlement.
- **Service-tab settlement in direct cohorts accepts cash only.** Its `qr` method completed orders on the cashier's word, which violates the POS evidence requirement. A counter QR payment is rung up as a POS order instead.
- *Alternatives:*
  - Keep the platform QR and confirm via the platform webhook. Rejected: the money lands in the platform account, which contradicts the report.
  - Settle a tab on receipt through a deferred tab-completion branch. Deferred: it adds a cross-entity completion path that the demo does not need, and it can reuse the same `pos_order` attempt later.

### 5. Peer-to-peer resale
- **Listing.**
  - A paid listing requires the seller's bank code, account number and holder name.
  - They are validated, then stored encrypted in a new nullable `marketplace_listings.seller_receiver_ciphertext` column.
  - New listings get `fee_bps = 0`.
  - Listing lists never include the receiver.
  - A listing covers one entitlement, which is one booked slot, one hour in every seeded zone (Decision 10). The holder of a two-hour booking lists each hour separately, as today.
- **Lock (the existing `checkout` route).**
  - It runs under a row lock and only from `listed`. A `checkout` listing whose lock has expired is released first.
  - It sets status `checkout`, which already passes the status CHECK, and fills metadata `locked_by`, `locked_until` and `reference`.
    - `locked_until` = min(now + 15 min, cutoff).
    - `reference` is `RS` + 10 uppercase alphanumerics, a distinct prefix so a resale transfer can never match an owner attempt.
  - Only the locking buyer and the seller receive the seller's VietQR for the exact asking price. It is rendered by the same image-URL helper, extracted from `ReservationPaymentService::instructions`.
  - A concurrent second lock gets 409 and sees no receiver.
- **New endpoints:**
  - `payment-sent` (buyer): sets `payment_reported_at` and holds the lock until the seller resolves it or the cutoff passes.
  - `release`:
    - The buyer may release before reporting payment.
    - The seller may release by reporting non-receipt.
    - Both cases notify the other party. A buyer's payment report is shown as unconfirmed.
  - `confirm-receipt` (seller):
    - It rechecks eligibility: not disputed, buyer not restricted, before cutoff.
    - It runs the existing atomic entitlement transfer without any owner evidence.
    - It writes the transfer record as `completed`, `fee=0`, `seller_refund=0`, `settlement_mode=peer_to_peer`, with no journal.
  - A free listing transfers on the buyer's explicit acceptance inside the lock transaction.
- **Removed:**
  - `POST marketplace/listings/{listing}/purchase`
  - the owner-attempt `transfer_consideration` creation path
  - the owner reimbursement evidence check

  Historical `compensated` and commission records stay unchanged.
- Disputes use support cases, with `reference_type` pointing at the listing.
- *Alternatives:*
  - Keep owner-collected resale and fix the 422. Rejected: the owner would collect and reimburse, which contradicts the report.
  - Verify P2P payments through SePay. Rejected: the seller's account is not a platform connection.

### 6. Tournament fees use the owner-attempt path
- **Venue.**
  - Add a nullable `tournaments.venue_id`, with a restrict-on-delete foreign key and an index as the FK-index conformance test requires.
  - Create and update require it when the entry fee is greater than 0. It must be a venue of the tournament's organization with a current verified connection.
  - The create form replaces the free-text venue with a select of the organization's venues. `settings['venue']` keeps the display name, so display and search do not change.
- **Paid registration.**
  - It runs under the tournament row lock.
  - It counts confirmed participants plus live pending attempts (`purpose=tournament_registration`, `purpose_id` = tournament uuid, status pending, not expired) against capacity.
  - It then creates or returns the caller's attempt, idempotent per (tournament, user).
  - Expiry is 15 minutes, capped at the registration deadline.
  - The QR comes from `ReservationPaymentService::instructions`, and the page polls `GET tournaments/{tournament}/registration`.
- **`processReceipt` gets a `tournament_registration` branch.** It creates the participant exactly as free entry does, recording the receipt. If the tournament closed or the attempt expired, it becomes the exception `tournament_registration_unavailable`.
- **Organizer adds** (`addParticipant`) to a paid tournament require a `waiver_reason`, which is recorded on the participant and in the audit log. Every wallet debit leaves `TournamentService`.
- *Alternatives:*
  - Resolve the venue from the `settings['venue']` slug. Rejected: it is free text, and guessing could route money to the wrong connection.
  - Store a venue id inside the `settings` JSON. Rejected: no FK integrity.

### 7. Remove cancellation, refunds and rescheduling
- The user also removed rescheduling on 2026-10-13. A customer who cannot attend passes the slot on through resale ("pass sân"), which is now the only remedy.
- **Routes deleted:**
  - v1 `refund-quote` and `refund-requests` (`routes/api.php:267-269`), and `reschedule-preview` and `reschedule-requests` (`:270-271`)
  - v2 `refund-quote` and `refund-requests`, the owner inbox, approve, reject, report-execution and acknowledge, and `reschedule-preview` and `reschedule-requests` (`:435-444`)
  - `admin/refunds` (`:367`)
- **Services and code deleted:**
  - `OwnerRefundService` and its controller
  - `RescheduleService` and `RescheduleController`
  - the `booking_change` attempt purpose: its `PURPOSES` entry, its `processReceipt` branch and its entry in the `ExpirePendingBookings` purpose list. No handler is kept for old rows, because prod holds none and the reseed creates none.
  - the `excludeReservationId` and `excludeBookingSlotIds` parameters of `SlotSelectionPolicy::assertAllowed`. Only rescheduling passed them.
  - `RecurringReleaseService::repairLegacyCancel` with its action and command
  - `recordPaidValue` and `refundAttempt`
  - `SplitPaymentService::refundAllocations`, which is already dead code
  - the dispute decision `resolve_and_refund`
- **Ending a recurring contract** (`contracts/{id}/cancel`) releases only unpaid future occurrences. Paid occurrences stay booked.
- **An expired split group** records one `FinanceException` per paid share. No refund obligation is created.
- **Policy settings.**
  - The venue policy request drops `cancellation`, `change`, `ancillary_credit_policy` and `transfer.seller_reimbursement_required`. Nothing reads `ancillary_credit_policy`. Only the owner-collected resale removed by Decision 5 reads the reimbursement flag.
  - Readiness only checks that a policy exists, so it does not change.
  - Stored policy versions and booking snapshots keep these keys as history.
  - The frontend loses the policy fields, the cancel and reschedule modals, the refund panels, the arbitration refund option and Terms §5.
- **Stored identifiers stay; only the copy changes.** The partner consent type `settlement_refund_terms` keeps its key, because consent history and the required-consent list store it. Its text changes to: the owner receives payments directly and handles late or mismatched money outside the system.
- *Alternatives:*
  - Hide the refund UI but keep the endpoints. Rejected: the API would still promise refunds (no shims).
  - Drop the refund tables. Rejected: destructive, and the history must stay intact.
  - Keep rescheduling to an equal or higher price. Rejected by the user: the report keeps only resale, and rescheduling needs a second payment flow for the difference.
  - Keep a `booking_change` handler for old attempts. Rejected: there are none, and the reseed runs before the deploy.

### 8. Wallet and finance surfaces for direct cohorts
- **Server.** The server refuses wallet deposit creation and wallet tender (booking, POS, tab, tournament) for direct-cohort organizations.
- **Customer UI.** `/me` capabilities gain `wallet_enabled`. It is true when an active legacy-model organization exists or the user holds a non-zero wallet balance. The "Ví Aura" nav item, the profile wallet card and the `/wallet` route all follow it.
- **Admin.**
  - The whole Finance group (Rút tiền / Đối soát / Ngoại lệ, which includes "Phí & Hoa hồng") renders only when `marketplace_payouts` is enabled.
  - A direct link shows "capability unavailable".
  - POS hides wallet tender when the organization's `direct_owner_payments` capability is on.
- *Alternative:* un-gate reconciliation and exceptions for direct cohorts. Rejected: their APIs sit behind the payouts capability, and the specs keep them there.

### 9. Only priced courts are public
- Extract the rule-loading query of `PriceQuoteService::priceSnapshots` into one method. It loads active `PriceRule` rows scoped by org, venue, zone or court, plus active `CourtPriceSlot` rows.
- A court counts as priced when that query returns any rule.
- `VerifyEffectiveBookingEligibility` applies the check, which covers detail, availability and new bookings. The public list applies it in one batched query.
- The frontend fallbacks are removed: 120000/100000 in `useCourtDetail`, `HomeView`, `VenueCourtsGrid` and `CourtItemCard`, and 200000 in `useResaleMarketplace`.
- *Alternative:* filter only on the frontend. Rejected: direct requests and other entry points would bypass it.

### 10. One-hour slots in data and wording, with no new enforcement
- **Decision.** On 2026-10-13 the user chose wording and data only. Slots stay one hour in the seeded data, the report and the Vietnamese text. The code gains no new slot-length or start-time check. The staff extension is removed.
- **What already holds without new code.**
  - Every seeded zone, and every zone `CreateVenue` creates, has `slot_duration` 60. Prod data already fits a one-hour grid (see Context).
  - V1 checkout, walk-in and the waitlist already require slots that start on the hour, and ordinary checkout prices 60-minute slots.
  - No frontend form sends `slot_duration`. The court page's 1.5-hour option only changes the estimated total and never creates a booking.
- **Staff extension is removed.**
  - `AttendanceService::extendSession` and `POST attendance/extend-session` stretched the last slot past its hour without payment, and checked conflicts outside the transaction.
  - Extra time is booked as a walk-in booking of the next free slot. That path already runs through `assertAllowed` and takes its own payment.
  - No frontend called the endpoint. `reassignCourt` stays.
  - The path now answers 405, because only `GET attendance/{code}` still matches it.
- **Wording.** Vietnamese text never shows "slot". The user asked for this on 2026-10-13.
  - Counts say "giờ", for example "Chọn từ 1 đến 12 giờ".
  - A single unit says "1h", for example "Pass lại 1h này".
  - Existing "khung giờ" labels stay.
  - Code identifiers, API fields and English text keep "slot". Renaming them would break clients and gain nothing a user can see.
- **No data migration.** Prod already fits (see Context). The seed test checks that seeded data does too.
- **Accepted gaps.** The seeded data avoids each of these:
  - V2 quotes and holds still accept half-hour starts. Only the admin pricing simulator sends one, and it only quotes.
  - The zone API still accepts `slot_duration` 30–120. No interface sends the field.
  - Single price slots and policy durations still accept any minute count, so an owner can still type a non-whole-hour value.
- *Alternatives:*
  - Enforce one hour on every path: one shared check in `SlotSelectionPolicy`, `slot_duration` retired as a setting, `multiple_of:60` policies, `HH:00` price slots and no 1.5-hour option. Rejected by the user on 2026-10-13. It touches about fifteen files three weeks before the defense, while the data and every booking interface already show one hour. It can follow the defense as its own change.
  - Keep the extension and charge for it. Rejected: it needs its own payment flow, and a walk-in booking of the next slot already does the job.

### 11. Seed data mirrors the live paths
- **Removed from the seed:**
  - wallet top-ups and wallet-paid bookings, POS sales or tournament fees
  - payouts, settlements and beneficiaries
  - refunds, cancellations and refund-resolved disputes
- **Seeded paid history** is written as the live code would write it: attempt `paid`, receipt `allocated`, journal `owner.direct_receipt`. No platform `payments` rows are written.
- **Seeded policies** have no cancellation, change, ancillary-credit or seller-reimbursement terms, and their durations are whole hours. `E2ESeedFixturesCommand` follows the same shape.
- **Seeded resale** has P2P listings with seller receivers and some completed transfers with `fee=0`.
- **Seeded tournaments** have a `venue_id`, and there is one upcoming Cầu Giấy tournament with a small fee for the live demo.
- **Cầu Giấy** gets one POS product priced at or below 10,000 VND.
- Junk courts disappear with the reseed. Decision 9 keeps any unpriced court out of public view.

### 12. Expiry: a scheduled sweep plus lazy checks
- Schedule `PosService::releaseExpiredPendingOrders` every minute for active organizations. It exists today but is not scheduled. It also expires the matching attempt.
- Add a sweep that releases expired resale locks.
- At the cutoff, a listing that is still unsold becomes `withdrawn`.
- Every command that reads a lock or attempt treats an expired one as released or expired at that moment. Correctness therefore never waits for the cron.

### 13. Rollout order: reseed, then deploy
- The schema change is additive: two nullable columns, and no status values added or removed. The old image therefore runs safely on the new schema.
- The reseed (dump/restore of a freshly migrated `booking_demo_btprod`) carries both the schema and the `migrations` rows. It runs first.
- The push to `main` then deploys the image with the flag on. The reverse order would serve the new code against missing columns.

### 14. Cross-change and archive handling
- `retire-v1-booking` (in progress) also modifies "Versioned reservation API compatibility". Its delta is edited to use this change's wording:
  - The gate's client list drops "booking changes".
  - The superseded-by-policy-governed-requests sentence goes.
  - The stale-client scenario names the removed cancellation, refund and reschedule commands.
- Whichever change archives second rebases its MODIFIED block onto the main spec the first one left.
- Some main spec Purposes mention refunds and do not change through deltas. They are edited by hand at archive:
  - `direct-owner-payments` ("owner-executed refunds")
  - `reservations-and-pricing` ("policy-governed cancellations and reschedules")
  - `wallet-payment` ("booking changes … owner-executed obligations")
- `harden-db-integrity`'s status-CHECK test is unaffected because no status values change.

## Risks / Trade-offs

- **[SePay does not detect `code`]** This happens if the format is missing or a bank rewrites the content. Receipts then land as `missing_payment_code` exceptions, and nothing confirms. → The user configures the `AC`/18/NumberAndLetter format, and the QR embeds the reference so nobody types it. The rehearsal sends real transfers from at least two banks.
- **[Wrong amount]** If the payer edits the amount, the receipt becomes an `invalid_amount` or exception outcome. → VietQR fixes the amount, and the demo script uses the QR.
- **[One physical account behind every seeded receiver]**
  - SePay delivers every transfer on that account to every webhook registered for it. Only Cầu Giấy's routing id is registered.
  - A transfer for another venue therefore reaches Cầu Giấy's endpoint, does not match, becomes an exception there and never confirms the booking.
  - A resale transfer (`RS…`) also lands there as `missing_payment_code`.
  - → The demo script uses Cầu Giấy for anything that must auto-confirm. Registering more routing ids is possible, at the cost of one unmatched exception per extra webhook per transfer.
- **[Static API key leaks]** Anyone holding the key could post forged events that confirm obligations without money. → The key lives only in gitignored `.env.btprod`, in the encrypted connection and in SePay. The Authorization header is never logged. Transport is TLS only. The key is rotated after the defense with the existing rotation endpoint. Receipts settle only live obligations with an exact receiver, reference and amount.
- **[SePay's 8 s response limit vs in-process processing]** → The receipt commits before processing (`afterCommit`). If processing is slow, SePay logs a failed delivery, but the receipt is already stored. The 1-minute recovery tick finishes it, and no retry is needed.
- **[Old platform webhook left enabled]** After Decision 4 it can match nothing for direct cohorts, so it only logs unmatched events. → The user disables it after deploy. Leaving it on is harmless.
- **[A customer wants to move a booking]** There is no reschedule. → The booking page points to resale plus a new booking, and the demo script shows that path. For an incident on site, staff can still move a booking to an equivalent court.
- **[P2P buyer pays and the seller never confirms]** → The lock holds until the cutoff, then the buyer is notified and can open a support case. The UI says that money moves between buyer and seller, as the report states.
- **[Finance exceptions are invisible in the UI while payouts are off]** → This is accepted by the `financial-ledger-and-payouts` delta. During the demo, unmatched money is checked in SePay or the bank app.
- **[Large files hit the architecture line ceilings]** For example, `PosService` is over 1,100 lines. → New logic goes into focused services, such as POS QR payment, tournament registration payment and P2P resale, and the removed paths are deleted first.
- **[Parallel `main` pipelines roll back a deploy]** → Push one merge, wait for that pipeline, and check `build-info.json`.
- **[Seeded data is relative to seed time]** → Reseed 1–2 days before the defense, after 07:00, and rerun the smoke rehearsal.

## Migration Plan

1. Implement on a feature branch and pass the local gates:
   - scratch phpunit config on a `*_test` database
   - temporary `.env` for PHPStan
   - `vitest --maxWorkers=1` alone
   - Playwright synthetic events with the API-key header
2. User actions, done outside chat:
   - Add the four `SEED_DEMO_RECEIVER_*` values to `backend/.env.btprod`.
   - In SePay, add the `AC` payment-code format and the Cầu Giấy webhook (Api_Key, In_only).
   - In Portainer, remove the stale `PILOT_ORGANIZATION_IDS` value and confirm stack 77 passes no `FEATURE_*`.
3. Seed `booking_demo_btprod` from `.env.btprod` with the branch code, then run the readiness checks locally against it.
4. Back up prod with `pg_dump` into `backend/storage/app/backups/`.
5. After the user confirms timing:
   - Reseed prod through the dump/restore procedure.
   - Merge `--no-ff` into `main` and push, which deploys.
   - Verify the revision.
   - Disable the old platform webhook in SePay.
6. Rehearse on prod with real small transfers:
   - booking at Cầu Giấy auto-confirms
   - POS QR order completes
   - tournament fee registers the player
   - P2P resale lock → pay → seller confirms
   - a two-hour booking shows two one-hour slots, each of which can be listed for resale
   - no cancel, refund or reschedule action appears anywhere
   - no wallet or finance menus appear
7. Freeze. Reseed again 1–2 days before the defense using steps 3–5 (no merge needed), then rerun the smoke checks.

**Rollback:**
1. Redeploy the previous image by reverting the merge on `main`.
2. Restore the step-4 backup.
3. Re-enable the old platform webhook if wallet top-ups must work again.
