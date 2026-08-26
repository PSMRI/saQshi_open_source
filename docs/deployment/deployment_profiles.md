# Deployment Profile Configuration

Version: 1.1
Updated: 2026-08-26
License: GPL-3.0

## Purpose

This page explains how SaQshi deployment profiles control the active modules,
user-facing labels and default checklist framework. It is for deployment owners
who need to confirm the configuration used by their healthcare, education or
generic-inspection implementation.

The standard healthcare profile is NQAS-aligned. Education and
generic-inspection profiles use the same configurable platform with their own
labels, modules and framework defaults.

## Public Landing-Page Behaviour

The public root URL is profile-aware:

| Active profile | Root route behaviour | Primary public page |
|---|---|---|
| `healthcare` | Serves the healthcare landing page | `index.html` |
| `education` | Redirects after reading public deployment configuration | `education-index.html` |
| `generic-inspection` | Verify the configured generic-inspection experience during acceptance testing | Deployment-specific |

`index.html` reads `GET /api/config/v1/public_deployment.php` without authentication. That endpoint returns only public branding and label data; it must not return credentials, user data, infrastructure values or operational configuration. If the call is temporarily unavailable, the root page remains on the healthcare landing page rather than exposing an error.

Authenticated users still use `ui/login.html`; protected pages and APIs continue to enforce role and session checks.

## Active Configuration Files

Runtime configuration is stored in:

```text
api/config/domain.json
api/config/modules.json
```

## First Deployment Profile Selection

Before users are created, the deployer can select the operating profile without
manually editing configuration files:

```powershell
php api/cli/configure-deployment-profile.php
```

Available options are `healthcare`, `education`, and `generic-inspection`.
For unattended deployment, specify the choice directly:

```powershell
php api/cli/configure-deployment-profile.php --profile=healthcare
```

The command writes `api/config/domain.json` and `api/config/modules.json`.
Choose the profile before creating assessments; existing assessments retain the
framework that was selected when they were created.

Changing an active profile is a controlled deployment change, not a routine user action. It can alter labels, modules, default framework selection and the public landing-page experience. Before changing it, take a database/configuration backup, obtain owner approval, test in non-production and communicate the change. Do not switch a production profile simply to test the landing page.

Profile source files are available in:

```text
api/config/profiles/healthcare.json
api/config/profiles/education.json
api/config/profiles/generic-inspection.json
```

## Readiness Check

Before go-live, run the read-only readiness check:

```powershell
php api/cli/deployment-readiness.php
```

It validates deployment/profile JSON, required PHP extensions, database
connectivity, the background-jobs table and the selected worker mode. Use
`--json` for automated deployment pipelines.

## Post-Selection Verification

After selecting or restoring a profile, verify the effective configuration before inviting users:

```powershell
php api/cli/deployment-readiness.php
Invoke-WebRequest -UseBasicParsing -Uri "{main_url}/api/config/v1/public_deployment.php"
Invoke-WebRequest -UseBasicParsing -Uri "{main_url}/"
```

Confirm that `public_deployment.php` reports the intended `profile_code` and public labels only; Healthcare opens the healthcare landing page; Education opens `education-index.html` after the profile check; and Login opens `ui/login.html`. Then sign in with an authorised test account to confirm expected labels, menus, framework and modules. Record the profile code, deployment date, approving owner and result in the release/deployment record.

Healthcare copy-ready examples are stored in:

```text
api/config/examples/healthcare-domain.example.json
api/config/examples/healthcare-modules.example.json
```

Use these examples when a deployment owner wants to restore the standard
healthcare/NQAS configuration.

## Healthcare Labels

The standard healthcare labels are:

```text
Facility
NIN
Facility User
Department
Assessment
Checklist
Checkpoint
Assessor
CQI
KPI
Outcome
Certification
Evidence
```

Example `domain.json` source:

```text
api/config/examples/healthcare-domain.example.json
```

This file controls UI wording such as:

| Key | Healthcare Label |
|---|---|
| `facility` | Facility |
| `facility_code` | NIN |
| `department` | Department |
| `assessment` | Assessment |
| `checklist` | Checklist |
| `checkpoint` | Checkpoint |
| `map` | Certification Map |
| `field_analytics` | Indicator Analytics |

## Healthcare Modules

The standard healthcare deployment enables:

```text
assessment = true
cqi = true
performance = true
kpi = true
outcome = true
certification = true
reports = true
field_analytics = true
map = true
```

Example `modules.json` source:

```text
api/config/examples/healthcare-modules.example.json
```

These modules support the complete workflow:

| Module | Purpose |
|---|---|
| `assessment` | Assessment creation, department activation, assessor info and checklist entry. |
| `cqi` | Gap analysis, action plan and gap closure. |
| `performance` | KPI/outcome dashboard and trend pages. |
| `kpi` | Monthly KPI indicator entry and history. |
| `outcome` | Monthly outcome indicator entry and history. |
| `certification` | Certification status, validity, renewal and history. |
| `reports` | Scorecards, checklist reports, CQI reports and state reports. |
| `field_analytics` | Indicator analytics and low-performing checklist observations. |
| `map` | Certification map and geo-based facility view. |

## Healthcare Framework Files

Primary checklist framework:

```text
api/config/frameworks/saqshi-nqas.json
```

Developer/sample healthcare framework:

```text
api/config/frameworks/healthcare-example.json
```

Use `saqshi-nqas.json` for the actual NQAS-aligned assessment. Use
`healthcare-example.json` only for development/testing because it is a compact
example containing all supported response controls.

## Manual Restore

If the active configuration has been changed during testing, restore healthcare
configuration by copying:

```text
copy api/config/examples/healthcare-domain.example.json api/config/domain.json
copy api/config/examples/healthcare-modules.example.json api/config/modules.json
```

After copying:

1. Confirm `default_framework` points to `saqshi-nqas`.
2. Hard refresh browser cache.
3. Open `{main_url}/api/config/v1/deployment.php`.
4. Confirm `domain = healthcare` and `active_profile = healthcare`.

## Configuration API

Endpoint:

```text
GET {main_url}/api/config/v1/deployment.php
```

Returns:

```json
{
  "domain": {},
  "modules": {},
  "profiles": []
}
```

The UI uses this response for:

- page labels,
- sidebar module visibility,
- active framework reference,
- deployment setup screens.

### Public Branding API

The unauthenticated landing page uses a narrower endpoint:

```text
GET {main_url}/api/config/v1/public_deployment.php
```

It returns `profile_code`, `profile_name`, public labels, branding and public content. Treat it as a public contract: keep values presentation-safe, do not add secrets or environment-specific service details, and recheck the response whenever profile configuration or landing-page code changes.

## One Assessment, One Checklist

Each assessment uses one framework/checklist at a time. For the current
healthcare release, new assessments should normally use:

```text
saqshi-nqas
```

This keeps all responses, scorecards, CQI actions and reports tied to the exact
framework used when the assessment was created.

## Response Types

Healthcare/NQAS scoring primarily uses `radio` options:

```json
{
  "type": "radio",
  "mandatory": true,
  "options": [
    { "label": "Fully Compliant", "value": "2", "score": 2 },
    { "label": "Partially Compliant", "value": "1", "score": 1 },
    { "label": "Non Compliant", "value": "0", "score": 0 }
  ]
}
```

The compact healthcare example also demonstrates:

```text
radio
yes_no
dropdown
number
text
form
```

These examples are useful for developers validating the configurable assessment
engine, while the production NQAS checklist continues to follow the approved
healthcare scoring structure.

## Rollback and Support

If a profile change causes incorrect labels, unexpected menus or landing-page routing, stop further rollout and restore the last approved `domain.json` and `modules.json` backup. Re-run `deployment-readiness.php`, clear browser cache only where necessary, and repeat post-selection verification. Do not delete existing assessments to correct profile configuration; escalate framework or data-migration decisions to the deployment owner.
