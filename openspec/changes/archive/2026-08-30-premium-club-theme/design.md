## Context

See `proposal.md` for motivation and `specs/theme-system/spec.md` for behavioral requirements.
The booking platform's visual presentation is defined by global stylesheets in `frontend/src/assets/styles/` (`variables.css`, `buttons.css`, `cards.css`, `components.css`, `forms.css`) along with scoped styles in Vue components (`Navbar.vue`, `SiteFooter.vue`, `AdminLayout.vue`, etc.).

## Goals / Non-Goals

**Goals:**
- Implement the "Option A: Sang trọng" color palette across the entire application in both Light Mode and Dark Mode.
- Define clean semantic tokens: `--primary` (`#6B2D3F`), `--accent` / `--cta` (`#C9A15A`), `--bg` (`#FAF7F3`), `--surface` (`#FFFFFF`), `--border` (`#E8E1D7`), `--ink` (`#1C1618`).
- Maintain WCAG AA/AAA contrast standards on all text, interactive buttons, form elements, and status badges.
- Provide backward compatibility for existing Vue components referencing legacy tokens (`--pink-dark`, `--pink`, `--pink-light`).
- Enhance visual depth with warm ambient shadows and gold accent highlights.

**Non-Goals:**
- Restructuring Vue component layouts or business logic workflows.
- Altering backend API endpoints, state management stores, or database models.

## Decisions

### Decision 1: Token Architecture in `variables.css`
- **Choice**: Centralize all color tokens inside `variables.css` using CSS custom properties with semantic naming (`--primary`, `--accent`, `--on-primary`, `--on-accent`, `--surface-container`, `--shadow-gold`).
- **Rationale**: Enables seamless dynamic theme switching (`html.dark`) and guarantees that future branding updates only require changes in one place.
- **Alternatives considered**: Hardcoding utility classes in Tailwind/CSS; rejected due to lack of flexibility with existing CSS architecture.

### Decision 2: Button Contrast and Typography Standards
- **Choice**:
  - Primary button: Background `#6B2D3F` with white text `#FFFFFF` (Contrast: 8.5:1, WCAG AAA).
  - Gold VIP / Accent CTA button: Background `#C9A15A` with dark espresso text `#2A1704` (Contrast: 6.4:1, WCAG AAA).
  - Dark mode primary button: Background `#E58E9F` with dark espresso text `#1C0A10` (Contrast: 8.1:1, WCAG AAA).
- **Rationale**: Gold background with white text has poor contrast (approx. 1.8:1, failing WCAG). Dark text ensures crisp readability and a luxurious, gold-embossed finish.
- **Alternatives considered**: Using white text on gold with black text-shadow; rejected as it looks cluttered and fails accessibility benchmarks.

### Decision 3: Backward-Compatible Alias Bridge
- **Choice**: Map legacy tokens `--pink-dark: var(--primary, #6B2D3F)`, `--pink: var(--accent, #C9A15A)`, and `--pink-light: #F8E8EC` in `variables.css`.
- **Rationale**: Prevents visual regression in existing components while allowing gradual cleanup.
- **Alternatives considered**: Mass global find-and-replace without aliasing; rejected as it carries higher risk of missing dynamic style bindings.

### Decision 4: Warm Undertone Ambient Shadows
- **Choice**: Replace sterile black/gray `rgba(0, 0, 0, ...)` shadows with warm burgundy/amber tones:
  - `--shadow-sm: 0 2px 8px rgba(107, 45, 63, 0.06);`
  - `--shadow-md: 0 8px 24px rgba(107, 45, 63, 0.09);`
  - `--shadow-gold: 0 4px 16px rgba(201, 161, 90, 0.28);`
- **Rationale**: Infuses a warm, cohesive radiance across cards, modals, and dropdowns.

## Risks / Trade-offs

- **[Risk]** Components with hardcoded inline hex colors (`#78555e`, `#ffd1dc`) might not adapt automatically.
  - **Mitigation**: Conduct a grep audit across `frontend/src` for legacy hex values and replace them with semantic `var(--primary)` or `var(--accent)`.
- **[Risk]** In dark mode, pure dark burgundy might have low contrast if used as background for dark text.
  - **Mitigation**: In dark mode (`html.dark`), shift `--primary` to luminous Rose-Velvet `#E58E9F` and `--accent` to radiant Gold `#D8B46E` with dark text `--on-primary: #1C0A10`.
