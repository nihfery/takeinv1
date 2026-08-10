# Deployment runbook

## Preconditions

- Change, migration impact, image versions/digests, rollback owner, and window
  have approval.
- MySQL/Redis persistent volumes and an independently verified backup exist.
- DNS/TLS/edge routes, admin access control, mail, Midtrans, and optional
  storage/telemetry dependencies are explicitly verified or declared out of
  scope; repository templates do not provision them.
- Required secrets are supplied by the orchestrator. Never paste secret values
  into commits, workflow YAML, or shared logs.

## Preflight

```bash
docker compose --env-file <approved-env-file> config --quiet
docker compose --env-file <approved-env-file> build backend backend-http customer provider
```

Review rendered configuration without publishing it to a public artifact.
Confirm production URLs/hosts, `APP_DEBUG=false`, valid `APP_KEY`, host-only
session unless reviewed, Redis drivers, explicit Reverb origins, manual payment
confirmation false, and media retirement false. `REDIS_QUEUE_RETRY_AFTER` must
be greater than `HORIZON_WORKER_TIMEOUT`.

Run source gates before promotion: Composer/Pint/PHPUnit, MySQL concurrency
suite, route/migration parity, both Next builds, OpenAPI contracts, dependency /
secret / container scans, and HTTP runtime validator. A green concurrency suite
is not a load test; see `docs/architecture/concurrency-validation.md`.

## Staging rollout

1. Deploy the immutable Laravel and `backend-http` images to staging. From the
   exact verified commit SHA, build the customer/provider images with approved
   staging public variables. Use production-shaped MySQL/Redis and private
   origins.
2. Start the backend. Its entrypoint creates a private gzip pre-migration dump
   when migrations are pending, applies migration, and runs
   `app:deployment-check`. A backup or migration failure must keep backend
   unhealthy and stop rollout.
3. Start `backend-http`, Horizon, scheduler, Reverb, and frontends only after
   backend health succeeds.
4. Verify:

   ```bash
   docker compose --env-file <approved-env-file> exec --user www-data backend php artisan migrate:status
   docker compose --env-file <approved-env-file> exec --user www-data backend php artisan app:deployment-check
   docker compose --env-file <approved-env-file> exec horizon php artisan horizon:status
   curl --fail --show-error https://api-staging.example/api/health
   curl --fail --show-error https://api-staging.example/api/readiness
   ```

   Replace example staging URLs with inventory-approved hosts; do not copy them
   as production claims.

5. Smoke test customer, provider landing/sign-in/Blade dashboard, admin login /
   dashboard, public catalog category hierarchy/search, a non-money booking
   scenario, authorized and cross-tenant-denied resources, queue processing,
   scheduler, allowed/disallowed WebSocket origin, and signed private download.
6. For payment, use the approved Midtrans sandbox/test transaction; verify
   signature/status reconciliation, idempotent duplicate notification, audit,
   and booking state. Never mark paid from the browser.

## Production promotion

Promote the same validated Laravel and `backend-http` image digests/tags. Check
out the exact verified commit SHA and build customer/provider again with the
approved production public variables; do not claim a cross-environment frontend
digest because Next.js bakes those values into its image. Keep
`DEPLOY_BACKUP_BEFORE_MIGRATE` enabled. Monitor backend/HTTP health, readiness, MySQL locks, Redis, Horizon
backlog/failures, webhook errors, Reverb connections, audit persistence warnings,
and request/correlation IDs. Sample every host responsibility, including stronger
admin ingress. Pause and follow rollback on invariant, authorization, migration,
or payment reconciliation failure.

`platform/deploy/dokploy.env.example` and `docs/dokploy-deployment.md` are
templates. Real Dokploy environment, routes, secrets, DNS, and certificate state
must be verified in the deployment platform.
