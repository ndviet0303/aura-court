# Production Playwright UI acceptance — 2026-10-04

## Requested outcome

Run `npm run test:e2e:ui:prod`, select every project, and stop only after the full suite passes. The current inventory is 342 executions, including the domain projects, priority gates and browser/mobile smoke projects. A local passing result does not establish production acceptance.

## Decisions and evidence

- Use actual HTTP requests through the browser context's request client. Production serves compiled assets, so importing `/src/...` in browser callbacks is not available. A route shim would test replacement behavior; the request client exercises the deployed API, its cookies, CSRF validation and idempotency contracts.
- Use fresh synthetic actors and two run-owned test organizations. Reusing demo actors introduced unrelated data and demo mutation behavior. The production seeder verifies the database host/name and limits tenant reuse to organizations already marked with a production E2E run ID. Test fixture expiry and cleanup are tenant/run scoped.
- Read the application encryption key for the selected target. Local Laravel processes load `.env.testing`; using `.env` in their helpers produced invalid verification signatures and encrypted payment snapshots. Production helpers use the production key without writing its value to reports.
- Give the fixture subscription enough capacity for all 342 executions. The full inventory creates more than 50 courts and 10 venues. Dedicated quota tests still create and exercise their own small subscription limits.
- Generate unique arrangement names and coupon/source references for each invocation. Domain and priority projects exercise the same scenarios in one database; deterministic reused references violated uniqueness on the second execution.
- Assert that direct-owner refunds preserve the wallet balance measured at the start of each test. Another scenario intentionally seeds a legacy wallet with 500,000 VND. Assuming every subsequent test starts with zero incorrectly flags unchanged legacy money as newly credited funds.
- Keep `marketplace_payouts` disabled. The user explicitly approved enabling direct payments, POS/wallet, social/tournaments, split payments and resale for production acceptance. Activation is restricted to run-owned test organizations 73 and 74, as verified below.

- Resolve every fixture actor and trial plan from the run manifest. Trial activation had selected and updated the first global active plan; inventory scripts selected the first global cashier/user. Those selectors now use only the generated fixture identities and plan. The split expiry scenario calls the public single-group expiry service, preserving its partial-payment/refund assertions without sweeping unrelated production groups.
- Open the cashier shift through its real UI before inspecting close/reconciliation. Creating a shift through a separate HTTP test client after the POS view mounted does not update the view's local state. The scenario now awaits the actual opening response and active-shift indicator.
- The user authorized GitLab deployment and confirmed that pushing triggers the build. GitLab SSH and API authentication were verified through the operating-system keyring. No deployment token values were copied into reports.

- Inspect the actual GitLab project runner rather than infer its executor from its tag. Runner 53418129 (`macos`) is online and uses Docker through OrbStack on the host, so the configured glibc Playwright image is appropriate. Fresh CI jobs now initialize their own loopback PostgreSQL cluster and testing encryption key; scheduled browser jobs use the same bootstrap. Registry login can use GitLab's scoped CI registry credentials when optional deploy credentials are absent. Webhook URLs are no longer echoed into build logs.
- Partner applicant/admin switches now invalidate the server session before deleting cookies. A late response could otherwise recreate the previous actor session and redirect the login page during form entry. All eight partner domain/priority executions passed after this repair.

- Diagnose session resurrection from trace HTTP timing: the community moderation case logged out successfully, but a late prior request reintroduced the manager's session cookie. The shared logout helper now navigates away before invalidating the session, cancelling the old page's background requests. The case retains its authorization, audit and moderation assertions.
- Navigate after DOM readiness and use actual UI assertions for page readiness. A reconciliation failure waited for the browser load event until the whole 60-second test deadline despite a usable page. External resource completion does not establish application readiness; reconciliation still asserts the real heading/view and persistence after reload.
- Fresh CI container revealed that locked Symfony packages require PHP >=8.4.1, while Ubuntu's default PHP is 8.3. CI bootstrap and the optional runner image now install PHP 8.5 to match the production build/runtime.

## Verification completed so far

- First complete local run: 342 executed; 280 passed and 62 failed (9 assertion failures plus 53 execution errors); 0 skipped.
- Tenant-scoped expiry and existing benefit reservation regression: 6 passed, 67 assertions, using a separate disposable PostgreSQL database.
- Refund fixture repair: held reservation and slot are confirmed by verified receipt allocation before requesting the 200,000 VND refund; both domain/priority executions passed.
- Second full local run: 342 executed; 330 passed and 12 failed; 0 skipped. Changes made during a running suite require a new full run because existing workers retain the code loaded at startup.

- Third full local run: 342 executed; 341 passed and 1 failed; 0 skipped. The remaining failure was the out-of-band cashier-shift setup described above.
- Focused shift/isolation verification after the repair: 18 passed, 0 failed, including both domain and priority executions.
- Frontend unit gate: 75 files and 382 tests passed.
- Pre-push GitLab CI lint rejected an unquoted YAML script containing a colon. Echo script values are now quoted as YAML strings, preserving their original shell commands.

- Fourth full local run: 341 passed, 1 failed, 0 skipped. The remaining partner session race was repaired; all eight partner executions passed afterward.
- Fifth full local run: 340 passed, 2 failed, 0 skipped. These supplied the session-resurrection and load-event evidence described above. A targeted selection omitting the earlier partner draft steps was also run: 16 passed, 2 partner setup failures (required draft fields absent); this is not counted as full acceptance.

- Sixth full local run: 341 passed, 1 failed, 0 skipped (19.7 minutes). Venue-wide closure collided with earlier run-owned reservations in sibling courts. The closure scenario now clones its own run-owned venue/calendar and creates a dedicated court, preserving closure/restoration assertions and the backend conflict rule. Choosing a different fixed date or cancelling prior scenario bookings would retain order dependence.

- The isolated schedule-closure repair passed both domain and priority executions (2/2); frontend type checking passed again.
- The AMD64 CI preflight ran under CPU emulation on this ARM64 host. Its three-actor authorization scenario reached the final navigation but exhausted the 60-second deadline. Verify the unchanged test under native ARM64 Linux, matching the actual runner, rather than widen timeouts for emulation.
- Activate the authorized flags for the two dedicated production test organizations (73 and 74). The partner-provisioning case checks tenant management and then removes only its new synthetic organization; it does not require broad activation for existing business tenants. Preserve marketplace payouts as false.

- GitLab commit cb02f5d8 was pushed to main as authorized. Pipeline 2911026861 completed successfully: 382 frontend unit tests, frontend build, 28 P0 browser executions with zero failures/skips/flaky cases, and backend image build/publication. Portainer deployment callback returned successfully in the pipeline.
- The UI viewer automatically runs global setup when a new browser connects. Repeated diagnostic viewers triggered fixture reseeding and slow artifact cleanup before listing. Keep one visible controlled browser connected during the full production run.

- Production frontend index matches the successful pipeline build (index-Db5w5kC8.js); the unchanged hash observed after pushing was not evidence of a stale frontend.
- The authorized Portainer callbacks returned HTTP 204, but authenticated production capability reads still show five flags disabled for test organization 73. The deployed stack has not consumed the new flag query parameters. Supply explicit runtime defaults in the server image from the deployment pipeline, restricted to organizations 73 and 74; Portainer remains able to override them and payouts remain false. A public non-secret build revision file will distinguish an old running image from a configuration override. Changing feature-service defaults or bypassing capability middleware would alter application behavior beyond deployment configuration.

- Deployment configuration commit 9d49e9f12ebac2ac34dff6e51f794dbdd2388f50 passed pipeline 2911069643 (frontend validation, all 28 P0 cases, build/deploy). Production /build-info.json confirms that exact revision. An authenticated capability read for organization 73 confirms all five approved flags true and marketplace payouts false. The full 342-execution production UI run has started with all projects selected.

- The first production UI attempt was stopped after 59/342 executions (53 passed, 5 failed, 1 skipped) to repair a real route-target defect. A trace showed `/courts/243/schedule` posting `court_id: 1`: the cached public list omitted the new test court, and the schedule silently chose the first court. Its legacy-wallet rejection was correct for that unintended court.
- Resolve explicit schedule routes through the existing uncached court-detail endpoint, merge the resolved court into the selector list, and load availability only for that court. A missing/ineligible target displays an error and clears prior slots/selection. Remove the view's duplicate mount initialization, which could overwrite the resolved list. Considered cache eviction and test interception; rejected because neither corrects the real fallback behavior.
- Schedule regression verification: 5/5 unit tests passed, including cached-list omission and unavailable target with stale selection; frontend type checking passed. The production attempt above is not full acceptance.

- Schedule deployment pipeline 2911112744 passed frontend validation (384 unit tests) but its P0 gate stopped at 27/28. Trace timing proves the delayed-response scenario installed its interception before court initialization: both date clicks happened before `/courts/18` resolved, so the only request was initialization for date2 and was incorrectly held as date1. Wait for initial real slots and the first intercepted date request before issuing date2; retain all late-response and sentinel-price assertions.

- The delayed-response scenario passed both domain and priority executions locally after the readiness repair (2/2).

- Commit acf36d0743f83714d09faa5eac9fb691c66e04c8 passed pipeline 2911124764: frontend validation, all 28 P0 executions (zero failed/skipped/flaky) and image deployment. Production `/build-info.json` confirms the exact revision; the frontend bundle `index-BTIQO1p_.js` contains the explicit schedule-target repair. A fresh all-projects production UI run is retained under `/private/tmp/booking-prod-acceptance-v3`.

- The second all-projects production UI attempt was stopped after 69/342 executions: 66 passed and 3 failed. It is not acceptance. All five booking/payment scenarios that failed in the earlier attempt passed after explicit route resolution.
- Production owner webhooks acknowledge receipt ingestion before the queue allocates payment. Fresh diagnostic attempts produced allocated receipts roughly three seconds after acknowledgement, after the tests had read pending state or rendered a pending booking card. Wait for the real receipt's terminal processing state, scoped to the run-owned connection/event; do not force status or change production queue behavior. Preserve the same provider event, transaction date and signature timestamp on the idempotent redelivery test.
- The direct-cohort deposit API correctly returned 409 for test organization 73, but the generic wallet UI posted without organization context and created a pending deposit in the default legacy organization. The existing wallet read endpoint supports an organization query and returns its authoritative ID. Carry that selection through the wallet route/read and deposit payload, and make the cohort test select the same wallet. Do not activate features for existing legacy tenants. Wallet regression verification: 6/6 unit tests passed.

- Fresh production payment/POS verification after terminal-receipt waiting: 4/6 passed. Idempotent checkout/webhook/reschedule and cash POS passed. Reschedule test waited on a refresh button that was removed when automatic polling observed payment; await the authoritative paid indicator instead. POS QR correctly rejected the local testing HMAC with HTTP 401. Production uses its configured legacy provider key; no credential or authentication override has been applied.
- Automatic approval review rejected using the production HMAC to sign POS test callbacks because that credential use needs specific authorization. The concrete proposed scope is fresh POS orders only: reference returned by the test checkout, organization 73/74, cashier among this run's generated actors, and creation after the run manifest timestamp. No existing financial record replay or real transfer is required. Continue independent repairs while this authorization is pending.

- The complete three-part production reschedule scenario passed on fresh fixtures after awaiting the paid indicator (1/1, 1.4 minutes). Remove the same refresh-button race from the initial payment/security journeys because terminal-receipt waiting can let automatic UI polling remove that button before a click.

- Keep the legacy-balance preservation fixture and wallet navigation in its explicit run-owned organization as well; do not seed its 500,000 VND historical balance into the production default tenant.

- Production payment/wallet selection after the paid-indicator and issuer fixes: 14/15 passed. The remaining reschedule failure omitted its new expensive court from the cached public list. Trace responses for both `/courts` requests were identical and excluded the newly arranged court, while uncached court detail was correct.
- Warm the actual public court catalog before arranging the two reschedule fixtures, then publish only those new run-owned courts through the authenticated owner update endpoint. This invokes the deployed cache invalidation before the customer journey and verifies both IDs appear in the real catalog. Considered waiting for the five-minute TTL or accessing shared Redis; chose the existing scoped owner API to keep fixture setup correct without broad eviction or extra production credentials.
- Commit 464538442cd31ff137d65e3c2739164aaa4e7fc2 passed pipeline 2911208210, including 28/28 P0 cases and deployment; the public runtime revision matches.

- A focused verification omitted the tenant selection and seeded fresh test organizations outside the enabled pilot scope; four checks failed and do not count as acceptance. Persist `E2E_PROD_ORGANIZATION_IDS=73,74` in the ignored local backend configuration so the requested npm production command selects the authorized test tenants consistently.

- The scoped owner update still returned a stale real catalog. Read-only inspection found production catalog entries under `laravel-cache-courts:v1:*` in the database cache, with no `courts_version` counter. Laravel database cache increments return false for a missing counter. Initialize the counter atomically when building the catalog, before any subsequent mutation increments it. Add a regression that warms the database-backed catalog, updates one court through the real API, and verifies its new name in the list. The production reschedule scenario retains its cache warm-up and scoped publishing checks.

- Database catalog regression: 1/1 passed, 5 assertions, on a new disposable PostgreSQL database. Removing counter initialization reproduces the stale-name failure; source was restored. Frontend type checking passed.

## Completion evidence still required

A fresh full production run with all projects selected, 342 passing executions, zero failures/skips/flaky results, and its retained report. Keep the production UI open for review.
