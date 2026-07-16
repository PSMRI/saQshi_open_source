# SaQshi GitBook Publishing Guide

Version: 1.0  
Updated: 2026-07-14

## Purpose

This project root is GitBook-ready. GitBook can use `README.md` as the landing
page and `SUMMARY.md` as the left navigation.

## Files Used by GitBook

| File | Purpose |
|---|---|
| `README.md` | Main landing page for the book. |
| `SUMMARY.md` | GitBook navigation/sidebar. |
| `docs/api/` | API, OpenAPI, Postman and source reference documentation. |
| `docs/database/` | Database setup and migration guide. |
| `docs/security/` | Security review documentation. |
| `docs/testing/` | Test plan, VAPT, load testing and WCAG documents. |
| `docs/compliance/` | Open-source, licensing, DPG and release readiness documents. |

## Current Release-Readiness Pages

The GitBook includes a consolidated reviewer page:

```text
docs/compliance/open_source_dpg_release_status.md
```

Use this page as the first stop for open-source and DPG readiness review. It links to the detailed readiness checklist, DPG assessment, release checklist, security scan, public data audit, legal/privacy confirmation and data redistribution approval records.

## Recommended GitBook Structure

```text
README.md
SUMMARY.md
docs/
  api/
  database/
  security/
  testing/
  compliance/
```

## Import Steps

1. Create a GitBook space.
2. Connect the repository that uses this folder as the project root.
3. Confirm GitBook detects `README.md` and `SUMMARY.md`.
4. Review the generated sidebar.
5. Publish the space after checking links and formatting.

## Maintenance Rule

Whenever a new public document is added under `docs/`, update `SUMMARY.md` so
the page appears in GitBook navigation.

If the document should appear in the standalone HTML reader, also update the document list in `gitbook.html`.

Do not add `.env`, logs, uploads, keys, database dumps or real user/facility data
to the GitBook repository.
