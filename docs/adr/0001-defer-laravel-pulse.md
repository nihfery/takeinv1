# ADR 0001: Defer Laravel Pulse

- Status: accepted
- Date: 2026-08-10

## Context

Phase 7 asks for Laravel Pulse only where it is safe. Horizon already provides
queue operations, structured logs and request correlation are available, and
OTLP-compatible trace export is optional. Adding Pulse now would add package,
database-write, retention, and access-control decisions without workload and
storage measurements.

## Decision

Laravel Pulse is not installed or enabled in this refactor. This is an explicit
deferral, not a claim that Pulse is running.

Reconsider it only after:

1. representative staging load and write-amplification benchmarks exist;
2. a dedicated persistence/retention design is approved;
3. dashboard authentication and provider-tenant data exposure are reviewed;
4. sampling and pruning limits are tested against booking/payment peak load;
5. Pulse adds operational value not already covered by Horizon, OTLP, and the
   selected metrics/log platform.

## Consequences

The first production observability level uses JSON stderr logs, request and
correlation IDs, audit records, Horizon, and optional fail-open OTLP traces.
Technical and business metric dashboard/alert files remain integration
templates until a metrics backend and instrumentation mapping are deployed.
