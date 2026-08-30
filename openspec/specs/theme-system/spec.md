# Theme System Specification

## Purpose

Defines the visual token contracts, contrast standards, and theme switching behaviors for the Premium Club luxury design system across all user interfaces.

## Requirements

### Requirement: Premium Club Color Tokens for Light Theme
The platform SHALL provide core design tokens in Light Mode that render the background as Warm Ivory (`#FAF7F3`), primary brand elements as Deep Burgundy (`#6B2D3F`), and highlight/call-to-action elements as Antique Gold (`#C9A15A`).

#### Scenario: Light theme token resolution
- **WHEN** the application renders in default or Light Mode
- **THEN** `--bg` and `--background` resolve to `#FAF7F3`, `--primary` resolves to `#6B2D3F`, and `--accent` resolves to `#C9A15A`

#### Scenario: Surface and container contrast in light mode
- **WHEN** cards or content surfaces are displayed on the light background
- **THEN** surfaces SHALL render in `#FFFFFF` with warm borders `#E8E1D7` and warm ambient elevation shadows

### Requirement: Luxury Dark Theme Adaptation
The platform SHALL provide dark theme design tokens when `html.dark` is active, utilizing a Dark Espresso/Velvet background (`#141113`), an illuminated Rose-Velvet primary accent (`#E58E9F`), and a luminous Gold highlight (`#D8B46E`).

#### Scenario: Dark theme toggle activation
- **WHEN** the user switches the UI to Dark Mode
- **THEN** `--bg` SHALL resolve to `#141113`, `--surface` SHALL resolve to `#1E191C`, and primary interactive accents SHALL resolve to luminous Rose-Velvet and Gold

#### Scenario: Text legibility in dark mode
- **WHEN** body text or labels render in Dark Mode
- **THEN** primary text `--ink` SHALL resolve to warm ivory `#FAF7F3` and muted text `--muted` SHALL resolve to `#A89FA4`, ensuring high contrast against dark surfaces

### Requirement: Accessible Contrast and Button Styling Variants
The platform SHALL provide high-contrast primary and VIP action buttons meeting WCAG 2.1 AA/AAA requirements (contrast ratio >= 4.5:1 for normal text and >= 3:1 for large text).

#### Scenario: Primary button rendering
- **WHEN** a `.btn-primary` or standard action button is rendered in Light Mode
- **THEN** the background SHALL be Deep Burgundy (`#6B2D3F`) and the text color SHALL be pure white (`#FFFFFF`) with a contrast ratio of at least 7:1

#### Scenario: Gold VIP CTA button rendering
- **WHEN** a `.btn-accent` or `.btn-gold` CTA button is rendered
- **THEN** the background SHALL be Antique Gold (`#C9A15A`) and the text color SHALL be deep espresso/charcoal (`#2A1704`) with a contrast ratio of at least 5.5:1

### Requirement: Legacy CSS Variable Compatibility
The platform SHALL maintain legacy CSS variables (`--pink-dark`, `--pink`, `--pink-light`) mapped to the corresponding tokens in the new palette to guarantee that existing feature components without immediate refactoring continue to display harmoniously.

#### Scenario: Legacy component styling evaluation
- **WHEN** an unrefactored component references `var(--pink-dark)` or `var(--pink)`
- **THEN** `--pink-dark` SHALL map to `--primary` (`#6B2D3F` in light, `#E58E9F` in dark) and `--pink` SHALL map to `--accent` (`#C9A15A` in light, `#D8B46E` in dark) without visual breakage
