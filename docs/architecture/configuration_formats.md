# SaQshi Configuration JSON Formats

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

SaQshi uses JSON configuration files for facility masters, department masters,
facility type mapping, map boundaries and assessment checklist/framework
definitions. This document explains how to create and maintain the two most
important configuration files:

- Facility master JSON: `api/config/masters/facilities.json`
- Checklist/framework JSON: `api/config/frameworks/saqshi-nqas.json`
- Module configuration JSON: `api/config/modules.json`

Keep these files valid JSON. A single missing comma, extra comma or mismatched
bracket can stop the related module from loading.

## Module Configuration JSON

Path:

```text
api/config/modules.json
```

This file controls optional healthcare modules such as CQI, performance, KPI,
outcome, certification, reports, analytics and map features.

Example:

```json
{
  "domain": "healthcare",
  "modules": {
    "assessment": { "enabled": true, "label": "Assessment" },
    "cqi": { "enabled": true, "label": "CQI / Gap Closure" },
    "performance": { "enabled": true, "label": "Performance Monitoring" },
    "kpi": { "enabled": true, "label": "KPI" },
    "outcome": { "enabled": true, "label": "Outcome" },
    "certification": { "enabled": true, "label": "Certification" },
    "reports": { "enabled": true, "label": "Reports" }
  },
  "role_visibility": {
    "assessor": ["assessment", "reports"]
  }
}
```

Rules:

- Keep `assessment.enabled = true` when checklist assessment is required.
- Disable `kpi`, `outcome` and `performance` only when the healthcare deployment does not collect monthly indicator data.
- Disable `certification` if the deployment does not track facility certification.
- Keep labels short because they are displayed in compact dashboards.
- Do not delete unknown module keys during upgrades; set `enabled` instead.

## Facility Master JSON

Path:

```text
api/config/masters/facilities.json
```

### Structure

The facility master is hierarchical:

```text
State
  -> Division
    -> District
      -> Block
        -> Facility
```

### Example

```json
[
  {
    "state_id": 10,
    "state_name": "UP",
    "divisions": [
      {
        "division_id": 1,
        "division_name": "Varanasi",
        "districts": [
          {
            "dist_id": 1,
            "dist_name": "Varanasi",
            "blocks": [
              {
                "block_id": 1,
                "block_name": "Arajiline",
                "facilities": [
                  {
                    "nin_no": 2642768820,
                    "fac_id": 5,
                    "fac_name": "Bhikhipur",
                    "fac_type_id": 8,
                    "facilities_type": "AAM-SC"
                  }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
]
```

### Field Rules

| Field | Required | Type | Notes |
|---|---:|---|---|
| `state_id` | Yes | Number | State identifier. Keep stable once used. |
| `state_name` | Yes | String | Display name, for example `UP` or `Bihar`. |
| `division_id` | Yes | Number | Division/region identifier. |
| `division_name` | Yes | String | Division/region display name. |
| `dist_id` | Yes | Number | District identifier. |
| `dist_name` | Yes | String | District display name. |
| `block_id` | Yes | Number | Block identifier. |
| `block_name` | Yes | String | Block display name. |
| `fac_id` | Yes | Number | Facility identifier used by application workflows. |
| `nin_no` | Yes | Number/String | Facility NIN. Must be unique across facilities. |
| `fac_name` | Yes | String | Facility display name. |
| `fac_type_id` | Yes | Number | Must match `facility_types.json`. |
| `facilities_type` | Yes | String | Human-readable facility type, such as `AAM-SC`. |

### Facility Type Mapping

Facility types are maintained here:

```text
api/config/masters/facility_types.json
```

Example:

```json
[
  {
    "fac_type_id": 8,
    "facilities_type": "AAM-SC",
    "fac": "HWC"
  }
]
```

Rules:

- `fac_type_id` in `facilities.json` must exist in `facility_types.json`.
- `facilities_type` is what users see in UI and reports.
- `fac` can be used as a shorter or program-facing type name.

### Facility Master Checklist

Before adding or publishing a facility JSON:

- Check that the file starts with `[` and ends with `]`.
- Confirm every state contains `divisions`.
- Confirm every division contains `districts`.
- Confirm every district contains `blocks`.
- Confirm every block contains `facilities`.
- Confirm `nin_no` is unique.
- Confirm `fac_id` is unique.
- Confirm `fac_type_id` exists in `facility_types.json`.
- Avoid real patient/person information in any master file.

## Department Master JSON

Path:

```text
api/config/masters/departmet.json
```

Example:

```json
[
  {
    "fac_dept_id": 25,
    "dept_name": "AAM 12 package Type B",
    "active_status": 1,
    "program_tag": "NQAS"
  }
]
```

Rules:

- `fac_dept_id` must match department IDs used in checklist/framework JSON.
- `active_status` should be `1` for available departments and `0` for inactive departments.
- `program_tag` helps group departments by programme, for example `NQAS`, `LaQshya` or `MusQan`.

## Checklist / Framework JSON

Path:

```text
api/config/frameworks/saqshi-nqas.json
```

The framework JSON defines which checklist is applicable for each facility type
and department.

### Structure

```text
Facility Type
  -> Department
    -> Area of Concern
      -> Subtype / Standard
        -> Checkpoint
          -> Response options and score
```

### Example

```json
[
  {
    "fac_type_id": 8,
    "facilities_type": "AAM-SC",
    "departments": [
      {
        "fac_dept_id": 25,
        "dept_name": "AAM 12 package Type B",
        "program_tag": "NQAS",
        "concerns": [
          {
            "concern_id": 1,
            "concern_name": "Service Provision",
            "concern_des": "Area of Concern-A",
            "subtypes": [
              {
                "c_subtype_id": 1,
                "area_of_con_subtypedeatils": "The facility provides drugs and diagnostic services as mandated",
                "Reference_No": "Standard A2",
                "checkpoints": [
                  {
                    "csqa_id": 10001,
                    "c_subtype_Reference_No_fk": "Standard A2",
                    "csqa_reference_id": "ME A2.1",
                    "Measurable_Element": "The facility provides laboratory services",
                    "Checkpoint": "Availability of diagnostic services including NHP",
                    "Assessment_Method": "CI/RR",
                    "action_plan": "Ensure diagnostic services are available as per package requirements.",
                    "program_tag": "NQAS",
                    "response": {
                      "type": "radio",
                      "mandatory": true,
                      "remarks_required": false,
                      "evidence_required": false,
                      "options": [
                        {
                          "label": "Fully Compliant",
                          "value": 2,
                          "score": 2
                        },
                        {
                          "label": "Partially Compliant",
                          "value": 1,
                          "score": 1
                        },
                        {
                          "label": "Non Compliant",
                          "value": 0,
                          "score": 0
                        }
                      ]
                    }
                  }
                ]
              }
            ]
          }
        ]
      }
    ]
  }
]
```

### Framework Field Rules

| Field | Required | Type | Notes |
|---|---:|---|---|
| `fac_type_id` | Yes | Number | Must match `facility_types.json`. |
| `facilities_type` | Yes | String | Facility type display name. |
| `departments` | Yes | Array | Departments applicable for this facility type. |
| `fac_dept_id` | Yes | Number | Must match `departmet.json`. |
| `dept_name` | Yes | String | Department display name. |
| `program_tag` | Recommended | String | Programme grouping such as `NQAS`. |
| `concerns` | Yes | Array | Area of concern list. |
| `concern_id` | Yes | Number | Area of concern identifier. |
| `concern_name` | Yes | String | Example: `Service Provision`. |
| `concern_des` | Recommended | String | Example: `Area of Concern-A`. |
| `subtypes` | Yes | Array | Standard/subtype list. |
| `c_subtype_id` | Yes | Number | Subtype identifier. |
| `Reference_No` | Yes | String | Standard name, for example `Standard A2`. |
| `checkpoints` | Yes | Array | Checkpoint list under this standard. |
| `csqa_id` | Yes | Number | Unique checkpoint ID. Do not leave blank or null. |
| `csqa_reference_id` | Yes | String | Measurable element reference, for example `ME A2.1`. |
| `Measurable_Element` | Yes | String | Measurable element text. |
| `Checkpoint` | Yes | String | Actual checkpoint question/statement. |
| `Assessment_Method` | Optional | String | Can be blank if not applicable. |
| `action_plan` | Recommended | String | Default suggested action plan for gaps. |
| `response` | Yes | Object | Response control and scoring definition. |

### Response Format

The healthcare/NQAS checklist scoring response is radio-based:

```json
{
  "type": "radio",
  "mandatory": true,
  "remarks_required": false,
  "evidence_required": false,
  "options": [
    {
      "label": "Fully Compliant",
      "value": 2,
      "score": 2
    },
    {
      "label": "Partially Compliant",
      "value": 1,
      "score": 1
    },
    {
      "label": "Non Compliant",
      "value": 0,
      "score": 0
    }
  ]
}
```

Rules:

- Keep `value` and `score` numeric.
- Standard checklist score is `0`, `1`, or `2`.
- Score percentage is calculated from completed checkpoint score divided by total possible checkpoint score.
- If `evidence_required` is `true`, the UI/API may require evidence upload before completion.
- If `remarks_required` is `true`, the UI/API may require remarks before saving.

For healthcare development and future approved extensions, a checkpoint may use
structured response types. The assessment engine stores `response_value` for
quick display and `response_json` for the full structured answer.

Supported response types:

| Type | Use For | Scoring |
|---|---|---|
| `radio` | Standard `0/1/2` compliance scoring or any configured option set. | Uses option `score`. |
| `yes_no` | Simple health service availability questions. | Uses configured option score. |
| `dropdown` | Controlled single-select answers. | Uses option `score` when supplied. |
| `number` | Counts such as OPD attendance, beds, equipment or staff. | Stored as data; not scored by default. |
| `text` | Narrative/short remarks. | Stored as data; not scored by default. |
| `form` | Multi-field healthcare operational data. | Stored as indexed data; not scored by default. |

Example non-scored number response:

```json
{
  "type": "number",
  "label": "Average Daily OPD",
  "mandatory": true,
  "score_mode": "none"
}
```

Example form response:

```json
{
  "type": "form",
  "label": "OPD Load and Staffing",
  "mandatory": true,
  "fields": [
    { "key": "monthly_opd", "label": "Monthly OPD", "type": "number", "required": true },
    { "key": "doctor_count", "label": "Doctors Available", "type": "number", "required": true },
    { "key": "nurse_count", "label": "Nurses Available", "type": "number", "required": true },
    { "key": "has_staff_gap", "label": "Staff Gap", "type": "yes_no", "required": true }
  ]
}
```

Non-scored responses are saved with `score_status = NOT_SCORED` and indexed in
`assessment_response_field_index`. This supports healthcare analytics such as
service load, staff availability or operational counts without forcing every
data point to contribute to the quality score.

### Healthcare Framework Example

The official health checklist remains:

```text
api/config/frameworks/saqshi-nqas.json
```

Do not modify this large NQAS-aligned framework casually. For healthcare
deployment validation and developer validation, a smaller health example with
every supported response type is available here:

```text
api/config/frameworks/healthcare-example.json
```

The sample contains:

| Question Type | Example Use |
|---|---|
| `radio` | OPD readiness compliance scored as `0/1/2`. |
| `yes_no` | Emergency support availability. |
| `dropdown` | Essential drug stock status. |
| `number` | Average daily OPD count. |
| `text` | Service gap remarks. |
| `form` | OPD load and staffing data. |

## Checklist Validation Checklist

Before publishing checklist/framework JSON:

- Validate JSON syntax with a JSON validator.
- Confirm every `fac_type_id` exists in `facility_types.json`.
- Confirm every `fac_dept_id` exists in `departmet.json`.
- Confirm every `csqa_id` is unique and non-empty.
- Confirm every checkpoint has `Checkpoint`, `Measurable_Element`, and `csqa_reference_id`.
- Confirm every response option has `label`, `value`, and `score`.
- Confirm all standards have `Reference_No`, such as `Standard A1`, `Standard A2`, `Standard B1`.
- Confirm no duplicate checkpoint IDs exist across the framework file.
- Keep file encoding as UTF-8.

## Recommended Update Process

1. Create or edit JSON outside production first.
2. Validate JSON syntax.
3. Check IDs against master files.
4. Load in a local SaQshi environment.
5. Create a test assessment.
6. Activate the department.
7. Open checklist page and verify concern, subtype, method and checkpoint loading.
8. Save sample `0`, `1`, and `2` responses for scored frameworks.
9. Save sample text/number/form responses when validating the healthcare example framework.
10. Check scorecard/report output and field analytics output.
11. Only then publish to production.

## Related Files

- `api/config/masters/facilities.json`
- `api/config/masters/facility_types.json`
- `api/config/masters/departmet.json`
- `api/config/frameworks/saqshi-nqas.json`
- `api/config/frameworks/healthcare-example.json`
- `api/config/examples/healthcare-domain.example.json`
- `api/config/examples/healthcare-modules.example.json`
- `docs/architecture/technical_architecture.md`
