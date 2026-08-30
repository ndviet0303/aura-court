## Context

The project is built with Vue 3 and uses vanilla CSS without a comprehensive utility framework like Tailwind. The current responsive strategy is fragmented, with only a few basic media queries defined in `responsive.css`. We need a scalable, consistent way to handle layout changes for over 40 distinct views. See `proposal.md` for motivation.

## Goals / Non-Goals

**Goals:**
- Establish a unified CSS variable or utility system for breakpoints.
- Create reusable responsive layout patterns (e.g., standard grid layouts that collapse on mobile).
- Ensure all admin data tables are usable on mobile (via horizontal scrolling or card stacking).
- Ensure navigation elements are mobile-friendly (Hamburger/Bottom Nav).

**Non-Goals:**
- Completely rewriting the CSS architecture to use Tailwind or another framework.
- Redesigning the visual aesthetics (colors, typography) of the application; we are purely adapting the layout for smaller viewports.

## Decisions

1. **Breakpoint System**: 
   - Define standard media queries in a single place. We will stick with CSS custom properties if possible, or standard media queries in `responsive.css` to be `@import`ed or globally applied.
   - Standard Tiers:
     - Mobile: `<= 640px` (or keep current `<= 560px` but standardize it)
     - Tablet: `<= 1024px`
     - Desktop: `> 1024px`
   - *Rationale*: Sticking to simple CSS media queries avoids adding preprocessors or heavy frameworks, aligning with the current tech stack.

2. **Data Table Strategy**:
   - **Decision**: For Admin views with large tables, we will wrap the tables in a `.table-responsive` container with `overflow-x: auto;` rather than refactoring to card-based layouts.
   - *Rationale*: Horizontal scrolling is less disruptive to implement across the many admin views and keeps the codebase simpler than building custom responsive cards for every distinct table.

3. **Grid/Form Layouts**:
   - **Decision**: Define a standard `.grid-stack-mobile` utility class in `responsive.css` that sets `grid-template-columns: 1fr;` on mobile devices.
   - *Rationale*: Many views currently hardcode grid templates. A utility class reduces CSS duplication and makes templates cleaner.

## Risks / Trade-offs

- **[Risk] Regression in existing layouts** → *Mitigation*: We will systematically test each view type (Admin, Public, Interactive) after applying the responsive utility classes.
- **[Risk] High volume of files to edit** → *Mitigation*: Group views by their underlying layout patterns and update them in batches (e.g., all forms first, all tables second).
