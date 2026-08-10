# Security incident

## 1. Declare and preserve

Open the private incident channel, assign incident commander, security lead,
operations lead, recorder, and domain owner. Record UTC timeline, affected
hosts/tenants/data, request/correlation IDs, deploy/image versions, alerts, and
known indicators. Preserve immutable edge/application/audit/database/object
access evidence; do not paste secrets, raw KTP/NIB/chat, dumps, or signed URLs
into tickets/chat.

## 2. Contain proportionately

Use the narrowest reversible control: block malicious source/path at edge,
disable a compromised actor/provider, pause an affected worker/route, or enter
maintenance for broad integrity risk. Keep webhook evidence and transaction
truth intact. Do not delete volumes/logs, rotate `APP_KEY` blindly, purge Redis,
delete attacker accounts, or modify payment rows before evidence and recovery
impact are understood.

If a secret is exposed, identify every consumer and rotate through the owning
system's approved dual-key/cutover procedure. Session/token revocation and
credential resets must be scoped and communicated. APP_KEY rotation can make
encrypted application data/session unreadable and requires a dedicated plan.

## 3. Investigate

- Validate tenant/branch authorization, account status/menu/document state,
  route signature, upload path, and audit coverage.
- Correlate edge, backend-http, Laravel structured, Horizon/Reverb, MySQL, Redis,
  Midtrans, and object-store logs by UTC/request/correlation ID.
- For payment, compare every affected order with authoritative Midtrans status.
- For file exposure, inventory object/path/access without redistributing content.
- For dependency compromise, preserve lockfiles/image digest/SBOM/scan output.

External edge, S3/R2, OTLP/log sink, mail, and Midtrans consoles are not managed
by repository code and require their platform owners.

## 4. Eradicate and recover

Patch with negative regression tests, rotate compromised credentials, rebuild
immutable images from trusted source, and follow deployment/rollback runbooks.
Validate auth/IDOR, private files, webhook replay, concurrency, health/readiness,
queue, Reverb, and audit persistence before gradual traffic restore. Reconcile
missed jobs/webhooks and notify affected stakeholders under the applicable legal
and product policy.

## 5. Close

Document root cause, blast radius, accessed/changed data, containment, exact
credential rotations, data/payment reconciliation, validation evidence, and
owners/dates for follow-up. Update the threat model/runbook/tests without
publishing exploitable details. Use the private reporting channel in
`SECURITY.md` for coordinated disclosure.
