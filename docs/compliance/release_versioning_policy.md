# Release and Versioning Policy

Version: 1.0  
Updated: 2026-07-16  
License: GPL-3.0

## Purpose

This policy defines how SaQshi versions, release branches, release tags, changelog entries and release approvals should be managed.

It applies to public open-source releases, release archives, tagged GitHub releases and production deployment baselines.

## Version Format

SaQshi uses Semantic Versioning once public release tags begin:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
1.0.0
1.1.0
1.1.1
2.0.0
```

Development builds may use a suffix:

```text
1.0.0-dev
1.0.0-rc.1
```

## Version Increment Rules

| Change Type | Version Change | Example |
|---|---|---|
| Breaking API, database or workflow change | MAJOR | `1.4.2` to `2.0.0` |
| New backward-compatible feature | MINOR | `1.4.2` to `1.5.0` |
| Bug fix, security patch or documentation-only correction | PATCH | `1.4.2` to `1.4.3` |
| Pre-release testing candidate | Suffix | `1.5.0-rc.1` |

Breaking changes include:

- API request/response contract changes that break existing clients.
- Database schema changes that require manual migration without backward compatibility.
- Removal or rename of public UI routes, API endpoints, JSON configuration keys or report columns.
- Permission/role changes that materially alter access behavior.

## Branch and Tag Rules

Recommended branch names:

```text
main
develop
release/v1.0.0
hotfix/v1.0.1
feature/<short-name>
fix/<short-name>
```

Release tags should use:

```text
vMAJOR.MINOR.PATCH
```

Examples:

```text
v1.0.0
v1.1.0
v1.1.1
```

## Changelog Policy

`CHANGELOG.md` must be updated for every release-visible change.

Use these sections where relevant:

- `Added`
- `Changed`
- `Deprecated`
- `Removed`
- `Fixed`
- `Security`
- `Documentation`

Before tagging a release:

1. Move completed items from `Unreleased` into a release heading.
2. Add release date in `YYYY-MM-DD` format.
3. Keep future work under `Unreleased`.
4. Do not include secrets, private URLs, credentials, patient data, private facility data or exploit details.

Example:

```text
## 1.0.0 - 2026-07-16

### Added

- Initial open-source release package.
```

## Release Gate

A release may be tagged only after:

- `docs/compliance/release_checklist.md` is reviewed.
- `php tools/release_readiness_check.php` is run and warnings are accepted or resolved.
- `CHANGELOG.md` is updated.
- `README.md`, GitBook docs and API docs are synchronized with release behavior.
- `SECURITY.md`, `MAINTAINERS.md` and governance contacts are current.
- License and third-party attribution documents are current.
- Database schema/migration instructions are reproducible.
- No `.env`, secrets, uploads, logs, private keys, database dumps or sensitive data are included.

## Release Approval Roles

Before a public release, approvals should be recorded from:

| Role | Responsibility |
|---|---|
| Product owner | Confirms release scope and user-facing behavior. |
| Release manager | Confirms version, changelog, tag, package and release checklist. |
| Security owner | Confirms security scan, VAPT status and security contact path. |
| Data owner | Confirms no restricted data is published. |
| Legal/privacy reviewer | Confirms license, privacy and redistribution readiness. |
| Technical maintainer | Confirms build, API, database and documentation readiness. |

## Hotfix Policy

Security and production hotfixes should:

1. Branch from the affected release tag.
2. Make the smallest safe fix.
3. Increment PATCH version.
4. Update `CHANGELOG.md` under `Security` or `Fixed`.
5. Run the release readiness checker.
6. Tag the hotfix release.

## Release Artifact Policy

Public release artifacts should include source and documentation only.

Do not include:

- `.env` or secret files.
- Runtime uploads.
- Logs.
- Generated private keys.
- Database dumps.
- Patient/person data.
- Sensitive facility data without explicit redistribution approval.
- Local backup archives or development-only `.docx/.xlsx/.zip` files unless approved as release documentation artifacts.

## Documentation Synchronization

When a release changes behavior, update:

- `README.md`
- `CHANGELOG.md`
- `SUMMARY.md`
- `docs/compliance/release_checklist.md`
- `docs/api/openapi.yaml`
- Postman collection/environment where relevant.
- User/developer GitBook pages where relevant.

## Current Status

Current development baseline:

```text
1.0.0-dev
```

First public release target should be tagged only after remaining release review gates are closed or formally accepted by the release owner.
