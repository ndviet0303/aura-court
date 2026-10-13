## ADDED Requirements

### Requirement: Manually started delivery pipeline
The delivery pipeline SHALL start only when a maintainer starts it by hand. A push, a merge request or a schedule SHALL NOT start a pipeline, so a push to a deployable branch SHALL NOT deploy anything by itself. A pipeline started on a deployable branch (`main` or `dev`) SHALL run every validation gate and SHALL build and deploy only after all of them pass. A pipeline started on any other branch SHALL run the validation gates only.

#### Scenario: Push to main
- **WHEN** a commit is pushed to `main`
- **THEN** no pipeline SHALL start and production SHALL keep running the previously deployed revision

#### Scenario: Maintainer deploys main
- **WHEN** a maintainer starts the pipeline on `main`
- **THEN** the validation gates SHALL run first, and the images SHALL be built and deployed only if every gate passes

#### Scenario: Maintainer checks another branch
- **WHEN** a maintainer starts the pipeline on a branch other than `main` or `dev`
- **THEN** the validation gates SHALL run and nothing SHALL be built or deployed

## MODIFIED Requirements

### Requirement: Static analysis delivery gate
The delivery pipeline SHALL run the backend and frontend static checks on every pipeline, before any image is built. The checks cover code formatting, linting, type analysis, static analysis, architecture conformance and locale key parity. Type analysis covers every shipped source entry point, including the edge worker. Static checks MUST run in check-only mode without modifying files, and MUST NOT depend on a database, cache, queue or other runtime service. Deployment MUST NOT start unless every static check passes. Passing static checks SHALL NOT replace the existing test, provider or rollout gates.

#### Scenario: New violation blocks delivery
- **WHEN** a change introduces a formatting deviation, lint violation, type error, static-analysis finding or architecture violation that is not recorded in a debt baseline
- **THEN** the validation job SHALL fail naming the file and rule, and no image SHALL be built or deployed

#### Scenario: Missing translation key
- **WHEN** a message key exists in one supported locale but not in the other
- **THEN** the validation job SHALL fail and name the missing key

#### Scenario: Edge worker type error
- **WHEN** the edge worker source contains a type error
- **THEN** the validation job SHALL fail even though the browser bundle still builds

#### Scenario: Runner without runtime services
- **WHEN** the static checks run where no database, cache or queue service is reachable
- **THEN** they SHALL produce the same result as on a developer machine

### Requirement: Priority-based browser delivery gates
The delivery pipeline SHALL execute required critical browser acceptance before deployment. A missing environment, failed test, unexpected skip, empty test selection or retry-only pass in the required critical suite MUST NOT yield a successful gate. Full regression SHALL have an explicit pre-release execution path; secondary-browser/mobile checks SHALL be separately identifiable. Reports MUST distinguish passed, failed, flaky, skipped, blocked and not-run outcomes and link to the requirement matrix. Passing isolated tests SHALL NOT authorize production rollout or override existing provider and operating-model gates.

#### Scenario: Critical gate fails
- **WHEN** any required critical journey cannot complete cleanly
- **THEN** the validation job SHALL fail and dependent deployment SHALL not proceed

#### Scenario: Regression evidence is produced
- **WHEN** a pre-release regression finishes
- **THEN** its report SHALL identify source revision, environment, browser, locale/timezone, capability profile and per-requirement outcomes with diagnostic links, without counting skips or retries as clean passes
