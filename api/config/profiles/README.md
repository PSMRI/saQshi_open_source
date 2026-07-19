# Deployment Profiles

Deployment profiles help implementers choose sensible defaults without editing
every module and label manually.

| Profile | Use When | Main Modules |
| --- | --- | --- |
| `healthcare` | NQAS-style health facility quality assessment | Assessment, CQI, KPI, Outcome, Certification, Reports |
| `education` | School assessment and school data capture | Assessment, Field Analytics, Reports, Map |
| `generic-inspection` | Any checklist-based inspection/audit | Assessment, Field Analytics, Reports |

To apply a profile manually:

1. Copy profile `labels` into `api/config/domain.json`.
2. Copy profile `modules` booleans into `api/config/modules.json`.
3. Set `default_framework` to the required checklist/framework code.
4. Hard refresh the browser.

Future setup UI can automate this copy/merge process.
