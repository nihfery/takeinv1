# TAKEIN Enterprise Refactor — Phase Status

Last updated: 2026-08-10 (Asia/Bangkok)

This file records only validations that were actually run. A phase may be structurally complete while its gate is explicitly non-green because of a documented local-environment limitation.

| Phase | Status | Gate |
| --- | --- | --- |
| PHASE 0 — Baseline inventory | DONE | NOT GREEN — Laravel gates pass; frontend clean install is blocked by a locked SWC binary |
| PHASE 1 — Safe monorepo move | DONE | GREEN — application parity checks pass; Docker image build unavailable because daemon is stopped |
| PHASE 2 — Module relocation | DONE | GREEN — 77 mapped classes relocated; autoload, routes, tests, Blade, and frontend builds pass |
| PHASE 3 — Split route files | DONE | GREEN — 296-route method/URI/name fingerprint is unchanged live and cached |
| PHASE 4 — Application boundaries | DONE | GREEN — compatibility facades preserved; 81 tests, route/migration parity, lint, and frontend builds pass |
| PHASE 5 — Security hardening | DONE | GREEN — security regressions, full Laravel suite, lint, route compatibility, Compose, and both frontend builds pass |
| PHASE 6 — Redis / queue / Horizon | DONE | GREEN — Redis multi-process state, transactional queues, Horizon, Docker runtime, and recovery gates pass |
| PHASE 7 — Reverb / storage / observability | DONE | GREEN — private media, Reverb authorization, telemetry, PHP-FPM/Nginx, and live Docker gates pass |
| PHASE 8 — Contracts / CI / documentation | DONE | GREEN — OpenAPI parity, 13 CI workflows, required documentation, catalog enforcement, builds, and full suite pass |
| PHASE 9 — Production-readiness validation | DONE | GREEN for repository-owned gates — external production dependencies remain explicitly unprovisioned |

## PHASE 0 — Baseline inventory

### Repository baseline

- Branch: `main`
- Baseline commit: `023424fb4f70306ce9b58aab51bde8c7223ec20e`
- Worktree before refactor: clean except for the user-supplied untracked `CODEX-EXECUTE-TAKEIN-ENTERPRISE-REFACTOR.md`.
- Tracked files: 507.
- Existing top-level application layout: Laravel at repository root, customer Next.js at `frontend/customer-landing`, provider landing Next.js at `frontend/provider-landing`.

### Backend inventory

- Runtime: PHP `8.2.12`, Laravel `12.64.0`, Composer `2.10.0`.
- Direct production packages: Laravel Framework `12.64.0`, Sanctum `4.3.2`, Reverb `1.10.2`, Scramble `0.13.26`, Tinker `2.11.1`, Pusher PHP `7.2.8`.
- Direct development packages: PHPUnit `11.5.55`, Collision `8.9.4`, Faker `1.24.1`, Mockery `1.6.12`, Pail `1.2.7`, Pint `1.29.1`, Sail `1.61.0`.
- Current local drivers: MySQL connection, Reverb broadcasting, database cache, database queue, file sessions.
- Actual local database server: MariaDB `10.4.32`; the requested deployment target remains MySQL 8.
- Models: 25 under `App\Models`.
- Services: 6 under `App\Services`.
- Controllers: 44 (3 root, 9 Admin, 8 Provider, 1 Auth, 5 API root, 6 API Admin, 7 API Customer, 5 API Provider).
- Other application code: 7 middleware, 3 events, 1 console command, 1 application provider, and support classes/helpers.
- Largest current classes: `BookingFlowService` 1,928 lines, `SupportChatController` 1,440, Provider `BookingController` 924, `PublicCatalogController` 862, Provider `DashboardController` 765, Admin `DashboardController` 736.

### Database and route baseline

- Tracked migrations: 75; all 75 reported `Ran` locally.
- Relocation-stable migration fingerprint: `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`.
- Routes: 296 total, 294 named, 2 unnamed, no duplicate names.
- Relocation-stable normalized route fingerprint: `66e191deafad70940061a6b386247f56b215d1bcb37ffd1abaec346b5d049df4`.
- Route normalization: ordered `{methods,uri,name,action,middleware}` records, ordinal-sorted, compact JSON, SHA-256 over UTF-8 without BOM. Class namespaces are intentionally part of this PHASE 0 fingerprint; later structural gates compare method/URI/name separately when namespaces change.
- Route files before split: `routes/web.php` 663 lines and `routes/api.php` 199 lines.
- Migration normalization: tracked migration relative path plus SHA-256 of raw contents, ordinal-sorted and hashed again as canonical JSON.

### Test baseline

- Test files: 19 PHP files (17 Feature classes, 1 Unit class, plus `TestCase`).
- PHPUnit discovery: 79 tests (78 Feature, 1 Unit).
- `composer validate`: PASS.
- PHP syntax: PASS, 282 project PHP files checked, 0 failures.
- `php artisan route:list`: PASS, 296 routes.
- `php artisan test`: PASS, 79 tests and 612 assertions, 0 failures.
- Docker Compose static config: PASS with `docker compose --env-file deploy/dokploy.env.example config --quiet`.

### Frontend inventory

- Local runtime: Node `24.16.0`, npm `11.13.0`; Docker frontend base is Node 22 Alpine.
- Customer package: Next `16.2.11`, React/React DOM `19.2.6`; 20 app-router page routes.
- Provider landing package: Next `16.2.11`, React/React DOM `19.2.6`; 2 app-router page routes.
- Customer backend fallback chain: `BACKEND_PROXY_URL`, `NEXT_PUBLIC_BACKEND_URL`, `VITE_BACKEND_URL`, then `http://127.0.0.1:8000`.
- Current local ports: backend 8000, provider landing 5173, customer web 5174.
- Current Docker services: `db`, `backend`, `provider`, `customer`; MySQL 8 image, two persistent volumes, one application network.

### Pre-existing and environmental findings

- `npm ci && npm run build` was attempted for both frontends. Clean install failed with Windows `EPERM` while unlinking `@next/swc-win32-x64-msvc/next-swc.win32-x64-msvc.node`; both existing `node_modules` trees are incomplete afterward. No force-delete or stronger deletion primitive was used. Gate 0 is therefore not claimed green.
- Docker CLI and Compose are installed, but the Docker Desktop daemon is not running; image/container validation is unavailable at this point.
- Local MariaDB differs from the MySQL 8 target, and `php artisan db:show` cannot query MariaDB's missing `performance_schema.session_status` table. Migration status itself succeeds.
- Frontend environment examples mix legacy `VITE_*` keys with source code that reads `NEXT_PUBLIC_*`; Compose currently supplies compatibility values.
- Root-level helper/debug artifacts and hard-coded old paths exist and must be handled without changing business behavior.

### Gate 0 conclusion

PHASE 0 inventory is complete. Laravel, route, migration, PHPUnit, PHP syntax, and static Compose validations were run and recorded. The baseline is explicitly **not green** because clean frontend installs/builds could not complete due to the locked SWC binary. Per the execution brief, safe independent work may continue, and both frontend builds must be rerun from their clean PHASE 1 locations.

## PHASE 1 — Safe monorepo move

### Structural changes

- Customer Next.js moved from `frontend/customer-landing` to `apps/customer-web`.
- Provider landing Next.js moved from `frontend/provider-landing` to `apps/provider-landing`.
- Laravel moved from repository root to `backend/laravel-core` without namespace or method-body changes.
- Laravel tests moved with the Laravel application to `backend/laravel-core/tests`.
- Runtime Docker assets moved to `platform/docker` and deployment environment example to `platform/deploy`.
- Legacy root helper scripts moved to `tools/legacy/laravel`; generated historical artifacts moved to `tools/legacy/artifacts`.
- Docker build contexts, ignore rules, README paths, Dokploy documentation, and legacy helper bootstrap paths were updated.
- The existing local root `.env` was copied to ignored `backend/laravel-core/.env` without displaying or changing secret values, so the relocated application uses the same local environment.
- Old ignored `vendor`, `node_modules`, build, cache, session, and storage remnants were left in place rather than force-deleted; root ignore rules explicitly keep them out of Git.

### Gate 1 evidence

- `composer install --no-interaction --prefer-dist` from `backend/laravel-core`: PASS.
- `composer validate` and platform requirement check from the new Laravel root: PASS.
- Laravel application boot / `php artisan route:list`: PASS.
- Route parity: 296 routes; normalized SHA-256 remains `66e191deafad70940061a6b386247f56b215d1bcb37ffd1abaec346b5d049df4`.
- Migration parity: 75 migrations; normalized SHA-256 remains `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`; all 75 report `Ran`.
- PHP syntax: 274 Laravel PHP files plus 8 moved legacy helpers checked, 0 failures (same 282-file source baseline).
- `php artisan test`: PASS, 79 tests and 612 assertions.
- Customer `npm ci`: PASS; `npm run build`: PASS; 20 declared routes preserved.
- Provider landing `npm ci`: PASS; `npm run build`: PASS; 2 declared routes preserved.
- `docker compose --env-file platform/deploy/dokploy.env.example config --quiet`: PASS.
- `git diff --check`: PASS during path updates.
- Stale old-layout path scan: no old `frontend/customer-landing`, `frontend/provider-landing`, old absolute user path, or old Laravel bootstrap path remains in active tracked configuration/source.

### Gate 1 limitations

- Docker image build was attempted but could not connect to `dockerDesktopLinuxEngine` because the local Docker daemon is not running. Static Compose validation and source-path validation pass; no image-build success is claimed.
- `npm audit` reports 3 findings in each frontend (2 moderate, 1 high). No automatic dependency upgrade was applied during the structural move because that could alter behavior; the findings remain for the security phase.

## PHASE 2 — Module relocation

### Structural changes

- All 77 classes explicitly mapped in Section 10 were relocated to 14 implemented modules: Identity, Customer, Provider, Branch, Catalog, Staff, Booking, Payment, Subscription, Promotion, Review, Notification, Chat, and Support.
- Availability, Checkout, Media, and Audit ownership boundaries were created as documentation-only modules; no duplicate or fictional implementation was added.
- `app/Shared/README.md` records the technical-only Shared rule without creating empty folder forests.
- Namespaces, imports, model relationships, route controller references, tests, factories, middleware, events, support classes, and legacy helper references were updated mechanically.
- `tools/refactor/relocate-modules.ps1` records and re-verifies all 77 mappings, including implicit imports that were previously resolved only because classes shared one flat namespace.
- The relocated `GrantLegacySubscriptions` command is explicitly registered in `bootstrap/app.php`.
- `User::factory()` remains compatible through an explicit `UserFactory` binding.
- A morph map preserves the legacy Sanctum discriminator `App\\Models\\User`, so existing personal access tokens remain readable and new tokens keep the established database value. `AUTH_MODEL=App\\Models\\User` is also normalized to the relocated model.

### Gate 2 evidence

- Mapping verification: 77 destinations present, 0 mapped source files remain.
- `composer dump-autoload`: PASS; optimized autoload generated.
- Composer validation: PASS.
- PHP syntax: PASS, 275 active Laravel PHP files, 0 failures.
- Stale exact imports for every mapped `App\\Models`, `App\\Services`, `App\\Events`, and mapped `App\\Support` class: none.
- Route boot: PASS, 296 total / 294 named / 2 unnamed.
- Route source diff against PHASE 1 changes controller/model imports only; method, URI, name, middleware, and route bodies are unchanged.
- Route behavior fingerprint for method/URI/name: `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb` (296 records). This is the PHASE 3 pre-split reference.
- Reflection audit of all non-Closure route actions: 0 missing classes or methods.
- Blade validation: 40 static controller `view()` references and 45 Blade extends/include references resolve; `php artisan view:cache` passes.
- Original command signature `app:grant-legacy-subscriptions` is present in `php artisan list`.
- Migration parity: 75 files and baseline SHA-256 `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f` unchanged.
- `php artisan test`: PASS, 80 tests and 615 assertions, including the new legacy Sanctum-token regression test.
- Customer Next.js build: PASS, 20 declared routes preserved.
- Provider landing Next.js build: PASS, 2 declared routes preserved.

### Preserved legacy exception

- The unrouted `Api/Customer/CartController.php` still imports nonexistent `App\\Models\\CustomerCart`. This was already unreachable before refactor, and Section 10 explicitly requires Cart to remain a legacy concern until its data model is reconciled. It was not silently remapped to `CustomerActivity`, which has different semantics. No mapped old namespace remains in executable routes.

## PHASE 3 — Split route files

### Structural changes

- `routes/web.php` is now an ordered compatibility aggregator for `routes/web/public.php`, `auth.php`, `provider.php`, and `admin.php`.
- `routes/api.php` is now an ordered compatibility aggregator for `routes/api/v1/public.php`, `auth.php`, `customer.php`, `provider.php`, `admin.php`, `partner.php`, and `webhooks.php`.
- The `v1` directory establishes ownership only; no `/api/v1` prefix or alias was exposed.
- Existing `/api/health` registration moved to `routes/ops/health.php` without changing its URL.
- `routes/ops/readiness.php`, `routes/internal/v1.php`, and the partner registrar are explicit placeholders and register no public routes yet.
- `tools/validation/compare-routes.ps1` verifies method, URI, and route-name parity using canonical ordinal-sorted JSON and UTF-8 without BOM.

### Gate 3 evidence

- Route files lint: PASS, all 18 route PHP files.
- Web endpoint declarations: 128 before split and 128 after split.
- API routes: 93 before and after; zero `/api/v1` routes exposed.
- Full route parity: PASS, 296 records and SHA-256 `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb`.
- `php artisan route:cache`: PASS.
- Cached route parity: PASS after normalizing Laravel's ephemeral `generated::*` names for the two intentionally unnamed framework routes (`/up` and `broadcasting/auth`); all contractual names remain strict.
- `php artisan test`: PASS, 80 tests and 615 assertions.
- `git diff --check`: PASS.

- No route was intentionally removed or renamed, so no compatibility alias was required.

## PHASE 4 — Application boundaries

### Strangler extractions

- Provider dashboard period parsing moved into `DashboardPeriod` and `ResolveDashboardPeriod`; the controller keeps its route contract and delegates to the application query.
- Admin dashboard statistics moved into `GetAdminDashboardStats`; compatibility wrappers remain for controller-internal callers.
- Booking service resolution moved into `ResolveBookingServices`, staff eligibility into `ResolveEligibleStaff`, and overlap detection into `CheckConflict`; `BookingFlowService` remains the public compatibility facade with unchanged public signatures.
- Public branch search orchestration moved into `SearchPublicBranches` with `PublicBranchSearchCriteria`; shared provider eligibility clauses are centralized in `PublicProviderEligibilityFilter`, while validation and response shaping remain in `PublicCatalogController`.
- Chat thread authorization moved into `ChatThreadAccessService`; `SupportChatController` retains its existing private compatibility wrappers and route behavior.

### Gate 4 evidence

- Slice tests: booking and salon eligibility, public catalog/category hierarchy, provider navigation/chat, provider and admin dashboards all pass.
- `composer validate`: PASS.
- `composer dump-autoload`: PASS, 7,398 optimized classes.
- PHP syntax: PASS, 300 active project PHP files, 0 failures.
- `php artisan route:list`: PASS.
- Full route parity: PASS, 296 records and SHA-256 `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb`.
- Migration parity: 75 files and SHA-256 `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f` unchanged.
- `php artisan test`: PASS, 81 tests and 622 assertions.
- Customer Next.js production build: PASS, 20 declared routes preserved.
- Provider landing Next.js production build: PASS, 2 declared routes preserved.
- `git diff --check`: PASS.

## PHASE 5 — Security hardening

### Security changes

- Provider APIs now require an explicit provider actor boundary. Profile, service, staff, and branch writes enforce provider menu access and provider resource scope; subscription purchase is restricted to the provider owner and always records the owner ID.
- KTP and NIB uploads now target the private `provider_documents` disk. Owner/admin access uses short-lived signed and authenticated actions, raw paths are hidden from serialization, branch accounts receive no document URLs, and successful access is audited. Legacy public objects remain available only through the authorized compatibility reader during the planned storage migration.
- All upload validators now combine MIME/type, original extension, size, authorization, visibility, and generated storage keys. Review images require real images, raw SVG uploads are excluded, and branch gallery capacity is checked before storage.
- Midtrans webhook signatures are validated and followed by an authoritative status API lookup. Payment/subscription rows are transactionally locked, amount and currency must match, terminal states cannot regress, and duplicate settlement cannot repeat date or booking side effects.
- Browser-only payment confirmation is disabled in production and remains opt-in for the existing local/demo flow; manual confirmation is explicitly attributed to the manual gateway and audited.
- Booking payment charge/status and provider subscription purchase now create Midtrans transactions server-side, expose only payment instructions needed by the client, reconcile authoritative gateway status, and preserve idempotency under retry. Overdue booking orders are remotely expired before their slots are released; gateway outages remain fail-closed. Superseded subscription settlements are recorded as paid and explicitly audited for manual resolution without silently stacking entitlement.
- The customer payment page now initializes the server-side transaction, renders QR/VA/bill/deeplink instructions, polls authoritative status, handles pay-at-salon separately, and redirects only after the backend reports `paid`. Production frontend builds cannot expose the local/demo confirmation control.
- Named rate limiters cover login, registration, password reset, availability, search, booking writes, coupon validation, payment writes, provider writes, and webhooks with actor/business-aware keys.
- The backend production port binds to loopback by default and proxy trust is environment-scoped instead of trusting every proxy on a directly reachable origin.
- An additive `audit_logs` migration and fail-open `RecordAuditEvent` action cover provider approval/rejection/suspension/deletion, role and branch-account permission changes, provider/admin booking status changes, password changes, subscription purchase/status, payment webhook/manual status, and sensitive document access. Audit snapshots exclude credentials, document paths/content, and rejection notes.

### Gate 5 evidence

- Targeted security/payment/booking gate: PASS, 91 tests and 696 assertions; after adding the final remote-expiry regression, the affected payment/booking slice also passed with 42 tests and 308 assertions.
- Full `php artisan test`: PASS, 136 tests and 1,066 assertions.
- `composer validate --no-check-publish`: PASS.
- `composer dump-autoload --optimize`: PASS, 7,410 optimized classes.
- PHP syntax: PASS, 315 active Laravel PHP files checked correctly with `php -l <file>`, 0 failures.
- Routes: 300 total. The four additive signed document routes are explicitly allowlisted; after excluding only those additions, all 296 legacy method/URI/name records retain SHA-256 `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb`. Live and route-cached parity both pass.
- Migrations: 77 total. The original 75 files remain byte-for-byte unchanged with SHA-256 `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`; the additive audit and subscription-checkout migrations both ran locally, with 0 pending migrations.
- Permanent KTP/NIB URL scan across application, routes, and Blade source: no match.
- Static Compose validation: PASS; backend host binding resolves to `127.0.0.1:8000` and private-document disk configuration is present.
- Laravel `config:cache`, `route:cache`, and `view:cache`: PASS; caches were cleared after validation.
- Customer Next.js production build: PASS, 20 declared routes preserved (21 build-table entries including generated `/_not-found`).
- Provider landing Next.js production build: PASS, 2 declared routes preserved (3 build-table entries including generated `/_not-found`).
- `git diff --check`: PASS.

### Residual migration risk

- Legacy KTP/NIB files intentionally remain on the old public disk so rollout does not delete production data before copy/checksum verification. Their paths are no longer emitted by application responses, but an already-known historical URL remains reachable until the PHASE 7 copy, checksum, cutover, acceptance-window, and separately enabled retirement procedure completes for each exact source object.
- Midtrans behavior is covered with isolated authoritative-response contract tests; no real sandbox charge was created because this environment has no approved live/sandbox credential transaction. External gateway smoke remains a PHASE 9 deployment validation.
- Docker image/runtime validation remains unavailable because the local Docker daemon is stopped; no container-build success is claimed.

## PHASE 6 — Redis / queue / Horizon

### Runtime changes

- Redis 7.4 is private to the Docker data network, password-protected, AOF-persistent, and has no published host port.
- Logical Redis ownership is explicit: default DB 0, cache 1, sessions 2, queue 3, rate limits 4, Horizon metadata 5, and Reverb scaling 6.
- Production defaults use Redis for cache, sessions, limiter state, and queues. Queue dispatch is after-commit by default, `retry_after` is 120 seconds, and Horizon worker timeout is 90 seconds.
- Horizon 5.48.2 is installed with three priority pools: critical; payments/bookings/default; and notifications/emails/media/analytics. Its dashboard requires an authenticated admin, and queue metrics are snapshotted every five minutes.
- Backend, Horizon, scheduler, and Reverb run as separate processes from one Laravel image. Only the backend entrypoint runs migrations and the production deployment check.
- Chat message, chat thread, and user-notification broadcasts now use queued after-commit delivery with bounded tries, timeout, and backoff while preserving their channels, event names, and payloads.
- `/api/readiness` checks the database and every active Redis dependency and returns only a sanitized unavailable response on failure.
- The Laravel image pins phpredis 6.3.0, excludes every nested `.env*` and runtime upload path from its build context, and starts the PHP router without reloading a repository-local environment file.
- Patch-level dependency remediation leaves both Composer and npm audits at zero known advisories; Next 16.2.11 and React 19.2.6 remain unchanged.

### Gate 6 evidence

- Full `php artisan test`: PASS, 150 tests and 1,196 assertions.
- Runtime/Horizon focused suite: PASS, 10 tests and 89 assertions; queued broadcast suite: PASS, 4 tests and 41 assertions.
- `composer validate --no-check-publish`: PASS; `composer audit --locked`: PASS with 0 advisories; optimized autoload: PASS, 7,566 classes.
- PHP syntax: PASS, 322 active Laravel PHP files checked, 0 failures.
- Routes: 323 total = 296 immutable legacy records + 27 reviewed security/operations/Horizon additions. Legacy SHA-256 remains `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb` both live and route-cached.
- Migrations: 77 total = 75 immutable legacy migrations + 2 reviewed PHASE 5 additions. Legacy SHA-256 remains `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`; all 77 report `Ran` locally.
- Laravel `config:cache`, `route:cache`, and `view:cache`: PASS; caches were cleared after validation. `horizon:snapshot` is registered on the expected five-minute schedule.
- Static Compose validation and final Docker image build: PASS. The image contains phpredis 6.3.0 and no Laravel `.env` file.
- Fresh isolated Docker stack: MySQL, Redis, backend, Horizon, scheduler, and Reverb all became healthy; all 77 migrations ran, the deployment check passed, `/api/health` returned `ok`, and `/api/readiness` returned `ready`.
- Cross-process Redis gate: backend-written cache, session, and rate-limit state was read unchanged from the scheduler container. A lock held by backend excluded a concurrent scheduler contender.
- Transactional queue gate: rollback enqueued 0 jobs; commit enqueued exactly 1 notification job; Horizon drained it with 0 failed jobs.
- Failure/recovery gate: stopping Redis changed `/api/readiness` to HTTP 503 with `{"status":"unavailable"}`; restarting Redis restored HTTP 200 with `{"status":"ready"}`.
- Redis authentication, AOF persistence across container replacement, Reverb TCP/health, Horizon health, and the no-migrations invariant for non-backend processes all passed live.
- Customer and provider `npm ci`, `npm audit`, and production builds: PASS; both audits report 0 vulnerabilities and route counts remain 21/3 build-table entries including generated `/_not-found`.
- `git diff --check`: PASS. All isolated PHASE 6 Docker containers, networks, and volumes were enumerated by project label and removed after the gate; unrelated Docker resources were untouched.

### External boundary

- No external managed Redis, CDN, object-storage, or telemetry service is claimed. This gate proves the repository-owned Docker/runtime topology only; production provider provisioning remains an operations responsibility.

## PHASE 7 — Production platform foundations

### Platform changes

- The production HTTP process now uses PHP-FPM behind an always-on, loopback-published Nginx `backend-http` service. The old backend host contract remains `127.0.0.1:8000`, while application processes communicate over the private Docker network.
- Nginx access logs omit query strings, PHP-FPM request logging is disabled, and errors remain on stderr. FastCGI still forwards signed query parameters to Laravel. Proxy-supplied forwarding and observability IDs are replaced at this trust boundary.
- The backend entrypoint performs bounded writable-directory ownership normalization for persistent Laravel storage, rejects symlink components, and starts FPM. Horizon, scheduler, and Reverb run as `www-data` and never run migrations.
- Reverb now requires explicit host origins, rejects production wildcard/URL origin values, disables client events, and reuses the application chat authorization rules for tenant, participant, permission, verification, approval, and open-thread lifecycle checks. An opt-in WebSocket-only Nginx gateway profile and runbook are included.
- A Media application boundary provides generated keys, stream-based SHA-256 verification, explicit visibility, private provider-document and chat-attachment storage, signed relative download URLs, and audited access without logging object paths.
- `media:migrate-legacy` supplies dry-run, copy, verified cutover, rollback, and separately gated retirement stages backed by an idempotent manifest. Retirement is disabled by default, requires a minimum 30-day acceptance age, archives and verifies the source privately before deletion, and supports restoration. Targeted `--id` operations require an explicit scope.
- Local public/private disks remain the default. Optional S3/R2-compatible disk configuration is present through Flysystem's S3 adapter, but no external bucket is claimed or enabled.
- Request and correlation IDs are established after trusted-proxy normalization and before CORS or other early responses. Inbound IDs are rejected by default, exposed through CORS, propagated to queued jobs and audit records, and emitted in structured logs.
- Sensitive structured-log context is recursively redacted across production-capable channels before interpolation. The optional OTLP HTTP/JSON exporter is fail-open, has no retries, and clamps its timeout to 20–250 ms so telemetry cannot become a booking or payment availability dependency.
- Production deployment validation now rejects unsupported or malformed `APP_KEY` values before serving traffic.

### Gate 7 evidence

- Full `php artisan test`: PASS, 192 tests and 1,506 assertions.
- Composer strict validation, optimized autoload, and locked dependency audit: PASS; 8,898 optimized classes and 0 known advisories.
- PHP syntax: PASS, 359 project PHP files, 0 failures. Focused Pint validation of all PHASE 7 PHP changes: PASS.
- Routes: 324 total = 296 immutable legacy records + 28 reviewed additions. The legacy SHA-256 remains `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb` both live and route-cached.
- Migrations: 79 total = 75 immutable legacy migrations + 4 reviewed additions. The legacy SHA-256 remains `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`; the additive audit-correlation and media-manifest schema is applied locally with no pending migration.
- Laravel config, event, route, and Blade cache builds: PASS; cached route parity is exact and caches were cleared afterward.
- Customer and provider production builds: PASS with 21 and 3 build-table entries respectively, including generated `/_not-found`; npm audits remain at 0 known vulnerabilities.
- Default and WebSocket-gateway Compose rendering, HTTP-runtime validator, backend image build, `backend-http` image build, PHP-FPM configuration, and Nginx syntax: PASS.
- A fresh isolated Docker stack brought MySQL, Redis, backend, backend-http, Horizon, scheduler, Reverb, and the opt-in WebSocket gateway healthy. `/api/health` returned `ok`, `/api/readiness` returned `ready`, deployment validation passed, and no migration remained pending.
- Live probes confirmed PHP-FPM on port 9000, the S3 adapter at 3.35.2, all three sidecars at UID 33, public storage serving, sensitive prefixes denied, and repair of a pre-existing root-owned writable directory without following symlinks.
- A signed-query sentinel and attacker-supplied request/correlation IDs were absent from backend/FPM logs. The gateway preserved functionality while replacing those untrusted values.
- All isolated PHASE 7 Docker containers, networks, and three volumes were enumerated by project label, removed, and verified absent; unrelated Docker resources were untouched.

### Operational boundary

- Historical public object URLs remain reachable until operators execute the documented copy/checksum/cutover flow, observe the minimum acceptance window, explicitly enable retirement, and verify the private archive. No production object was deleted during this refactor.
- S3/R2 buckets, DNS, TLS, edge routing, OTLP collectors, Grafana/Loki, and external Midtrans credentials are environment-owned dependencies and were not provisioned or claimed here. The repository supplies fail-closed configuration checks and runbooks for those boundaries.

## PHASE 8 — Contracts, CI, documentation, and public taxonomy

### Contract and delivery changes

- Seven OpenAPI v1 documents now describe every active external API surface and are checked against runtime method/path, operation ID, middleware, request-field, response-status, and provider-permission inference.
- Thirteen mandatory GitHub workflows cover backend, frontend, contract, security, dependency, image, concurrency, staging, and production paths. All third-party action references are full commit SHAs; deployment accepts only an exact reachable 40-character commit and a clean remote worktree.
- Required architecture, ADR, domain, database, security, deployment, rollback, and operations documents are present. Documentation states clearly that only Laravel and `backend-http` are promoted CI images; environment-specific Next.js images are rebuilt from the verified source SHA.
- Public services now require an active leaf subcategory under an active root category, exactly two taxonomy levels, and no child categories. The rule applies to list, search, direct detail, branch payloads, grouped services, and staff skills; provider/admin management remains backward-compatible for legacy cleanup.
- The demo seeder creates only normalized public taxonomy and safely skips its optional queue example outside opening hours without hiding unrelated validation errors.

### Gate 8 evidence

- Full Laravel suite: PASS, 201 tests and 1,643 assertions at the PHASE 8 checkpoint.
- Public taxonomy regression: PASS, 5 tests and 64 assertions; full seeder onboarding regression: PASS, 1 test and 9 assertions.
- OpenAPI contract gate: PASS, 7 documents, 101 active method/path operations, and 101 unique operation IDs (admin 30, auth 5, customer 20, partner 0, provider 30, public 15, webhook 1).
- CI structure gate: PASS, 13 required workflows, 39 SHA-pinned action references, and 17 real command paths. Actionlint, ShellCheck, Bash syntax, deployment positive/injection-negative probes, dependency audits, and full-history Gitleaks validation passed.
- PHP syntax: PASS, 359 files at the checkpoint. Pint exact-hash ratchet passed without refreshing legacy hashes; new/changed catalog and seeder files are Pint-clean.
- Routes: 324 total = 296 immutable legacy routes + 28 reviewed additions; legacy SHA-256 remains `7e0838b62ee918c559594688c6f72a3d7a5d15adfb8bf0b97826a2435eb268bb` live and cached.
- Migrations: 79 total = 75 immutable legacy migrations + 4 reviewed additions; legacy SHA-256 remains `3a7f3071ec70a56de2d2593ffbd721994a0167f47941f495073c0e1d73d2e72f`.
- Customer/provider clean installs, npm audits, and production builds: PASS, 0 known vulnerabilities and 21/3 build-table entries including generated `/_not-found`.
- Fresh isolated nine-service Docker stack: PASS. All 79 migrations and the demo seed ran, deployment validation passed, all services became healthy, and all 750 active demo services satisfied the public taxonomy predicate.
- Health, readiness, public API, customer landing, provider landing, provider login, and admin login returned HTTP 200/expected redirects. Authenticated admin/provider sessions rendered both dashboards successfully. Interactive browser automation was unavailable, so no browser click-through claim is made.
- Documentation mechanical gate: PASS, all required paths present, no broken relative links, unbalanced fences, or trailing whitespace.

## PHASE 9 — Production-readiness validation

### Final validation evidence

- Final full Laravel suite after the readiness/logger regression: PASS, 202 tests and 1,650 assertions in 46.22 seconds, including all unit, feature, security, runtime, and six real-MySQL concurrency tests.
- Three consecutive dedicated concurrency executions passed at 6 tests and 75 assertions each: single-winner capacity/idempotency, hold finalization side effects, coupon quota and golden total, reschedule conflict, and duplicate payment notification replay.
- Final static gates: Composer strict validation/audit/optimized autoload PASS with 0 advisories and 8,900 classes; PHP lint PASS for 360 files; Pint ratchet PASS for 120 exact legacy hashes; OpenAPI and workflow validators PASS; live and cached route parity plus migration parity PASS.
- Final runtime image build: PASS. All nine isolated services were healthy; deployment validation and migration status ran as `www-data`, no migration was pending, Horizon reported running, and health/readiness/customer/provider surfaces returned HTTP 200.
- Queue/Horizon live probe: a notification queued while the background supervisor was paused, Redis queue depth changed to one, the job drained after resume, Horizon completed-job count increased, and failed jobs remained zero.
- Reverb live probe: TCP health and a native WebSocket handshake succeeded with the allowed local Origin and received `pusher:connection_established`.
- Redis recovery drill: unauthenticated access returned `NOAUTH`, authenticated PING returned `PONG`, runtime AOF was enabled, readiness changed HTTP 200 → 503 → 200 during stop/start, the sentinel survived restart, and cleanup removed it. The first run exposed a root-owned log edge that produced HTTP 500; readiness now fails closed even when logging also fails, operational Artisan commands use `www-data`, the image was rebuilt, and the full live rerun passed.
- Basic mixed HTTP load smoke: PASS at concurrency 6, 120/120 HTTP 200, 0 errors, 614.633 ms total, 195.239 requests/second, p50 22.095 ms, p95 59.855 ms, p99 77.861 ms, maximum 91.778 ms. Concurrency 12 produced local backend timeouts, so this is explicitly not a production capacity benchmark.
- Backup/restore drill: PASS on MySQL 8.0.46. A 946,951-byte dump (`b26326eba180c4835544d8770ac41bcbd87edf7d6aa758d68ce58db6194de35a`) restored to the bounded `youyaku_phase9_restore` database; 38 tables, 79 migration rows, schema/migration fingerprints, and every row count matched. The dump and restore database were then removed and the source remained present.
- Rollback mechanics: PASS for exact image-pin/recreate/restore of stateless `backend-http`, with health/readiness and database counts unchanged. No trustworthy older image was present, so previous-release/current-schema compatibility and write-quiescing remain staging obligations.
- After all drills, the exact project labels and attachments were revalidated; 9 isolated containers, 2 isolated networks, and 3 isolated test volumes were removed. The unrelated `skymap` volume and network remained present. The fresh demo database, Redis state, and test storage in those removed volumes are not recoverable and contained no production data.
- `docs/architecture/FINAL-REFACTOR-REPORT.md` contains all 16 mandatory Section 42 topics and the exact commands, evidence, risks, deployment path, and rollback procedure.

### Production decision boundary

- Repository-owned implementation and gates are complete, but this report does not approve production launch. External Midtrans sandbox/live, S3/R2, DNS/TLS/edge, managed database/Redis, OTLP/Grafana/Loki, HA/off-site backup, admin MFA/edge controls, malware scanning, GitHub environment protections, and production-shaped load/rollback drills remain unprovisioned or unverified.
- Historical public media URLs remain a known risk until the documented checksum/cutover, minimum 30-day acceptance window, explicitly enabled retirement, and private archive verification complete.
