## Why

The application currently has over 40 distinct views (screens) but lacks a comprehensive, consistent responsive design strategy. Elements like data tables, complex scheduling grids, and multi-column forms often break or overflow on mobile and tablet devices. A holistic responsive overhaul is required to ensure a premium, seamless experience across all device sizes (mobile, tablet, and desktop).

## What Changes

- **Systematic Breakpoints**: Introduce or refine a unified breakpoint system (Mobile: < 640px, Tablet: 640px - 1024px, Desktop: > 1024px).
- **Reusable Layout Utilities**: Create utility classes for common responsive patterns (e.g., `.grid-1-mobile`, `.hide-on-mobile`) in `responsive.css` or `components.css`.
- **Admin & Data Tables**: Adapt large data tables to scroll horizontally or transform into card-based layouts on smaller screens.
- **Complex Grids & Forms**: Refactor multi-column layouts (like `.court-grid`, `.slot-grid`, forms) to collapse cleanly into single-column layouts on mobile.
- **Navigation & Headers**: Ensure the main navigation and admin sidebars collapse into accessible hamburger menus or bottom navigation bars on mobile.

## Capabilities

### New Capabilities
None.

### Modified Capabilities
None. 
*Note: This change focuses entirely on UI/CSS responsive adaptations. No core business logic or behavioral requirements are changing. Therefore, specs are skipped.*

## Impact

- **Affected Code**: Primarily CSS files (`frontend/src/assets/styles/*.css`) and Vue component templates (`frontend/src/views/**/*.vue`, `frontend/src/components/**/*.vue`).
- **Dependencies**: No new external dependencies required (using vanilla CSS).
- **Systems**: No impact on backend APIs or database schemas. Purely frontend presentation tier.
