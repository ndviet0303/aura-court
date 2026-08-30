# Proposal: Premium Club Luxury Theme (Sang Trọng)

## Why

The platform's current color scheme relies on a cool mauve/pink palette (`#78555e`) and cool gray background (`#eff1f4`) that does not convey the high-end, exclusive experience expected from a modern sports club and tournament booking platform. Transitioning to the "A. Sang trọng" palette (Deep Burgundy `#6B2D3F`, Antique Gold `#C9A15A`, and Warm Ivory `#FAF7F3`) elevates brand perception, improves visual hierarchy, and delivers a prestigious "Private Premium Club" aesthetic while maintaining strict WCAG AA/AAA accessibility compliance.

## What Changes

- **Core Design Tokens in `variables.css`**:
  - Update Light Theme with Deep Burgundy (`#6B2D3F`) as `--primary`, Antique Gold (`#C9A15A`) as `--accent` / `--cta`, and Warm Ivory (`#FAF7F3`) as `--bg` / `--background`.
  - Introduce warm surface, border (`#E8E1D7`), and text tokens (`--ink: #1C1618`, `--muted: #6E6668`).
  - Re-engineer Dark Theme (`html.dark`) with an Espresso/Dark Velvet base (`#141113`), illuminated Rose-Velvet primary (`#E58E9F`), and radiant Gold accent (`#D8B46E`).
  - Update legacy token aliases (`--pink-dark`, `--pink`, `--pink-light`) to alias the new palette to prevent breaking legacy component references.
  - Introduce warm burgundy and amber ambient shadow tokens (`--shadow-sm`, `--shadow-md`, `--shadow-gold`).
- **Button & Interactive Styles in `buttons.css`**:
  - Primary button: Solid Burgundy with white text and velvet glow.
  - Gold / VIP CTA button: Solid Gold (`#C9A15A`) with dark charcoal/espresso text (`#2A1704`) for AAA contrast.
  - Hover and active states enhanced with micro-animations and warm elevation.
- **Surface, Card & Navigation Components**:
  - Update `cards.css`, `components.css`, `Navbar.vue`, `SiteFooter.vue`, and `AdminLayout.vue` to adopt warm border accents, gold highlights for VIP/Club elements, and cohesive elevation.

## Capabilities

### New Capabilities
- `theme-system`: Establishes the luxury premium club design tokens, dark mode parity, semantic button variants, and accessible contrast standards across all UI components.

### Modified Capabilities
*(None - no behavioral spec requirements for existing backend or business flows are changing)*

## Impact

- **Affected Code**:
  - `frontend/src/assets/styles/variables.css`
  - `frontend/src/assets/styles/buttons.css`
  - `frontend/src/assets/styles/cards.css`
  - `frontend/src/assets/styles/components.css`
  - `frontend/src/assets/styles/forms.css`
  - `frontend/src/shared/layouts/Navbar.vue`, `SiteFooter.vue`, `AdminLayout.vue`
- **Dependencies**: No new npm packages required; pure CSS token and component styling update.
- **APIs / Data**: Zero impact on backend APIs, database models, or business logic.
