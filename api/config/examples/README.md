# Deployment Configuration Examples

These files are copy-ready examples for deployers.

Active runtime files:

```text
api/config/domain.json
api/config/modules.json
```

Example files:

```text
api/config/examples/healthcare-domain.example.json
api/config/examples/healthcare-modules.example.json
api/config/examples/education-domain.example.json
api/config/examples/education-modules.example.json
```

Use them when the profile UI is not available or when a deployment owner wants
to review the exact JSON before applying a domain.

Copy examples manually:

```text
copy api/config/examples/education-domain.example.json api/config/domain.json
copy api/config/examples/education-modules.example.json api/config/modules.json
```

or:

```text
copy api/config/examples/healthcare-domain.example.json api/config/domain.json
copy api/config/examples/healthcare-modules.example.json api/config/modules.json
```

After copying, hard refresh the browser.
