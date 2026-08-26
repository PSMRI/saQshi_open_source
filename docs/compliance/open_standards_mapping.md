# Open Standards and Best Practices Mapping

Version: 1.1
Updated: 2026-08-26
License: GPL-3.0

## Purpose

This document maps SaQshi to open standards and implementation best practices relevant to open-source and DPG readiness.

## Standards and Practices

| Area | Standard / Practice | SaQshi Position |
| --- | --- | --- |
| API documentation | OpenAPI | `docs/api/openapi.yaml` and Swagger UI are included. |
| API testing | Postman collection/environment | Collection and local environment are included. |
| Data exchange | JSON, CSV, XLSX-style reports, FHIR R4 MeasureReport | Configuration uses JSON; reports use downloadable tabular formats; authenticated facility assessment summaries can be exchanged as FHIR R4. |
| Web accessibility | WCAG 2.2 | WCAG and screen-reader documentation exists; manual audits should continue. |
| Web security | OWASP-style controls | CSRF/session/error handling and SQL injection review are documented. |
| Transport security | HTTPS | Required for production deployments. |
| Configuration | `.env` and JSON config | Secrets are kept outside source code; framework behavior is configuration-driven. |
| Licensing | GPL-3.0 | Root license and public documentation identify GPL-3.0. |
| Documentation | Markdown/GitBook | User, developer, API, deployment, testing and compliance docs are included. |

## Open Configuration Formats

SaQshi uses JSON configuration for:

- Facility master data.
- Framework/checklist structure.
- Departments and concerns.
- KPI/outcome indicators.
- Formula and validation rules.
- Map/boundary configuration.

## Best Practice Gaps to Keep Reviewing

- The facility-scoped FHIR R4 `MeasureReport` export is available at `/api/assessment/v1/fhir_measure_reports.php`. Map the deployment's identifiers, terminology and any national exchange gateway requirements before connecting it to ABDM, DHIS2 or another external system.

- Keep the pinned Leaflet map dependency documented, or vendor the exact same files for offline releases.
- Keep stable non-PII export schemas in `docs/compliance/sample_exports/` synchronized with report schema changes.
- Keep OpenAPI synchronized with API changes.
- Keep role access matrix synchronized with new roles/pages.
- Continue accessibility and security testing before each release.

## 2026-08-26 Follow-up

Local public-route revalidation confirmed the landing page and GitBook documentation reader are available. The current source package supports profile-aware healthcare, education and generic-inspection deployments. Validate profile-specific terminology, identifiers, interoperability mappings and receiving-system requirements before each deployment; availability is not a standards-conformance certification.
