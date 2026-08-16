# WHO Digital Health Compatibility

## Purpose

This page records how SaQshi supports the WHO digital-health design principles of low-bandwidth operation, scalability, adaptability and interoperability. It is an implementation-readiness statement, not a WHO endorsement, certification or formal conformance assessment.

## Current Position

| WHO expectation | SaQshi implementation | Status |
| --- | --- | --- |
| Low-bandwidth operation | Lightweight UI assets, local icon assets, installable web manifest, offline shell caching and an IndexedDB response queue with later synchronisation. | Implemented; validate on target devices and networks. |
| Scalability | MySQL query indexes, Redis cache support, durable `background_jobs` queue and selectable worker execution modes. | Implemented foundation; size infrastructure through load testing. |
| Adaptability | JSON-driven frameworks, terminology, labels, modules and deployment profiles for healthcare, education and generic inspection. | Implemented. |
| Interoperability | Documented JSON APIs/OpenAPI, CSV/XLSX exports and a facility-scoped FHIR R4 `MeasureReport` assessment-summary endpoint. | Implemented foundation; map deployment identifiers before external integration. |

## Deployment Choices

During initial deployment, choose the operating profile and worker mode:

```powershell
php api/cli/configure-deployment-profile.php --profile=healthcare
powershell -ExecutionPolicy Bypass -File api/cli/configure-background-worker.ps1 -Mode scheduled-task
php api/cli/deployment-readiness.php
```

Use `windows-service` for a continuous Windows worker, or `redis-coordinated` when multiple worker hosts must coordinate. See [Healthcare Deployment Configuration](../deployment/deployment_profiles.md) and [Background Worker Deployment](../deployment/background-workers.md).

## Interoperability Boundary

`/api/assessment/v1/fhir_measure_reports.php` returns a FHIR R4 Bundle for the authenticated facility. It intentionally excludes individual response text, evidence, credentials and user data. Before an ABDM, DHIS2, HL7 or other gateway integration, the deploying organisation must approve identifier mapping, terminology, authentication, consent/privacy and data-sharing rules.

## Evidence and Remaining Validation

- Run the deployment readiness command before go-live.
- Test the offline response queue with actual target devices and weak/unstable networks.
- Perform expected-load and failover testing for the selected worker topology.
- Validate any external exchange against the receiving system's current implementation guide.
- Maintain HTTPS, backups, monitoring and operational incident procedures.
