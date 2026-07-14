# Security Policy

Version: 1.0  
Updated: 2026-07-13

## Reporting a Vulnerability

If you find a security issue in SaQshi, please report it privately first. Do not open a public issue with exploit details, credentials, database dumps, private keys, personal data, facility data, or screenshots containing sensitive information.

Report vulnerabilities to the project maintainers through the private channel configured by the SaQshi project owner.

Recommended reporting email placeholder:

```text
security@saqshi.org
```

Project owners should replace this placeholder with the real security contact before public release.

## What to Include

Please include:

- A short description of the vulnerability.
- Affected module or endpoint.
- Steps to reproduce.
- Impact and likelihood.
- Screenshots or logs with sensitive values redacted.
- Suggested fix, if available.
- Your contact details for follow-up.

## Response Target

Target response timeline:

| Step | Target |
|---|---:|
| Acknowledge report | 3 working days |
| Initial triage | 7 working days |
| Fix plan or rejection reason | 15 working days |
| Patch/release timeline | Based on severity |

## Supported Versions

Until formal release versioning is introduced, security fixes should target the current development branch and the latest deployed SaQshi release.

| Version | Supported |
|---|---|
| Current development version | Yes |
| Older local copies/forks | Best effort only |

## Severity Guidance

| Severity | Examples |
|---|---|
| Critical | Remote code execution, authentication bypass, secret/key exposure, arbitrary file upload leading to execution. |
| High | SQL injection, privilege escalation, unauthorized access to facility/state data, stored XSS in authenticated workflows. |
| Medium | Reflected XSS, CSRF on sensitive state-changing actions, weak access control on low-risk data, sensitive error disclosure. |
| Low | Missing security headers, minor information disclosure, rate-limit improvements. |

## Sensitive Data Rules

Do not commit:

- `.env`
- Database passwords
- API keys
- Private keys
- Uploaded facility evidence
- Logs containing credentials/session IDs
- Real patient or personally identifiable data
- Production database dumps

The repository `.gitignore` already excludes `.env`, `.env.*`, generated logs, generated keys and uploads. Confirm this before every release.

## Security Features Already Present

SaQshi currently includes or has documentation for:

- Environment-based database configuration.
- Friendly error handling path.
- Password hashing and first-login hash upgrade.
- Login password transport encryption support.
- CSRF support for state-changing APIs.
- Session management.
- Event logging abstraction.
- SQL injection review documentation.
- VAPT test case documentation.

Security review documentation:

```text
docs/security/sql_injection_security_review.md
docs/testing/saqshi_vapt_report.md
docs/testing/saqshi_vapt_test_cases.csv
```

## Disclosure

Please allow maintainers reasonable time to investigate and fix security issues before public disclosure.
