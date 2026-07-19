# SMS and Email Notification Configuration

Version: 1.0  
Updated: 2026-07-18  
License: GPL-3.0

## Purpose

SaQshi notification services are configurable without changing PHP code. Use
JSON files for gateway shape, subject and message templates. Use `.env` for
passwords, tokens and API keys.

## Files

| File | Purpose |
| --- | --- |
| `api/config/notifications/email.json` | Email transport, gateway payload, subject and body templates. |
| `api/config/notifications/sms.json` | SMS transport, gateway payload and SMS templates. |
| `.env` | Secret values such as API token, gateway username/password and main URL. |

Do not store gateway passwords or API keys in JSON files.

## Supported Transports

| Transport | Email | SMS | Use |
| --- | --- | --- | --- |
| `log` | Yes | Yes | Development/testing. Writes notification payload to storage log. |
| `mail` | Yes | No | Uses PHP `mail()` if enabled on the server. |
| `http` | Yes | Yes | Calls a configurable HTTP API gateway. Recommended for production gateway integration. |

## Environment Variables

Example:

```text
SAQSHI_MAIN_URL=https://saqshi.example.org

SAQSHI_EMAIL_API_TOKEN=
SAQSHI_EMAIL_API_USERNAME=
SAQSHI_EMAIL_API_PASSWORD=
SAQSHI_EMAIL_API_KEY=

SAQSHI_SMS_API_TOKEN=
SAQSHI_SMS_API_USERNAME=
SAQSHI_SMS_API_PASSWORD=
SAQSHI_SMS_API_KEY=
```

## Email Gateway Configuration

Path:

```text
api/config/notifications/email.json
```

Important fields:

| Field | Meaning |
| --- | --- |
| `enabled` | `true` sends through configured transport. `false` logs only. |
| `transport` | `log`, `mail` or `http`. |
| `from_email` / `from_name` | Sender identity used in template/gateway payload. |
| `templates.assessor_login.subject` | Email subject for assessor login creation. |
| `templates.assessor_login.body` | Email body for assessor login creation. |
| `http.url` | Email API gateway URL. |
| `http.method` | Usually `POST`. |
| `http.content_type` | `json` or `form`. |
| `http.auth.type` | `none`, `bearer`, `basic` or `api_key`. |
| `http.body` | Gateway payload mapping using template variables. |

## SMS Gateway Configuration

Path:

```text
api/config/notifications/sms.json
```

Important fields:

| Field | Meaning |
| --- | --- |
| `enabled` | `true` sends through configured transport. `false` logs only. |
| `transport` | `log` or `http`. |
| `sender_id` | SMS sender ID if gateway supports it. |
| `templates.assessor_login.body` | SMS text for assessor login creation. |
| `http.url` | SMS API gateway URL. |
| `http.method` | Usually `POST`. |
| `http.content_type` | `json` or `form`. |
| `http.auth.type` | `none`, `bearer`, `basic` or `api_key`. |
| `http.body` | Gateway payload mapping using template variables. |

## Auth Types

| Auth Type | Required Config |
| --- | --- |
| `none` | No auth header. |
| `bearer` | `bearer_token_env` points to token in `.env`. |
| `basic` | `username_env` and `password_env` point to `.env`. |
| `api_key` | `api_key_header` and `api_key_env`. |

## Template Variables

Current assessor-login template variables:

| Variable | Meaning |
| --- | --- |
| `{{assessor_code}}` | Assessor code. |
| `{{assessor_name}}` | Assessor name. |
| `{{username}}` | Login username, same as assessor code. |
| `{{temporary_password}}` | Generated temporary password. |
| `{{main_url}}` | Value of `SAQSHI_MAIN_URL` from `.env`. |

Email HTTP payload also supports:

| Variable | Meaning |
| --- | --- |
| `{{to}}` | Receiver email address. |
| `{{subject}}` | Rendered email subject. |
| `{{message}}` | Rendered email body. |
| `{{from_email}}` | Configured sender email. |
| `{{from_name}}` | Configured sender name. |

SMS HTTP payload also supports:

| Variable | Meaning |
| --- | --- |
| `{{mobile}}` | Receiver mobile number. |
| `{{message}}` | Rendered SMS text. |
| `{{sender_id}}` | Configured sender ID. |

## Security Rules

- Never put API passwords, tokens or keys in JSON config.
- Use `.env` for gateway secrets.
- The temporary password is not returned to browser/API response.
- `s_user.u_password` stores only the password hash.
- Auto-created assessor accounts are marked for password change on first login.
- Keep notification logs outside public web access and exclude them from public releases.

## Test Steps

1. Keep `enabled=false` and `transport=log`.
2. Create an assessor with mobile/email.
3. Confirm log files are created under `api/storage/notifications/`.
4. Configure gateway URL and `.env` secrets.
5. Set `enabled=true` and `transport=http`.
6. Create another assessor and verify gateway delivery.
7. Login as assessor and confirm password change is required.

