## MODIFIED Requirements

### Requirement: Premium Club Color Tokens for Light Theme
For presentation V1, including the default when no valid presentation preference is saved, the platform SHALL provide core design tokens in Light Mode that render the background as Warm Ivory (`#FAF7F3`), primary brand elements as Deep Burgundy (`#6B2D3F`), and highlight/call-to-action elements as Antique Gold (`#C9A15A`). These V1 values SHALL NOT override the Erabook V2 theme.

#### Scenario: Light theme token resolution
- **WHEN** the application renders V1 in default or Light Mode
- **THEN** `--bg` and `--background` resolve to `#FAF7F3`, `--primary` resolves to `#6B2D3F`, and `--accent` resolves to `#C9A15A`

#### Scenario: Surface and container contrast in light mode
- **WHEN** cards or content surfaces are displayed on the V1 light background
- **THEN** surfaces SHALL render in `#FFFFFF` with warm borders `#E8E1D7` and warm ambient elevation shadows

### Requirement: Luxury Dark Theme Adaptation
For presentation V1, the platform SHALL provide dark theme design tokens when dark mode is active, utilizing a Dark Espresso/Velvet background (`#141113`), an illuminated Rose-Velvet primary accent (`#E58E9F`), and a luminous Gold highlight (`#D8B46E`). These values SHALL remain isolated from V2 dark mode.

#### Scenario: Dark theme toggle activation
- **WHEN** the user switches V1 to Dark Mode
- **THEN** `--bg` SHALL resolve to `#141113`, `--surface` SHALL resolve to `#1E191C`, and primary interactive accents SHALL resolve to luminous Rose-Velvet and Gold

#### Scenario: Text legibility in dark mode
- **WHEN** body text or labels render in V1 Dark Mode
- **THEN** primary text `--ink` SHALL resolve to warm ivory `#FAF7F3` and muted text `--muted` SHALL resolve to `#A89FA4`, ensuring high contrast against dark surfaces

### Requirement: Accessible Contrast and Button Styling Variants
The platform SHALL provide readable action buttons in both presentations and color modes, with text contrast of at least 4.5:1 for normal text and 3:1 for large text. Essential control boundaries, state indicators and focus indicators SHALL have at least 3:1 contrast with adjacent colors. Color SHALL NOT be the sole indication of availability, selection, errors or payment status. Erabook-derived colors MUST be adapted where necessary to satisfy these thresholds; the design source does not waive accessibility.

#### Scenario: Primary button rendering
- **WHEN** a `.btn-primary` or standard action button is rendered in V1 Light Mode
- **THEN** the background SHALL be Deep Burgundy (`#6B2D3F`) and the text color SHALL be pure white (`#FFFFFF`) with a contrast ratio of at least 7:1

#### Scenario: Gold VIP CTA button rendering
- **WHEN** a `.btn-accent` or `.btn-gold` CTA button is rendered in V1 Light Mode
- **THEN** the background SHALL be Antique Gold (`#C9A15A`) and the text color SHALL be deep espresso/charcoal (`#2A1704`) with a contrast ratio of at least 5.5:1

#### Scenario: Erabook action contrast
- **WHEN** a V2 action uses orange or another Erabook-derived fill
- **THEN** its text and focus appearance SHALL meet the required contrast in normal, hover, focus and pressed states rather than assuming white text is readable on every brand color

#### Scenario: Schedule and financial state recognition
- **WHEN** the UI shows selected/unavailable court times or payment success/error/pending states
- **THEN** labels, icons or other non-color cues SHALL distinguish the states in both presentations

### Requirement: Legacy CSS Variable Compatibility
The platform SHALL maintain existing CSS variables (`--pink-dark`, `--pink`, `--pink-light`) mapped to the corresponding semantic tokens of the active presentation and color mode so existing consumers remain coherent. In V1 their palette mappings SHALL remain unchanged. These are existing token contracts, not a requirement to introduce new compatibility layers or duplicate components.

#### Scenario: Legacy component styling evaluation
- **WHEN** a V1 component references `var(--pink-dark)` or `var(--pink)`
- **THEN** `--pink-dark` SHALL map to `--primary` (`#6B2D3F` in light, `#E58E9F` in dark) and `--pink` SHALL map to `--accent` (`#C9A15A` in light, `#D8B46E` in dark) without visual breakage

#### Scenario: Existing token consumer in V2
- **WHEN** a V2 component consumes an existing legacy color variable
- **THEN** it SHALL resolve to the corresponding V2 semantic primary, accent or primary-container token rather than a hardcoded V1 palette

## ADDED Requirements

### Requirement: Erabook presentation palette and visual language
V2 SHALL use the locally preserved Erabook design system as its visual reference: orange primary `#F89300`, secondary `#FFC107`, light background `#FFFFFF` with neutral surface differentiation and primary text `#212121`; dark background `#181A20`, surfaces `#1F222A`, and elevated/border treatment based on `#35383F`. V2 SHALL apply a coherent scale of typography, rounded controls, card shapes, spacing and elevation adapted to court booking and operational screens. Semantic accessible variants SHALL distinguish brand colors from text/link/focus colors where required. V1 styling MUST NOT leak into V2 overlays or hover/focus states.

#### Scenario: V2 light and dark resolution
- **WHEN** a user selects V2 in either color mode
- **THEN** shared navigation, cards, forms, dialogs and feature content SHALL use the corresponding Erabook palette and coherent component styling

#### Scenario: Operational layout adaptation
- **WHEN** V2 displays a dense court schedule, administration table or POS screen
- **THEN** typography and controls SHALL follow V2 while maintaining readable data density and access to all authorized operations at the supported viewport

### Requirement: Vietnamese typography and usable design assets
V2 SHALL use Be Vietnam Pro with Vietnamese and English glyph coverage, readable fallback fonts, body weight 400, label/action weight 500 and heading weights 600–700. Interface text MUST remain selectable, localizable and accessible rather than outlined text embedded in component artwork. Only required design assets SHALL be shipped to the application; whole exported design boards MUST NOT be runtime UI. Missing source layout/font metadata SHALL be recorded as an implementation adaptation, not represented as recovered Figma data.

#### Scenario: Vietnamese text and font failure
- **WHEN** Vietnamese content with diacritics renders, including when the preferred font cannot load
- **THEN** text SHALL remain legible without missing glyphs, clipped accents or blocked interaction

#### Scenario: Interactive component from a static reference
- **WHEN** a button, input, selector or dialog is implemented from an Erabook SVG reference
- **THEN** its text, semantics and behavior SHALL be real interface elements with appropriate focus/loading/disabled/error behavior, not a clickable screenshot of the reference
