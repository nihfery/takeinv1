# Loki ingestion notes

Laravel production logs are newline-delimited JSON on `stderr`. Configure the
container log collector to parse JSON and retain `context` and `extra` as
structured fields.

Recommended low-cardinality labels are `service`, `environment`, `level_name`,
and `channel`. Keep `request_id`, `correlation_id`, route, actor identifiers,
booking identifiers, and payment identifiers as searchable fields, never Loki
labels; promoting them to labels creates unbounded cardinality.

Keep the application redactor enabled and also apply a collector-side denylist
for authorization, cookies, sessions, tokens, passwords, identity-document
metadata, payment raw payloads, email, phone, and address fields. The second
layer protects against future logging call sites that bypass application
conventions.

Set retention and access policies before production ingestion. Logs containing
audit/security events should be available only to the incident-response role.
This repository does not provision a Loki tenant or retention policy.
