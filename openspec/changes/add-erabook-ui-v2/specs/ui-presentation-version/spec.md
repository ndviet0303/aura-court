## Purpose

Cho phép người dùng chọn giao diện Premium Club V1 hoặc Erabook V2 mà không thay đổi đường dẫn, quyền truy cập, dữ liệu hay trạng thái nghiệp vụ đang thao tác; xác định phạm vi và bằng chứng hoàn thành giao diện mới.

## ADDED Requirements

### Requirement: Profile and mobile presentation switch
The application SHALL expose a localized “Giao diện Erabook V2” on/off control in the profile dropdown for both guests and authenticated users, and an equivalent synchronized control in mobile navigation. Off SHALL mean V1 and on SHALL mean V2. Controls MUST expose their accessible name and checked state and be operable using keyboard and touch.

#### Scenario: Guest selects V2
- **WHEN** a guest enables V2 from the profile dropdown
- **THEN** the current page SHALL use V2 without requiring authentication
- **AND** the mobile navigation control SHALL show the same selected state

#### Scenario: Authenticated user returns to V1
- **WHEN** an authenticated user disables V2 through either control
- **THEN** the current page SHALL return to V1 and both controls SHALL reflect V1
- **AND** the account session and selected language SHALL remain unchanged

#### Scenario: Keyboard and localized control
- **WHEN** the user navigates to the switch by keyboard in Vietnamese or English and activates it
- **THEN** its localized name and resulting checked state SHALL be available to assistive technology
- **AND** focus SHALL remain on a usable control without being lost to the page body

### Requirement: Browser-local presentation preference
The application SHALL default to V1 when no valid saved preference exists and SHALL remember a valid selection for the same browser origin when storage is available. Saved selection MUST be applied before application content is first displayed. Presentation preference SHALL be independent of color mode, language and authentication; login or logout SHALL NOT reset it. Unavailable storage MUST NOT prevent startup or in-session switching. No account synchronization is required.

#### Scenario: New or invalid saved preference
- **WHEN** the browser has no preference or an unsupported saved value
- **THEN** the application SHALL start in V1 without an error

#### Scenario: Reload saved V2 in dark mode
- **WHEN** V2 and dark mode were selected and the user reloads or opens a deep link on the same origin
- **THEN** the first displayed application content SHALL use V2 with dark mode, without first displaying V1

#### Scenario: Browser storage is denied
- **WHEN** reading or writing browser preference storage fails
- **THEN** the application SHALL remain usable, default to V1 if no selection can be read, and allow in-memory switching for the current page lifetime
- **AND** it SHALL NOT falsely claim that the preference was saved

### Requirement: Independent presentation and color mode
The application SHALL support V1-light, V1-dark, V2-light and V2-dark. Switching presentation SHALL NOT change color mode, and switching color mode SHALL NOT change presentation. The active combination SHALL apply consistently to navigation, page content, dialogs, popovers and mobile drawers.

#### Scenario: Switch version while dark mode and a dialog are active
- **WHEN** a user switches from V1 to V2 with dark mode selected and an open overlay
- **THEN** page content and the overlay SHALL use V2-dark while preserving the overlay's state

#### Scenario: Change color mode in V2
- **WHEN** a user changes V2 from light to dark and back
- **THEN** V2 SHALL remain selected and no V1 visual theme SHALL replace it

### Requirement: In-place switching preserves active work
Presentation switching SHALL preserve the current URL including query and hash, form values and validation state, selected filters, court/date/time selections, reservation and payment identities, countdown deadlines, active dialogs, POS cart and pending operation state. Switching MUST NOT reload or navigate the page, recreate business sessions, restart deadlines, submit forms or trigger business mutations. In-flight work and realtime updates SHALL continue exactly once; normal server expiry and authorization rules remain authoritative.

#### Scenario: Switch with selected court and times
- **WHEN** the user switches V1 ↔ V2 after selecting a venue, court, date and multiple time slots
- **THEN** selections, filters and displayed quote SHALL remain associated with the same booking context
- **AND** switching alone SHALL NOT create a reservation or reset selection

#### Scenario: Switch during hold and payment
- **WHEN** the user switches presentation with a pending reservation or payment request
- **THEN** the same reservation, payment reference and original expiration deadline SHALL remain in use
- **AND** completion or expiry SHALL be processed once without duplicate charges, holds or polling subscriptions caused by switching

#### Scenario: Unsaved work and POS cart
- **WHEN** the user switches while editing a profile/admin form or building a POS cart
- **THEN** entered values, errors, quantities and pending submission state SHALL remain intact
- **AND** the switch SHALL NOT submit the form or finalize a sale

### Requirement: Functional and access parity
V1 and V2 SHALL expose the same authorized functionality, existing URLs and aliases, navigation outcomes, business validation and financial truth. Selecting V2 MUST NOT grant permissions, change tenant scope, activate a disabled capability, change payment models, reinterpret legacy wallet history or invent unavailable backend functionality. Switching back SHALL restore the V1 presentation except for the new version control and explicitly recorded shared accessibility corrections.

#### Scenario: Restricted and disabled functionality
- **WHEN** a guest or a limited-role user selects V2 and visits a protected route or encounters a disabled capability
- **THEN** the same access decision and truthful unavailable state as V1 SHALL apply

#### Scenario: Direct-owner and historical payment records
- **WHEN** an authorized user views a direct-owner payment, refund obligation or historical wallet record in either presentation
- **THEN** receiver, amounts, statuses and available operations SHALL represent the same underlying records without changing the operating model

### Requirement: Complete route presentation coverage
The completed V2 SHALL cover all existing frontend route families: public/auth/legal, court discovery/directions/schedule, customer bookings and payments, profile/wallet/notifications, recurring/split/resale, community/tournaments, partner onboarding/subscriptions, administration and POS, including not-found and shared overlays. Layouts SHALL adapt to mobile and desktop without hiding authorized actions or essential financial/schedule information. Dense operational tables and time grids MUST remain usable rather than being replaced with unrelated ebook layouts.

#### Scenario: Route family acceptance
- **WHEN** a route family is marked V2-complete
- **THEN** its route inventory SHALL link to checked normal, loading, empty, error and relevant disabled/pending states, with inapplicable states explained
- **AND** responsive behavior, role-specific actions and shared overlays SHALL have recorded evidence

#### Scenario: Incomplete intermediate build
- **WHEN** only some delivery phases are implemented
- **THEN** the build SHALL NOT be declared full V2 acceptance or enabled as the new default
- **AND** any separately approved early preview SHALL disclose its exact covered routes and remaining gaps instead of silently claiming full V2

### Requirement: Verifiable presentation acceptance
V2 completion SHALL require passing evidence for both presentations' critical journeys, bidirectional state-preserving switching, persistence and storage failure behavior, representative VI/EN and light/dark mobile/desktop rendering, keyboard/focus behavior and V1 visual regression checks. Evidence MUST identify source revision, environment, route/role/state coverage and remaining gaps. UI-only or simulated-payment evidence SHALL NOT substitute for real provider readiness.

#### Scenario: Critical state regression
- **WHEN** switching loses a booking selection, resets a deadline, duplicates an operation or changes an access decision
- **THEN** V2 acceptance SHALL fail until the regression is corrected and the relevant check passes

#### Scenario: Missing visual or behavioral evidence
- **WHEN** a required route or state has no evidence or only a global color replacement
- **THEN** that route SHALL remain pending and the full change SHALL NOT be marked complete
