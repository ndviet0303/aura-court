## Context

See `proposal.md` (Why) for motivation. Measurements below come from read-only catalog queries against `bt-server` (PostgreSQL 18.6, database `booking`) on 2026-10-11.

**Schema shape.** 124 tables, about 301 foreign keys, 418 indexes, 0 CHECK constraints, 0 exclusion constraints, 2 triggers. The triggers are `journals_append_only` and `journal_entries_append_only`, both calling `prevent_financial_mutation()`. All timestamps are `timestamp(0) without time zone`. By backend rule, domain times are stored as UTC wall-clock, and the availability code converts with `->utc()` before comparing.

**Inventory guard today.**

- All four writers of `reservation_slots` lock the `courts` row first, then call `SlotSelectionPolicy::assertAllowed()`. That method runs a true interval-overlap query against both `booking_slots` and `reservation_slots`:
  - `ReservationService::hold`
  - `OrdinaryCheckoutService::checkoutV2`
  - `OrdinaryCheckoutService::checkoutV1`, through `LegacyBookingBackfillService::backfillBooking`
  - `RescheduleService::createReplacement`
- The only store-level backstops compare identical start times:
  - `booking_slots_active_unique (court_id, starts_at)`
  - `reservation_entitlements_active_unique (court_id, starts_at)`
- Slot length varies per sport zone. Stored durations today are 1 h (400 rows) and 2 h (91 rows).
- The batch `LegacyBookingBackfillService::backfill` writes slots without the court lock.

**Reschedule keeps two active reservations at once, but never overlapping.**

- `RescheduleService::createReplacement` inserts the replacement's `held` slots and entitlements while the original's slots stay `confirmed`.
- When an extra payment is due, both stay active across transactions until the payment is verified, then `applySwap` cancels the original. This matches the spec: the original "SHALL remain protected until verified difference and replacement commit".
- The replacement can never overlap its original. Both the preview and the request call `PriceQuoteService::create` first (`RescheduleService.php:61`, `:125`), and it rejects any interval that overlaps a held or confirmed reservation, including the original. `SlotSelectionPolicy`'s `excludeReservationId` is therefore never what admits a reschedule. Found during implementation (see Implementation Decisions).

**Data preconditions (bt-server).**

| Check | Result |
|---|---|
| Pairs of held/confirmed `reservation_slots` on one court with overlapping intervals and different reservations | 0 |
| Active slots under a cancelled/expired reservation | 0 |
| Held slots under a confirmed reservation | 10. Active either way, non-overlapping. |
| Active `booking_slots` without a reservation mirror | 15, all in the past |
| `btree_gist` | available (1.8), not installed. Role `ziet_dev` owns the database. |
| Rows in `reserves`, `memberships`, `membership_plans` | 0 / 0 / 0 |
| Pending migration | `2026_10_07_000001_change_avatar_url_to_text_in_users_table` |
| Distinct status values outside the allowed sets (Decision 5) | 1: `booking_slots.status = 'booked'` (D4) |
| Foreign keys without a covering index, excluding dropped tables | 186, identical to `booking_test` after `migrate:fresh` |

Preflight script: `docs/operations/harden-db-integrity-preflight.sql` (read-only). Run on bt-server 2026-10-11; a production snapshot has not been run yet (no access from this environment).

**Deletion paths.** No application code deletes an organization, user, booking, reservation, POS order, wallet, subscription or refund obligation. The only test that deletes owners is `ReconcileLegacyPartnersCommandTest`, which calls `Organization::query()->delete()`.

**Constraint-violation handling.** Four `catch (QueryException)` sites match constraint names in the message and convert them to 409:

- `OrdinaryCheckoutService` around lines 436 and 703
- `ReservationService` around line 276
- `RescheduleService` around line 201

## Goals / Non-Goals

**Goals:**
- Overlapping active inventory is impossible at the store level, without changing which requests succeed today.
- Status typos, cascading loss of financial history, and unindexed foreign keys are rejected by the schema, and a test keeps them from coming back.
- Every migration is reversible and leaves existing rows untouched. The one exception is adding a nullable column.

**Non-Goals:**
- Covering court blocks, owner-hosted match posts or tournaments in the store-level constraint. They live in other tables, and the application guard keeps handling them.
- Changing `booking_slots` or the entitlement unique index.
- Moving status strings into PHP enums or model constants.

## Decisions

### 1. Plain exclusion constraint on active slots

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE reservation_slots ADD CONSTRAINT reservation_slots_no_overlap
  EXCLUDE USING gist (
    court_id WITH =,
    tsrange(starts_at, ends_at, '[)') WITH &&
  ) WHERE (status IN ('held', 'confirmed'));
```

- `[)` bounds let back-to-back slots (10–11, 11–12) coexist.
- The `WHERE` matches the status filter that `SlotSelectionPolicy` already applies to slots.
- A held replacement and its confirmed original never overlap (see Context), so no reschedule that succeeds today is rejected.

Alternatives considered:
- **Exclusion keyed by a reschedule lineage column** (`COALESCE(lineage_reservation_id, reservation_id) WITH <>`), letting a replacement overlap its original. This was the first design. It rested on the wrong premise that the application admits such reschedules today; it does not (see Context). It would add a column and a write path that nothing needs. If product later wants overlapping reschedules, that change adds the lineage key together with the quote change.
- **`DEFERRABLE INITIALLY DEFERRED`.** Not needed: no flow writes overlapping active slots, even transiently.
- **Keep identical-start unique indexes.** That is today's state, and it misses overlaps between different grids.
- **Trigger-based overlap check.** A trigger reimplements the exclusion and is only race-safe with explicit locking. The native constraint is race-safe by construction.
- **Constrain `reservation_entitlements` instead.** Slots carry the interval. Entitlements are 1:1 with slots and only have a start time.

### 2. Map the new violation where the old ones are mapped

Add `reservation_slots_no_overlap` to the message match at the four catch sites listed in Context. They return the same 409 messages.

- The V1 checkout site needs it too, because V1 dual-writes reservation slots.
- A global handler for SQLSTATE `23P01` was rejected. Every existing mapping is local and per-flow, with flow-specific messages and metrics such as `reservation.overbooking_conflict`.

### 3. RESTRICT instead of CASCADE into financial history

Drop and re-add these 45 foreign keys with `ON DELETE RESTRICT`. They are listed as child.column → parent.

| Group | Foreign keys |
|---|---|
| organizations | `accounts`, `journals`, `journal_entries`, `beneficiaries`, `settlements`, `payouts`, `monthly_invoices`, `split_groups`, `split_shares`, `entitlement_transfers`, `stock_movements`, `pos_orders`, `pos_order_items`, `pos_payments`, `cash_shifts`, `shift_reconciliations`, `point_ledgers`, `wallet_accounts`, `wallet_transactions`, `service_tabs`, `platform_subscriptions`, `platform_payment_attempts`, `match_bills`, `owner_refund_obligations` (`.organization_id`) |
| users | `elevated_audit_logs.user_id`, `point_ledgers.user_id`, `wallet_accounts.user_id`, `service_tabs.opened_by` |
| other owners | `refunds.booking_id → bookings`, `monthly_invoices.recurring_series_id → recurring_series`, `split_groups.reservation_id → reservations`, `split_shares.split_group_id → split_groups`, `pos_order_items.order_id → pos_orders`, `pos_payments.order_id → pos_orders`, `shift_reconciliations.cash_shift_id → cash_shifts`, `wallet_transactions.wallet_account_id → wallet_accounts`, `service_tabs.reservation_id → reservations`, `service_tab_items.product_id → pos_products`, `service_tab_items.service_tab_id → service_tabs`, `platform_subscriptions.plan_id → platform_plans`, `platform_payment_attempts.subscription_id → platform_subscriptions`, `platform_provider_receipts.payment_attempt_id → platform_payment_attempts`, `match_bills.match_post_id → match_posts`, `owner_refund_allocations.owner_refund_obligation_id → owner_refund_obligations`, `owner_refund_evidence.owner_refund_obligation_id → owner_refund_obligations` |

- The "financial table" set is the child tables above plus the tables that already have no cascade: `payments`, `owner_payment_*`, `payment_intents`, `audit_logs`, `provider_events`, `receiving_connection_evidence`, `finance_exceptions`, `reconciliation_runs`.
- The schema conformance test (Decision 7) holds this set as a constant.
- `down()` restores CASCADE for exactly these constraints.

Alternatives considered:
- **SET NULL.** These columns are NOT NULL tenant or ownership keys. Nulling them would orphan the history the spec says must stay reportable.
- **Soft delete.** No deletion workflow exists to adapt, so this is YAGNI.
- **Leave CASCADE and add more append-only triggers.** It duplicates the referential rule and keeps the declared intent wrong.

### 4. One static migration for foreign-key indexes

- Generate the list at implementation time from the catalog query used in the audit. That query finds foreign keys with no index whose leading columns equal the FK columns. It found 189 on bt-server.
- Cross-check the list against a fresh `migrate` on `booking_test`. Both come from the same migrations, so they must match. Exclude foreign keys on tables this change drops.
- Write the migration as explicit `CREATE INDEX IF NOT EXISTS {table}_{column}_index ON {table} ({column})` statements, with the matching `DROP INDEX IF EXISTS` in `down()`.
- Plain `CREATE INDEX` inside the migration transaction is enough. The largest table is `court_price_slots` with about 64k rows, and the rest are under 1k.

Alternatives considered:
- **`CONCURRENTLY`.** It needs a non-transactional migration and per-statement error handling, which is unnecessary at this size.
- **A dynamic migration that loops over the catalog at run time.** It is non-deterministic across environments and its `down()` cannot know what it created.

### 5. CHECK constraints for status columns

- **Scope.** Every `varchar`/`text` column named `status`, `*_status` or `state`: 80 columns. Eight drop out:
  - `memberships.status` and `reserves.status` disappear with their tables (Decision 6).
  - `legacy_booking_mappings.legacy_status` is exempt: verbatim copy of a legacy value.
  - `provider_events.status`, `payouts.status` and `provider_operations.status` are exempt: they store the status a payment provider reported, as received.
  - `host_kycs.tax_status` and `host_kycs.lease_contract_status` are exempt: free text entered by the host (the default is a Vietnamese phrase), not a lifecycle status.

  That leaves 72 constrained columns. `idempotency_records.response_status` is a `smallint` HTTP code and falls outside the scope by type. The conformance test (Decision 7) holds the six exemptions with their reasons.
- **Allowed set per column:** the union of values the code writes (constants, literals in create/update/forceFill), DB defaults, model constants, values the code reads (`whereIn`/`in_array`/`where` literals), and distinct values present on bt-server.
  - Seeder and test values join the set only when they fill a gap in that vocabulary. A seeder, test or e2e value that contradicts the vocabulary the code uses for the same state is fixed at the writer instead.
  - A value present in data but absent from code is investigated and recorded in the migration as a comment, never dropped silently.
  - Bad data is not rewritten by these migrations. It gets a separate, reviewed data fix first.
- **Form:** `ALTER TABLE t ADD CONSTRAINT t_col_check CHECK (col IN (...))`. Nullable columns keep accepting NULL, because CHECK passes NULL.
- **Layout:** one migration per domain group, so a failure names a small area. The groups are inventory/booking, payments/finance, POS/on-site, organization/partner/venue, community/tournament/disputes, and rollout runs.
- **Evolution.** Adding a status means a migration that drops and re-adds the constraint. The conformance test catches a new status column without a constraint.

Alternatives considered:
- **PostgreSQL `ENUM` types.** Values cannot be removed, and changes need type rewrites. CHECK is easier to evolve.
- **PHP enums for the 272 literals.** A large diff that catches nothing CHECK plus the PostgreSQL test suite does not already catch.

### 6. Drop dead tables behind an emptiness guard

- One migration drops `memberships`, then `membership_plans`, then `reserves`.
  - `memberships` goes first because it references `membership_plans`.
  - Nothing references these three tables.
- `up()` throws a `RuntimeException` naming the table if any of them has a row. A production database with unexpected data stops the deploy instead of losing data.
- `down()` recreates them empty with their original columns and foreign keys.
- Code removal:
  - models `Reserve`, `Membership`, `MembershipPlan`
  - `User::memberships()`
  - `PromotionController::plans`, `storePlan`, `assignMembership`
  - their routes `GET/POST api/v1/admin/membership-plans` and `POST api/v1/admin/memberships`
- `MembershipPlanSeeder` also seeded a `membership_plans` row; that block goes, and the seeder keeps seeding `tenant_membership_plans` and `loyalty_rules`.
- The frontend admin membership screen calls `api/v2/admin/memberships/*` (`AdminMembershipController` → `tenant_membership_plans`) and is unaffected.
- `refunds` is not dropped. It holds historical rows, and the ledger spec keeps historical refunds reportable. Its dead write path belongs to `retire-v1-booking`.

### 7. Schema conformance test

`tests/Feature/Infrastructure/SchemaConformanceTest.php` runs three catalog queries on the freshly migrated test database:

1. Foreign keys without a covering index.
2. Status columns in scope (Decision 5) without a CHECK, minus a constant exemption map of `table.column → reason`.
3. Foreign keys with `confdeltype = 'c'` whose child is in the financial table set.

Each failure message lists the offending table, column and constraint.

CI runs the full PHPUnit suite against a `postgres:17-alpine` service, so this test is a merge gate. That image ships `btree_gist`, and its service user is a superuser, so the exclusion migration runs there unchanged.

## Risks / Trade-offs

- [The production role cannot create `btree_gist`] → It is a trusted extension (PG13+) and needs only CREATE on the database. Preflight checks it. If the check fails, a DBA runs `CREATE EXTENSION btree_gist` before deploy; the migration's `IF NOT EXISTS` then no-ops.
- [Production data violates a CHECK or the exclusion constraint] → Each migration runs in one transaction and rolls back on failure. The preflight runs the same validation queries on a production snapshot first.
- [A rare code path writes a status that the inventory missed] → That write now fails loudly instead of storing bad data. Mitigations: derive sets from code and data, run the full PostgreSQL suite, and review Laravel logs after deploy.
- [An expired-but-unswept `held` slot keeps blocking its interval] → Same semantics as the current application check, which also treats `held` as blocking regardless of `hold_expires_at`.
- [Gist index write cost on every slot write] → Negligible at about 500 rows. It grows with slot volume like any index.
- [RESTRICT breaks `ReconcileLegacyPartnersCommandTest`] → Adjust its setup to delete dependents explicitly, or to avoid creating financial rows.
- [Spec overlap with `retire-v1-booking`] → Both touch `reservations-and-pricing`, but different requirements. Archive this change first.

## Migration Plan

1. Preflight on each environment: no pending migrations; `btree_gist` creatable; the validation queries for overlap, slot/reservation status agreement, status outliers and dead-table emptiness all return clean.
2. Migrations in order:
   1. FK indexes, so later RESTRICT checks and validations are fast.
   2. RESTRICT financial cascades.
   3. Drop dead tables, before status checks so their columns are out of scope.
   4. Status CHECKs, one migration per group.
   5. `btree_gist`, then the exclusion constraint.
3. Deploy the code changes (Decisions 2 and 6) in the same release as the migrations. Decision 2's mapping must ship with the constraint so a store-level conflict surfaces as 409, not 500.
4. Before the CHECK migrations run on bt-server, a reviewed data fix rewrites `booking_slots.status = 'booked'` to `confirmed` (Implementation Decisions, D4).
5. Rollback: `php artisan migrate:rollback --step=N` reverses each migration. Rollback leaves the extension installed, because other objects may rely on it.

## Implementation Decisions

Recorded during `/openspec-apply-change`, following AGENTS.md (resolve from context, then decide).

**D1. No reschedule lineage on the exclusion constraint.**
- Found: a reschedule from 10–12 to 11–13 already returns 409 before any slot is written, because `PriceQuoteService::create` rejects intervals that overlap any held or confirmed reservation, including the original (`RescheduleService.php:61`, `:125`). The design premise that the application admits self-overlapping reschedules was wrong.
- Options: (A) plain exclusion on active slots; (B) lineage column plus relaxing the quote check so reschedules may overlap their original; (C) add the lineage column but leave it unused.
- Chosen: A. It changes no behavior and adds no column. B is a new product feature outside this change. C is dead schema.
- Effect: Decision 1 rewritten, the spec scenario became "Replacement held while its original stays protected", and tasks 6.1, 6.2 and 6.4 were reworded.

**D2. Provider-reported and free-text columns are exempt from CHECKs.**
- Found: `provider_events.status` stores the uppercased provider value, `payouts.status` and `provider_operations.status` store lowercased provider values, and the two `host_kycs` columns hold free text.
- Options: (A) exempt them, with the reason in the conformance test; (B) map provider values to an internal vocabulary in code.
- Chosen: A. B changes behavior and discards the raw provider value.
- Effect: 72 constrained columns instead of 77, and the `platform-readiness` spec lists the three exemption kinds.

**D3. Allowed-set rule.** As in Decision 5. Code vocabulary wins over seeder, test and e2e values that contradict it. Writers fixed:
- `MatchmakingSeeder`: participation `accepted` → `joined`.
- `TournamentSeeder`: tournament `ongoing`/`upcoming`/`completed` → `in_progress`/`open`/`finalized`, fixture `ongoing` → `live`.
- `frontend/e2e/p1-pricing-promotions-loyalty.spec.ts`: booking slot `booked` → `confirmed`.
- `AdditiveMigrationAndRollbackRehearsalTest`: a slot under a `pending_payment` booking is `held`.
- `ReconcileLegacyPartnersCommandTest`, `BackfillReviewedPartnerMappingsCommandTest`: partner status `pending_verification` → `pending`.
- `ReliabilityRealtimeAndSchedulerReadinessTest`: entitlement `confirmed` → `active`.

Test fixtures that broke the new invariants, fixed at the fixture:
- `AttendanceOperationsAndReadinessTest::test_check_in_validates_arrival_window_and_court_readiness` put overlapping confirmed slots of different reservations on one court. The expired-window case moved to the other court, and the test freezes time so back-to-back slots stay adjacent.
- `TenantMembershipCheckoutBenefitsTest` planted a legacy `memberships` row as a decoy. The table is gone, so the decoy went with it.
- The four `catch (QueryException)` sites fold the new constraint name into the existing `Str::contains` check, so no class grows past its architecture ceiling.

**D4. `booking_slots.status = 'booked'` on bt-server.** No code writes or reads `booked`; the v1 vocabulary for a paid slot is `confirmed`. bt-server holds 12 such rows, all under confirmed bookings, none mirrored to `reservation_slots`, with no unique-index collision and no overlap. They get the reviewed, atomic data fix `docs/operations/harden-db-integrity-booked-slots-fix.sql` before deploy; it aborts if any `booked` row sits under a booking that is not confirmed. The bt-server rows are ids 54, 63, 65, 93, 123, 125, 155, 157, 187, 192, 208, 238; to revert, set those ids back to `booked` after rolling back the CHECK migrations. The CHECK migration does not rewrite data.

**Noted, out of scope.** `BackfillReviewedPartnerMappingsCommand` writes `supplement_requested`, which no code reads. The shift cash sum reads `pos_payments` `completed` while some writers use `paid`. `match_posts` `full` does not block the court. `ReconcileLegacyPartnersCommand:72` filters bookings on `paid`/`pending`, which bookings never hold.

## Rollout Log

**bt-server, 2026-10-11.** Before applying: re-ran the preflight (no change from Context) and took a schema-only backup. A full data dump was not taken, because it would copy user data locally; the change is reversible without one. Steps, each run by the user:
1. `2026_10_07_000001_change_avatar_url_to_text_in_users_table` (batch 6).
2. `docs/operations/harden-db-integrity-booked-slots-fix.sql`: 12 rows `booked` → `confirmed` (D4). A second run rewrote 0.
3. `php artisan migrate --step --force`: the ten migrations of this change, one batch each, about 30 s of migration time (19 s of it for the FK indexes).

Audit after applying (read-only):
- `migrate:status`: nothing pending.
- FKs without a covering index: 0. All 186 indexes from the migration exist and are valid; no invalid index in the schema.
- CASCADE FKs into financial tables: 0. All 45 FKs from Decision 3 are `ON DELETE RESTRICT`.
- Status CHECKs: 72, each value list identical to its migration. No other CHECK constraints.
- `reservation_slots_no_overlap`: present, `EXCLUDE USING gist (court_id WITH =, tsrange(starts_at, ends_at, '[)') WITH &&) WHERE status IN ('held', 'confirmed')`. `btree_gist` 1.8 installed.
- Overlapping active slots: 0. Active slots under a terminal reservation: 0. `memberships`, `membership_plans`, `reserves`: dropped.

**Production.** Not applied. Tasks 1.1–1.3 and 8.4 still need the production preflight, the `btree_gist` privilege check and the post-deploy log review.
