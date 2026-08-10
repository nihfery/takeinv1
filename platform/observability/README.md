# YouYaku observability templates

This directory contains deployment templates, not provisioned infrastructure.
No Grafana, Prometheus, Loki, OpenTelemetry Collector, or managed telemetry
account is created by this repository.

## Application baseline

The Laravel runtime provides:

- validated `X-Request-ID` and `X-Correlation-ID` response headers;
- request/correlation context in application logs, queued-job metadata, and
  audit records;
- newline-delimited JSON logs on `stderr` in the production Compose topology;
- recursive redaction of credentials, identity-document metadata, payment raw
  payloads, contact PII, cookies, sessions, and tokens;
- an optional OTLP HTTP/JSON trace exporter with one short attempt, no retry,
  and a fail-open wrapper;
- Horizon as the queue operations dashboard.

Inbound identifiers are used only when
`OBSERVABILITY_TRUST_INBOUND_IDS=true`, the immediate peer matches Laravel's
`TRUSTED_PROXIES`, and each identifier passes the 64-character safe-character
validation. Otherwise Laravel creates a UUID and does not echo the untrusted
value.

Production examples keep this flag `false`. Enable it only after the deployed
HTTP ingress is verified to remove any client-supplied `X-Request-ID` and
`X-Correlation-ID` values and overwrite both headers with identifiers generated
by that ingress. Merely reaching Laravel through a trusted proxy is not enough,
because a proxy can forward the original client headers unchanged.

Do not expose the Laravel origin directly when trusting all proxies. In the
Compose production topology, `TRUSTED_PROXIES=*` is safe only while the origin
port remains loopback/private and the gateway is the sole ingress.

## Optional OpenTelemetry export

The exporter is disabled by default. A collector endpoint is not required for
booking, payment, audit, or queue processing.

```dotenv
OBSERVABILITY_TELEMETRY_ENABLED=true
OTEL_SDK_DISABLED=false
OTEL_SERVICE_NAME=youyaku-laravel
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/json
OTEL_EXPORTER_OTLP_TIMEOUT=50
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=production,service.namespace=youyaku
```

`OTEL_EXPORTER_OTLP_HEADERS` follows the OpenTelemetry comma-separated
`key=value` convention. Treat its value as a secret and inject it through the
deployment secret store. It is never written to the application logs.

The request exporter sends only route templates, method, status, timestamps,
and opaque request/correlation identifiers. It does not send query strings,
request bodies, raw URLs, user-agent strings, or authenticated user records.

All configured application log channels attach the keyed-context redactor.
Code must still never place credentials, signed URLs, or sensitive PII directly
inside a free-form log message: arbitrary message text cannot be reliably
classified or recovered by a context processor. Put only approved structured
fields in log context and let the redactor run before placeholder expansion.

If a collector is slow or unavailable, the client makes no retry and aborts
the attempt after the configured short timeout. Set
`OBSERVABILITY_TELEMETRY_ENABLED=false` for immediate mitigation; application
traffic does not need a restart when environment configuration is re-deployed
and workers are gracefully restarted.

## Templates

- `opentelemetry/collector.example.yaml`: OTLP receiver/processor/exporter
  starting point.
- `grafana/dashboards/youyaku-overview.json`: importable technical and business
  overview dashboard.
- `alerting/prometheus-rules.example.yaml`: alert policy examples.
- `loki/README.md`: safe structured-log ingestion and label guidance.
- `runbooks/`: operator response procedures referenced by the alerts.

Metric names in dashboard and alert templates are the target contract. Before
enabling alerts, map the selected ingress, collector, Horizon exporter, and
business-metric instrumentation to those names in staging. Missing metrics
must be treated as an incomplete integration, not as a healthy signal.

Laravel Pulse is intentionally deferred. See
`docs/adr/0001-defer-laravel-pulse.md` for the decision and prerequisites.
