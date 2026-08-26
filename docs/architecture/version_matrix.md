# SaQshi Version Matrix

Version: 1.1
Updated: 2026-08-26

This page records how SaQshi evolved before the current open-source release. It helps users and implementers understand what was available in earlier builds and what is now covered in the open-source application.

## Version Summary

<details>
<summary><strong>+ Version Summary</strong></summary>

| Feature | MVP | V1 | V2 | Open Source |
| --- | --- | --- | --- | --- |
| Facility assessment using checklist | Yes | Yes | Yes | Yes |
| Single facility type focus | Yes | No | No | No |
| Basic reports | Yes | Yes | Yes | Yes |
| Multiple facility type assessment | No | Yes | Yes | Yes |
| Action plan management | No | Yes | Yes | Yes |
| Outcome indicator tracking | No | Yes | Yes | Yes |
| KPI tracking | No | Yes | Yes | Yes |
| User role and login management | No | Yes | Yes | Yes |
| District / State dashboard | No | Yes | Yes | Yes |
| Basic analytics | No | Yes | Yes | Yes |
| All facility types supported | No | No | Yes | Yes |
| Reports as per NHSRC/NQAS-style formats | No | Partial | Yes | Yes |
| District, division and state analytics | No | No | Yes | Yes |
| New UI/UX enhancements | No | No | Yes | Yes |
| Certification tracking | No | No | Yes | Yes |
| Mobile and tablet friendly design | No | No | Yes | Yes |
| Downloadable reports | No | No | Yes | Yes |
| Department-wise action plans | No | No | Yes | Yes |
| Advanced visualizations | No | No | Yes | Yes |
| Source code available for review and modification | No | No | No | Yes |
| GPL-3.0 open-source license | No | No | No | Yes |
| Public GitBook documentation | No | No | No | Yes |
| User guide | No | Partial | Yes | Yes |
| Developer guide | No | No | Partial | Yes |
| API documentation | No | Partial | Yes | Yes |
| Swagger/OpenAPI support | No | No | Partial | Yes |
| Postman testing collection | No | No | Partial | Yes |
| Database setup and migration documentation | No | No | Partial | Yes |
| Security policy | No | No | No | Yes |
| VAPT/security review documentation | No | No | No | Yes |
| SQL injection review documentation | No | No | No | Yes |
| WCAG/accessibility documentation | No | No | Partial | Yes |
| Third-party license/NOTICE documentation | No | No | No | Yes |
| Contribution guide | No | No | No | Yes |
| Code of Conduct | No | No | No | Yes |
| Release checklist | No | No | No | Yes |
| Configurable JSON-driven framework | Partial | Yes | Yes | Yes |
| Environment-based configuration using `.env` | No | No | Partial | Yes |
| Modular API/UI folder structure | Partial | Yes | Yes | Yes |
| Event abstraction layer for future message queue/Kafka use | No | No | Partial | Yes |
| Test case documentation | No | No | Partial | Yes |
| Load testing documentation | No | No | No | Yes |
| Deployment profiles for healthcare, education and generic inspection | No | No | No | Yes |
| Profile-aware public landing page | No | No | No | Yes |
| Public deployment-branding API | No | No | No | Yes |
| Non-PII sample export package | No | No | No | Yes |
| FHIR R4 facility assessment-summary export | No | No | No | Yes |

</details>

## MVP

The MVP focused on proving the checklist-based facility assessment workflow. It supported basic scoring and basic reports, but it was limited to a smaller workflow and a single facility type focus.

## V1

V1 expanded SaQshi into a fuller facility assessment application. It introduced multiple facility type support, action plans, performance monitoring through KPI and Outcome indicators, user role/login management, dashboards and basic analytics.

## V2

V2 turned SaQshi into a broader quality monitoring platform. It includes all major facility types, improved UI/UX, certification tracking, downloadable reports, department-wise CQI/action plans, advanced visualizations, and state/district/division/block monitoring.

## Open Source

The open-source release keeps the V2 feature direction and adds the elements needed for public review, reuse, contribution and deployment:

- Source code can be reviewed, modified and redistributed under GPL-3.0 terms.
- Documentation is available through GitBook-style Markdown pages.
- User, developer, API, testing, accessibility, security and compliance documentation are included.
- API testing is supported through Swagger/OpenAPI and Postman artifacts.
- Database setup and migration guidance is included for local and deployment use.
- Configuration is separated from code through `.env` and JSON-driven framework files.
- Third-party license/NOTICE files, release checklist, contribution guide and Code of Conduct are included.
- Event abstraction is available so future distributed/event-driven integrations, such as Kafka, can be added without changing every API workflow.
- Deployment profiles support healthcare/NQAS, education and generic-inspection implementations. The root landing page uses public profile branding to select the healthcare or education entry page; authenticated application access remains role/session protected.
- Stable non-PII sample exports and a facility-scoped FHIR R4 assessment-summary export provide an interoperability foundation subject to deployment-specific identifier, consent and data-sharing approval.

## Current Open-Source Baseline and Evidence Boundary

The current development baseline is documented as `1.0.0-dev` in the release policy. The matrix records product capability and documentation availability; it is not a release-approval record, an impact evaluation, a standards certification or a WHO/DPG endorsement.

Before a tagged public or production release, complete the release checklist and verify the selected deployment profile, public landing route, role-based user flows, accessibility, security headers, data-sharing requirements and active-UAT VAPT evidence. The current release-readiness posture is `PASSED_WITH_REVIEW`, with final legal/privacy approval and controlled-UAT security validation still required.

## Why This Matters

The current open-source version is not only a checklist entry screen. It is a modular application covering:

- Facility assessment
- Continuous Quality Improvement
- Certification tracking
- KPI and Outcome performance monitoring
- Reports and downloads
- State, district, division and block dashboards
- Documentation and API testing support

For profile terminology, healthcare deployments use Facility/NIN/Department language, while education deployments use School/UDISE/Class language where configured. See [Deployment Profile Configuration](../deployment/deployment_profiles.md) for selection, validation and rollback guidance.
