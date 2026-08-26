# Deployment Profile Configuration

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

This page explains how SaQshi deployment profiles control the active modules,
user-facing labels and default checklist framework. It is for deployment owners
who need to confirm the configuration used by their healthcare, education or
generic-inspection implementation.

The standard healthcare profile is NQAS-aligned. Education and
generic-inspection profiles use the same configurable platform with their own
labels, modules and framework defaults.

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
