## Context

See `proposal.md` for motivation. This is a cross-cutting test-infrastructure and acceptance change, so a design is required; it does not change business contracts.

Static baseline inspected on 2026-09-28 (not a new executed test result):

- `frontend/e2e/` contains 12 tests in three files. The current configuration starts only Vite, uses Chromium, one worker and retained failure traces.
- Old owner UAT expects `/admin/organization` as the owner landing page and denies `/admin/manage` and `/admin/venues`; the current router lands owners on `/admin/manage` and allows scoped operational routes.
- Tests embed demo credentials, assume named courts/time slots and a 500-point balance. `SimulateDemoMutations` rolls back non-read requests for demo users.
- The client can select an API origin from build-time environment or `/runtime-config.json`; Vite also proxies API requests. A frontend URL alone does not prove backend isolation.
- `backend/tests/TestCase.php` has a database safety check and a multi-organization fixture pattern. These do not automatically protect an independently running HTTP server. `phpunit.xml` uses array sessions/cache, unsuitable as a copied configuration for multi-request browser journeys.
- `.gitlab-ci.yml` combines frontend checks with image build/push/deployment. No browser gate exists; its current Docker/Alpine assumptions and `macos` runner tag require executor inspection before selecting a supported browser runtime.
- Main specs supersede old wallet-only/no-cancellation deltas. The archived `align-report-and-direct-owner-payments/report-traceability.md` supplies the 126 FR inventory, not current browser execution evidence.

## Goals / Non-Goals

**Goals:** a reproducible local/CI command, trustworthy browser-to-database outcomes, explicit requirement coverage and a small maintainable test suite. Establish P0 first, then its CI gate, followed by P1/P2.

**Non-Goals:** a second test framework, broad Page Object hierarchy, one test per endpoint, an exhaustive browser × locale × theme Cartesian product, load-test replacement, real bank transfers, production rollout, or silently implementing missing business interfaces. Semantic labels/test IDs needed by existing controls are allowed; product fixes require a separate scoped change.

## Decisions

### 1. One existing suite, with priority and evidence tags

Extend `frontend/e2e` and the installed Playwright version. Use domain-focused spec files and small shared fixtures for identity, run data and provider-event delivery; extract only genuinely repeated actions. Keep one worker initially. Tag P0/P1/P2 and synthetic-error/provider boundaries so reports and CI select explicit sets; never select zero tests successfully.

Use role/label locators, stable test IDs only where necessary, and condition-based assertions. Keep successful critical flows unmocked between browser and backend. API/CLI helpers may arrange prerequisites and verify outcomes, but must not perform the user action being claimed as browser coverage. Keep one explicit browser login/logout journey; reuse fresh authenticated storage state elsewhere. Authentication state remains run-local and ignored by Git/artifact uploads.

Alternative rejected: generate 126 shallow page-opening tests or introduce a generic Page Object framework. Neither proves the requirements, and both increase maintenance.

### 2. Own the whole test environment before mutating anything

Provide a small runner using existing runtimes to create a unique local run directory, dedicated PostgreSQL database such as `booking_e2e_<runid>_test`, restricted database credentials, private storage/cache/session namespace and owned backend/frontend processes. Use persistent test sessions across HTTP requests, `APP_ENV=testing`, synchronous queue execution and a local mail sink. Do not copy PHPUnit's array-session setting into the HTTP environment. No default database/demo seeder is run against an existing environment.

Preflight checks resolve the actual active database connection, connection URL overrides, cached configuration, API origin and server ownership before migration/seed or business mutation. Reject remote/non-test targets and unknown pre-existing processes; do not reuse a random running server in CI. Backend and runner share a non-public run-identity check (test-only boot/CLI metadata), without adding a production reset endpoint. Guard cleanup with the explicit run manifest and database ownership, not merely an `_test` name suffix. Never read production environment files into the runner or reuse `booking_test` while PHPUnit may own it.

Launch the frontend with an explicit isolated API destination and check runtime configuration cannot override it. Restrict provider/SMTP/OAuth egress in the test process. Teardown terminates only owned processes and removes only owned ephemeral resources, including on failure. A supported provisioned CI database is acceptable only if it meets the same isolation contract.

Alternative rejected: reuse demo accounts or a developer's running API/database. Browser context isolation does not isolate server data, and demo rollback creates false persistence evidence.

### 3. Small deterministic fixtures with real eligibility

Use existing factories/domain services and the multi-organization fixture pattern, but satisfy current partner approval, subscription entitlement, venue publication, operating calendar, price/policy and receiving-connection requirements. Do not weaken those requirements to make fixture creation easy.

Provide two organizations, one with two sibling venues; customer/owner/manager/cashier/staff/platform-admin actors, unverified/restricted customers and a foreign owner. Identities are non-demo and scoped; credentials are generated per run. The manifest contains generated IDs instead of hardcoded organization/court names. Use a per-test namespace and reset/arrange each scenario independently; tests neither consume nor depend on previous tests' bookings, stock or points. Authentication-mutating tests use dedicated accounts. Future parallelism requires separate worker data/accounts first.

Derive a shared future play date at run start in `Asia/Ho_Chi_Minh`; make seeded calendars and prices valid on it. Expiry cases arrange timestamps through test-only CLI support and invoke the real expiration command, then assert browser state. Do not wait real minutes or freeze only the browser while the server clock advances. Include a controlled timezone-boundary case.

Alternative rejected: hardcode current-day 19:00 slots or globally freeze production time. Both break determinism or broaden the product surface.

### 4. Real application payments, synthetic external evidence

Seed synthetic verified receiving connections with generated test-only secrets. Deliver authenticated synthetic events through the real isolated webhook boundary and run the actual receipt processor. Test exact amount/receiver/reference, duplicate delivery, incorrect/late events, partial benefits and state reload. Never set booking/receipt status directly to bypass the operation under test, and never treat a frontend success callback as money received.

Subscription fixtures have a separate platform receiver. Refund journeys distinguish obligation creation, owner-reported evidence and recipient acknowledgement/verification. Preserve direct and legacy payment-model identities; only test legacy spend where its current cohort contract explicitly allows it. New pooled top-ups in direct cohorts must be unavailable rather than asserted successful.

Email verification/reset uses locally captured messages or generated test-only signed links; OAuth uses a controlled provider adapter/callback contract, not live provider credentials. These tests carry synthetic-external labels. Real provider sandbox evidence remains a separate operational gate; lack of credentials is not a reason to mark it passed or block unrelated local journeys.

Alternative rejected: mock a paid API response or enable automatic bank payouts. That bypasses the contract the browser suite needs to prove.

### 5. Coverage inventory and prioritized journeys

During implementation create `docs/testing/playwright-coverage.md` with one row per existing FR, retaining exact identifiers/names. Columns: FR, main spec, UI/API surface, actor/tenant/venue scope, priority, browser test and assertion/step, backend test, external-evidence class, status, revision/environment and report link. Statuses are planned/not-run, passed, failed, flaky, skipped, blocked and API-only; API-only is a disposition, not browser passed. Preserve historical reports rather than rewriting their results. Link existing D01–D71 dispositions where relevant without opening a fresh security audit.

The table below is the planned routing of all 126 FR, not executed coverage. Range mappings must be expanded into individual rows; a journey can cover several rows only with a concrete assertion for each.

| Inventory | Count | Main journeys / priority |
|---|---:|---|
| FR-ACC-01–07 | 7 | Auth/profile, verification/reset/OAuth; P0 login/session/eligibility, P1 remaining lifecycle |
| FR-ACC-08–12 | 5 | Partner draft/documents/correction/review/provisioning; P1 |
| FR-ACC-13–16 | 4 | Plan catalog/trial/payment/renewal/expiry/quota; P1 |
| FR-ACC-17–18 | 2 | Staff invitations/grants/revocation; P1, denial matrix P0 |
| FR-VEN-01–14 | 14 | Draft/catalog/calendar/receiver/readiness/publication; P1, availability and scoping P0 |
| FR-PRM-01–12 | 12 | Price/coupon/campaign/membership/loyalty management P1; quote and final totals P0 |
| FR-BKG-01–14 | 14 | Discovery/slots/conflict/hold/payment/history/change/cancel; P0 |
| FR-BKG-15–18 | 4 | Recurrence preview/conflicts/series/periods; P1 |
| FR-BKG-19–21 | 3 | Listing/buyer acceptance/holder transfer; P1 |
| FR-PAY-01–06 | 6 | Payment receipt/status/replay/exceptions; P0 |
| FR-PAY-07–08 | 2 | Split contributions/expiry/host coverage; P1 |
| FR-PAY-09–11 | 3 | Refund quote/request/execution acknowledgement; P0 |
| FR-PAY-12–13 | 2 | Platform receipt and entitlement activation; P1 |
| FR-PAY-14–20 | 7 | Revenue separation/reconciliation/exceptions/reports/CSV; P1 |
| FR-OPS-01–08 | 8 | Schedule/lookup/check-in/no-show/use/check-out; core P0, boundary variants P1 |
| FR-OPS-09–17 | 9 | Product/stock/shift/order/payment/receipt/adjustment/close; core P0, variants P1 |
| FR-COM-01–08 | 8 | Profile/activity/invitation/registration/roster/attendance; P1 |
| FR-COM-09–16 | 8 | Tournament publication/admission/bracket/scores/results/reputation; P1 |
| FR-COM-17–24 | 8 | Report/evidence/moderation/decision/remedy/reconsideration/closure; P1 |

Track additional rows without inventing report FR numbers: waitlist and release notification; friends/feed; service-tab stock/settlement; owner-hosted drop-in bill; legacy-wallet history; notification read/unread; venue directions; public/legal/404 navigation; localization/theme/accessibility; realtime.

P0 independently runnable journeys:

1. Login/return-path/session reload/logout plus unverified/restricted account rejection.
2. Owner/manager/cashier/staff/customer/platform access, foreign tenant and sibling-venue denial, including relevant direct requests.
3. Court/date selection and slot status; court A occupied does not block court B.
4. Ordinary booking → pending hold/receiver QR → verified test receipt → confirmed history after reload.
5. Two separate customer browser contexts compete for one slot; exactly one hold wins (backend tests retain high-load race proofs).
6. Expiry releases the slot; late receipt does not restore it and keeps the required obligation/exception.
7. Coupon/member/points preview and persisted final benefit; stale/invalid selection or conflict does not consume twice; cover supported shared entry points without duplicating the entire checkout per page.
8. Cancellation/partial cancellation and reschedule (extra payment timeout preserves original; lower price creates bounded refund obligation).
9. Cashier open shift → cash or pending/verified QR sale → inventory/receipt → close/review; retry retains one order and reconciled totals.
10. Staff lookup/check-in → service-tab items → settlement/check-out; invalid booking/stock/duplicate actions remain rejected.

P1 expands each inventory row with happy/reload and relevant negative cases: partner correction, subscription quotas, venue publication/closure, staff grants, promotion eligibility, recurrence all-or-nothing/partial acceptance, split incomplete expiry, transfer eligibility/competing buyers, inventory adjustments/last item/POS refunds, community/rosters/moderation, tournament capacity/score finality, disputes and authoritative CSV data. Drop-in bills remain informational and distinct from advance split payment; exclude auxiliary purchases according to the existing spec.

P2 samples representative forms and money journeys on Vietnamese/English, mobile and themes; covers keyboard focus, errors, capability toggles and cross-session availability/tournament updates. Use a real isolated realtime process when that transport is claimed; separately verify the existing polling fallback, without claiming an injected DOM update as realtime coverage.

Alternative rejected: regard backend test names or 126 report rows as 126 browser tests already passed. UI gaps remain documented and the affected acceptance task stays open until a separately authorized fix or explicit scope disposition.

### 6. Gates, diagnostics and rollout of the suite

Local baseline and P0 run on Chromium, Vietnamese, venue timezone, one worker, no automatic retry. Reports include HTML/JUnit, failure screenshot and retained trace. CI may rerun only for diagnostics; a retry-only success remains flaky and fails required P0. Do not use optional assertions, broad skips or `allow_failure` to turn missing prerequisites into green jobs.

Split E2E validation from deploy-capable jobs. Add required P0 execution on merge requests and deployable branch pipelines and make build/push/deploy depend on it. Scheduled/manual regression must not trigger deployment; inspect and adjust existing workflow/job rules accordingly. Add separate P1/P2 regression and Firefox/WebKit/mobile smoke selections. Initially run a small critical cross-browser subset, not the whole matrix on every MR. Gate selection has an expected nonzero test inventory and disallows critical skips.

Use the actual runner executor to choose a supported runtime: native macOS with installed browsers if shell-based, or a matching Playwright-supported image in a compatible container runner. Do not assume Docker job images run under a shell executor, and do not install browsers into the existing Alpine deployment job. Runner capability/registration changes needing outside authority become explicit operational prerequisites, not claimed completed work.

Upload diagnostics even on failure, restricted to project members with an initial seven-day retention. Exclude auth state, generated secrets and raw provider credentials; use synthetic user/payment data. Produce environment metadata (revision, backend/frontend build, database run ID without credentials, browser/version, locale/timezone, flags/cohort and test data version). Record measured duration rather than promise a runtime before baseline measurement.

Exit criteria: selected P0 cases pass independently and in three consecutive clean full runs without retries; all 126 FR and supplementary rows have truthful dispositions; every implemented UI journey has linked evidence; no unresolved P0 gap; CI nonzero/skip/failure behavior is verified. P1/P2 gaps remain visible and do not count as full regression completion. Product defects do not authorize unrelated fixes or weakening a gate.

Alternative rejected: add E2E inside the existing deployment script or count retried tests as healthy. A standalone validation gate prevents accidental deploys on scheduled regression and exposes instability.

## Risks / Trade-offs

- [A suffix-only database check approves a shared database] → Require owned run identity, explicit target resolution and dedicated credentials before any mutation/cleanup; add runnable refusal checks.
- [A product bug prevents P0 completion] → Preserve the failing reproduction, record the spec/code evidence and separate repair scope; continue independent suites but do not claim acceptance complete.
- [Synthetic payments are mistaken for bank certification] → Label evidence at test and report level; retain existing external provider gates.
- [UI or capabilities are absent] → Check current main specs/routes/callers first; record API-only/blocked disposition, never weaken auth or flags globally.
- [Session/clock/cache differences cause flaky tests] → Persistent isolated sessions, run-local storage, shared date assumptions and server-driven expiry fixtures.
- [Single worker increases duration] → Measure first; parallelize only after isolating mutable accounts and inventory per worker.
- [Trace files contain sensitive state] → Generated test identities, no real credentials, restricted retention and no auth-state artifacts.
- [Broad coverage becomes an abstraction project] → Domain spec files and a few fixture helpers; leave deep money/locking/load permutations to existing backend suites.

## Migration Plan

1. Establish current baseline and individual FR mapping without marking old reports as current passes.
2. Implement owned environment/fixtures/safety checks; replace stale baseline expectations and retain useful assertions.
3. Complete P0 locally; establish mandatory CI gating before expanding to P1 and P2.
4. Add full regression entry points and secondary-browser smoke, document actual results and outstanding external gates.
5. No application data migration or production activation is part of this change. Rollback removes only new test job/script wiring and run-owned ephemeral resources; preserve reports and existing tests. Disabling a failing release gate requires an explicit reviewed decision and never silently restores deployment eligibility.

## References

- Main source of business truth: current `openspec/specs/`, not superseded wallet-only delta text.
- FR inventory: `openspec/changes/archive/2026-09-27-align-report-and-direct-owner-payments/report-traceability.md`.
- Playwright documentation retrieved through Context7 during exploration: [authentication and isolation](https://github.com/microsoft/playwright/blob/main/docs/src/auth.md), [best practices](https://github.com/microsoft/playwright/blob/main/docs/src/best-practices-js.md). Verify version-specific options against the installed package during implementation.
