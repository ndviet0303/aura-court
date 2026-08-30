## 1. Core Token & Theme System Update

- [x] 1.1 Update `variables.css` with Light Theme tokens (`--primary: #6B2D3F`, `--accent: #C9A15A`, `--bg: #FAF7F3`, `--surface: #FFFFFF`, `--border: #E8E1D7`, `--ink: #1C1618`, `--muted: #6E6668`).
- [x] 1.2 Update `variables.css` with Dark Theme tokens (`html.dark`) with Espresso background `#141113`, Rose-Velvet primary `#E58E9F`, and radiant Gold `#D8B46E`.
- [x] 1.3 Configure backward compatibility aliases (`--pink-dark`, `--pink`, `--pink-light`) and warm ambient shadow tokens (`--shadow-sm`, `--shadow-md`, `--shadow-gold`).

## 2. Interactive & Button Styling Refactoring

- [x] 2.1 Refactor `.btn-primary` in `buttons.css` and `variables.css` to use Deep Burgundy background with white text and velvet glow.
- [x] 2.2 Add `.btn-accent` / `.btn-gold` button classes with Antique Gold background `#C9A15A` and dark espresso text `#2A1704` for WCAG AAA contrast.
- [x] 2.3 Configure Dark Mode button states (`html.dark .btn-primary`) for high contrast readability and radiant hover elevation.

## 3. Surface, Layout & Component Adaptation

- [x] 3.1 Update `cards.css`, `components.css`, and `forms.css` to use warm borders, soft surfaces, and refined focus rings.
- [x] 3.2 Update header, navigation, and footer styling in `Navbar.vue`, `SiteFooter.vue`, and `AdminLayout.vue` to align with the luxury club aesthetic.
- [x] 3.3 Replace direct legacy hex colors (`#78555e`, `#ffd1dc`) in feature SFCs with semantic CSS variables (`var(--primary)`, `var(--accent)`, `var(--primary-container)`).

## 4. Verification & Contrast Quality Audit

- [x] 4.1 Validate WCAG AA/AAA contrast ratios across all buttons, text hierarchies, and badges in both Light and Dark themes.
- [x] 4.2 Run test suites and verify frontend build (`npm run test`, `npm run type-check` or `npm run build`) to ensure zero visual regressions.
