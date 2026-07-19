# Evidence Upload and Log Retention Policy

Version: 1.0  
Updated: 2026-07-17  
License: GPL-3.0

## Purpose

This document explains how SaQshi currently handles uploaded evidence files and logs/audit events, and what deployment owners must configure before production use.

## Evidence Upload Handling

SaQshi evidence upload is used for assessment/CQI/gap-closure supporting documents. Evidence is optional in the default workflow unless a local deployment policy makes it required.

Current application controls:

| Control | Current Handling | Evidence |
|---|---|---|
| Authentication required | Upload and delete APIs require a logged-in user and facility session. | `api/files/v1/upload.php`, `api/files/v1/delete.php` |
| File size limit | Upload size is limited to 10 MB. | `api/files/v1/upload.php` |
| Extension allow-list | Allowed extensions are image, PDF, Word, Excel and CSV formats. Executable/script files are rejected. | `api/files/v1/upload.php` |
| MIME/content validation | MIME type is checked using `finfo`; images are additionally checked with `getimagesize()`. | `api/files/v1/upload.php` |
| Safe stored name | Stored filenames are sanitized and include timestamp/random suffix. | `api/files/v1/upload.php` |
| Category sanitization | Upload category is sanitized to alphanumeric, underscore and dash. | `api/files/v1/upload.php` |
| Delete path safety | Delete API only deletes files under the local `uploads/` path and blocks path traversal/outside paths. | `api/files/v1/delete.php` |
| Public source protection | Runtime uploads are ignored by git and must not be committed. | `.gitignore`, `uploads/README.md` |

Current limitation:

- Uploaded file URLs are returned under `/uploads/...`. If the web server serves `uploads/` publicly, anyone with the exact URL may be able to open the file. For stricter deployments, place uploads outside the public web root or serve them through an authenticated download endpoint.

Production requirements:

- Use HTTPS.
- Disable directory listing for `uploads/`.
- Store uploads outside executable PHP paths where possible.
- Do not upload patient-level personal health information.
- Redact patient-identifiable content before upload.
- Add antivirus/malware scanning where required by policy.
- Apply the default evidence retention schedule below or replace it with an approved local retention schedule before go-live.
- Back up evidence securely if the deployment requires evidence retention.
- Restrict evidence access by role/geography scope.

Default retention schedule:

| Evidence / File Type | Default Retention Period | Disposal Rule |
|---|---|
| Draft/wrong upload | Delete immediately when identified, or within 30 days if left unused. | User/API delete where supported; otherwise scheduled cleanup by administrator. |
| Assessment evidence | 3 years after assessment completion/cancellation, unless local programme policy requires longer. | Delete securely after retention period and backup expiry. |
| CQI action-plan/closure evidence | 3 years after gap closure/completion, unless local programme policy requires longer. | Delete securely after retention period and backup expiry. |
| Certification evidence/certificates, if uploaded | Validity period plus 1 year, or 3 years minimum, whichever is longer. | Delete securely after retention period and backup expiry. |
| Uploaded file backups | Match the source evidence retention period. | Expire from backup storage according to backup policy. |
| Public repository | 0 days. Evidence files must not be committed. | Remove before release; release checker/manual review should confirm. |

Deployment owners may increase or reduce these periods based on government/programme policy, audit requirements and legal instructions. Any change should be recorded in the deployment operations document.

## Logs and Audit/Event Handling

SaQshi uses runtime logs and event logs for troubleshooting, auditability and future event-driven integration.

Current application controls:

| Control | Current Handling | Evidence |
|---|---|---|
| Friendly user errors | API errors return friendly messages/request IDs instead of raw SQL/PHP details. | `api/core/Response.php`, `api/core/ErrorHandler.php` |
| Event abstraction | Events are written as JSON lines under `api/storage/events/events-YYYY-MM-DD.log`. | `api/core/Event.php` |
| Event redaction | Event payload/meta/query values are redacted for sensitive keys such as password, token, CSRF, captcha, secret, API key, authorization, cookie and session. | `api/core/Event.php` |
| Public source protection | Runtime event/log files are ignored by git. | `.gitignore`, `api/storage/README.md` |
| Scope metadata | Events include request ID, method, path, user ID and facility ID where available. | `api/core/Event.php` |

Current limitation:

- Automatic log retention/deletion is not built into the application. Retention must be configured by the deployment owner through server log rotation, scheduled cleanup, SIEM policy or backup policy using the default schedule below or an approved local schedule.

Production requirements:

- Keep logs outside public web access.
- Configure log rotation and retention according to the default schedule below or approved local policy.
- Do not log passwords, raw tokens, captcha values, uploaded file contents or personal health information.
- Restrict log access to authorized technical/security users.
- Back up logs only if required by the deployment audit policy.
- Review logs during security incidents.

Default retention schedule:

| Log Type | Default Retention Period | Disposal Rule |
|---|---|
| Application error logs | 90 days. | Rotate daily/weekly and delete after retention unless needed for incident review. |
| API request/event logs | 180 days. | Rotate and delete after retention unless audit policy requires longer. |
| Security incident logs | 1 year after incident closure, or longer if directed by legal/security authority. | Archive securely; restrict access. |
| Failed login/security monitoring logs | 180 days. | Rotate and delete after retention unless linked to incident. |
| Database/web-server operational logs | 90 days unless local IT policy requires longer. | Rotate and delete after retention. |
| Public repository | 0 days. Runtime logs must not be committed. | Remove before release; release checker/manual review should confirm. |

If logs are sent to a SIEM or centralized monitoring system, the SIEM retention policy must be aligned with these defaults or formally approved by the deployment owner.

## Open Production Improvement

For stricter deployments, add:

- authenticated evidence download endpoint,
- evidence file encryption at rest,
- malware scanning pipeline,
- configurable retention cleanup job,
- centralized audit log viewer with role-based access.

## Related Documents

- [Production Hardening Guide](production_hardening.md)
- [VAPT Report](../testing/saqshi_vapt_report.md)
- [Privacy and Data Protection Note](../compliance/privacy_data_protection.md)
- [Legal and Privacy Confirmation](../compliance/legal_privacy_confirmation.md)
