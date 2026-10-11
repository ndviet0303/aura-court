## Context

See `proposal.md` (Why) for motivation. This section covers the current state and the constraints that shape the approach.

**Pipeline.** GitLab runs a Docker executor on a `macos`-tagged runner, with two stages: `validate` → `build`.

- `frontend_check` (`node:22-alpine`) runs `type-check`, Vitest and `build`. `build` runs `type-check` a second time through `run-p`.
- `build_and_deploy` `needs` only `frontend_check`.
- No backend job has ever existed.
- The production API image is built from `php:8.5-alpine` with `composer install --ignore-platform-reqs` and a `.env` copied from `.env.example`. A static backend job can mirror that build exactly, with no database.

**Tooling already installed.**

- Pint 1.27: no `pint.json`, so the Laravel preset applies.
- ESLint 10.7, configured with:
  - `eslint-plugin-vue` 10.9 (`flat/essential`)
  - `typescript-eslint` recommended
  - oxlint 1.73 bridged through `eslint-plugin-oxlint` (`correctness` = error)
- Prettier 3.9.5. The `format` script only covers `src/`.
- `vue-tsc --build` over the app, node and vitest projects. `worker.ts` is in none of them.
- Vitest 4.1 (jsdom).

**Architecture checks.** Each stack has two scoped checkers that pass while repo-wide debt is large:

- Backend PHPUnit test (`tests/Unit/Architecture`):
  - Covers only Partner/Subscription controllers and Partner/Subscription/Venue actions and DTOs.
  - Model-query detection uses a hard-coded list of 8 models.
- Backend PHP script: its docblock promises checks it does not implement.
- Frontend script and Vitest spec (`src/features/__tests__/architecture.spec.ts`): both cover only 2 features, with a 300-line component limit. The spec runs in `test:unit`, so it gates CI today.

**Debt measured on 2026-10-11:**

| Check | Today | After this change |
|---|---|---|
| Pint | 293 / 767 files | 0 (format commit) |
| Prettier | ~500 files (452 `src`, 48 `e2e` + config) | 0 (format commit) |
| vue-tsc | 0 | 0, now also covering `worker.ts` |
| ESLint | 598 (508 `no-explicit-any`) in 169 files | 507 `any` suppressed in 112 files, rest fixed |
| oxlint | 394 (327 `vitest/require-mock-type-parameters`) | 0 (rule off, rest fixed) |
| Larastan level 0 | 18 (17 with casts parsing) | 0, never baselined |
| Larastan level 5 | 1,343 (1,057 with casts parsing) | 1,021 in baseline (769 entries) |
| Backend architecture, repo-wide (after Pint) | 31 classes > 300 lines; 15 controllers > 7 public methods; 29 controllers with inline validation; 31/49 controllers and 17 domain files query models (by a broader grep, see §6) | 121 allowlist entries with ceilings |
| Frontend architecture, repo-wide (after Prettier) | 36/52 views > 250 lines; 2 view scripts > 200 lines; 101/271 components > 400 lines; 21 `.vue` files import legacy stores; 6 import the raw API client; 5 legacy stores; 17 eager routes | 172 allowlist entries with ceilings |
| Locale parity | 3,638 keys per locale, 0 missing | enforced by unit test |

**Larastan reads casts only from the `casts()` return type by default.** The 96 models here declare `casts(): array` without an array shape, so `datetime` and `array` casts are invisible to it. Probes showed `Tournament::$settings` as `string|null` and `SplitGroup::$expires_at` as `string`. With `parseModelCastsMethod: true`:

- level 5 drops by 286 findings;
- the level-0 `unset.offset` finding in `TournamentService` disappears.

**Known drift, not addressed here.** The unarchived `add-playwright-functional-coverage` delta requires a critical browser gate in the pipeline. Commit `e8fd401` removed that gate.

## Goals / Non-Goals

**Goals:**
- Every static check blocks merge and deploy from this change on. Existing debt is counted, visible and can only shrink.
- Each concern has one checker per stack, run by the same command locally and in CI.
- No user-visible behavior change, except the QR modal layout fix and the crash fixes.

**Non-Goals:**
- New architecture rules. This change only widens the scope of the documented rules and aligns the component limit to 400.
- PHPStan analysis of `tests/`, `database/`, `routes/` and `config/`. Only `app/` for now.
- Type-aware ESLint rules and pre-commit hooks.
- Automatically lowering allowlist ceilings when a file shrinks but still violates its rule.

## Decisions

### 1. Ratchet with baselines, not fix-first or warn-only
New violations fail immediately. Existing ones live in versioned baselines that fail when stale:

| Tool | Baseline file | Behavior |
|---|---|---|
| ESLint | `eslint-suppressions.json` | Counts per file per rule. Unused suppressions are errors by default in ESLint 10. |
| PHPStan | `phpstan-baseline.neon` | Counts per message per file. `reportUnmatchedIgnoredErrors` defaults to true. |
| Architecture checks | Allowlist, see §6 | Own implementation. |

Alternatives considered:
- **Fix all debt before gating:** weeks of work that block other changes.
- **Warnings only:** this is today's situation, and the code drifted under it.

### 2. One-shot formatting in two mechanical commits
- The Pint commit (backend) and the Prettier commit (frontend) contain only formatter output.
- Both SHAs go into `.git-blame-ignore-revs`.
- They land back-to-back, right before the baselines. Open branches rebase once and re-run the formatter instead of resolving formatting conflicts by hand.
- `V2ReservationQrModal.vue` must be fixed first, because Prettier cannot parse it.
- Prettier scope is `src/`, `e2e/` and the root config files. `format` and the new `format:check` target the same paths.
- Both scripts pass `--no-cache`. With its cache on, the experimental CLI skipped the second pass that `ArbitrationDrawer.vue` needs, so `format` never converged.

**What applying Prettier to the templates turned up:**

- **Multi-statement inline handlers.** With `semi: false`, Prettier puts each statement on its own line without `;`, and the Vue compiler then fails to parse the handler. The 8 affected handlers moved into functions in a commit before the format commit, so the format commit stays formatter output only.
- **Edge whitespace in text.** Condense mode keeps a single space where Prettier wraps text onto its own line. 119 text nodes gained a leading or trailing space.
  - Every one sits at the start of a first child or the end of a last child of a `p`, `li`, `div`, `h1`/`h3`/`h4` (block) or `button` (inline-block). CSS drops such spaces at line edges.
  - No rule gives those elements `white-space: pre*`, `display: inline`/`contents`, or `::before`/`::after` content, which would make the spaces visible.
- **Verification, used in place of a manual smoke test.** It covers every screen instead of a sample:
  - template ASTs compared before and after: only those edge spaces differ;
  - script, TS and JSON ASTs compared: the only differences are equivalent, either `;` ASI guards or regrouped `&&`/`||`/`??` chains;
  - production builds compared after normalizing content hashes and scope ids: CSS is identical, and 22 JS chunks differ only in whitespace inside strings.

Alternative considered: format-on-touch. It never converges, and every diff mixes formatting with logic.

### 3. Larastan level 5 with casts parsing, plus a level-0 run without baseline
**`phpstan-level0.neon`** holds the shared settings at level 0:
- Larastan and Carbon extensions;
- `paths: [app/]`;
- `parseModelCastsMethod: true`;
- `reportIgnoresWithoutComments: true`, so every inline ignore must state a reason.

**`phpstan.neon`** includes `phpstan-level0.neon` and `phpstan-baseline.neon`, and raises the level to 5. Developers get level 5 by default from `vendor/bin/phpstan`. CI runs both configs.

Why each choice:
- **Level 5.** Levels 1–5 add real defect classes: argument types, nullsafe misuse, unknown methods and properties. Level 6 adds about 1,100 missing iterable and generic annotations. That is annotation work, not bugs, and is left to later changes.
- **Casts parsing.** Without it the baseline would carry 286 false positives and teach people to ignore the analyzer.
- **Level-0 run without baseline.** A regenerated baseline covering about 1,000 errors can silently absorb a crash. A level-0 run with no baseline cannot. It costs about 10 s.

Alternatives considered:
- **Grep the baseline for crash identifiers.** The list would be hand-maintained, and identifiers such as `class.notFound` are also emitted for PHPDoc at higher levels.
- **Rely on review only.**

### 4. Resolution of the 17 level-0 findings

| Findings | Decision |
|---|---|
| `AdvancedBookingController` (3), `OrdinaryCheckoutService` (1), `SplitPaymentService` (1) | Fix the imports. These are real fatals when the branch runs. |
| `SnapshotSettlement`, `OpenShift`, `CloseShift`, `JoinMatch` (5) | Delete. They type-hint models that do not exist and have no references. |
| `env()` in `E2EArrangeTestDataCommand`, `E2EExpireHoldsCommand`, `E2EInspectTargetCommand` (4) | Read the same values through `config()` (`database.connections.<default>.host` / `.url`). The guards then behave the same with and without config caching. `DATABASE_URL` is dropped, because Laravel's database config never reads it. |
| `rules.modelAppends` on `SplitGroup`, `SplitShare` (2) | Inline ignore with reason. The helper methods `coveragePaymentAttempt()` / `paymentAttempt()` have the name Larastan expects for a new-style accessor. That hides the classic `get…Attribute` accessor, which Laravel resolves at runtime. |
| `larastan.console.undefinedOption` in `E2ESeedFixturesCommand` (1) | Inline ignore with reason. The only subclass, `e2e:seed-production-fixtures`, overrides `handle()`, so this option read never runs for it. |

Follow-up flagged, not changed here: in two E2E commands the host allowlist contains the configured database host itself, so the "refusing remote target" check never refuses the configured target. The other guards still apply:
- `APP_ENV` must be `testing` or `local`;
- for normal runs, the database name must be the run's own;
- for `prod_e2e_` runs, the organization must be owned by the run.

Tightening the host check is an E2E safety decision outside this change.

### 5. Lint debt: fix everything except `any`

**ESLint.**
- Fix now: `no-unused-vars`, `prefer-const`, `no-empty-object-type`, deprecated slot syntax, the template parse error and prop mutation.
- Record the remaining `@typescript-eslint/no-explicit-any` with `eslint . --suppress-rule @typescript-eslint/no-explicit-any`. That is 507: one of the 508 went when a test callback got a type during the lint fixes. This suppresses only that rule, so any other leftover still fails.
- CI never passes `--pass-on-unpruned-suppressions`.
- `frontend/.prettierignore` lists `eslint-suppressions.json`. ESLint rewrites the file on every prune, without a final newline, and `format:check` covers the root `*.json` files.

**oxlint.**
- Turn off `vitest/require-mock-type-parameters`. Its 327 findings are type parameters on test mocks, which is churn with no defect value.
- Fix the remaining 67 findings. oxlint then needs no baseline.

**Prop mutation (23 findings in 3 components).** The parent stays the single writer.
- Each component declares one `defineModel` per field it edits, and the parent binds `v-model:<field>`.
- An object-valued `defineModel` with nested writes is not acceptable: it is the same mutation with the warning hidden.
- `defineModel` is new to this codebase, but it is Vue 3.5's built-in mechanism for this.
- Save and cancel behavior stays unchanged.

Alternatives considered:
- **Suppress all current ESLint errors:** keeps real bugs such as prop mutation.
- **Type the 508 `any` now:** large manual typing work with no type source, at high risk.

### 6. Architecture checks: one checker per stack, count-based rules with ceilings
**Where they live.**
- Backend: `tests/Unit/Architecture` stays a plain PHPUnit test with no app boot. The model-query rule covers every class in `app/Models` instead of the hard-coded list.
- Frontend: a Vitest spec under `src/__tests__/` that reads files with `node:fs`. It runs inside the existing `test:unit` step.

**How a rule is measured.**
- Each rule yields one number per file: lines, script lines, public methods or occurrences.
- A file violates the rule when its number exceeds the rule's limit.
- Frontend definitions (files under `__tests__/` are skipped):
  - A view is a `.vue` file under a `views/` directory; every other `.vue` file is a component.
  - Lines are editor lines; a trailing newline does not add one. Script lines are the lines inside all `<script>` blocks.
  - Raw API and legacy store imports are import specifiers, `@/` or relative, that resolve to `core/api/client`, `lib/api` (its re-export), or under `stores/`.
  - Each file in `src/stores/` counts 1 against a limit of 0, so the existing stores are allowlisted and a new one fails.
  - A router file counts each `component:` bound to a statically imported `.vue` file as an eager route.
- Backend definitions (paths are relative to `app/`):
  - Lines are editor lines of every PHP file, so the 300-line limit covers services, models and commands too. The old scoped test counted a trailing newline as an extra line.
  - Controller rules apply under `Http/Controllers/`, action rules under `Domain/*/Actions/`, and the DTO rule under `Domain/*/DTOs/`.
  - Public methods are those a controller class declares itself, without magic methods. Methods from a trait count toward the class that uses it.
  - Inline validation counts `$request->validate(`, and storage access counts `Storage::`.
  - A direct model query is `<Model>::query|where|find|findOrFail|create|update|first|get|all|paginate` for every class in `Models/`. This is the old test's method list, so calls such as `with()`, `whereIn()` or `firstOrCreate()` are not counted. The proposal's grep counted them, which is why it found 31 controllers and 17 domain files instead of 30 and 16.
  - The request dependency counts an `Illuminate\Http\Request` import, plus each `execute()` parameter typed as `Request` or a subclass such as a FormRequest.
  - A DTO that is not a `readonly class` counts 1.
  - Two checks from the old test stay, each with a limit of 0. A file under `Http/Requests/` extends `FormRequest` and declares `rules()` and `authorize()`. Each interface under `Contracts/Repositories/` is bound in `RepositoryServiceProvider`.

**Allowlist semantics.** An entry is `(rule, file, ceiling)`.
- The check fails if the file's number exceeds its ceiling.
- The entry is stale, and the check fails, if the number is at or below the rule's limit.
- Ceilings are lowered only on purpose. A shrinking file may grow back up to its ceiling, but never beyond. This avoids an allowlist edit for every removed line.

**Failure output.** The check collects every violation and fails once. Each message is a ready-to-paste allowlist line, so the initial allowlist comes from the first run instead of being hand-written.

**Timing.** Ceilings are measured after the format commits, because Prettier added about 7% to `.vue` line counts.
- The frontend spec lands right after the Prettier commit, not in step 5. The old scoped spec fails on formatted code: two files grew past its limits, and its line-based router regex misses wrapped `component: () =>` lines.
- Keeping the old spec green would mean raising its limits or allowlisting files in a file about to be deleted.
- The allowlist stays a draft until this change merges. When a fix this change specifies grows an allowlisted file, the fix's commit re-measures that file, because a ceiling is the file's value when the requirement lands. In the planned task order the allowlist would have been measured after those fixes anyway. Task 3.2 re-measured 3 files this way.

**Cleanup.** Delete both scripts and the old scoped frontend spec. Rename the backend test to drop "Scoped".

Alternatives considered:
- **Name-only allowlist:** allowlisted files could grow without limit.
- **ESLint `max-lines` / `vue/max-lines-per-block` with suppressions:** gives one violation per file whatever its size, so there is no ceiling, and it splits the rules across two tools.
- **Deptrac / pest-arch:** new dependencies for rules we already measure.

### 7. CI layout

**`backend_static`** runs in `validate` on the official Composer image, with the same `rules` as `frontend_check`.
1. Mirror the production install: copy `.env.example` to `.env`, then `composer install --ignore-platform-reqs`.
2. `pint --test`
3. PHPStan with `phpstan-level0.neon`
4. PHPStan with the default config
5. `phpunit tests/Unit/Architecture`

Both PHPStan runs pass `--memory-limit=1G`. On a cold result cache, which is every CI run, the largest of 8 workers peaked at 188 MB, above PHP's common 128M default. A local run passes only while its result cache is warm.

**`frontend_check`** changes:
- add `oxlint .`, `eslint .` and `format:check`, all without `--fix` or cache;
- use `build-only`, because type-check already ran;
- stays the only Node job, so i18n parity and the architecture spec run inside `test:unit`.

**`build_and_deploy`:** add `backend_static` to its `needs`.

**Other changes.**
- `worker.ts` gets its own `tsconfig.worker.json` (Workers types), referenced from `tsconfig.json`, so `vue-tsc --build` covers it.
- `bilingual.spec.ts` gains a recursive key-parity test in both directions. The root `check_i18n.ts` is deleted: it is unreferenced, untyped and outside every tsconfig.
- `.nvmrc` is set to `22`, matching the CI image and `engines`.

Alternatives considered:
- **One combined job:** needs PHP and Node in a single image.
- **Pre-commit hooks only:** easy to bypass and not enforced on the server.

## Risks / Trade-offs

- **Format commits conflict with open branches.** → Land them back-to-back at a quiet moment. Branches rebase and re-run `pint` / `npm run format` instead of resolving conflicts by hand.
- **Prettier changes rendered whitespace in a template.** → It did, in 119 text nodes. §2 explains why none of them is visible and how every screen was checked. Tests that compare raw `textContent` without trimming could still notice; the unit suite passes, and Playwright's text matchers normalize whitespace.
- **The Composer image differs from production PHP, so CI results differ from local ones.** → The first pipeline must reproduce the local counts exactly. If it does not, pin the image to the production PHP minor version.
- **Someone regenerates a baseline to hide new debt.** → The level-0 run blocks crash-class findings. Baseline diffs are visible in review. The regeneration commands are documented in the agent rules.
- **Allowlisted files can regrow up to their ceiling.** → Accepted. Burn-down changes lower ceilings explicitly.
- **507 suppressed `any` and 1,021 baselined errors can feel permanent.** → Follow-up changes per domain, each reducing the counts.
- **CI gets about 1–1.5 minutes slower.** → Accepted. `backend_static` and `frontend_check` run in parallel.

## Migration Plan

Each step keeps the existing pipeline green:

1. Fix the blocking and crash issues: QR modal, imports, dead actions, E2E `env()`.
2. Pint commit, then Prettier commit, then the frontend architecture spec (see §6, Timing). Record both format SHAs.
3. Small lint fixes; turn the oxlint rule off.
4. Install Larastan and the configs, add the inline ignores, generate the PHPStan baseline and the ESLint suppressions.
5. Repo-wide architecture checks and allowlists; delete the old scripts; align the docs.
6. CI gates last, so they go live only once every check is green.

Rollback: revert the CI commit to remove the gates. The format and fix commits need no rollback, because they do not change behavior.

## Open Questions

- Running backend PHPUnit (PostgreSQL service) in CI is needed before the first backend burn-down change. It does not affect this change's specs or tasks.
- The Playwright delta drift needs its own decision: either restore the browser gate or modify that delta.
