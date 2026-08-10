# Audit domain

`Audit` owns `audit_logs` and `RecordAuditEvent`. Records include actor type/ID,
action, resource, provider/branch scope, request/correlation ID, IP, user agent,
and redacted before/after data.

Current call sites cover payment/subscription webhook and customer payment
actions, provider lifecycle/document/profile/password/role changes, booking
status changes, admin profile/provider operations, and private attachment/
document access. This list is implemented coverage, not a claim that every
mutation in the application is audited.

Audit persistence is fail-open for business availability: a failure is logged
and returns null rather than breaking the transaction. Operators must alert on
that warning because prolonged audit loss is a security incident. Secrets,
signature values, tokens, cookies, and signed URLs are redacted before storage
or structured logging.
