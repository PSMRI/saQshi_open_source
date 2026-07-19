# Legacy Naming Migration Policy

SaQshi contains some legacy database and endpoint names because the application evolved from an assessment prototype into a reusable platform. These names should be handled carefully so production deployments and historical data are not broken.

## Policy

- Do not rename active database columns or tables directly without a migration.
- Do not change public API paths without a compatibility route or release note.
- New code should follow the naming conventions in `docs/architecture/coding_standards_and_conventions.md`.
- Legacy names may remain in database access code when they map to existing schema.
- UI labels should use user-friendly names even when the database field is legacy.
- Compatibility wrappers are preferred when moving a module or endpoint.

## Migration Pattern

1. Add the new name in code or schema.
2. Keep the old name working during the transition.
3. Add a migration script under `api/sql`.
4. Update service classes first, then endpoints, then UI.
5. Update reports and exports.
6. Document the change in `CHANGELOG.md`.
7. Remove the old compatibility name only in a major release.

## Examples

| Legacy Area | Future Direction |
| --- | --- |
| Mixed database column casing | Prefer lowercase snake_case in new schema. |
| Endpoint action names | Prefer clear resource/action names under `api/<module>/v1`. |
| UI direct page links | Prefer dashboard router links. |
| Module movement | Keep compatibility route until menus, docs and reports are updated. |

