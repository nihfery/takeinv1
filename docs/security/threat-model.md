# Threat model

## Assets and boundaries

Critical assets are customer/provider identities, tenant isolation, booking
capacity, payment/subscription state, KTP/NIB/chat data, session/token/keys,
audit evidence, database backups, and operational availability. Trust boundaries
are public Next.js, Laravel edge/origin, webhook ingress, WebSocket gateway,
MySQL/Redis network, media storage, and optional telemetry/object-store egress.

## Primary threats and controls

| Threat | Implemented controls | Residual / required operations |
| --- | --- | --- |
| Credential stuffing/session theft | Login rate limits, hashed passwords, secure/http-only encrypted Redis session, regeneration | MFA/admin edge policy not provisioned; credential monitoring external |
| IDOR/BOLA/cross-tenant access | Auth middleware, provider owner/branch/menu/status/document scope, customer ownership checks, negative tests | Continue per-endpoint review; some compatibility controllers remain large |
| Booking double-spend/slot race | MySQL/InnoDB transaction, row locks, unique customer idempotency, real-process concurrency suite | Production load/capacity and deadlock monitoring still required |
| Coupon/payment replay | Coupon locking/quantity tests, signature verification, authoritative Midtrans status, order/amount/currency checks, state transitions/audit | Midtrans keys/IP/edge monitoring external; reconciliation runbook required |
| Malicious upload/data exposure | MIME+extension+size allowlist, private KTP/NIB/chat disks, signed and authorized downloads | No malware scanner; legacy public image flows and historical public objects remain |
| WebSocket impersonation | Explicit origin hosts, no client events, private channel actor/tenant/thread checks | DNS/TLS/edge and multi-instance production smoke test external |
| Secret/query leakage | `.env` ignored, orchestrator references, log redaction, queryless Nginx/FPM access logs, inbound ID overwrite | CI/host/log sink policy must be operated; historical logs need separate review |
| Dependency/supply-chain compromise | Lockfiles, Composer/npm audit and CI scan workflows | Pin/registry/provenance policy and timely remediation owned by maintainers |
| Redis/MySQL outage | Internal network, password, persistence, readiness, runbooks | HA/failover/backups/off-site restore not provisioned by repository |
| Telemetry outage/exfiltration | Optional fail-open exporter, bounded timeout/no retry, redaction | External collector trust, TLS, retention, and access controls not provisioned |

## Known residual risks

- Admin MFA and external access policy are not implemented here.
- API registration and provider sign-in retain CSRF compatibility exceptions.
- Audit persistence is fail-open and coverage is not universal.
- Malware scanning is absent; several public image flows remain legacy.
- S3/R2, CDN, DNS/TLS, OTLP/Grafana/Loki, alert delivery, mail delivery, MySQL
  HA/PITR, Redis HA, and off-site backup are templates or external work only.
- Category relation normalization is not a security boundary and legacy service
  rows may retain a nullable `category_id`.

Threat-model review is required for new host sharing, session domain widening,
new file types, new webhook providers, partner APIs, or data export features.
