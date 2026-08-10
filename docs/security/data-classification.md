# Data classification

| Class | Examples | Minimum handling |
| --- | --- | --- |
| Public | Active category/service/branch names, public images, ratings | Integrity validation; publish only eligible records |
| Internal | Operational dashboard metrics, schedule, queue state, deployment config without secrets | Authenticated access, least privilege, no public indexing |
| Confidential | Customer/provider contact, booking details, chat body, allergies/religion, payment metadata | Tenant scope, TLS, restricted logs/exports, retention policy |
| Restricted | KTP image, NIB document, session/token/credential, Midtrans server key/signature, database backup | Private storage/secret manager, signed+authorized access, encryption/backup controls, audited access |

Payment card data is not intended to be stored by TAKEIN; Midtrans payment
references/status are persisted instead. Do not add PAN/CVV fields or log raw
gateway credentials/payload secrets.

## Logging and audit

Structured logging adds request/correlation IDs and redacts configured secret
keys including password, token, authorization/cookie, signature, and signed/temp
URL. Avoid logging entire request/response objects. Audit before/after values are
redacted, but audit coverage is intentionally partial and persistence currently
fails open with a warning.

## Retention

Formal data retention/deletion, legal basis, backup retention, and regional
residency policy are not encoded in this repository. Product/security owners
must define them before production. Database deletion must account for audit,
payment reconciliation, media objects, and off-site backup lifecycle rather
than only deleting one Eloquent row.
