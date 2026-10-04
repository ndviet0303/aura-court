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

## Completion evidence still required

A fresh full production run with all projects selected, 342 passing executions, zero failures/skips/flaky results, and its retained report. Keep the production UI open for review.
