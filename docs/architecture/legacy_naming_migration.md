# Legacy Naming Migration Policy

Version: 1.1
Updated: 2026-08-26

SaQshi contains some legacy database and endpoint names because the application evolved from an assessment prototype into a reusable platform. These names should be handled carefully so production deployments and historical data are not broken.

## Policy

- Do not rename active database columns or tables directly without a migration.
- Do not change public API paths without a compatibility route or release note.
- New code should follow the naming conventions in `docs/architecture/coding_standards_and_conventions.md`.
- Legacy names may remain in database access code when they map to existing schema.
- UI labels should use user-friendly names even when the database field is legacy.
- Compatibility wrappers are preferred when moving a module or endpoint.
- Deployment profiles change presentation labels, not storage/API identifiers. Healthcare may display Facility/NIN/Department while Education displays School/UDISE/Class; both can continue to use stable compatibility fields such as `fac_id`, `NIN_no` and assessment-department structures.
- Do not rename a field solely because a different profile uses a different user-facing term.

## Current Compatibility Examples

| Established identifier | Current role | Presentation/migration rule |
| --- | --- | --- |
| `facilities`, `fac_id`, `fac_id_fk` | Shared operational unit and ownership identifiers. | Use profile labels in UI; retain identifiers until a reviewed schema/API migration proves compatibility. |
| `NIN_no` / `nin_no` | Existing facility-code storage/config naming. | Education deployments may display UDISE Code, but API/database contracts must remain explicit and documented. |
| `assessment_department` | Stores configured work-area lifecycle information. | Display Department/service area or Class according to profile/framework; do not duplicate tables only to change labels. |
| `assessment_cycle_response` | Compatibility view for older reporting paths. | New writes use `assessment_response`; retain/verify the view until all deployed legacy callers are migrated. |
| `ass_period_id` and `assessment_id` | Department-status compatibility fields. | Use the documented compatibility migration/triggers where required; test both current and legacy service paths before retiring either field. |
| Legacy action-style endpoint names | Existing module URLs in clients/scripts. | Add a documented compatibility route or release migration window before removal. |

## Migration Pattern

1. Add the new name in code or schema.
2. Keep the old name working during the transition.
3. Add a migration script under `api/sql`.
4. Update service classes first, then endpoints, then UI.
5. Update reports and exports.
6. Document the change in `CHANGELOG.md`.
7. Remove the old compatibility name only in a major release.

## Required Migration Controls

Before changing a legacy name:

1. Inventory every database query, API caller, UI route, report/export, scheduled task, integration and documentation reference.
2. Define the old-to-new mapping, data type/nullability/index changes, backfill approach and rollback procedure.
3. Add an ordered, idempotent migration under `api/sql/`; do not edit an applied migration in place.
4. Preserve read compatibility first, then dual-read/dual-write only where necessary and time-bounded.
5. Add database constraints/indexes only after checking existing data and deployment engine compatibility.
6. Test a sanitized copy of representative historical data, including upgrade and rollback/recovery steps.
7. Version and publish API/report contract changes; give integrators a retirement date and migration notes.
8. Obtain deployment-owner approval before production rollout and record backup, validation and rollback evidence.

Never perform a broad rename, table drop, destructive data rewrite or migration against production solely from a local test result. Use an approved change window and recoverable backup.

## Examples

| Legacy Area | Future Direction |
| --- | --- |
| Mixed database column casing | Prefer lowercase snake_case in new schema. |
| Endpoint action names | Prefer clear resource/action names under `api/<module>/v1`. |
| UI direct page links | Prefer dashboard router links. |
| Module movement | Keep compatibility route until menus, docs and reports are updated. |
| Healthcare/education terminology | Keep stable contracts; change labels through deployment profile configuration. |

## Verification Before Retiring a Compatibility Layer

- Run the schema migration on a fresh sanitized database and a representative upgrade database.
- Confirm schema migration history, indexes/views/triggers and expected row counts.
- Test least-privilege facility/school, assessor, monitoring and administrator workflows.
- Test reports, exports, OpenAPI/Postman clients and any scheduled/background paths affected by the name.
- Confirm legacy requests behave as documented during the transition and new requests use the preferred contract.
- Update the data dictionary, SQL query inventory, endpoint inventory, user/developer/deployment docs, test evidence and `CHANGELOG.md`.
- Remove a legacy layer only after all supported deployments and integrations complete the declared migration window.

## Naming Rules for New Work

Use lowercase snake_case for new SQL identifiers, `PascalCase` PHP class names, `camelCase` PHP/JavaScript methods, stable versioned endpoint paths and profile-configured UI labels. Prefer clear domain-neutral terms in new internal code where practical, but prioritize backward compatibility over cosmetic renaming of existing production contracts.
