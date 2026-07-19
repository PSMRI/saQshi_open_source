# Third-Party License and Attribution Inventory

Version: 1.1  
Updated: 2026-07-16  
Project license: GPL-3.0

## Purpose

This document records third-party libraries, browser CDN references, public map services and release attribution checks known in the current `open_source` package. It is a release-readiness document and should be reviewed before every public release.

This inventory is not a legal opinion. Project maintainers should still complete final owner/legal verification before publishing a public release package.

## Current Source Scan

Scan scope:

```text
open_source/ui/
open_source/api/
open_source/docs/
open_source/gitbook.html
open_source/developer.php
```

Scan date: 2026-07-16

No `package.json`, `composer.json`, `node_modules/` or Composer `vendor/` dependency manifest was found in the current public source package. The PHP API currently uses project PHP code and PHP runtime extensions directly rather than Composer-managed libraries.

## Detected Runtime / Documentation Dependencies

| Component / Service | Version / Source | Where Used | License / Attribution | Release Status |
|---|---|---|---|---|
| Bootstrap Icons | `bootstrap-icons@1.11.3` from `cdn.jsdelivr.net` | `ui/login.html`, `ui/dashboard.html` | MIT | Detected and version-pinned. Keep attribution/license note if vendored locally. |
| Mermaid | `mermaid@10` from `cdn.jsdelivr.net` | `gitbook.html` for Mermaid diagram rendering | MIT | Detected and major-version pinned. Consider pinning an exact patch version before public release if strict reproducibility is required. |
| Swagger UI | `swagger-ui-dist@5` from `unpkg.com` | `docs/api/swagger-ui.html` | Apache-2.0 | Detected and major-version pinned. Consider pinning an exact patch version or vendoring with license before public release. |
| js-yaml | `js-yaml@4` from `cdn.jsdelivr.net` | `docs/api/swagger-ui.html` for loading OpenAPI YAML | MIT | Detected and major-version pinned. Consider pinning an exact patch version before public release. |
| Leaflet | `leaflet@1.9.4` from `cdn.jsdelivr.net`; upstream source `https://github.com/Leaflet/Leaflet` | `ui/pages/state/map.json`, `ui/pages/state/map.js` for interactive certification maps | BSD-2-Clause | Version-pinned CDN dependency. Leaflet copyright/license notice is retained through this inventory. For offline/production releases, vendor the exact `leaflet.css` and `leaflet.js` files with the BSD-2-Clause license. |
| OpenStreetMap tiles/data | Configured tile URL in `api/config/masters/map_config.json` | State certification map | OSM attribution required | Detected. UI must keep visible `OpenStreetMap contributors` attribution wherever OSM tiles are shown. |
| Postman Collection schema | `https://schema.getpostman.com/json/collection/v2.1.0/collection.json` | API testing collections | Postman schema reference | Documentation/test artifact reference only. |

## Referenced But Missing From Public Source Package

No active missing third-party UI assets are recorded after the current update. Leaflet is intentionally loaded as a pinned CDN dependency.

## Not Detected In Current Public Source Scan

The following libraries appeared in older notes or common UI assumptions, but no current bundled file or active CDN reference was detected under the scan scope above:

| Component | Current Status |
|---|---|
| Bootstrap CSS/JS | Not detected. Bootstrap Icons only is detected. |
| jQuery | Not detected. |
| jQuery UI | Not detected. |
| DataTables | Not detected as bundled JavaScript/CSS in `open_source/ui/assets/`. |
| Font Awesome Free | Not detected. |
| ApexCharts | Not detected. |
| Chart.js | Not detected. |
| SheetJS / `xlsx` | Not detected. |
| `xlsx-populate` | Not detected. |
| JSZip | Not detected. |
| jsPDF | Not detected. |
| pdfmake | Not detected. |
| html2canvas | Not detected. |
| PhpXlsxGenerator | Not detected in current `open_source/api/` scan. |

If any of these are reintroduced later, add the exact version, source path, license and attribution requirement in this document before release.

## Public Data and Map Attribution

The state map uses OpenStreetMap tiles through configurable map settings. Any UI displaying OSM map tiles must keep visible attribution such as:

```text
© OpenStreetMap contributors
```

If custom state boundary files are used, document the source and redistribution permission for each boundary/config file, for example:

```text
api/config/masters/map_config.json
api/config/masters/biharmap.json
api/config/masters/upmap.json
```

Current boundary/map source:

```text
DataMeet maps repository: https://github.com/datameet/maps
License/attribution: CC BY 4.0 unless explicitly stated by DataMeet.
Suggested attribution: India boundaries by DataMeet India community (CC BY 4.0).
```

Facility master data, framework/checklist/action-plan data, outcome configuration and state boundary data are treated as data/configuration ownership items, not only software dependencies. Their redistribution approval is tracked separately in:

```text
docs/compliance/data_redistribution_approval.md
docs/compliance/public_data_audit.md
```

Current data/configuration position:

- Local facility master data is deployment/runtime data and may contain authorized real facility records for testing or live use. Public redistribution of real facility names, NINs and hierarchy data requires data-owner approval or sample/template packaging.
- NQAS-aligned framework/checklist/action-plan configuration is retained as approved core configuration with NHSRC/QPS source context.
- Outcome configuration is retained as approved core quality-monitoring configuration.
- State boundary/map data is attributed to DataMeet where used.

## CDN Release Rule

Current CDN dependencies are acceptable for development documentation, but before a formal public release maintainers should choose one release approach:

1. Keep CDN usage and pin exact patch versions.
2. Vendor the exact dependency files under `ui/` or `docs/` with their license files.
3. Replace the dependency with project-owned code.

For production/offline deployments, vendoring exact local copies is recommended so the application and documentation do not depend on public CDN availability.

## Release Checklist

Before publishing SaQshi:

1. Confirm every bundled third-party file has an allowed license.
2. Keep original third-party copyright/license notices.
3. Do not rewrite third-party license headers.
4. Update this document when adding/removing dependencies.
5. Keep map/data attribution visible in the UI.
6. Keep the pinned Leaflet source/license entry current, or vendor the exact files for offline releases.
7. Re-run dependency and secret scans before tagging a release.
