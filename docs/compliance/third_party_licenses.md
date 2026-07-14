# Third-Party License and Attribution Inventory

Version: 1.0  
Updated: 2026-07-13  
Project license: GPL-3.0

## Purpose

This document records third-party libraries, UI files and public services known to be used by SaQshi. It is a release-readiness document and should be reviewed before every public release.

This inventory is not a legal opinion. Project maintainers should verify exact versions and license files before publishing a release package.

## Summary

| Component / Service | Usage | License / Attribution | Status |
|---|---|---|---|
| Bootstrap | UI JavaScript/styles and responsive components. | MIT. | Review supported UI copies under `ui/` before release. |
| Bootstrap Icons | UI icons. | MIT. | Review supported UI copies under `ui/` before release. |
| Font Awesome Free | Icons/fonts where used by the UI. | Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT. | Review supported UI copies under `ui/` before release. |
| Leaflet | State certification map. | BSD-2-Clause. | Review supported UI map files/manifests under `ui/` before release. |
| OpenStreetMap | Map tile/data attribution. | OSM contributors; attribution required. | Used by state map. |
| jQuery | UI and DataTables dependency where used. | MIT. | Review supported UI copies under `ui/` before release. |
| jQuery UI | UI support where used. | MIT. | Review supported UI copies under `ui/` before release. |
| DataTables | Tabular UI/export support where used. | MIT. | Review supported UI copies under `ui/` before release. |
| JSZip | Spreadsheet/export support. | MIT or dual-licensed depending version; verify exact bundled version. | Review supported UI/report copies under `ui/` or `api/` before release. |
| jsPDF | PDF/export support. | MIT. | Review supported UI/report copies under `ui/` or `api/` before release. |
| pdfmake | PDF/export support. | MIT. | Review supported UI/report copies under `ui/` or `api/` before release. |
| html2canvas | Image/PDF export support. | MIT; verify exact bundled version. | Review supported UI/report copies under `ui/` or `api/` before release. |
| ApexCharts | Dashboard/chart pages where used. | MIT for included open-source build; verify exact version. | Review supported UI copies under `ui/` before release. |
| Chart.js | Legacy report charts via CDN. | MIT. | Used in legacy report renderers. |
| SheetJS / xlsx | Excel export in legacy report renderers via CDN. | Apache-2.0 for some community versions; verify exact CDN version/license before release. | Used by legacy report renderers. |
| xlsx-populate | Excel workbook generation in legacy report renderers via CDN. | MIT. | Used by legacy report renderers. |
| Swagger UI | Local OpenAPI viewer via CDN. | Apache-2.0. | Referenced by `docs/api/swagger-ui.html`. |
| PhpXlsxGenerator | PHP XLSX generation utility. | Verify bundled source/license before public release. | Present as `PhpXlsxGenerator.php`. |

## Public Data and Map Attribution

State map pages use OpenStreetMap/Leaflet. Any UI displaying OSM map tiles must keep visible attribution such as:

```text
© OpenStreetMap contributors
```

If custom state boundary files are used, document the source and license of each boundary file, for example:

```text
bihar.geojson
api/config/masters/map.json
```

## Bundled UI Files To Review Before Release

Only `ui/` and `api/` paths should be used as release references. Review supported third-party copies under:

```text
ui/assets/
ui/components/
ui/pages/
api/
```

## CDN References To Review

Several legacy report renderers reference CDN libraries. Before an offline or public release, either:

- document each CDN dependency and version, or
- vendor the exact dependency with its license file under `ui/` or `api/`, or
- replace CDN usage with project-managed UI/API files.

Examples found in legacy report renderers:

```text
cdn.jsdelivr.net/npm/chart.js
cdn.jsdelivr.net/npm/xlsx
cdnjs.cloudflare.com/ajax/libs/jspdf
unpkg.com/xlsx-populate
```

## Release Rule

Before publishing SaQshi:

1. Confirm every bundled third-party file has an allowed license.
2. Keep original third-party copyright/license notices.
3. Do not rewrite third-party license headers.
4. Update this document when adding/removing dependencies.
5. Keep map/data attribution visible in the UI.
