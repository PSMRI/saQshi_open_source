# Open Standards and Best Practices Mapping

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This document maps SaQshi to open standards and implementation best practices relevant to open-source and DPG readiness.

## Standards and Practices

| Area | Standard / Practice | SaQshi Position |
| --- | --- | --- |
| API documentation | OpenAPI | `docs/api/openapi.yaml` and Swagger UI are included. |
| API testing | Postman collection/environment | Collection and local environment are included. |
| Data exchange | JSON, CSV, XLSX-style reports | Configuration uses JSON; reports use downloadable tabular formats. |
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

- Resolve or vendor the missing Leaflet map reference and keep the third-party dependency inventory current.
- Add stable non-PII export schemas.
- Keep OpenAPI synchronized with API changes.
- Keep role access matrix synchronized with new roles/pages.
- Continue accessibility and security testing before each release.
