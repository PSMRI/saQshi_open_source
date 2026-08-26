# SaQshi GitBook Publishing Guide

Version: 1.2
Updated: 2026-08-26

## Purpose

This project root is GitBook-ready. GitBook can use `README.md` as the landing
page and `SUMMARY.md` as the left navigation.

The repository also includes a local standalone documentation reader at:

```text
{main_url}/gitbook.html
```

The standalone reader is a public web page that loads repository documentation through a `doc` query parameter, for example:

```text
{main_url}/gitbook.html?doc=docs%2Fuser%2Fuser_guide.md
```

It is separate from hosted GitBook publishing. Keep both navigation systems current when a page is intended to be visible in both places.

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

## Accessibility Evidence

The GitBook Testing and Accessibility section publishes both the WCAG review and
the execution record:

```text
docs/testing/saqshi_wcag_web_platform_compliance.md
docs/testing/accessibility_test_execution_report_2026_07_17.md
```

The current record includes a passed NVDA run for every result captured in the
authenticated login and assessment flows. Preserve its scoped wording when
publishing: it records the tested flows and does not claim validation of
untested pages, JAWS or VoiceOver.

The Developer Guide includes `docs/database/sql_query_inventory.md`, which maps
the application's SQL query operations to their responsible modules and source
files.

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

## Standalone Reader Maintenance

The local reader navigation is maintained in `gitbook.html`. It currently groups user, developer, deployment, API, security/testing, compliance and Open Source/DPG documents. The reader supports the public documentation formats used by the repository, including Markdown and selected CSV/JSON/YAML reference artifacts.

When adding or renaming a reader-visible document:

1. Add or update the appropriate navigation item in `gitbook.html`.
2. Update `SUMMARY.md` when the document should also appear in hosted GitBook navigation.
3. Use a repository-relative document path; do not link to local disk paths or private deployment folders.
4. Open the exact `gitbook.html?doc=...` URL and confirm HTTP 200, readable rendering, working internal links and an appropriate title.
5. Check the page at narrow and desktop widths and confirm keyboard navigation still reaches the sidebar and content.

The root landing page links users to this reader. It is publicly reachable, but its navigation is not an authorization mechanism: never include restricted operational data because a document is hidden from a menu.

## Maintenance Rule

Whenever a new public document is added under `docs/`, update `SUMMARY.md` so
the page appears in hosted GitBook navigation.

If the document should appear in the standalone HTML reader, also update the document list in `gitbook.html`.

Do not add `.env`, logs, uploads, keys, database dumps or real user/facility data
to the GitBook repository.

Also exclude passwords, temporary credentials, session/CSRF values, API secrets, raw error output, internal hostnames, production storage paths, unapproved facility hierarchy data, patient/student data and unredacted evidence. Public documentation should describe controls and use fictional/sample values, never reproduce sensitive runtime output.

## Release Validation Boundary

Before a public release, verify GitBook links and all reader-visible routes alongside the release checklist. A local HTTP 200 result confirms availability only; it does not prove legal/privacy approval, accessibility conformance, security-header coverage or that documentation content is safe to publish. Record content review and final owner approval in the release evidence.
