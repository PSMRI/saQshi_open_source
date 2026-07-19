# SDG Mapping

Version: 1.2  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

This document maps SaQshi to Sustainable Development Goal relevance for Digital Public Good readiness review.

## Primary SDG Alignment

| SDG | Alignment | SaQshi Evidence |
| --- | --- | --- |
| SDG 3: Good Health and Well-being | SaQshi supports healthcare quality assessment, continuous quality improvement, certification tracking, performance monitoring and public health facility monitoring. | Assessment, CQI, performance, certification and state monitoring modules. |

## Supporting SDG Alignment

| SDG | Alignment | SaQshi Evidence |
| --- | --- | --- |
| SDG 9: Industry, Innovation and Infrastructure | The platform provides reusable digital public infrastructure patterns for healthcare quality monitoring. | Config-driven framework files, API-first PHP services, GitBook documentation, OpenAPI/Postman assets and reusable UI modules. |
| SDG 10: Reduced Inequalities | SaQshi can help programme teams identify quality gaps across facility types and administrative geographies, supporting more equitable quality-improvement attention. | State, division, district, block and facility drill-down dashboards; facility type categorisation; indicator analytics; CQI monitoring. |
| SDG 16: Peace, Justice and Strong Institutions | Role-based monitoring, audit-ready workflows, documented governance and transparent reporting support institutional accountability. | Role access matrix, audit-ready workflow records, governance documents, state monitoring reports and release compliance documents. |
| SDG 17: Partnerships for the Goals | Open documentation, APIs, JSON-driven configuration and GPL-3.0 licensing support reuse by public-sector and partner organizations. | Open-source release package, contribution guide, third-party attribution, DPG readiness docs, sample exports and configuration format docs. |

## Target-Level Mapping

This table uses cautious alignment language. SaQshi supports these areas indirectly as a digital quality-improvement and monitoring platform; it does not itself deliver clinical services.

| SDG Target Area | SaQshi Contribution |
|---|---|
| SDG 3.8: Universal health coverage and quality essential health services | Supports facility quality assessment, certification readiness, gap tracking and monitoring of public health facility quality. |
| SDG 3.c: Health workforce and institutional capacity | Helps facility and programme teams structure assessment, action planning, responsibility assignment and monthly performance review. |
| SDG 3.d: Health-system capacity and risk management | Supports routine monitoring, dashboards, performance trends and facility-level quality signals that can inform system strengthening. |
| SDG 9.c: Access to information and communications technology | Provides a web-based, configurable digital platform for health quality monitoring and reporting. |
| SDG 10.3: Equal opportunity and reduced inequalities of outcome | Enables comparison of facility quality gaps across locations and facility types so programme teams can identify where support is most needed. |
| SDG 16.6: Effective, accountable and transparent institutions | Provides role-scoped dashboards, reports, documented workflows and audit-friendly records for assessment, CQI and certification processes. |
| SDG 16.7: Responsive and inclusive decision-making | Supports district, division, block and state review through structured data, facility drill-down and aggregated monitoring. |
| SDG 17.17: Public, public-private and civil society partnerships | GPL-3.0 licensing, public documentation, API specifications and sample exports support collaboration and reuse by implementation partners. |
| SDG 17.18: Data availability and capacity-building | Provides structured non-PII sample exports, role-scoped reports and dashboard data models that can improve data use for programme review. |

## Public Benefit

SaQshi is intended to strengthen healthcare facilities and improve the quality
of healthcare services delivered to patients and communities. It helps health
systems:

- Measure healthcare facility quality consistently using standardized assessment frameworks.
- Identify gaps in infrastructure, processes, clinical practices, patient safety and service delivery.
- Convert identified gaps into structured and time-bound action plans.
- Assign responsibilities and monitor corrective actions until gaps are closed.
- Track continuous quality improvement across multiple assessment cycles.
- Monitor facility performance, readiness and certification status.
- Improve accountability by giving clear visibility of pending actions and progress.
- Support evidence-based decisions at facility, block, district, division, state and programme levels.
- Compare quality and performance gaps across facility types, departments, programmes and geographical areas.
- Help authorities prioritize technical support, training, infrastructure, equipment, medicines and other resources.
- Encourage facilities to follow standard operating procedures and quality standards.
- Improve patient safety, service availability, operational efficiency and continuity of care.
- Strengthen preparedness for NQAS, LaQshya, MusQan and other approved healthcare quality-certification programmes.
- Reduce dependence on manual assessments, spreadsheets and fragmented reporting.
- Create reliable and structured data for monitoring, planning, evaluation and policy decisions.
- Share non-PII sample export formats to support safer interoperability, research, transparency and public-good review.
- Enable early identification of recurring and systemic quality issues across the health system.
- Strengthen healthcare facilities through continuous assessment, action, monitoring and learning.

By strengthening facility-level systems and supporting timely improvement,
SaQshi contributes to safer, more accessible, reliable, equitable and
patient-centred healthcare for the public.

## Alignment Boundary

SaQshi's SDG contribution is primarily through **digital quality assessment, monitoring, reporting and CQI workflows**. It should not be described as directly providing healthcare services, directly reducing disease burden, or directly achieving SDG targets by itself. Actual SDG impact depends on how health-system teams use the data and complete improvement actions.

## Evidence in Repository

- [Project Overview and NQAS Alignment](../architecture/project_overview.md)
- [Use Cases](../architecture/use_cases.md)
- [Technical Architecture Overview](../architecture/technical_architecture.md)
- [Open Source Readiness Checklist](open_source_readiness_checklist.md)
- [DPG Readiness Assessment](dpg_readiness_assessment.md)
- [Non-PII Export and Import](non_pii_data_export_import.md)
- [Open Standards Mapping](open_standards_mapping.md)
