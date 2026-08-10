# ADR-003: Redis shared runtime

- Status: Accepted
- Date: 2026-08-10

## Context

Multi-instance Laravel tidak boleh kehilangan session atau mengandalkan cache
dan queue lokal. Horizon dan Reverb juga memerlukan koordinasi shared.

## Decision

Gunakan Redis/PhpRedis untuk session, cache, queue, rate limiter, Horizon
metadata, dan Reverb scaling. Pisahkan database logis: default `0`, cache `1`,
session `2`, queue `3`, rate limit `4`, Horizon `5`, Reverb `6`. Production
memakai persistence AOF dan `noeviction`; `retry_after` queue harus melebihi
worker timeout.

## Consequences

Session dan job dapat diproses lintas instance, tetapi Redis menjadi dependency
readiness kritis. Outage ditangani dengan fail-closed untuk auth/write yang
memerlukan state; operator tidak boleh mengganti queue ke sync secara diam-diam.
Network/database separation logis bukan pengganti ACL dan backup.
