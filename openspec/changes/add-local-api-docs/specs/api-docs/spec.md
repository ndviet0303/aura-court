## Purpose

Gives developers an interactive reference for the backend HTTP API on their own machine, generated from the code, without exposing it on any deployed environment.

## ADDED Requirements

### Requirement: Local interactive API reference
On a backend running with `APP_ENV=local`, the system SHALL serve an interactive API reference at `/docs/api` and its OpenAPI document at `/docs/api.json`, covering every route under the `/api` prefix, including `/api/v1/*`.

#### Scenario: Developer opens the reference locally
- **WHEN** a developer runs the backend with `APP_ENV=local` and opens `/docs/api`
- **THEN** the page lists the API's endpoints grouped by controller and lets the developer send a request to any of them

#### Scenario: OpenAPI document is reachable locally
- **WHEN** a developer requests `/docs/api.json` with `APP_ENV=local`
- **THEN** the response is HTTP 200 with an OpenAPI document whose paths include `/auth/login` and at least one `/v1/` path, and no path outside the `/api` prefix

### Requirement: Reference is not reachable outside local
The system SHALL NOT serve `/docs/api` or `/docs/api.json` when `APP_ENV` is anything other than `local`, and the production image SHALL NOT contain the documentation tool.

#### Scenario: Production image
- **WHEN** the production image (built with dev dependencies excluded) receives a request to `/docs/api` or `/docs/api.json`
- **THEN** it responds HTTP 404, as for any unknown route

#### Scenario: Tool present but environment not local
- **WHEN** the tool is installed and `APP_ENV` is `production`, `staging` or `testing`, and a signed-in or signed-out user requests `/docs/api`
- **THEN** it responds HTTP 403, because no access gate is defined

#### Scenario: Guard against regressions
- **WHEN** the tool is moved from dev dependencies to runtime dependencies, the backend image stops installing with `--no-dev`, or application code or routes define the `viewApiDocs` gate
- **THEN** the CI architecture check fails

### Requirement: Try-it requests stay first-party
Requests sent from the reference SHALL go directly from the browser to the local API, with cookies included, and SHALL NOT be routed through any third-party proxy.

#### Scenario: Sending a request from the reference
- **WHEN** a developer sends a request from `/docs/api`
- **THEN** the browser sends it to the same origin that served the page, and no request goes to `proxy.scalar.com`

### Requirement: Authenticated endpoints can be called from the reference
The reference SHALL let a developer call `auth:sanctum` endpoints with the session cookie, and SHALL offer a single `X-XSRF-TOKEN` header input that it applies to every request.

#### Scenario: Signing in and calling a protected endpoint
- **WHEN** the developer calls `GET /sanctum/csrf-cookie`, enters the URL-decoded `XSRF-TOKEN` cookie value as `X-XSRF-TOKEN`, and calls `POST /api/auth/login` with a valid account
- **THEN** login returns HTTP 200 with `user`, and a following call to a protected endpoint such as `GET /api/auth/me` returns that user instead of 401

#### Scenario: Write call without the CSRF header
- **WHEN** the developer sends a `POST` from the reference without the `X-XSRF-TOKEN` value
- **THEN** the API answers HTTP 419, as it does for the SPA

#### Scenario: Signed-out call to a protected endpoint
- **WHEN** the developer calls a protected endpoint before signing in
- **THEN** the API answers HTTP 401 JSON

### Requirement: Root status points to the reference only where it exists
`GET /` SHALL include `"docs": "/docs/api"` when `APP_ENV=local` and SHALL omit the `docs` key otherwise, keeping `name` and `status` unchanged.

#### Scenario: Local root
- **WHEN** `GET /` is called with `APP_ENV=local`
- **THEN** the JSON is `{"name": "Aura Court API", "status": "ok", "docs": "/docs/api"}`

#### Scenario: Production root
- **WHEN** `GET /` is called with `APP_ENV=production`
- **THEN** the JSON is `{"name": "Aura Court API", "status": "ok"}` with no `docs` key
