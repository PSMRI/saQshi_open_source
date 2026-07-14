# SaQshi License Consistency Fix - Before and After

Version: 1.0  
Fixed: 2026-07-13  
Final selected project license: **MIT**  
Root license file: `LICENSE.txt`

## Purpose

This document records the license inconsistency that existed in SaQshi and what was changed to make the application internally consistent for open-source release preparation.

## Before Fix

| Area | Before |
|---|---|
| Root license file | `LICENSE.txt` contained the MIT License. |
| UI source headers | Many SaQshi-owned UI files said `License : Apache-2.0`. |
| Layout/component headers | Shared layout and component files said `Apache-2.0`. |
| Runtime app metadata | `ui/config/app.json` had `"license": "Apache-2.0"`. |
| Footer default metadata | `ui/components/footer/footer.js` had `license: "Apache-2.0"`. |
| Visible footer text | `ui/components/footer/footer.html` displayed `Apache-2.0`. |
| Login page badge | `ui/pages/login/login.html` displayed `Apache-2.0`. |
| Open-source readiness verdict | License consistency was marked `Partial`. |

Problem:

The project had two different license signals. A public user could see MIT in the root license file but Apache-2.0 in source headers and UI text. That creates confusion for reuse, distribution, legal review, and contributor expectations.

## Decision

SaQshi is aligned to **MIT** because:

- `LICENSE.txt` already contains the MIT License.
- MIT is OSI-recognized and permissive.
- Most bundled front-end vendor files appear permissive or have their own license text, but release references should stay under `ui/` and `api/`.
- This change avoids replacing the root legal license without project-owner approval.

## After Fix

| Area | After |
|---|---|
| Root license file | Still MIT in `LICENSE.txt`. |
| UI source headers | SaQshi-owned UI headers now say `MIT`. |
| Layout/component headers | Shared layout and component headers now say `MIT`. |
| Runtime app metadata | `ui/config/app.json` now has `"license": "MIT"`. |
| Footer default metadata | `ui/components/footer/footer.js` now has `license: "MIT"`. |
| Visible footer text | `ui/components/footer/footer.html` now displays `MIT`. |
| Login page badge | `ui/pages/login/login.html` now displays `MIT`. |
| Open-source readiness verdict | License consistency is now marked `Done` for SaQshi-owned UI/config files. |

## Files Updated

The following SaQshi-owned areas were mechanically aligned from `Apache-2.0` to `MIT`:

- `ui/assets/css/**`
- `ui/assets/js/**`
- `ui/components/**`
- `ui/config/app.json`
- `ui/layouts/dashboard.html`
- `ui/pages/assessment/**`
- `ui/pages/cqi/**`
- `ui/pages/dashboard/**`
- `ui/pages/facilityusers/**`
- `ui/pages/login/**`
- `ui/pages/reports/**`

Third-party/vendor license notices were not changed.

## Current Check Result

Command used for SaQshi-owned UI/API files:

```text
rg -n "Apache-2\.0" ui api README.md CONTRIBUTING.md CODE_OF_CONDUCT.md LICENSE.txt -g "!api/config/frameworks/**" -g "!api/templates/**"
```

Result:

```text
No remaining Apache-2.0 references were found in the scanned SaQshi-owned UI/API files.
```

## Remaining Open Source Release Items

License consistency is fixed, but the project still needs:

- `SECURITY.md`
- Third-party attribution / `NOTICE` or `docs/compliance/third_party_licenses.md`
- Expanded `README.md`
- `CHANGELOG.md`
- Release checklist
- Database setup/migration documentation
