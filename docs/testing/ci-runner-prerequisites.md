# CI Runner Prerequisites & E2E Gate Architecture

## 1. Runner Executor Verification (Task 6.2)

- **Configured Executor**: GitLab Runner on `zietmac` uses the `docker` executor with OrbStack socket (`unix:///Users/ziet/.orbstack/run/docker.sock`).
- **Tag**: `macos`
- **Supported Playwright Runtime**:
  - Playwright browser binaries require a Linux glibc runtime (e.g., `mcr.microsoft.com/playwright:v1.62.1-noble`) or native macOS host.
  - Alpine Linux (`musl` libc) does NOT support Playwright browser execution. Browser execution MUST NOT be injected into the Alpine deploy job.
- **External Prerequisites**:
  - Docker daemon with access to glibc-compatible Playwright images or native shell executor with PHP 8.2+ (`pdo_pgsql`, `pgsql`, `mbstring`, `bcmath`, `intl`), PostgreSQL 16+, and Node.js 22+.
  - When running containerized E2E, the test container requires both PHP CLI + extensions and Playwright Chromium dependencies.
  - Automated bootstrap provisioner: `scripts/ci/setup-e2e-runtime.sh` ensures fresh Linux checkout or container installs required PHP, Composer, and starts local PostgreSQL 16 loopback service.
  - Standalone pre-baked runner image available: `devops/build/e2e-runner.Dockerfile`.
## 2. Pipeline Gate Structure (Task 6.3)

```
[validate]          [test-e2e]                     [build]
frontend_check  --> e2e_p0_gate (Mandatory)  -->  build_and_deploy (needs: e2e_p0_gate)
                    e2e_regression_scheduled (Separate, non-deployable)
```

- **Mandatory P0 Gate**:
  - Runs on all merge requests and deployable branches (`dev`, `main`).
  - Executes `npx playwright test --project p0`.
  - Deployment (`build_and_deploy`) explicitly depends on `e2e_p0_gate` via `needs: [frontend_check, e2e_p0_gate]`.
- **Scheduled Regression Isolation**:
  - Triggered only via scheduled pipelines or manual web trigger.
  - Runs full regression suite (`@p0`, `@p1`, `@p2`).
  - Does NOT trigger deployment.

## 3. Failure Conditions & Diagnostics Retention (Task 6.4)

| Condition | Observed Result | CI Gate Status |
| :--- | :--- | :--- |
| **Zero-test selection** | `Error: No tests found` (exit code 1) | **FAILED** (Red) |
| **Missing backend / target mismatch** | Preflight rejects target with non-zero exit code | **FAILED** (Red) |
| **P0 test failure** | Playwright reports failure (exit code 1) | **FAILED** (Red, blocks deploy) |
| **Flaky retry-only pass** | `failOnFlakyTests: true` and `verify-gate.ts` reject retry-only passes | **FAILED** (Red, blocks deploy) |
| **Skipped required tests** | `verify-gate.ts` enforces `effectiveSkipped === 0` | **FAILED** (Red, blocks deploy) |
| **Insufficient test count** | `verify-gate.ts` enforces `totalTests >= minExpected` (24 for P0) | **FAILED** (Red, blocks deploy) |
| **Artifact retention** | JUnit XML + HTML report + failure traces retained for 7 days | Available with restricted access |
| **Secrets exclusion** | `auth-states/**`, `*.auth.json`, `manifest.json` excluded from artifacts | Sanitized |
