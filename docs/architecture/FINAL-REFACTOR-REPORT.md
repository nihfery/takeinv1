# TAKEIN enterprise refactor — final validation report

Status date: 2026-08-10 (Asia/Bangkok)

This report records the repository state produced by PHASE 0 through PHASE 9
and the evidence available in the working tree. It does not claim that an
external production environment exists or that the repository evidence alone
approves a production launch.

The permanent architecture is still a Laravel modular monolith, with public
Next.js surfaces and server-rendered Blade dashboards. The detailed design and
operational documents are [ARCHITECTURE.md](../../ARCHITECTURE.md), the
[system context](system-context.md), the [deployment runbook](../runbooks/deployment.md),
and the [rollback runbook](../runbooks/rollback.md).

## 1. What was moved

The refactor first performed path-only relocation, then modularized the Laravel
namespace. The principal path moves were:

| Before | After | Responsibility |
| --- | --- | --- |
| Laravel application at repository root | `backend/laravel-core` | REST API, webhooks, Blade admin/provider, queue, Reverb |
| `frontend/customer-landing` | `apps/customer-web` | Customer marketplace Next.js |
| `frontend/provider-landing` | `apps/provider-landing` | Provider public landing/sign-in Next.js |
| Root Laravel `tests` | `backend/laravel-core/tests` | Laravel feature, unit, and concurrency tests |
| `Dockerfile.laravel` and `docker/laravel-entrypoint.sh` | `backend/laravel-core/Dockerfile` and `platform/docker/laravel-entrypoint.sh` | Laravel image and runtime entrypoint |
| `Dockerfile.next` | `platform/docker/Dockerfile.next` | Shared Next.js build definition |
| `deploy/dokploy.env.example` | `platform/deploy/dokploy.env.example` | Deployment environment template |
| Root helper/debug scripts and generated artifacts | `tools/legacy/laravel` and `tools/legacy/artifacts` | Explicitly isolated legacy tooling |

New repository-owned boundaries were also added under `contracts/openapi/v1`,
`docs`, `platform/gateway`, `platform/observability`, `tools/ci`, and
`tools/validation`. The resulting top-level layout is:

```text
.
|-- apps/
|   |-- customer-web/
|   `-- provider-landing/
|-- backend/
|   `-- laravel-core/
|-- contracts/
|   `-- openapi/v1/
|-- docs/
|-- platform/
|-- tools/
|-- docker-compose.yml
`-- README.md
```

All 77 classes listed in the relocation manifest were moved into domain-owned
modules. No mapped source class remains at its old path. Compatibility-focused
global middleware and framework bootstrap code remain under the conventional
Laravel `app/Http/Middleware`, `app/Providers`, and `bootstrap` locations.

## 2. Namespace mappings

The mechanical rule is that business code moved from flat `App\Models`,
`App\Services`, controller, event, and support namespaces to
`App\Modules\{Domain}\{Layer}`. The important mappings are:

| Legacy namespace/examples | Current namespace/owner |
| --- | --- |
| `App\Models\User`, `App\Models\AdminProfile`, authentication controllers | `App\Modules\Identity\...` |
| `App\Models\CustomerProfile`, `App\Models\CustomerActivity`, customer/profile controllers | `App\Modules\Customer\...` |
| `App\Models\ProviderProfile`, provider roles/permissions, provider profile/dashboard controllers | `App\Modules\Provider\...` |
| `App\Models\ProviderBranch` and branch controllers | `App\Modules\Branch\...` |
| `App\Models\Service`, `App\Models\ServiceCategory`, service/category/public-catalog controllers | `App\Modules\Catalog\...` |
| `App\Models\ProviderStaff`, `StaffSkill`, `StaffSchedule`, staff controllers | `App\Modules\Staff\...` |
| `App\Models\Booking`, `BookingParticipant`, `App\Services\BookingFlowService`, booking controllers | `App\Modules\Booking\...` |
| Availability conflict and eligible-staff logic extracted from booking flow | `App\Modules\Availability\Application\Actions\...` |
| `App\Models\Payment`, `PaymentGatewayTransaction`, `App\Services\MidtransService`, payment/webhook controllers | `App\Modules\Payment\...` |
| Subscription models, entitlement service, API controller, and legacy grant command | `App\Modules\Subscription\...` |
| Coupon model/service/controllers | `App\Modules\Promotion\...` |
| Branch/staff reviews and review controller | `App\Modules\Review\...` |
| Application notification model/service/event/controller | `App\Modules\Notification\...` |
| Chat thread/message models, events, unread counter, access service, presenter | `App\Modules\Chat\...` |
| `App\Http\Controllers\SupportChatController` | `App\Modules\Support\Presentation\Web\SupportChatController` |
| New storage abstraction, private delivery, and migration manifest | `App\Modules\Media\...` |
| New security audit action/model | `App\Modules\Audit\...` |

Within a module, application actions/queries/services contain orchestration,
infrastructure owns Eloquent/storage/gateway adapters, presentation owns
controllers, and domain owns framework-light events/value rules. The dependency
rules are documented in [module dependencies](module-dependencies.md) and the
ownership table in [domain boundaries](domain-boundaries.md). `app/Shared` is
reserved for technical cross-cutting code, not business-domain dumping.

Two compatibility details are intentional:

- The Sanctum polymorphic discriminator `App\Models\User` is retained through
  an explicit morph map, so existing personal-access tokens remain readable.
- `BookingFlowService` remains a public compatibility facade while delegating
  conflict, staff eligibility, and service-resolution logic to application
  boundaries.

## 3. Behavior intentionally unchanged

The refactor preserved these contracts unless a reviewed security/operations
addition is listed later in this report:

- Laravel remains the permanent core backend. No Go service, microservice,
  gRPC, Kafka, or dashboard SPA rewrite was introduced.
- Admin and provider dashboards remain Blade. Customer and provider public
  surfaces remain Next.js.
- Existing API URLs remain under `/api`; the `routes/api/v1` and
  `contracts/openapi/v1` directories are ownership/versioning boundaries and do
  not expose a new `/api/v1` prefix.
- All 296 baseline method/URI/name route records remain present. No legacy route
  was removed or renamed.
- All 75 baseline migration files remain byte-for-byte represented by the same
  canonical inventory hash. Refactor schema changes are additive migrations.
- Existing response shapes were documented rather than replaced with a new
  response envelope. The OpenAPI files intentionally allow heterogeneous legacy
  response objects where controller behavior differs.
- Existing booking/payment/coupon/idempotency behavior was retained while
  adding locks, authoritative gateway reconciliation, and negative-path tests.
  The real-process concurrency suite confirms single-winner behavior rather
  than redefining it.
- Existing web route names, Blade view references, event channel names, event
  names, and broadcast payloads were retained.
- User-facing copy and historical `JasaKu`/`SalonKu` labels were not globally
  renamed. Their continued presence is product compatibility, not an unfinished
  filesystem move.
- The local public ports remain backend `8000`, provider landing `5173`,
  customer web `5174`, and Reverb `8080`.
- Session cookies remain host-only by default. A broad `.takein.id` session
  cookie was not silently enabled.

The customer payment page, private document/attachment downloads,
`/api/readiness`, and Horizon routes are reviewed additions, not replacements
for baseline routes.

## 4. Security fixes

The following controls are implemented and covered by automated tests or
runtime validation:

- Provider API writes enforce an authenticated provider actor, menu permission,
  owner/branch scope, and owner-only subscription purchase. Cross-tenant and
  branch-account denial cases are tested.
- KTP/NIB documents use private storage for new writes. Authenticated,
  short-lived signed actions re-authorize the owner/admin at read time; raw
  object paths are hidden from serialization and document access is audited.
- Chat attachments use private storage and an authorized download controller.
- Upload validation combines authorization, generated object keys, MIME/type,
  original extension, size, visibility, and applicable image validation. Raw
  SVG uploads are excluded from reviewed image flows.
- Midtrans notifications require signature validation and an authoritative
  status lookup. Transaction locks, amount/currency checks, terminal-state
  monotonicity, and idempotent side effects prevent replay/regression. Browser
  payment confirmation is disabled in production.
- Named rate limiters cover authentication, registration/reset, search,
  availability, booking/payment writes, coupon checks, provider writes, and
  webhooks with actor/business-aware keys.
- Sensitive admin/provider actions write fail-open audit records with redacted
  snapshots and request/correlation identifiers. Credentials, document paths,
  document content, and rejection notes are excluded.
- Request and correlation IDs are generated after trusted-proxy normalization;
  untrusted inbound values are rejected by default. IDs propagate to queued jobs
  and audit records.
- Structured log context is recursively redacted. HTTP/FPM access logging omits
  query strings, preventing signed download tokens from being written there.
- Reverb requires explicit allowed origins, disables client events, and applies
  existing chat tenant/participant/permission/thread-lifecycle authorization.
- The production application rejects malformed or unsupported `APP_KEY` values,
  `APP_DEBUG=true`, unsafe manual payment confirmation, unsafe Reverb origins,
  and invalid runtime relationships through `app:deployment-check`.
- Secret, dependency, backend security, contract, and container scan workflows
  are present. Workflow actions are pinned to commit SHAs; no real secret values
  were added to example environments or workflow files.

The threat boundaries and operational response are detailed in
[SECURITY.md](../../SECURITY.md), the [threat model](../security/threat-model.md),
and the [security incident runbook](../runbooks/security-incident.md).

## 5. Redis and Horizon changes

Production defaults now use password-protected Redis for distributed runtime
state. The repository-owned Compose service is `redis:7.4-alpine`, private to
the data network, AOF-persistent, and configured with `noeviction`. It has no
host-published port.

| Redis database | Owner |
| --- | --- |
| 0 | Default locks/general connection |
| 1 | Cache |
| 2 | Sessions |
| 3 | Queue |
| 4 | Rate limiting |
| 5 | Horizon metadata |
| 6 | Reverb scaling/coordination |

Laravel Horizon `5.48.2` manages three supervisor groups: critical; business
queues (`payments`, `bookings`, `default`); and background queues
(`notifications`, `emails`, `media`, `analytics`). Its dashboard requires an
authenticated admin. Metrics snapshots are scheduled every five minutes.

Queue dispatch is after-commit by default. Redis queue `retry_after` is 120
seconds while the Horizon worker timeout is 90 seconds, preventing a normal
worker timeout from making the same job visible too early. Queued chat and
notification broadcasts have bounded tries, timeout, and backoff.

Live PHASE 6 validation proved cross-process cache/session/rate-limit state,
distributed lock exclusion, rollback enqueue count zero, commit enqueue count
one, Horizon draining, AOF persistence, authenticated Redis, and readiness
failure/recovery (`503` while Redis was down, then `200` after recovery).
PHASE 9 repeated that failure path against the final image: unauthenticated
Redis returned `NOAUTH`, authenticated `PING` returned `PONG`, runtime AOF was
enabled, a sentinel survived restart, readiness changed `200 -> 503 -> 200`,
and the sentinel was deleted. This drill exposed and fixed an intervening
logging-permission edge: readiness now preserves its sanitized 503 response
even if the logging destination also fails, and documented/deployment Artisan
commands run as `www-data` so they cannot recreate root-owned runtime files.

## 6. Docker and runtime changes

The production-shaped runtime is documented in [container architecture](containers.md).
The active Compose topology is:

| Service | Process and boundary | Default host exposure |
| --- | --- | --- |
| `db` | MySQL 8.0 on private data network, persistent volume | None |
| `redis` | Redis 7.4 on private data network, persistent volume | None |
| `backend` | One Laravel image running PHP-FPM | Private port `9000` |
| `backend-http` | Dedicated Nginx FastCGI gateway | `127.0.0.1:8000 -> 8080` |
| `horizon` | Same Laravel image, `php artisan horizon` as `www-data` | None |
| `scheduler` | Same Laravel image, `php artisan schedule:work` as `www-data` | None |
| `reverb` | Same Laravel image, `php artisan reverb:start` as `www-data` | `127.0.0.1:8080 -> 8080` |
| `customer` | Standalone customer Next.js | `5174` |
| `provider` | Standalone provider Next.js | `5173` |
| `nginx` profile | Optional local WebSocket gateway | `127.0.0.1:8088` |

Only `backend` runs the entrypoint migration step. Horizon, scheduler, and
Reverb set `RUN_DATABASE_MIGRATIONS=false`. Before pending migrations, the
entrypoint can create a private gzip MySQL snapshot and stops rollout if backup,
migration, or deployment validation fails. This snapshot is not an off-site
backup program.

The HTTP process changed from a development PHP server to PHP-FPM behind an
always-on Nginx gateway while preserving the loopback `127.0.0.1:8000` contract.
Storage ownership repair is bounded to the expected Laravel writable tree and
refuses symlink components. Nginx mounts storage read-only. Health/readiness are
separate: `/api/health` is the existing application probe and
`/api/readiness` checks MySQL plus all active Redis dependencies.

The intended production host mapping is `takein.id` (customer Next.js),
`partners.takein.id` (provider landing), `provider.takein.id` (provider Blade),
`admin.takein.id` (admin Blade), `api.takein.id` (REST), `hooks.takein.id`
(webhooks), and `ws.takein.id` (Reverb). `assets.takein.id` is a future media/CDN
boundary. These are architecture responsibilities only; DNS, certificates, and
edge routing were not provisioned by this repository change.

## 7. Routes before versus after

| Inventory | PHASE 0 | Current | Result |
| --- | ---: | ---: | --- |
| Total routes | 296 | 325 | 29 reviewed additions |
| Immutable legacy method/URI/name records | 296 | 296 | Exact parity |
| Legacy route SHA-256 | `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb` | Same | Exact parity live and route-cached |
| Public `/api/v1` routes | 0 | 0 | Intentionally unchanged |

The 28 additions are exactly four signed provider-document routes, one private
chat-attachment route, one readiness route, and 22 Horizon UI/API routes. The
Horizon surface is admin-authorized. The partner route registrar remains empty
and reserved; the OpenAPI partner contract intentionally has no active paths.

The canonical gate sorts `{methods, uri, name}` using ordinal comparison and
hashes UTF-8 without BOM. It normalizes only Laravel's ephemeral
`generated::*` names for the two intentionally unnamed framework routes. Run it
with:

```powershell
pwsh -File tools/validation/compare-routes.ps1 -ShowEvidence
```

## 8. Tests executed and result

The phase-by-phase evidence is summarized below. Counts are the full Laravel
suite recorded at that gate, not an accumulated sum.

| Phase | Laravel result | Other decisive evidence |
| --- | --- | --- |
| PHASE 0 | 79 tests / 612 assertions, PASS | 296 routes and 75 migrations inventoried; clean frontend install blocked by a Windows-locked SWC binary and explicitly not called green |
| PHASE 1 | 79 / 612, PASS | Path relocation parity; customer/provider builds 20/2; Docker daemon unavailable at that point |
| PHASE 2 | 80 / 615, PASS | 77 mapped classes moved; autoload, route reflection, Blade, Sanctum compatibility pass |
| PHASE 3 | 80 / 615, PASS | 296-route live and cached fingerprint exact after route-file split |
| PHASE 4 | 81 / 622, PASS | Extracted booking/catalog/dashboard/chat boundary slices pass |
| PHASE 5 | 136 / 1,066, PASS | Authorization, upload, Midtrans, rate-limit, and audit regressions pass |
| PHASE 6 | 150 / 1,196, PASS | Redis/Horizon multi-process and failure/recovery Docker gates pass |
| PHASE 7 | 192 / 1,506, PASS | PHP-FPM/Nginx, Reverb, media, telemetry, redaction, runtime, and live Docker gates pass |
| PHASE 8 | 201 / 1,643, PASS | OpenAPI contract, CI/workflow, documentation, route/migration, security, and build gates pass locally |
| PHASE 9/final tree | 202 / 1,650, PASS | Final readiness/logger regression plus the complete unit, feature, security, runtime, and concurrency suite |

The dedicated MySQL concurrency suite is
`backend/laravel-core/tests/Concurrency/BookingConcurrencyTest.php`. It launches
real child PHP processes, waits for every readiness marker, and releases them
through one filesystem start barrier against the same MySQL schema. Three
consecutive full executions passed, each with 6 tests and 75 assertions:

- capacity `N=6` on one slot produces exactly one commit;
- concurrent retries with the same idempotency key resolve to one booking;
- concurrent hold finalization creates one payment/activity side effect;
- coupon quantity one has one winner and golden total `94,500`;
- competing reschedules have one winner; and
- duplicate payment notifications preserve one paid/confirmed transition and
  one activity side effect.

Booking create/finalize/reschedule transactions use a bounded five-attempt retry
for an InnoDB deadlock victim. The row locks, unique/idempotency contract,
availability recheck, and transaction commit still enforce the single-winner
invariant. This is concurrency correctness evidence, not a load or capacity
benchmark. See the [concurrency report](concurrency-validation.md).

Additional recorded gates include Composer strict validation, optimized
autoload, locked Composer audit with zero advisories, PHP syntax, the Pint
ratchet, Laravel cache builds, route and migration parity, npm audits with zero
known vulnerabilities, Compose rendering, image builds, Nginx/FPM syntax,
runtime health/readiness, dependency/secret/container scanning definitions, and
the seven-surface OpenAPI validator.

Interactive browser automation was unavailable in this execution environment.
Authenticated dashboard HTTP rendering and dashboard feature tests passed, but
this report does not convert those HTTP checks into a claim that a human-style
browser click-through was performed.

PHASE 8 documentation validation found all 51 required artifacts, 54 Markdown
files before this report, zero broken local Markdown links, zero unbalanced
fences, and zero trailing-whitespace findings.

PHASE 9 added bounded operational checks:

- The local mixed HTTP load smoke sent 120 read-only requests at concurrency 6
  across health, readiness, category, service, customer, and provider routes.
  All 120 returned HTTP 200 with zero request/application errors in 614.633 ms:
  195.239 requests/second, p50 22.095 ms, p95 59.855 ms, p99 77.861 ms, and
  maximum 91.778 ms. The configured gates were p95 at most 2,000 ms, p99 at
  most 5,000 ms, and at least 5 requests/second. An earlier concurrency-12 run
  timed out on this single-replica local stack, so this successful result is a
  smoke/stability bound, not production capacity evidence.

  ```powershell
  node tests\load\basic-http-load.mjs --backend-base-url http://127.0.0.1:28100 --customer-base-url http://127.0.0.1:15174 --provider-base-url http://127.0.0.1:15173 --requests 120 --concurrency 6 --timeout-ms 15000 --max-p95-ms 2000 --max-p99-ms 5000 --min-throughput-rps 5
  ```

- `tools/validation/backup-restore-drill.ps1` created a transaction-consistent
  MySQL 8.0.46 dump from the isolated `takein_phase8_gate` source, restored it
  only into `youyaku_phase9_restore`, and compared all 38 tables, all row
  counts, 79 migration records, schema fingerprint
  `587f5918b830da30719a7f1585c0830bbb4a3a83cbbfc82e8b5332d86ceaacad`,
  and migration-manifest fingerprint
  `5d18723f3a68883185c617381f5cdc6eaf614eab5b2d377a38e4c79317a77c49`.
  The 946,951-byte dump had SHA-256
  `b26326eba180c4835544d8770ac41bcbd87edf7d6aa758d68ce58db6194de35a`.
  Sample source/restored counts included 126 users, 100 branches, 25 service
  categories, 750 services, 20 bookings, and 20 payments. The script did not
  separately time the restore or boot Laravel against the temporary database;
  it instead proved exact schema, migration, table, and row-count parity.
  Cleanup verified the dump was removed, the bounded restore database was
  dropped, and the source database remained present.
- The Redis recovery drill described in Section 5 passed against the final
  rebuilt Laravel image. All nine isolated Compose services were healthy
  afterward; deployment validation and pending-migration checks ran as
  `www-data`, Horizon reported running, and health/readiness plus both Next.js
  surfaces returned HTTP 200.
- A rollback-mechanics drill recreated only stateless `backend-http` first from
  a bounded candidate tag and then from its original `:gate` pin. Both pins
  resolved to exact image ID
  `sha256:cda574d9a33e11f98e522e27d0e5df9d9fba7a833b9fd07616eec05c0ab795d4`;
  health/readiness remained 200 and the database remained at 79 migrations,
  750 services, 126 users, and 20 bookings. No trustworthy older compatible
  image existed locally, so this proves orchestrator pin/recreate/recovery
  mechanics only—not previous-release/schema compatibility. The temporary tag
  was removed and the original pin restored.
- After every PHASE 9 drill completed, project labels, service names, network
  attachments, and volume consumers were checked again. Exactly nine isolated
  containers, two isolated networks, and three isolated test volumes were
  removed; the unrelated `skymap` volume/network remained present. This
  intentionally discarded only the fresh demo database, Redis state, and test
  storage created for the gate; none of it was production data.

## 9. Frontend builds executed and result

Both current Next.js applications completed clean production builds:

| Application | PHASE 0 declared routes | Current build-table entries | Result |
| --- | ---: | ---: | --- |
| `apps/customer-web` | 20 | 21, including generated `/_not-found` | `npm ci`, `npm audit`, and `npm run build` PASS; 0 known vulnerabilities |
| `apps/provider-landing` | 2 | 3, including generated `/_not-found` | `npm ci`, `npm audit`, and `npm run build` PASS; 0 known vulnerabilities |

The initial Windows SWC lock was an environmental blocker and was resolved in
the relocated directories; it was not hidden. Neither package currently defines
a separate lint or frontend test script, so the available frontend release gate
is the clean production build. The customer payment route was a reviewed PHASE
5 product/security addition; the provider declared-route count stayed two.

## 10. Migration count, hash, and parity

| Inventory | PHASE 0 | Current | Result |
| --- | ---: | ---: | --- |
| Total tracked migrations | 75 | 79 | Four reviewed additive migrations |
| Immutable legacy migrations | 75 | 75 | Exact count and content parity |
| Legacy migration SHA-256 | `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f` | Same | PASS |
| Pending migrations at final local gate | 0 | 0 | PASS |

The four allowlisted additions are:

1. `2026_08_10_000002_create_audit_logs_table.php`;
2. `2026_08_10_000003_add_midtrans_checkout_fields_to_provider_subscriptions.php`;
3. `2026_08_10_000004_create_media_migration_entries_table.php`; and
4. `2026_08_10_000010_add_correlation_id_to_audit_logs_table.php`.

The pre-existing `2026_08_10_000001_add_age_group_to_booking_participants.php`
is part of the immutable 75-file baseline, not one of the refactor additions.
The migration validator removes only the four reviewed additions, hashes each
remaining filename plus raw-content SHA-256 in ordinal order, and compares the
result to the PHASE 0 fingerprint:

```powershell
pwsh -File tools/validation/compare-migrations.ps1 -ShowEvidence
```

The deployment policy is expand/contract and forward-compatible where possible.
Historical migrations must not be edited, and `migrate:rollback` is not a safe
generic incident response. See [migration policy](../database/migration-policy.md)
and [backup/restore](../database/backup-restore.md).

## 11. Known pre-existing defects

One explicit pre-refactor code defect remains preserved rather than guessed at:

- `backend/laravel-core/app/Http/Controllers/Api/Customer/CartController.php`
  imports the nonexistent `App\Models\CustomerCart`. The controller has no
  registered route in the current route inventory, so it is not executable
  through a routed HTTP surface. It must not be mapped to `CustomerActivity`,
  which has different semantics. Product/data-model ownership must decide
  whether to implement a cart aggregate or delete the orphan in a separate,
  reviewed change.

Large legacy controllers, including the compatibility-oriented support chat
surface, remain technical debt, but are not labeled defects without a failing
behavior. Historical `JasaKu`/`SalonKu` labels are intentionally preserved
product behavior rather than a defect.

## 12. New unresolved risks

The following risks are not concealed by the green repository gates:

- Midtrans behavior is covered by isolated authoritative-response, signature,
  replay, amount/currency, and idempotency tests. No approved external Midtrans
  sandbox credential/charge was available, so an actual sandbox transaction and
  notification round trip was not exercised.
- No external S3/R2 bucket, CDN, DNS, TLS certificate, edge/WAF route, OTLP
  collector, Grafana, Loki, managed Redis/MySQL, HA topology, or off-site backup
  program was provisioned. Repository files are configuration/templates only.
- Production backup encryption, retention, off-site copy, PITR, RPO/RTO, media
  backup/versioning, and recurring restore drills remain platform-owner work.
  The pre-migration dump on the Laravel storage volume is not an independent
  backup.
- Stronger admin ingress controls, enforced MFA, and malware/content scanning
  for uploads are not implemented by this repository refactor.
- Historical public KTP/NIB objects have not been deleted. Reads are protected
  by application authorization, but already-known legacy URLs can remain
  reachable until copy/checksum/cutover succeeds, the acceptance window is at
  least 30 days, retirement is separately enabled, and the private archive is
  verified. No production object retirement was performed.
- The optional S3/R2 adapter is configured but local public/private disks remain
  the default. Durability, lifecycle, encryption, replication, IAM, and bucket
  policy have not been externally validated.
- Interactive browser automation was unavailable. Authenticated dashboard HTTP
  rendering passed, but browser navigation, JavaScript, cookie, and edge behavior
  still require staging browser smoke tests.
- The bounded local load smoke is not a production capacity test. It cannot
  establish safe throughput, autoscaling, connection-pool limits, tail latency,
  or saturation behavior for production infrastructure.
- The local rollback drill validated exact image-pin and stateless service
  recreation mechanics with the same image bytes. No known-good older image
  was available, so a real previous-release rollback, current-schema
  compatibility, write quiescing, and queued-work reconciliation still require
  a staging release drill.
- Laravel and `backend-http` images publish GHCR tags of the form
  `sha-${FULL_COMMIT_SHA}`, but registry tags are still mutable. Images are not
  cryptographically signed, and deployment does not yet require signature or
  attestation verification.
- Customer/provider images are rebuilt on the destination from the checked-out
  exact source SHA because environment-specific public values are baked into
  Next.js. They are therefore exact-source builds, not promotion of the same
  immutable, scanned frontend artifact between environments.
- GitHub workflows are locally validated and actions are SHA-pinned, but this
  repository change cannot prove external branch protection, environment
  approvals, repository variables/secrets, runner policy, or successful remote
  workflow execution until the GitHub environments are configured and run.
- Observability export is deliberately fail-open with a bounded 20–250 ms
  timeout and no retry, so telemetry loss is possible during collector failure.
  This protects booking/payment availability but requires an external alerting
  and collection program.

These items mean “repository implementation validated,” not “production launch
approved.”

## 13. Follow-up work

Before production traffic, owners should complete these items in priority order:

1. Provision and verify MySQL 8, Redis, DNS/TLS/edge routes, environment secrets,
   strong admin ingress/MFA, mail, approved Midtrans sandbox/live accounts,
   storage, and an off-site backup/restore program.
2. Run the complete staging deployment checklist, including a real Midtrans
   sandbox payment/webhook round trip, authenticated browser smoke tests,
   tenant-denial tests, WebSocket allowed/denied origins, private downloads,
   queue/scheduler behavior, and restored-backup validation.
3. Establish load objectives from product traffic assumptions, then run a
   production-shaped load/capacity test with MySQL locks, Redis, Horizon,
   PHP-FPM, latency percentiles, errors, resource saturation, and recovery
   observed. Keep the concurrency correctness suite as a separate gate.
4. Complete legacy media inventory, copy, checksum, cutover, and the acceptance
   window. Enable retirement only after at least 30 days and explicit backup,
   archive, and rollback approval.
5. Publish images by immutable digest, add signing/attestation verification,
   and decide whether environment-specific frontend artifacts will be built,
   scanned, and promoted as immutable release artifacts per environment.
6. Configure GitHub protected branches/environments, required checks, deployment
   variables/secrets, approval rules, CODEOWNERS enforcement, and run every
   workflow on the exact candidate SHA.
7. Resolve the orphan Cart controller through an explicit product/data-model
   decision. Continue decomposing large compatibility controllers only behind
   route/contract/behavior tests.
8. Retire the Pint legacy baseline incrementally. Keep the ratchet green and do
   not rewrite unrelated files only for formatting.
9. Decide whether to activate Laravel Pulse under the deferred ADR; do not add
   it without a storage, access-control, and operations plan.
10. Add partner APIs or `/api/v1` aliases only with a client migration and
    compatibility plan. Their current empty/reserved state is intentional.

## 14. Commands to run locally

The authoritative newcomer setup is [README.md](../../README.md). The shortest
production-shaped Docker path from the repository root is:

```powershell
Copy-Item .env.example .env
php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
docker compose --env-file .env config --quiet
docker compose --env-file .env up -d --build
docker compose --env-file .env ps
docker compose --env-file .env exec --user www-data backend php artisan migrate --force --no-interaction
docker compose --env-file .env exec --user www-data backend php artisan app:deployment-check
```

Insert the generated values into the ignored `.env`: one Laravel `APP_KEY` and
distinct MySQL user, MySQL root, and Redis passwords. Do not paste them into a
commit or shared log. Optional snapshot seeding is testing-only and replaces
all application table data except the Laravel migration history:

```powershell
docker compose --env-file .env exec --user www-data backend php artisan db:seed --force
```

After creating the separate MySQL database `salonku_testing_fresh` exactly as
documented in the README, the backend and concurrency gates can be run natively
from `backend/laravel-core`:

```powershell
composer install --prefer-dist --no-interaction
composer validate --strict --no-check-publish
composer audit --locked
composer dump-autoload --optimize
php ../../tools/ci/check-pint-baseline.php
php artisan test
php artisan test --testsuite=Concurrency --colors=never
php artisan route:list
php artisan migrate:status
php artisan app:deployment-check
```

Run the concurrency suite by itself; it truncates/manages the shared fixture
schema and requires MySQL plus process-control support. From the repository root,
run the independent parity, contract, workflow, and runtime gates:

```powershell
pwsh -File tools/validation/compare-routes.ps1 -ShowEvidence
pwsh -File tools/validation/compare-migrations.ps1 -ShowEvidence
pwsh -File tools/validation/validate-http-runtime.ps1
php tests/contract/validate-openapi.php
php tools/ci/validate-workflows.php
php tools/ci/php-lint.php
```

Build both frontends from clean dependency installs:

```powershell
Set-Location apps/customer-web
npm ci
npm audit
npm run build
Set-Location ../provider-landing
npm ci
npm audit
npm run build
Set-Location ../..
```

Local surfaces are customer `http://127.0.0.1:5174`, provider landing
`http://127.0.0.1:5173`, provider Blade `/provider/login`, admin Blade
`/admin/login`, and backend health/readiness on
`http://127.0.0.1:8000/api/health` and `/api/readiness`. Stop without deleting
persistent volumes:

```powershell
docker compose --env-file .env down
```

Do not use `down -v` as a normal reset or deployment command.

## 15. Commands to deploy

Deployment is intentionally GitHub-environment driven. Before dispatch,
configure each `staging`/`production` environment with the documented
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_PATH`,
`DEPLOY_ENV_FILE`, `DEPLOY_COMPOSE_PROJECT`, `DEPLOY_ALLOWED_BRANCH`, and
`DEPLOY_SMOKE_URL` variables plus private `DEPLOY_SSH_KEY` and
`DEPLOY_KNOWN_HOSTS` secrets. The environment file must live outside the Git
worktree.

Push the exact candidate commit to the allowed branch and wait for all required
workflows, including image publication, to pass. Then dispatch staging with a
full 40-character commit SHA:

```bash
DEPLOY_SHA="$(git rev-parse HEAD)"
test "${#DEPLOY_SHA}" -eq 40
gh workflow run deploy-staging.yml --ref main -f commit_sha="$DEPLOY_SHA"
gh run list --workflow deploy-staging.yml --commit "$DEPLOY_SHA"
```

The workflow verifies required CI runs for that SHA, uses strict SSH host-key
checking, checks that the clean remote checkout is an ancestor of the allowed
branch, pulls `laravel:sha-${FULL_COMMIT_SHA}` and
`backend-http:sha-${FULL_COMMIT_SHA}`, builds
the customer/provider images from that exact checkout with staging public
values, starts the stack without rebuilding the backend images, runs deployment
checks, and probes the configured smoke URL.

After staging acceptance and production approval, dispatch the same candidate:

```bash
gh workflow run deploy-production.yml --ref main -f commit_sha="$DEPLOY_SHA"
gh run list --workflow deploy-production.yml --commit "$DEPLOY_SHA"
```

Then verify the actual approved hosts, not example URLs:

```bash
curl --fail --show-error https://api.takein.id/api/health
curl --fail --show-error https://api.takein.id/api/readiness
```

Also verify migration status, `app:deployment-check`, Horizon, scheduler,
Reverb, authenticated customer/provider/admin flows, cross-tenant denials,
private downloads, and Midtrans reconciliation through the deployment
checklist. The repository does not create DNS, TLS, edge policies, SSH hosts,
GitHub environments, or external services.

## 16. Rollback procedure

Choose rollback by failure class; do not treat every incident as a database
rollback.

1. Declare the incident/change, stop new writes at the edge or enter Laravel
   maintenance mode, record the deployed image digests and target release, and
   pause Horizon if queued writes are incompatible.
2. For an application-only regression, prove that the previous release can read
   the expanded current schema. Dispatch the deployment workflow with the exact
   last-known-good 40-character SHA, or pin the previously recorded image digest
   directly in the approved orchestrator. Because GHCR tags are mutable, verify
   the pulled digest against the release record.
3. The current frontend path rebuilds from the selected exact SHA with the
   target environment's public values. Record those resulting image IDs; do not
   claim they are byte-identical to an earlier environment build.
4. Run `app:deployment-check`, `migrate:status`, health/readiness, Horizon,
   scheduler, Reverb, authenticated dashboards, tenant-denial, booking, coupon,
   and payment status checks before reopening traffic.
5. Continue Horizon and traffic gradually. Reconcile queued jobs and every
   Midtrans notification/transaction that occurred during the incident window.

The current automated application rollback dispatch is:

```bash
: "${ROLLBACK_SHA:?set ROLLBACK_SHA to the approved previous 40-character SHA}"
test "${#ROLLBACK_SHA}" -eq 40
gh workflow run deploy-production.yml --ref main -f commit_sha="$ROLLBACK_SHA"
gh run list --workflow deploy-production.yml --commit "$ROLLBACK_SHA"
```

For schema/data corruption, prefer a reviewed forward fix. Never run
`migrate:rollback` blindly. If restore is required, stop every writer, preserve
an emergency current-state snapshot where possible, validate the source,
target, and checksum with a second operator, restore to an isolated/new MySQL
instance, and follow [backup/restore](../database/backup-restore.md). The
pre-migration dump does not include Redis jobs/sessions, media, or external
Midtrans state.

For media cutover rollback, first run the checksum-aware preview and proceed
only with approved output:

```bash
cd backend/laravel-core
php artisan media:migrate-legacy --scope=all --stage=rollback
php artisan media:migrate-legacy --scope=all --stage=rollback --execute
```

The command restores only allowlisted manifest-backed pointers/sources and does
not replace a general object-store backup. Full decision criteria, stop
conditions, and exit checks are in the [rollback runbook](../runbooks/rollback.md)
and [media cutover runbook](../runbooks/media-storage-cutover.md).
