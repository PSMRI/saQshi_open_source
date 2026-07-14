# SaQshi WCAG 2.2 and Web Platform Design Principles Review

Version: 1.0  
Updated: 2026-07-13

## Compliance Status

SaQshi is **code-level WCAG 2.2 aligned for the tested static checks** and actively aligned with relevant W3C Web Platform Design Principles. A full legal/formal WCAG conformance claim is **not yet made** because WCAG conformance still needs complete human keyboard testing, screen-reader testing and contrast verification on all light/dark theme states.

Latest static audit result:

| Metric | Result |
|---|---:|
| HTML files checked | 47 |
| Passed | 47 |
| Needs fix | 0 |
| Errors | 0 |
| Warnings | 0 |

Result file:

```text
docs/testing/wcag_static_audit_results.json
```

## References

- WCAG 2.2: `https://www.w3.org/TR/WCAG22/`
- Web Platform Design Principles: `https://www.w3.org/TR/design-principles/`

## Why We Cannot Claim Full Compliance Yet

WCAG 2.2 success criteria are testable statements and need both automated and human evaluation. SaQshi is a growing application with many dynamic pages, generated tables, charts, maps, modals, uploads and report downloads. Because of that, full compliance requires:

- Keyboard-only testing for every workflow.
- Screen-reader testing with NVDA/JAWS/VoiceOver.
- Color contrast testing in light and dark themes.
- Zoom/reflow testing at 200% and small screens.
- Error handling and status message testing.
- Testing of all dynamic components after data loads.

## Rectifications Applied

| ID | Area | Issue | Fix | Status |
|---|---|---|---|---|
| A11Y-001 | Keyboard navigation | Authenticated layout did not provide a bypass/skip link to jump past header/sidebar navigation. | Added `Skip to main content` link in `ui/layouts/dashboard.html`. | Rectified |
| A11Y-002 | Focus target | Dynamic content region could not receive focus after skip link activation. | Added `tabindex="-1"` to `#sq-page-content`. | Rectified |
| A11Y-003 | Focus visibility | Skip link needed visible styling when focused. | Added `.sq-skip-link` styles in `ui/assets/css/sq-ui.css`. | Rectified |
| A11Y-004 | Language of generated document | Performance trend export generated `<html>` without `lang`. | Changed generated export HTML to `<html lang="en">`. | Rectified |
| A11Y-005 | Text resizing | User requested A-- to A++ text controls for accessibility. | Added global header accessibility menu with `A--`, `A`, `A+`, `A++`. | Rectified |
| A11Y-006 | Screen-reader support | User requested screen reader support mode. | Added global screen-reader mode toggle with stronger focus ring, underlined links and higher contrast borders. | Rectified |
| A11Y-007 | Form controls | Static audit found controls without explicit accessible names. | Added `aria-label` to missing inputs, selects and textareas across 30 UI page files. | Rectified |
| A11Y-008 | Page heading | Dashboard fragment had no local heading. | Added screen-reader-only `Dashboard Overview` heading. | Rectified |
| A11Y-009 | Testing tool | No local page-by-page accessibility static checker existed. | Added `scripts/accessibility/a11y-static-check.js`. | Rectified |
| A11Y-010 | Repeatable label fixer | Missing control labels needed repeatable remediation. | Added `scripts/accessibility/a11y-auto-label-controls.js`. | Rectified |
| A11Y-011 | Built-in speech | User expected screen-reader mode to produce sound. | Added browser Web Speech API controls: `Read page`, `Stop`, and voice confirmation where supported. | Rectified |
| A11Y-012 | Automatic page speech | User expected screen-reader mode to start voicing automatically after changing pages. | Added `sq:page-ready` route event and automatic page speech when screen-reader mode is enabled. | Rectified |

## Current Positive Controls Observed

| WCAG Area | SaQshi Implementation |
|---|---|
| 1.3 Info and Relationships | Many pages use semantic headings, tables, labels and grouped cards. |
| 1.4 Distinguishable | Theme variables are centralized; dark-theme button/input contrast has been improved earlier. |
| 2.1 Keyboard Accessible | Native buttons, links, inputs and selects are used widely; global focus styles exist. |
| 2.4 Navigable | Page titles, breadcrumb, sidebar navigation and now skip link are present. |
| 2.4.7 Focus Visible | Global `:focus-visible` styles and component-specific focus styles exist. |
| 2.5.8 Target Size Minimum | Header accessibility controls and shared buttons use practical clickable targets; compact pages still need manual target-size testing. |
| 3.1.1 Language of Page | Main shell pages use `lang="en"`. |
| 3.3 Input Assistance | Forms include labels/instructions and validation messages in major workflows. |
| 4.1 Name, Role, Value | Key components use ARIA roles/labels: navigation, dialogs, notifications, chat, loader. |
| 4.1.3 Status Messages | Loader/notification/chat areas use `aria-live` in shared components. |

## Web Platform Design Principles Alignment

| Principle Area | SaQshi Status |
|---|---|
| User needs and usability | UI is workflow-first: assessment, checklist, CQI, reports and monitoring are direct pages, not marketing screens. |
| Secure contexts for powerful features | Location/geocoordinate features should be used only after user action and should be HTTPS in production. |
| Do not reveal assistive technology use | No code path intentionally detects screen readers or assistive technology. |
| Graceful feature handling | Map/chart/chat features show fallback or friendly failure messages where implemented. |
| Human-readable formats | API docs, Postman collection, OpenAPI, JSON configuration and test docs are readable and versioned. |

## WCAG 2.2 Review Checklist

| ID | Criterion Area | Test | Current Status |
|---|---|---|---|
| WCAG-001 | Language of Page | Check every top-level HTML page has `lang`. | Mostly Pass; dynamic export fixed |
| WCAG-002 | Page Titled | Check every route has title/header. | Mostly Pass |
| WCAG-003 | Keyboard | Use Tab/Shift+Tab/Enter/Escape on every page. | Needs full manual test |
| WCAG-004 | Bypass Blocks | Skip link appears on keyboard focus. | Rectified |
| WCAG-005 | Focus Visible | Focus ring visible for links, buttons, inputs, sidebar, modals. | Mostly Pass |
| WCAG-006 | Labels or Instructions | Every input/select/textarea has visible label or accessible name. | Needs page-by-page audit |
| WCAG-007 | Error Identification | Invalid inputs show clear messages. | Mostly Pass; needs workflow test |
| WCAG-008 | Contrast Minimum | Light and dark theme text/buttons pass contrast. | Needs automated contrast test |
| WCAG-009 | Reflow | Pages work at 200% zoom and responsive widths. | Needs browser test |
| WCAG-010 | Non-text Content | Icons/charts/maps have accessible names or text alternatives. | Partial; charts improved, map needs manual review |
| WCAG-011 | Status Messages | Loading, success, errors announced without focus stealing. | Mostly Pass |
| WCAG-012 | Target Size Minimum | Buttons and controls have practical target sizes. | Mostly Pass; compact UI should be checked |
| WCAG-013 | Accessible Authentication | Login avoids cognitive-function-only challenge where possible; captcha may need accessible alternative. | Needs improvement |

## Accessibility Controls Added

The authenticated header now includes an accessibility menu:

| Control | Purpose |
|---|---|
| `A--` | Smaller text |
| `A` | Normal text |
| `A+` | Larger text |
| `A++` | Extra large text |
| `Screen reader mode` | Stronger focus indicator, underlined links, improved border contrast, relaxed line-height, and automatic page speech after navigation |
| `Read page` | Uses browser speech synthesis to read the current page title/subtitle/content aloud |
| `Stop` | Stops current browser speech |

Settings are persisted through `SQ.storage` and applied to the root document:

```text
data-font-size="small|normal|large|xlarge"
data-screen-reader-mode="true|false"
```

Files:

```text
ui/assets/js/core/router.js
ui/components/header/header.html
ui/components/header/header.js
ui/components/header/header.css
ui/assets/css/sq-ui.css
```

Important distinction:

- External screen readers such as NVDA, JAWS and VoiceOver read the page through semantic HTML, ARIA labels and focus order.
- SaQshi's automatic page speech and `Read page` button are additional browser speech helpers using the Web Speech API.
- Speech availability depends on the browser, operating system voices and sound settings.

## Page-by-Page Static Audit Result

The static checker scanned all HTML pages/components/layouts under `ui/pages`, `ui/components`, `ui/layouts` and `ui/help`.

| Page/Area | Result |
|---|---|
| Assessment: Assessor Info | Pass |
| Assessment: Checklist | Pass |
| Assessment: Create | Pass |
| Assessment: Departments | Pass |
| Assessment: List | Pass |
| Certification: Dashboard | Pass |
| Certification: History | Pass |
| Certification: Manage | Pass |
| Certification: Renewal | Pass |
| CQI: Action Plan | Pass |
| CQI: Closure | Pass |
| CQI: Gap Analysis | Pass |
| Dashboard | Pass |
| Facility User: Facilities | Pass |
| Facility User: Users/Profile | Pass |
| Login | Pass |
| Performance: Dashboard | Pass |
| Performance: Indicator | Pass |
| Performance: KPI | Pass |
| Performance: Outcome | Pass |
| Performance: Trend | Pass |
| Reports: Dashboard | Pass |
| Reports: Progress | Pass |
| Reports: Score | Pass |
| State: Assessment Progress | Pass |
| State: Certification | Pass |
| State: CQI | Pass |
| State: Dashboard | Pass |
| State: Facility Category | Pass |
| State: Facility Detail | Pass |
| State: Indicator Analytics | Pass |
| State: Map | Pass |
| State: Performance | Pass |
| State: Reports | Pass |
| State: Users | Pass |
| Shared Components/Layout/Help | Pass |

## Known Accessibility Risks After Rectification

| Risk | Impact | Recommended Fix |
|---|---|---|
| Captcha may not be accessible to screen-reader users. | Some users may be unable to login independently. | Provide accessible captcha alternative or OTP/admin bypass for assistive users. |
| Some dynamic JS-rendered controls may be created after load. | Static HTML audit may not see runtime controls. | Add runtime accessibility checks with Playwright/axe later. |
| Charts/maps may not expose equivalent data summaries. | Non-visual users may not understand chart/map content. | Add data table summary below charts/maps or accessible descriptions. |
| Compact UI may reduce target size. | Motor-impaired users may struggle with small buttons. | Keep minimum practical target size and spacing for critical controls. |
| Full dark-theme contrast not fully measured. | Low-vision users may see insufficient contrast. | Run contrast audit across common states. |

## Recommended Manual Test Procedure

1. Login without using a mouse.
2. Use the skip link to jump to main content.
3. Navigate sidebar, header buttons, forms, modals and chat using only keyboard.
4. Confirm visible focus is never hidden behind sticky header/sidebar.
5. Run screen reader through:
   - Login
   - Dashboard
   - Assessment checklist
   - CQI action plan
   - Certification status
   - State monitoring pages
6. Test light and dark themes.
7. Test 200% zoom.
8. Test mobile width.
9. Verify all error messages are announced and readable.
10. Verify report/chart pages provide data in text/table form.

## Automated/Static Checks Performed

Executed scans:

```text
rg "<html|aria-label|role=|aria-live|sr-only|focus-visible" ui
rg "outline|focus-visible|:focus|skip|sq-sr-only" ui/assets/css ui/components ui/pages
node scripts/accessibility/a11y-auto-label-controls.js
node scripts/accessibility/a11y-static-check.js
node -c ui/components/header/header.js
node -c ui/pages/performance/trend.js
```

Findings:

- Main shell pages include `lang="en"`.
- Shared components include ARIA labels/roles for navigation, dialogs, loader, chat and notifications.
- Global focus-visible styles exist.
- Screen-reader utility classes exist.
- Skip link was missing and has now been added.
- Generated performance trend export missed `lang` and has now been fixed.
- Missing explicit control names were found and fixed across 30 UI page files.
- Final static audit result: 47 files checked, 47 passed, 0 errors, 0 warnings.
- Built-in speech controls were added to the accessibility menu for browsers that support `speechSynthesis`.

## Final Recommendation

Treat SaQshi as **WCAG 2.2 AA-aligned but not formally certified**. Before public/production release, run a dedicated accessibility audit using:

- Browser keyboard-only testing.
- NVDA on Windows.
- Lighthouse or axe DevTools.
- Manual contrast testing.
- Screen-reader review of charts/maps/tables.
