# Runbook: high API errors or latency

1. Break down 5xx and latency by route template and deployment revision. Never
   group by raw URL because it may contain identifiers and creates high
   cardinality.
2. Use a returned `X-Request-ID` or `X-Correlation-ID` to search structured
   logs and audit rows. Do not ask customers for cookies or tokens.
3. Check MySQL latency/locks, Redis latency, Horizon saturation, and Reverb
   health before restarting processes.
4. For booking errors, verify database conflict/transaction behavior; do not
   replace the database final truth with a Redis-only lock.
5. For payment errors, query authoritative gateway status before changing any
   local payment or booking state.
6. Roll back the offending revision when errors correlate with deployment.
7. After recovery, validate booking create, payment charge/status, provider
   authorization, and audit correlation smoke tests.
