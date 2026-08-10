# Runbook: Horizon queue saturation

1. Check `php artisan horizon:status`, supervisor status, queue depth, wait
   time, failure rate, worker memory, and Redis latency.
2. Prioritize `critical`, then payment/booking queues. Do not drain failed jobs
   blindly; inspect idempotency and after-commit guarantees first.
3. Use the queue payload's top-level `observability.request_id` and
   `observability.correlation_id` to follow the originating request. Never edit
   the serialized `data.command` payload.
4. Scale only the affected Horizon supervisor within configured database and
   gateway capacity.
5. Retry an idempotent failed job after its dependency recovers. Reconcile
   payment state against Midtrans before retrying payment mutations.
6. Confirm context cleanup by checking that adjacent jobs do not share request
   IDs, then document the failure class and queue wait window.
