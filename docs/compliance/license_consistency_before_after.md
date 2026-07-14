# SaQshi License Change - Before and After

Version: 2.0  
Updated: 2026-07-14  
Final selected project license: **GPL-3.0**  
Root license file: `LICENSE`

## Purpose

This document records the project license cleanup and the latest change from MIT-style project metadata to GPL-3.0.

## Before Fix

| Area | Before |
|---|---|
| Root license file | `LICENSE.txt` contained the MIT License. |
| UI source headers | SaQshi-owned UI files previously said `MIT`. Older files had also used `Apache-2.0`. |
| Runtime app metadata | `ui/config/app.json` previously had `"license": "MIT"`. |
| Footer default metadata | `ui/components/footer/footer.js` previously had `license: "MIT"`. |
| Visible footer text | `ui/components/footer/footer.html` displayed `MIT`. |
| Login page badge | `ui/pages/login/login.html` displayed `MIT`. |
| README / NOTICE | Project license text pointed to MIT or temporary GPL wording. |

Problem:

The project owner requested the project license be changed from MIT to GPL-3.0. Keeping old MIT text in headers, metadata, visible UI and release docs would make the public license signal inconsistent.

## Decision

SaQshi is now aligned to **GPL-3.0**.

The root license file uses:

```text
SPDX-License-Identifier: GPL-3.0-only
```

Third-party/vendor license notices were not changed. Third-party libraries keep their original licenses, such as MIT, BSD, Apache-2.0 or other licenses where applicable.

## After Fix

| Area | After |
|---|---|
| Root license file | `LICENSE` now contains GPL-3.0 project license notice and SPDX `GPL-3.0-only`. `LICENSE.txt` is kept as a compatibility copy. |
| UI source headers | SaQshi-owned UI headers now say `GPL-3.0`. |
| Runtime app metadata | `ui/config/app.json` now has `"license": "GPL-3.0"`. |
| Footer default metadata | `ui/components/footer/footer.js` now has `license: "GPL-3.0"`. |
| Visible footer text | `ui/components/footer/footer.html` now displays `GPL-3.0`. |
| Login page badge | `ui/pages/login/login.html` now displays `GPL-3.0`. |
| README / NOTICE | Project license text now points to `LICENSE` and GPL-3.0. |
| Open-source readiness verdict | License consistency is marked done for SaQshi-owned UI/config files. |

## Files Updated

The following SaQshi-owned areas were aligned from `MIT` to `GPL-3.0`:

- `LICENSE`
- `LICENSE.txt`
- `README.md`
- `NOTICE`
- `CHANGELOG.md`
- `ui/assets/**`
- `ui/components/**`
- `ui/config/app.json`
- `ui/layouts/**`
- `ui/pages/**`
- `docs/compliance/**`

## Important Boundary

Do not rewrite third-party/vendor license statements. For example, Bootstrap, jQuery, Leaflet, Font Awesome, Chart.js or Swagger UI may retain their own upstream licenses in the third-party attribution inventory.

## Current Check Result

Command used for SaQshi-owned project license references:

```text
rg -n "License.*MIT|license.*MIT|MIT License|SaQshi.*MIT" LICENSE LICENSE.txt README.md NOTICE CHANGELOG.md ui docs/compliance
```

Expected result:

```text
No SaQshi-owned project license references to MIT.
```
