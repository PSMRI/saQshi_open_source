# Healthcare Deployment Configuration

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

This page explains the healthcare/NQAS configuration used by SaQshi. It is for
deployment owners who need to confirm which modules are enabled, which labels
are shown to users, and which checklist framework is active.

For the current GitBook release, SaQshi is documented as a healthcare quality
assessment and CQI platform.

## Active Configuration Files

Runtime configuration is stored in:

```text
api/config/domain.json
api/config/modules.json
```

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
