# Runtime Storage Directory

This directory is intentionally kept free of runtime data in the public source release.

Do not commit:

- event logs,
- application logs,
- generated encryption keys,
- private/public key pairs,
- cached files,
- session artifacts,
- files containing request payloads or operational data.

Deployments should generate keys and logs locally, keep them outside version control, and rotate them according to the local security policy.
