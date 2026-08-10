# Runbook: observability backend unavailable

## Trigger

Collector health check fails, trace export failure notices appear, or Grafana /
Loki is unavailable while application health remains green.

## Immediate actions

1. Confirm `GET /api/health` and `GET /api/readiness` independently of the
   observability backend.
2. Set `OBSERVABILITY_TELEMETRY_ENABLED=false` and redeploy the environment if
   collector latency is affecting requests. Do not disable application audit
   writes or JSON `stderr` logs.
3. Confirm booking creation, payment status, and payment webhook smoke checks.
4. Inspect collector memory, queue, exporter authentication, DNS, and TLS.
5. Keep the collector retry queue bounded; never point application traffic at
   the telemetry backend through a shared blocking proxy.

## Recovery

1. Restore the collector in staging and confirm OTLP `/v1/traces` acceptance.
2. Re-enable export for one instance with a 50 ms application timeout.
3. Verify application p95 latency and error rate before rolling out.
4. Record the outage window; telemetry gaps are expected and must not be
   backfilled from sensitive raw request bodies.

## Escalation

Escalate only as an application incident when health/readiness, booking,
payment, audit persistence, or queue processing is also degraded.
