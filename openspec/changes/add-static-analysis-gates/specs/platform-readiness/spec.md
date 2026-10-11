## ADDED Requirements

### Requirement: Static analysis delivery gate
The delivery pipeline SHALL run the backend and frontend static checks on merge requests and on every pipeline that can deploy, before any image is built. The checks cover code formatting, linting, type analysis, static analysis, architecture conformance and locale key parity. Type analysis covers every shipped source entry point, including the edge worker. Static checks MUST run in check-only mode without modifying files, and MUST NOT depend on a database, cache, queue or other runtime service. Deployment MUST NOT start unless every static check passes. Passing static checks SHALL NOT replace the existing test, provider or rollout gates.

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

### Requirement: Shrinking debt baselines
Violations that exist when a check is introduced SHALL be recorded in versioned baseline files that the check reads. This lets the check block new violations before all existing debt is fixed. Each check MUST fail when a violation is not covered by its baseline, including one more violation of an already recorded kind in the same file. Each check MUST also fail when a baseline entry no longer matches a violation, so that fixed debt leaves the baseline in the same change. Baselines MUST only be written by an explicit, documented regeneration command and MUST NOT change as a side effect of running a check.

#### Scenario: Only recorded debt remains
- **WHEN** the code contains only violations recorded in the baselines
- **THEN** the static checks SHALL pass

#### Scenario: Recorded file gains a violation
- **WHEN** a file with recorded violations gains one more violation of the same rule
- **THEN** the check SHALL fail although the file already appears in the baseline

#### Scenario: Fixed debt left in the baseline
- **WHEN** a change fixes a recorded violation but keeps its baseline entry
- **THEN** the check SHALL fail and identify the stale entry until it is removed

### Requirement: Repository-wide architecture conformance
Architecture rules SHALL apply to every backend class and every frontend view, component and route in the repository, not to a selected set of features. The enforced limits are:

- Backend classes have at most 300 lines.
- Controllers have at most 7 public methods, with no inline request validation, direct storage access or direct model queries.
- Domain actions have no HTTP request dependency and no direct model queries.
- DTOs are immutable.
- Frontend views have at most 250 lines, of which at most 200 are script.
- Components have at most 400 lines.
- Views and components do not import the raw API client or legacy stores.
- No new legacy stores are added.
- Routes are loaded lazily.

Each file that violates a rule when this requirement is introduced SHALL be listed in an allowlist with its measured value as a ceiling. The documented architecture conventions MUST state the same limits that the checks enforce.

#### Scenario: New violation outside the allowlist
- **WHEN** a file that is not allowlisted for a rule violates that rule, for example a component that grows from 400 to 401 lines
- **THEN** the architecture check SHALL fail naming the file, rule, measured value and limit

#### Scenario: Allowlisted file grows
- **WHEN** an allowlisted file's measured value rises above its recorded ceiling
- **THEN** the architecture check SHALL fail

#### Scenario: Allowlisted file is fixed
- **WHEN** an allowlisted file no longer exceeds the rule's limit
- **THEN** the architecture check SHALL fail until its allowlist entry is removed

### Requirement: Crash-class findings are fixed, not baselined
Some findings mean that code fails, or silently misbehaves, whenever it runs. This covers findings at the backend analyzer's basic tier and frontend template parse errors. Basic-tier findings include references to unknown classes, functions, constants or console options, wrong argument counts, always-undefined variables and environment reads outside configuration. These findings MUST be fixed and MUST NOT be recorded in a debt baseline. A finding verified as a false positive SHALL be suppressed only at the reported line, by its identifier, with the reason stated in the suppression.

#### Scenario: Basic tier is clean
- **WHEN** the backend analyzer runs at its basic tier without any debt baseline
- **THEN** it SHALL report no findings

#### Scenario: Suppression without a reason
- **WHEN** a line-level suppression omits its reason
- **THEN** the static analysis check SHALL fail

#### Scenario: Unparseable template
- **WHEN** a component template cannot be parsed, for example because an element is never closed
- **THEN** the frontend static checks SHALL fail before the bundle is built
