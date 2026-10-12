# Responsive design conventions

Phase 19 completed the cross-module responsive audit. These rules apply to all existing and future screens.

## Breakpoints and navigation

- The persistent portal and administration sidebars are the desktop experience at 1024px and above.
- Below 1024px, use the dedicated sticky mobile header and its disclosure menu. The menu is viewport-bound, scrollable, closes after navigation or an outside click, and closes with Escape while returning focus to its trigger.
- Do not duplicate application pages by viewport or locale. Use the same authorized server-rendered content with responsive presentation and logical CSS properties.
- Horizontal navigation strips, including event workspace tabs, conversation history and social navigation, may scroll on narrow screens. Keep each destination at least 44px high and preserve a visible focus state.

## Content and controls

- Pages must not create document-level horizontal overflow down to 360px; 320px is the minimum defensive canvas.
- Mobile form controls use at least 16px text to prevent automatic browser zoom. Primary actions stack when a horizontal row would reduce touch size or truncate translations.
- Administration tables switch to labelled row cards on mobile. Dense social data tables retain horizontal scrolling and a sticky identifying column because their values require column comparison.
- Images, video and canvas content remain within their container. Remote thumbnails use lazy decoding/loading and meaningful alternative text.
- Notification panels and navigation disclosures stay within the visual viewport. Account for safe-area insets on page and sticky-composer edges.

## Feature-specific behavior

- The calendar uses the agenda representation on mobile instead of compressing month/week grids.
- The AI history becomes a horizontal strip below the desktop breakpoint. The composer remains sticky once the user reaches it, fits the viewport, and leaves room for the on-screen keyboard.
- Arabic and English share every breakpoint. Use `inset-inline`, logical padding/margins and automatic content direction rather than left/right overrides where possible.

## Verification

New or changed screens should be checked at 1440×900, 1280×800, 768×1024, 390×844 and 360px in English LTR and Arabic RTL. Browser checks must cover document overflow, mobile navigation containment, touch sizes, console errors and the screen-specific compact representation. `tests/Browser/responsive.spec.js` supplies the cross-screen baseline; feature suites remain responsible for successful, validation and authorization flows.

