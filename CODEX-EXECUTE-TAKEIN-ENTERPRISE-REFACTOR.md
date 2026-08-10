# CODEX EXECUTION MASTER PLAN — TAKEIN ENTERPRISE LARAVEL REFACTOR

> **Execution instruction for Codex**
>
> Read this file completely before modifying any code.
> Then **execute the plan**, do not only summarize it.
> Work phase-by-phase, run the required validation gates after every phase, and keep a running status in `PHASE-STATUS.md`.
> Do not ask for confirmation for ordinary engineering decisions covered by this document.
> If a requirement is impossible because a dependency/tool is unavailable, preserve the repository in a working state, record the blocker in `PHASE-STATUS.md`, and continue with independent work that is safe to complete.

---

## 0. Mission

Refactor the existing TAKEIN / DITAKEIN repository into a professional **enterprise Laravel modular monolith** while preserving the existing product behavior.

The target is **not** a rewrite.

The target is:

- easier maintenance for a larger engineering team;
- strong domain boundaries;
- API-first where external APIs are actually needed;
- Laravel Blade retained for operational dashboards;
- horizontally scalable Laravel runtime;
- Redis-backed shared runtime state;
- concurrency-safe booking and payment behavior;
- secure authorization boundaries;
- reliable async processing;
- observable production workloads;
- repeatable Docker/CI/CD/deployment;
- documented ownership and architecture;
- no unnecessary distributed-system complexity.

The repository currently has four user-facing surfaces:

1. **Customer website/application** — Next.js.
2. **Provider landing/marketing site** — Next.js, public domain `partners.takein.id`.
3. **Provider operational dashboard** — Laravel Blade. **Keep it Blade.**
4. **Admin dashboard** — Laravel Blade. **Keep it Blade.**

Laravel is the permanent backend.

---

# 1. Hard architectural decisions — DO NOT CHANGE

These decisions are final for this refactor.

## 1.1 Application surfaces

| Surface | Technology | Target host |
|---|---|---|
| Customer web | Next.js / existing JSX | `takein.id` / `www.takein.id` |
| Provider public landing | Next.js / existing JSX | `partners.takein.id` |
| Provider operational dashboard | Laravel Blade | `provider.takein.id` |
| Admin dashboard | Laravel Blade | `admin.takein.id` |
| External REST API | Laravel | `api.takein.id` |
| External webhooks | Laravel | `hooks.takein.id` |
| Realtime WebSocket | Laravel Reverb | `ws.takein.id` |
| Public media/CDN | S3/R2 + CDN | `assets.takein.id` |

Do **not** convert the Provider Dashboard or Admin Dashboard to Next.js.

Do **not** make Blade call the same Laravel application through HTTP just to look “API-driven”.
Blade web controllers must call Laravel application/domain services directly.

## 1.2 Core technology choices

Keep:

- PHP 8.2+ compatibility; production image may continue using PHP 8.3.
- Laravel 12 for this refactor.
- MySQL 8.
- Laravel Sanctum.
- Laravel Reverb.
- Midtrans.
- Next.js for the two existing frontend applications.
- Docker.

Introduce safely after structural parity:

- Redis for session/cache/queue/locks/rate limiting/idempotency/realtime coordination.
- Laravel Horizon for Redis queues.
- S3 or Cloudflare R2 compatible object storage.
- Laravel Pulse where safe.
- structured centralized logs;
- OpenTelemetry-compatible instrumentation;
- metrics/dashboard/alerting configuration;
- CI/CD quality and security gates.

## 1.3 Explicitly NOT part of this architecture

Do not add these unless this document is replaced by a later approved ADR:

- Go;
- gRPC;
- Kafka;
- microservices;
- service mesh;
- Kubernetes as a runtime requirement;
- Kong as a runtime requirement;
- database migration to PostgreSQL;
- Laravel major-version upgrade;
- frontend rewrite to TypeScript;
- admin/provider rewrite to React/Next.js.

Folders may reserve future documentation space, but do not install or operate these technologies during this execution.

---

# 2. Safety contract

## 2.1 Never destroy user work or production data

Never run:

```bash
git reset --hard
git clean -fd
php artisan migrate:fresh
php artisan db:wipe
php artisan schema:drop
docker compose down -v
DROP DATABASE
DROP TABLE
TRUNCATE
```

unless a test database created specifically for automated tests is being destroyed.

Never remove a migration that has already existed in the source repository.

Never rewrite applied migrations merely to make the schema prettier.

Use new migrations for future schema changes.

## 2.2 Do not modify secrets

Never commit:

- `.env`;
- APP_KEY;
- database passwords;
- Redis passwords;
- Midtrans secrets;
- Cloudflare tokens;
- S3/R2 keys;
- private keys;
- access tokens;
- actual KTP/NIB/private provider documents.

Only `.env.example` / `.env.production.example` style placeholders may be committed.

## 2.3 Preserve behavior before improving behavior

During structural relocation:

- preserve route URLs;
- preserve route names;
- preserve request shapes;
- preserve response shapes;
- preserve database table/column names;
- preserve validation behavior;
- preserve Blade view behavior;
- preserve Next.js behavior;
- preserve CSS/assets;
- preserve booking states;
- preserve payment states;
- preserve provider/branch scoping;
- preserve onboarding state behavior;
- preserve existing tests.

Security defects discovered during structural movement must be documented first and fixed in the dedicated security phase with regression tests.

---

# 3. Existing business behavior that must survive

Treat current code, migrations, and automated tests as the strongest evidence of actual runtime behavior.

Do not reinterpret these flows casually.

## 3.1 Booking

Preserve at minimum:

- scheduled booking;
- queue booking;
- walk-in booking;
- manual/provider booking;
- group booking;
- booking participants;
- participant service selection;
- availability lookup;
- eligible staff resolution;
- staff working schedule checks;
- branch/service/staff eligibility;
- temporary booking hold;
- hold extension;
- hold release;
- hold expiration;
- booking finalization;
- cancellation;
- rescheduling;
- conflict detection;
- database transactions;
- row locking;
- customer-scoped idempotency.

Known behavior to preserve unless source/tests prove otherwise:

- temporary customer booking hold is approximately 3 minutes;
- overlap logic is effectively:
  `requested_start < existing_end AND requested_end > existing_start`;
- conflict must be rechecked inside the transaction;
- retries using the same valid idempotency key must not create duplicate bookings;
- group booking service/price/duration snapshots must remain historical transaction data.

## 3.2 Payment

Preserve:

- `Payment`;
- `PaymentGatewayTransaction`;
- Midtrans integration;
- signature validation;
- webhook state mapping;
- payment expiry behavior;
- booking status transition triggered by valid payment state;
- duplicate/replayed webhook safety where already present.

Known Midtrans state intent currently includes:

- settlement → paid;
- capture → paid or pending depending on fraud state;
- pending → pending;
- refund → refunded;
- expire → expired;
- cancel / deny / failure → failed.

Do not duplicate payment side effects when a gateway retries the same notification.

## 3.3 Provider organization

Preserve:

- provider owner;
- provider branches;
- branch accounts;
- `provider_id`;
- `branch_id`;
- provider roles;
- provider menu permissions;
- provider verification;
- onboarding states;
- branch-level access constraints.

Provider owner/branch authorization must remain server-side.

Never rely on hidden UI/menu state as authorization.

## 3.4 Onboarding

Preserve state values such as:

- `not_started`;
- `in_progress`;
- `skipped`;
- `completed`;

and preserve resumable current-step/version state where present.

Do not collapse onboarding to one boolean.

## 3.5 Reviews

Preserve branch review and staff review separation.

Preserve booking-based eligibility.

Preserve staff participation validation.

Preserve review image constraints implemented in source.

## 3.6 Notification / realtime

A notification/business transaction must not fail simply because realtime broadcasting fails.

Persist core business state first.

Broadcast/notify asynchronously where safe.

## 3.7 Subscription / entitlement

Current source and older product documentation may disagree.

Do not silently “correct” entitlement/public eligibility during folder restructuring.

Capture conflicts as:

`docs/domains/subscription-business-rule-conflicts.md`

Only change semantics when a dedicated regression-tested business decision exists.

## 3.8 Legacy CustomerCart anomaly

The source has historical cart migrations and a `CartController`/`CustomerCart` concern that may no longer map cleanly to the current model state.

Do not invent a fake model just to satisfy an import.

If this remains a dangling legacy reference:

- preserve it if it is not active;
- document it;
- add a cleanup issue/note;
- do not route new production traffic through it until reconciled.

---

# 4. Final repository layout

The target root is:

```text
takein-platform/
├── apps/
│   ├── customer-web/
│   └── provider-landing/
│
├── backend/
│   └── laravel-core/
│
├── contracts/
│   └── openapi/
│       └── v1/
│
├── packages/
│   ├── api-client/
│   ├── schemas/
│   ├── frontend-utils/
│   ├── design-tokens/
│   └── eslint-config/
│
├── platform/
│   ├── edge/
│   │   └── cloudflare/
│   ├── gateway/
│   │   └── nginx/
│   ├── docker/
│   ├── observability/
│   ├── security/
│   └── terraform/
│
├── tests/
│   ├── contract/
│   ├── e2e/
│   ├── concurrency/
│   ├── security/
│   ├── load/
│   └── smoke/
│
├── tools/
├── scripts/
├── docs/
│   ├── architecture/
│   ├── adr/
│   ├── domains/
│   ├── api/
│   ├── database/
│   ├── security/
│   ├── runbooks/
│   └── onboarding/
│
├── .github/
│   └── workflows/
│
├── .editorconfig
├── .gitignore
├── .dockerignore
├── .env.example
├── docker-compose.yml
├── Makefile
├── README.md
├── ARCHITECTURE.md
├── SECURITY.md
├── CONTRIBUTING.md
├── CODEOWNERS
└── PHASE-STATUS.md
```

---

# 5. Final frontend ownership

## 5.1 Customer application

Move the current:

```text
frontend/customer-landing/
```

to:

```text
apps/customer-web/
```

Do not rewrite its JSX just to conform to a new style.

Preserve route URLs and visual behavior.

Gradual internal organization may use:

```text
apps/customer-web/
├── app/
├── src/
│   ├── components/
│   ├── features/
│   │   ├── auth/
│   │   ├── customer/
│   │   ├── provider/
│   │   ├── branch/
│   │   ├── catalog/
│   │   ├── staff/
│   │   ├── search/
│   │   ├── availability/
│   │   ├── booking/
│   │   ├── group-booking/
│   │   ├── checkout/
│   │   ├── payment/
│   │   ├── promotion/
│   │   ├── review/
│   │   ├── favorite/
│   │   ├── notification/
│   │   ├── chat/
│   │   └── profile/
│   ├── api/
│   ├── auth/
│   ├── hooks/
│   ├── stores/
│   ├── schemas/
│   ├── lib/
│   ├── utils/
│   └── constants/
├── public/
├── tests/
├── package.json
└── Dockerfile
```

Do not reorganize frontend components unnecessarily in the same commit as the top-level move.

First preserve working behavior.

## 5.2 Provider landing

Move:

```text
frontend/provider-landing/
```

to:

```text
apps/provider-landing/
```

This is **not** the provider dashboard.

Its canonical external host is:

```text
partners.takein.id
```

Its purpose is public provider acquisition/marketing/registration.

Successful provider authentication/registration may route to the Laravel Blade provider dashboard at:

```text
provider.takein.id
```

Preserve current landing design and existing assets.

---

# 6. Laravel core

Move the Laravel application into:

```text
backend/laravel-core/
```

Laravel is not called `laravel-api` because it owns more than an API.

It owns:

- Laravel web/session runtime;
- Admin Blade;
- Provider Blade;
- REST API;
- domain/application logic;
- authentication/authorization;
- queue dispatch;
- Reverb events;
- scheduled jobs;
- webhooks.

Expected high-level layout:

```text
backend/laravel-core/
├── app/
│   ├── Modules/
│   ├── Shared/
│   ├── Http/
│   ├── Console/
│   └── Providers/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── artisan
├── composer.json
├── composer.lock
├── phpunit.xml
└── Dockerfile
```

---

# 7. Laravel module boundaries

Create:

```text
app/Modules/
├── Identity/
├── Customer/
├── Provider/
├── Branch/
├── Catalog/
├── Staff/
├── Availability/
├── Booking/
├── Checkout/
├── Payment/
├── Subscription/
├── Promotion/
├── Review/
├── Notification/
├── Chat/
├── Support/
├── Media/
└── Audit/
```

## 7.1 Standard module shape

Use only the directories actually needed by each module.

Canonical shape:

```text
<Module>/
├── Domain/
│   ├── Enums/
│   ├── ValueObjects/
│   ├── Rules/
│   ├── Contracts/
│   ├── Events/
│   └── Exceptions/
├── Application/
│   ├── Actions/
│   ├── Queries/
│   ├── DTOs/
│   ├── Services/
│   └── Contracts/
├── Infrastructure/
│   ├── Persistence/
│   │   ├── Models/
│   │   └── Repositories/
│   ├── Cache/
│   ├── Locks/
│   └── External/
├── Presentation/
│   ├── Api/
│   └── Web/
├── Policies/
├── Jobs/
├── Listeners/
├── Providers/
└── README.md
```

Do not create empty folder forests merely for aesthetics.

---

# 8. Dependency rules

These rules are mandatory.

## 8.1 Direction

```text
Presentation
    ↓
Application
    ↓
Domain
```

Infrastructure implements technical contracts required by Application/Domain.

## 8.2 Domain purity

`Domain/` must not directly depend on:

- HTTP request objects;
- Blade;
- Next.js;
- controllers;
- Redis clients;
- Midtrans SDK details;
- filesystem implementations;
- Laravel routing;
- response objects.

When moving existing Eloquent models, it is acceptable initially to keep them under:

```text
Infrastructure/Persistence/Models/
```

to avoid pretending Eloquent models are pure domain entities.

## 8.3 Module isolation

Do not let arbitrary code do:

```php
OtherModuleModel::query()->update(...)
```

everywhere.

When a module needs another module, prefer:

- an Application Action;
- an Application Query;
- an explicit contract;
- a domain/application event for async side effects.

During the first structural phase, do not over-refactor working cross-model relationships merely to satisfy theoretical purity.
Document remaining cross-module coupling and remove it gradually with tests.

## 8.4 Shared rule

`app/Shared` may be used by modules.

`app/Shared` must not depend on business modules.

Do not place booking/provider/payment business rules in Shared.

---

# 9. Shared infrastructure

Target:

```text
app/Shared/
├── Auth/
├── Database/
├── Redis/
├── Cache/
├── Queue/
├── Locking/
├── Idempotency/
├── Outbox/
├── Money/
├── Clock/
├── Pagination/
├── Storage/
├── Logging/
├── Telemetry/
├── Security/
├── FeatureFlags/
├── Validation/
└── Exceptions/
```

Only introduce an Outbox implementation if it can be added without destabilizing the existing transaction flows. A documented scaffold is acceptable until its semantics are tested.

---

# 10. Exact source-to-module mapping

Move classes gradually and update namespaces/imports automatically and consistently.

Do not change method bodies during pure relocation unless required for namespace/import/path compatibility.

## 10.1 Identity

Move/own:

```text
app/Models/User.php
    → app/Modules/Identity/Infrastructure/Persistence/Models/User.php

app/Models/AdminProfile.php
    → app/Modules/Identity/Infrastructure/Persistence/Models/AdminProfile.php

app/Http/Controllers/Api/AuthController.php
    → app/Modules/Identity/Presentation/Api/Auth/AuthController.php

app/Http/Controllers/Auth/UnifiedLoginController.php
    → app/Modules/Identity/Presentation/Web/Auth/UnifiedLoginController.php
```

Global coarse-auth middleware may remain in `app/Http/Middleware` initially:

```text
AdminMiddleware.php
CustomerMiddleware.php
ProviderMiddleware.php
PreventBackHistory.php
```

Later improve naming/organization without changing behavior.

## 10.2 Customer

```text
app/Models/CustomerProfile.php
    → app/Modules/Customer/Infrastructure/Persistence/Models/CustomerProfile.php

app/Models/CustomerActivity.php
    → app/Modules/Customer/Infrastructure/Persistence/Models/CustomerActivity.php

app/Http/Controllers/Api/Customer/ProfileController.php
    → app/Modules/Customer/Presentation/Api/Customer/ProfileController.php

app/Http/Controllers/Api/Customer/ActivityController.php
    → app/Modules/Customer/Presentation/Api/Customer/ActivityController.php

app/Http/Controllers/Provider/CustomerController.php
    → app/Modules/Customer/Presentation/Web/Provider/CustomerController.php

app/Http/Controllers/Api/Admin/CustomerController.php
    → app/Modules/Customer/Presentation/Api/Admin/CustomerController.php
```

Handle `CartController.php` as a legacy concern until its current data model is reconciled.

## 10.3 Provider

```text
app/Models/ProviderProfile.php
    → app/Modules/Provider/Infrastructure/Persistence/Models/ProviderProfile.php

app/Models/ProviderRole.php
    → app/Modules/Provider/Infrastructure/Persistence/Models/ProviderRole.php

app/Models/ProviderRoleMenuPermission.php
    → app/Modules/Provider/Infrastructure/Persistence/Models/ProviderRoleMenuPermission.php

app/Services/SalonEligibilityService.php
    → app/Modules/Provider/Application/Services/SalonEligibilityService.php

app/Support/ProviderAccountScope.php
    → app/Modules/Provider/Application/Support/ProviderAccountScope.php

app/Support/ProviderMenuAccess.php
    → app/Modules/Provider/Application/Support/ProviderMenuAccess.php

app/Http/Controllers/Provider/ProfileController.php
    → app/Modules/Provider/Presentation/Web/Provider/ProfileController.php

app/Http/Controllers/Provider/DashboardController.php
    → app/Modules/Provider/Presentation/Web/Provider/DashboardController.php

app/Http/Controllers/Provider/RolePermissionController.php
    → app/Modules/Provider/Presentation/Web/Provider/RolePermissionController.php

app/Http/Controllers/Admin/ProviderController.php
    → app/Modules/Provider/Presentation/Web/Admin/ProviderController.php

app/Http/Controllers/Api/Admin/ProviderController.php
    → app/Modules/Provider/Presentation/Api/Admin/ProviderController.php

app/Http/Controllers/Api/Provider/ProfileController.php
    → app/Modules/Provider/Presentation/Api/Provider/ProfileController.php
```

Provider account/verification/menu middleware may remain globally registered but should call Provider module services rather than duplicate provider rules.

## 10.4 Branch

```text
app/Models/ProviderBranch.php
    → app/Modules/Branch/Infrastructure/Persistence/Models/ProviderBranch.php

app/Http/Controllers/Provider/BranchController.php
    → app/Modules/Branch/Presentation/Web/Provider/BranchController.php

app/Http/Controllers/Api/Provider/BranchController.php
    → app/Modules/Branch/Presentation/Api/Provider/BranchController.php
```

## 10.5 Catalog

```text
app/Models/Service.php
    → app/Modules/Catalog/Infrastructure/Persistence/Models/Service.php

app/Models/ServiceCategory.php
    → app/Modules/Catalog/Infrastructure/Persistence/Models/ServiceCategory.php

app/Http/Controllers/Provider/ServiceController.php
    → app/Modules/Catalog/Presentation/Web/Provider/ServiceController.php

app/Http/Controllers/Admin/ServiceController.php
    → app/Modules/Catalog/Presentation/Web/Admin/ServiceController.php

app/Http/Controllers/Admin/ServiceCategoryController.php
    → app/Modules/Catalog/Presentation/Web/Admin/ServiceCategoryController.php

app/Http/Controllers/Api/Provider/ServiceController.php
    → app/Modules/Catalog/Presentation/Api/Provider/ServiceController.php

app/Http/Controllers/Api/Admin/ServiceController.php
    → app/Modules/Catalog/Presentation/Api/Admin/ServiceController.php

app/Http/Controllers/Api/Admin/ServiceCategoryController.php
    → app/Modules/Catalog/Presentation/Api/Admin/ServiceCategoryController.php

app/Http/Controllers/Api/PublicCatalogController.php
    → app/Modules/Catalog/Presentation/Api/Public/PublicCatalogController.php
```

If `PublicCatalogController` contains provider eligibility logic, call Provider application services instead of duplicating the rule.

## 10.6 Staff

```text
app/Models/ProviderStaff.php
    → app/Modules/Staff/Infrastructure/Persistence/Models/ProviderStaff.php

app/Models/StaffSkill.php
    → app/Modules/Staff/Infrastructure/Persistence/Models/StaffSkill.php

app/Models/StaffSchedule.php
    → app/Modules/Staff/Infrastructure/Persistence/Models/StaffSchedule.php

app/Http/Controllers/Provider/StaffController.php
    → app/Modules/Staff/Presentation/Web/Provider/StaffController.php

app/Http/Controllers/Api/Provider/StaffController.php
    → app/Modules/Staff/Presentation/Api/Provider/StaffController.php
```

## 10.7 Booking

First move the existing booking implementation intact.

```text
app/Models/Booking.php
    → app/Modules/Booking/Infrastructure/Persistence/Models/Booking.php

app/Models/BookingParticipant.php
    → app/Modules/Booking/Infrastructure/Persistence/Models/BookingParticipant.php

app/Services/BookingFlowService.php
    → app/Modules/Booking/Application/Services/BookingFlowService.php

app/Http/Controllers/Api/Customer/BookingController.php
    → app/Modules/Booking/Presentation/Api/Customer/BookingController.php

app/Http/Controllers/Api/Admin/BookingController.php
    → app/Modules/Booking/Presentation/Api/Admin/BookingController.php

app/Http/Controllers/Admin/BookingController.php
    → app/Modules/Booking/Presentation/Web/Admin/BookingController.php

app/Http/Controllers/Provider/BookingController.php
    → app/Modules/Booking/Presentation/Web/Provider/BookingController.php

app/Http/Controllers/Admin/CalendarController.php
    → app/Modules/Booking/Presentation/Web/Admin/CalendarController.php
```

`Customer/GraphqlController.php` currently acts as a booking/availability-oriented endpoint. Move initially to:

```text
app/Modules/Booking/Presentation/Api/Customer/GraphqlController.php
```

Do not expand it into a new GraphQL architecture during this refactor.

## 10.8 Availability

The current availability logic appears to be embedded substantially in `BookingFlowService`.

Do **not** rewrite it immediately.

After Booking relocation is green:

1. identify pure availability functions;
2. extract behavior one use case at a time;
3. create:
   - `GetAvailableSlots`;
   - `ResolveEligibleStaff`;
   - `CheckConflict`;
   - `ReserveSlot`;
   - `ReleaseSlot`;
4. keep `BookingFlowService` as a compatibility facade until callers/tests are migrated;
5. run booking/concurrency tests after every extraction.

Target:

```text
app/Modules/Availability/
├── Domain/
│   ├── ValueObjects/
│   ├── Rules/
│   └── Exceptions/
├── Application/
│   ├── Actions/
│   └── DTOs/
└── Infrastructure/
    ├── Persistence/
    ├── Redis/
    └── Locks/
```

## 10.9 Checkout

Checkout is a target boundary, not an excuse to rewrite pricing now.

Create the module and document ownership.

Only extract existing quote/pricing/finalization calculations after Booking/Promotion/Payment parity is green.

Potential target actions:

```text
BuildQuote
ApplyPromotion
FinalizeQuote
```

## 10.10 Payment

```text
app/Models/Payment.php
    → app/Modules/Payment/Infrastructure/Persistence/Models/Payment.php

app/Models/PaymentGatewayTransaction.php
    → app/Modules/Payment/Infrastructure/Persistence/Models/PaymentGatewayTransaction.php

app/Services/MidtransService.php
    → app/Modules/Payment/Infrastructure/Gateways/Midtrans/MidtransService.php

app/Http/Controllers/Api/Customer/PaymentController.php
    → app/Modules/Payment/Presentation/Api/Customer/PaymentController.php

app/Http/Controllers/Api/MidtransNotificationController.php
    → app/Modules/Payment/Presentation/Webhook/MidtransNotificationController.php
```

If Admin payment UI is implemented through Booking/dashboard controllers today, do not create duplicate payment logic just for folder purity.

## 10.11 Subscription

```text
app/Models/SubscriptionPlan.php
    → app/Modules/Subscription/Infrastructure/Persistence/Models/SubscriptionPlan.php

app/Models/ProviderSubscription.php
    → app/Modules/Subscription/Infrastructure/Persistence/Models/ProviderSubscription.php

app/Services/ProviderEntitlementService.php
    → app/Modules/Subscription/Application/Services/ProviderEntitlementService.php

app/Http/Controllers/Api/Provider/SubscriptionController.php
    → app/Modules/Subscription/Presentation/Api/Provider/SubscriptionController.php

app/Console/Commands/GrantLegacySubscriptions.php
    → app/Modules/Subscription/Console/Commands/GrantLegacySubscriptions.php
```

Register relocated console commands if Laravel no longer discovers them automatically.

## 10.12 Promotion

```text
app/Models/Coupon.php
    → app/Modules/Promotion/Infrastructure/Persistence/Models/Coupon.php

app/Services/CouponService.php
    → app/Modules/Promotion/Application/Services/CouponService.php

app/Http/Controllers/Api/CouponValidationController.php
    → app/Modules/Promotion/Presentation/Api/Public/CouponValidationController.php

app/Http/Controllers/Admin/CouponController.php
    → app/Modules/Promotion/Presentation/Web/Admin/CouponController.php

app/Http/Controllers/Api/Admin/CouponController.php
    → app/Modules/Promotion/Presentation/Api/Admin/CouponController.php
```

## 10.13 Review

```text
app/Models/BranchReview.php
    → app/Modules/Review/Infrastructure/Persistence/Models/BranchReview.php

app/Models/StaffReview.php
    → app/Modules/Review/Infrastructure/Persistence/Models/StaffReview.php

app/Http/Controllers/Api/Customer/ReviewController.php
    → app/Modules/Review/Presentation/Api/Customer/ReviewController.php
```

## 10.14 Notification

```text
app/Models/AppNotification.php
    → app/Modules/Notification/Infrastructure/Persistence/Models/AppNotification.php

app/Services/AppNotificationService.php
    → app/Modules/Notification/Application/Services/AppNotificationService.php

app/Events/UserNotificationSent.php
    → app/Modules/Notification/Domain/Events/UserNotificationSent.php

app/Http/Controllers/NotificationController.php
    → app/Modules/Notification/Presentation/Web/NotificationController.php
```

## 10.15 Chat

```text
app/Models/ChatThread.php
    → app/Modules/Chat/Infrastructure/Persistence/Models/ChatThread.php

app/Models/ChatMessage.php
    → app/Modules/Chat/Infrastructure/Persistence/Models/ChatMessage.php

app/Events/ChatMessageSent.php
    → app/Modules/Chat/Domain/Events/ChatMessageSent.php

app/Events/ChatThreadUpdated.php
    → app/Modules/Chat/Domain/Events/ChatThreadUpdated.php

app/Support/ChatMessagePresenter.php
    → app/Modules/Chat/Presentation/Support/ChatMessagePresenter.php

app/Support/ChatUnreadCounter.php
    → app/Modules/Chat/Application/Support/ChatUnreadCounter.php
```

## 10.16 Support

`SupportChatController.php` currently mixes support/chat/ticket presentation concerns.

Move it intact first:

```text
app/Http/Controllers/SupportChatController.php
    → app/Modules/Support/Presentation/Web/SupportChatController.php
```

Do not split the 1,000+ line controller blindly.

After parity is green, extract smaller application actions/controllers while preserving routes and views.

## 10.17 Media

Create the ownership boundary for:

- provider images;
- service images;
- staff images;
- review images;
- chat attachments;
- KTP;
- NIB;
- provider verification documents;
- private support/dispute attachments.

Do not rewrite storage in the same step as module relocation.

## 10.18 Audit

Create a new Audit module.

Initial target:

```text
Audit/
├── Application/
│   ├── Actions/
│   │   └── RecordAuditEvent.php
│   └── Queries/
│       └── SearchAuditLogs.php
├── Infrastructure/
│   └── Persistence/
└── Presentation/
    └── Web/
        └── Admin/
```

Do not add a huge synchronous audit burden to every request before performance/testing exists.

Start with high-value security/business actions.

---

# 11. Blade must remain first-class

Preserve all current Admin and Provider Blade templates.

Target:

```text
backend/laravel-core/resources/views/
├── admin/
├── provider/
├── notifications/
└── ...
```

It is acceptable to gradually normalize:

```text
resources/views/
├── layouts/
├── components/
├── admin/
└── provider/
```

but do not rename/restructure hundreds of Blade view names in the same phase as controller relocation unless all references/tests are updated and verified.

## 11.1 Provider Blade flow

The correct architecture is:

```text
provider.takein.id
→ Laravel web route
→ provider auth/scope/permission
→ Module Presentation/Web/Provider controller
→ Module Application action/query
→ Domain
→ persistence
→ Blade response
```

Do not do:

```text
Blade → HTTP → api.takein.id → same Laravel app
```

## 11.2 Admin Blade flow

```text
admin.takein.id
→ Cloudflare Access / edge policy
→ Laravel admin auth
→ MFA target
→ Admin RBAC
→ Module Presentation/Web/Admin controller
→ Application
→ Domain
```

---

# 12. Routes

## 12.1 Web route structure

Refactor physical route files to:

```text
routes/
├── web.php
└── web/
    ├── public.php
    ├── auth.php
    ├── provider.php
    └── admin.php
```

`routes/web.php` may act as a compatibility aggregator:

```php
require __DIR__.'/web/public.php';
require __DIR__.'/web/auth.php';
require __DIR__.'/web/provider.php';
require __DIR__.'/web/admin.php';
```

Preserve all existing web URLs and route names during the first route split.

## 12.2 API route structure

Target physical layout:

```text
routes/
├── api.php
└── api/
    └── v1/
        ├── public.php
        ├── auth.php
        ├── customer.php
        ├── provider.php
        ├── admin.php
        ├── partner.php
        └── webhooks.php
```

During the first split:

- preserve current `/api/...` URLs;
- preserve existing route names;
- `routes/api.php` should require the split files;
- do not automatically add an extra `/v1` prefix if it breaks current Next.js clients.

The `v1/` directory establishes ownership/version intent.

Actual `/api/v1/...` rollout must be a separate compatibility phase.

If adding `/api/v1` aliases later:

- do not remove legacy `/api/...` routes immediately;
- use distinct route names;
- add contract tests;
- update clients;
- deprecate legacy endpoints explicitly.

## 12.3 Internal API

Create:

```text
routes/internal/v1.php
```

but do not expose it to the public gateway.

Because this is one Laravel modular monolith, modules should generally use direct application calls, not internal HTTP.

## 12.4 Operations

Create:

```text
routes/ops/
├── health.php
└── readiness.php
```

Keep a lightweight health endpoint.

Do not expose detailed metrics/debug data publicly.

---

# 13. API trust boundaries

External does not mean unauthenticated.

Use these classes:

1. Public API.
2. Auth API.
3. Customer-authenticated API.
4. Provider-authenticated API only where an external provider API is genuinely needed.
5. Admin API only where an external admin API is genuinely needed.
6. Partner API for future third-party clients.
7. Webhooks.
8. Internal/private endpoints.
9. Operational endpoints.

Authorization must remain in Laravel, not just at Nginx/Cloudflare.

---

# 14. Authentication and authorization

## 14.1 Customer Next.js

Keep Sanctum as the first-party authentication foundation.

Do not store sensitive long-lived auth tokens in browser localStorage merely for convenience.

Maintain:

- Secure cookies;
- HttpOnly where applicable;
- CSRF protection;
- strict allowed origins;
- session rotation;
- logout/revocation.

## 14.2 Provider Blade

Use Laravel web/session auth.

Enforce:

- provider role;
- provider active status;
- document verification where required;
- `provider_id` ownership;
- `branch_id` scope;
- provider role/menu permissions.

## 14.3 Admin Blade

Enforce:

- admin identity;
- role/permission boundary;
- target MFA architecture;
- audit of sensitive actions.

Do not assume “admin logged in” means unrestricted actions should be scattered across controllers.

## 14.4 Resource authorization

Every resource route that accepts an ID must check ownership/scope.

Examples:

- customer cannot read another customer booking;
- Provider A cannot access Provider B resources;
- Branch A account cannot mutate Branch B data unless explicitly allowed;
- Staff review must reference participating staff;
- admin function permissions must be explicit for sensitive operations.

Add regression tests for IDOR/BOLA-style access.

---

# 15. Security hardening phase

Do this only after structural parity is green.

## 15.1 Known high-priority concerns

### Provider subscription API authorization

Audit provider subscription endpoints.

Require correct provider actor/owner authorization.

Do not rely only on `auth:sanctum`.

Branch accounts must not receive subscription-purchase privilege unless the existing business rule explicitly permits it.

Add tests.

### Sensitive provider documents

KTP/NIB/verification files must not remain permanent public URLs.

Move target architecture toward:

```text
private object storage
→ authorized Laravel action
→ short-lived signed access
```

Public marketing/service images can remain public.

### Public backend port

Production Laravel origin must not be intended for direct internet access on `:8000`.

Nginx/private Docker networking/edge should be the external path.

Development port publishing is fine in local compose.

### Trusted proxy configuration

If `trustProxies('*')` remains, production origin must only be reachable through trusted infrastructure.

Document the reasoning.

### Rate limiting

Create named Laravel rate limiters for at least:

- login;
- registration;
- password reset;
- availability;
- search;
- booking creation/finalization;
- coupon validation;
- payment creation;
- provider write APIs;
- webhook endpoint.

Apply business-aware keys such as IP + email/account/user/provider where appropriate.

### Payment/webhook replay

Add regression tests proving duplicate notifications do not duplicate irreversible side effects.

### Subscription webhook replay

Make activation/period changes idempotent.

### File upload validation

Validate:

- MIME;
- extension;
- size;
- allowed image/document type;
- authorization;
- visibility;
- generated object key;
- never trust original filename for storage path.

---

# 16. Redis and Horizon phase

Do not activate this until structural and core feature tests are green.

## 16.1 Docker

Add a Redis service for local/staging production topology.

Production may later use managed Redis.

Laravel production target:

```env
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

Install/enable the Redis PHP extension in the Laravel Docker image if it is not already available.

Do not silently fall back to file session/cache in production.

## 16.2 Redis ownership

Use explicit prefixes/connections/config where practical for:

- sessions;
- cache;
- locks;
- rate limits;
- queue;
- idempotency;
- Reverb scale-out/PubSub.

This does not require seven Redis servers.

## 16.3 Horizon

Install/configure Laravel Horizon after Redis queue tests work.

Create queue classes/priorities such as:

```text
critical
payments
bookings
default
notifications
emails
media
analytics
```

Do not allow low-priority email/media jobs to block payment/booking work.

Create separate runtime process/container for:

- Laravel HTTP;
- Horizon;
- Scheduler;
- Reverb.

## 16.4 Queue safety

Queued jobs must be designed with:

- idempotency;
- retry safety;
- timeout;
- bounded attempts;
- backoff;
- useful logging;
- correlation/request IDs where possible.

When a job is a side effect of a DB transaction, dispatch after commit where required.

---

# 17. Reverb phase

Keep Laravel Reverb.

Do not add Node/Go websocket services.

Target production runtime:

```text
ws.takein.id
→ Nginx/WebSocket proxy
→ Reverb process(es)
→ Redis coordination when scale-out is required
```

Verify:

- private/presence channel authorization;
- customer/provider/admin cannot subscribe to unauthorized resources;
- failures in broadcasting do not roll back committed booking/payment state.

---

# 18. Storage phase

## 18.1 Object storage architecture

Prepare S3-compatible storage such as S3/R2.

Separate logical visibility:

```text
public/
├── providers/
├── branches/
├── services/
├── staff/
└── reviews/

private/
├── ktp/
├── nib/
├── provider-verification/
├── disputes/
├── support/
└── exports/
```

Do not hardcode one cloud provider deeply into domain code.

Use Laravel filesystem abstraction inside Media infrastructure.

## 18.2 Migration safety

Do not delete current local files during the first storage change.

Provide:

- migration command/tool;
- verification;
- reversible fallback/read path if needed;
- documented cutover procedure.

---

# 19. Database policy

Keep MySQL 8.

Do not migrate to PostgreSQL.

## 19.1 Source of truth

MySQL remains final source of truth for:

- booking;
- payment;
- subscription;
- promotion quota;
- provider data.

Redis is not the final transactional truth.

## 19.2 Module table ownership

Document table ownership in:

```text
docs/database/table-ownership.md
```

Example:

| Table/domain | Owner |
|---|---|
| users / auth | Identity |
| customer_profiles / customer activity | Customer |
| provider_profiles / roles | Provider |
| provider_branches | Branch |
| services / service_categories | Catalog |
| provider_staff / skills / schedule | Staff |
| bookings / participants / booking service selections | Booking |
| payments / payment_gateway_transactions | Payment |
| provider_subscriptions / subscription_plans | Subscription |
| coupons | Promotion |
| branch_reviews / staff_reviews | Review |
| chat_threads / chat_messages | Chat |
| notifications | Notification |

## 19.3 Migration policy

Never edit an already-applied migration for a future schema change.

Use:

```text
expand
→ deploy compatible code
→ migrate/backfill
→ verify
→ contract
```

for zero/minimal-downtime schema evolution.

Preserve the existing backup-before-migrate intention in the production entrypoint, but improve deployment so web process startup is not the only place schema migration can occur.

---

# 20. Money

Do not broadly rewrite monetary math during the structural phase.

Create a target Shared Money abstraction.

For new/refactored financial paths, prefer exact integer minor units / exact decimal behavior rather than binary floating point.

Before changing existing coupon/tax/payment arithmetic, add golden regression tests proving current expected values.

Treat hard-coded pricing/tax rules as configuration/business policy only after product semantics are confirmed.

---

# 21. API contracts

Create:

```text
contracts/openapi/v1/
├── public.yaml
├── auth.yaml
├── customer.yaml
├── provider.yaml
├── admin.yaml
├── partner.yaml
└── webhooks.yaml
```

Start by documenting the existing API.

Do not redesign every response in the first pass.

Once contracts exist, introduce stable response/error conventions gradually.

Target error code examples:

```text
AUTH_INVALID_CREDENTIALS
AUTH_UNAUTHORIZED
PROVIDER_NOT_VERIFIED
PROVIDER_SCOPE_VIOLATION
BRANCH_SCOPE_VIOLATION
BOOKING_SLOT_UNAVAILABLE
BOOKING_HOLD_EXPIRED
BOOKING_INVALID_TRANSITION
PAYMENT_INVALID_SIGNATURE
PAYMENT_ALREADY_PROCESSED
COUPON_EXHAUSTED
VALIDATION_FAILED
RATE_LIMITED
```

---

# 22. Platform structure

Create:

```text
platform/
├── edge/
│   └── cloudflare/
│       ├── dns/
│       ├── waf/
│       ├── rate-limits/
│       ├── access/
│       ├── cache/
│       └── origin/
│
├── gateway/
│   └── nginx/
│       ├── nginx.conf
│       ├── sites/
│       │   ├── customer.conf
│       │   ├── partners.conf
│       │   ├── provider.conf
│       │   ├── admin.conf
│       │   ├── api.conf
│       │   ├── hooks.conf
│       │   └── websocket.conf
│       └── snippets/
│
├── docker/
│   ├── local/
│   ├── staging/
│   ├── production/
│   └── images/
│
├── observability/
│   ├── opentelemetry/
│   ├── prometheus/
│   ├── grafana/
│   │   └── dashboards/
│   ├── loki/
│   └── alerting/
│
├── security/
│   ├── threat-models/
│   ├── rbac/
│   ├── policies/
│   ├── incident-response/
│   ├── vulnerability-management/
│   └── secrets/
│       └── README.md
│
└── terraform/
    ├── modules/
    └── environments/
        ├── staging/
        └── production/
```

Do not put real credentials here.

Cloudflare/Terraform files may be documented templates if account IDs/credentials are unavailable.

Do not fake a successful deployment to external infrastructure.

---

# 23. Nginx topology

The intended external routing is:

```text
takein.id / www.takein.id
    → customer Next.js

partners.takein.id
    → provider landing Next.js

provider.takein.id
    → Laravel Blade provider dashboard

admin.takein.id
    → Laravel Blade admin dashboard

api.takein.id
    → Laravel REST API

hooks.takein.id
    → Laravel webhook routes

ws.takein.id
    → Reverb
```

Production Laravel application containers should be private upstreams behind Nginx/edge infrastructure.

---

# 24. Docker target

Do not keep a single container responsible for every long-running Laravel workload.

Target services/processes:

```text
mysql
redis

laravel-api-1
laravel-api-2        # optional local; production horizontal target

horizon
scheduler
reverb

customer-web
provider-landing

nginx
```

MySQL may be external/managed in real production later.

Redis may be managed later.

S3/R2 is external object storage.

## 24.1 Laravel image

Update paths after monorepo move.

Keep a production-focused image.

Requirements:

- no source bind mounts in production;
- optimized Composer install;
- correct PHP extensions;
- Redis extension when Redis is enabled;
- writable storage/cache paths;
- no dev dependencies in final production image;
- health check;
- predictable entrypoint.

## 24.2 Deployment commands

Production deployment must have explicit steps for:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

only when compatible with the application.

Do not cache configuration before required environment values are available.

Workers must be gracefully restarted/reloaded after deployment.

---

# 25. Octane policy

Do not enable Laravel Octane during this refactor.

After:

- modular refactor is stable;
- tests are green;
- shared/static state is audited;
- load tests exist;
- memory behavior is understood;

create a benchmark ADR comparing the current PHP runtime against Octane/FrankenPHP.

Only enable Octane if measurements justify it.

---

# 26. Observability

## 26.1 First level

Add/prepare:

- structured application logs;
- Horizon dashboard/metrics;
- Laravel Pulse where safe;
- request IDs;
- correlation IDs.

## 26.2 Enterprise telemetry target

Prepare OpenTelemetry-compatible instrumentation and collector configuration.

Track:

### Technical metrics

- request throughput;
- p50/p95/p99 API latency;
- 5xx rate;
- DB query latency;
- slow query count;
- Redis latency;
- cache hit rate;
- queue depth;
- queue wait;
- failed jobs;
- Horizon worker saturation;
- Reverb connections;
- Reverb message rate;
- memory/CPU;
- disk/object storage errors.

### Business metrics

- booking create count;
- booking success rate;
- booking conflict rate;
- booking hold expiration;
- payment created;
- payment success;
- payment failure;
- invalid payment webhooks;
- provider active/verified count;
- subscription activation/expiry;
- review creation.

Do not log secrets or full sensitive PII.

---

# 27. Request/correlation IDs

Create middleware that ensures every request has a stable request/correlation identifier.

Include it in:

- logs;
- error responses when safe;
- jobs spawned by the request;
- audit records;
- payment processing logs;
- booking processing logs.

Do not use user-controlled unvalidated IDs blindly as trusted log metadata.

---

# 28. Audit log

Implement incrementally for high-value actions.

Initial audited actions should include:

- admin provider approve/reject/suspend;
- sensitive role/permission changes;
- provider branch/user permission changes;
- booking cancellation/reschedule by staff/admin;
- payment refund/manual status operation;
- subscription changes;
- sensitive document access;
- security setting changes.

Suggested fields:

```text
actor_type
actor_id
action
resource_type
resource_id
provider_id
branch_id
request_id
ip
user_agent
before
after
created_at
```

Redact sensitive fields.

---

# 29. Testing strategy

## 29.1 Preserve existing Laravel tests

Move existing tests to:

```text
backend/laravel-core/tests/
```

Do not rewrite assertions merely to make a refactor pass.

Fix production code/imports first.

## 29.2 Add

```text
backend/laravel-core/tests/
├── Unit/
├── Feature/
├── Integration/
├── Concurrency/
└── Security/
```

System-level:

```text
tests/
├── contract/
├── e2e/
│   ├── customer/
│   ├── provider/
│   └── admin/
├── concurrency/
├── security/
├── load/
└── smoke/
```

## 29.3 Mandatory new security tests

Add tests for:

- customer resource ownership;
- provider isolation;
- branch scope;
- provider role/menu permission;
- subscription purchase authorization;
- admin sensitive action authorization;
- private document authorization;
- duplicate Midtrans webhook;
- duplicate subscription webhook;
- rate limiting.

## 29.4 Mandatory concurrency tests

At minimum model/test scenarios for:

- many users requesting one-capacity slot;
- same idempotency key retried;
- finalize retried;
- voucher/quota contention where applicable;
- reschedule contention;
- duplicate webhook delivery.

Expected one-capacity booking result:

```text
capacity = 1
N concurrent attempts
→ at most 1 committed conflicting booking
```

Do not fake concurrency tests with sequential calls and call them concurrent.

Use a real test approach supported by the environment/database.

---

# 30. Frontend contract safety

Do not rename existing API fields used by Next.js without simultaneously:

- updating OpenAPI;
- updating the frontend caller;
- adding contract tests;
- preserving compatibility where needed.

Do not rewrite the Next.js apps during backend modularization.

Keep the provider landing domain documented as:

```text
partners.takein.id
```

not `partner.takein.id` and not `mitra.ditakein.com`.

---

# 31. CI/CD

Create GitHub Actions workflows or equivalent configuration for:

```text
.github/workflows/
├── backend-quality.yml
├── backend-tests.yml
├── backend-security.yml
├── customer-web.yml
├── provider-landing.yml
├── contract-tests.yml
├── concurrency-tests.yml
├── dependency-scan.yml
├── secret-scan.yml
├── container-scan.yml
├── build-images.yml
├── deploy-staging.yml
└── deploy-production.yml
```

Do not create a deployment job containing fake secrets.

Use environment/secret references.

Recommended checks:

### Backend

- Composer validation;
- Pint check;
- PHP syntax;
- PHPUnit;
- application boot;
- route listing;
- migration status on a test DB;
- optional static analyzer if added intentionally.

### Next.js

- `npm ci`;
- lint if configured;
- build;
- existing tests.

### Security

- dependency audit;
- secret scan;
- container scan;
- authorization test suite.

---

# 32. Documentation required

Create/update:

```text
README.md
ARCHITECTURE.md
SECURITY.md
CONTRIBUTING.md
CODEOWNERS
PHASE-STATUS.md
```

and:

```text
docs/architecture/
├── system-context.md
├── containers.md
├── domain-boundaries.md
├── module-dependencies.md
└── deployment.md

docs/adr/
├── ADR-001-laravel-modular-monolith.md
├── ADR-002-mysql-8.md
├── ADR-003-redis.md
├── ADR-004-sanctum.md
├── ADR-005-reverb.md
├── ADR-006-blade-admin-provider.md
└── ADR-007-nextjs-public-surfaces.md

docs/domains/
├── identity.md
├── customer.md
├── provider.md
├── branch.md
├── catalog.md
├── staff.md
├── availability.md
├── booking.md
├── checkout.md
├── payment.md
├── subscription.md
├── promotion.md
├── review.md
├── notification.md
├── chat.md
├── support.md
├── media.md
└── audit.md

docs/database/
├── table-ownership.md
├── migration-policy.md
└── backup-restore.md

docs/security/
├── authentication.md
├── authorization.md
├── data-classification.md
├── file-security.md
└── threat-model.md

docs/runbooks/
├── deployment.md
├── rollback.md
├── mysql-down.md
├── redis-down.md
├── queue-backlog.md
├── reverb-down.md
├── payment-webhook-failure.md
└── security-incident.md
```

Documentation must describe the actual implemented state, not a fictional future state.

Mark future items explicitly.

---

# 33. Execution phases

Codex must execute in this order.

Do not combine all phases into one uncontrolled mass edit.

---

## PHASE 0 — Baseline inventory

Before changing paths:

1. Read the repository.
2. Record:
   - Laravel version;
   - PHP constraint/runtime;
   - Composer dependencies;
   - Next.js package versions;
   - number/list of migrations;
   - number/list of tests;
   - route list/names;
   - controller/model/service/event/middleware inventory;
   - Docker topology;
   - current env driver choices;
   - current public/provider/admin/customer URLs from config.
3. Run whatever is available:
   ```bash
   composer validate
   php -l <php files>
   php artisan test
   php artisan route:list
   npm ci && npm run build
   ```
4. If dependencies are missing and can be installed safely, install them.
5. If a baseline test is already failing before refactor, record it as **pre-existing**.
6. Create `PHASE-STATUS.md`.

### Gate 0

Do not claim baseline green unless it was actually run.

---

## PHASE 1 — Safe monorepo move

Move:

```text
frontend/customer-landing
→ apps/customer-web

frontend/provider-landing
→ apps/provider-landing
```

Move Laravel root files/directories into:

```text
backend/laravel-core/
```

Keep repository-level concerns at root:

```text
README.md
ARCHITECTURE.md
SECURITY.md
CONTRIBUTING.md
CODEOWNERS
docker-compose.yml
platform/
contracts/
packages/
tests/
docs/
.github/
```

Update:

- Docker build contexts;
- Dockerfile paths;
- frontend APP_PATH;
- scripts;
- README references;
- CI paths;
- `.dockerignore`;
- Makefile.

Do not change Laravel namespaces yet unless needed by relocation.

### Gate 1

Prove:

- Laravel boots from `backend/laravel-core`;
- Next customer builds from `apps/customer-web`;
- Next provider landing builds from `apps/provider-landing`;
- route count/names are unchanged;
- migrations unchanged;
- existing tests do not regress.

---

## PHASE 2 — Module relocation

Create module tree.

Move the classes in Section 10.

For every relocated PHP class:

1. update namespace;
2. update imports across repository;
3. update class references;
4. update route imports;
5. update model relationship imports;
6. update factory references;
7. update tests;
8. update providers/bootstrap registration if needed;
9. run `composer dump-autoload`;
10. run syntax/test gates.

**Do not alter method bodies unless import/namespace compatibility requires it.**

Prefer mechanical relocation first.

### Compatibility rule

If too many callers depend on an old namespace and a safe one-shot migration is not possible:

- temporarily create a deprecated compatibility alias/facade;
- document it;
- migrate callers;
- remove compatibility layer only after tests are green.

Do not keep duplicate business implementations.

### Gate 2

Verify:

- no stale `App\Models\...` imports for classes that moved;
- no stale `App\Services\...` imports for classes that moved;
- no broken route controller references;
- Composer autoload works;
- PHPUnit does not regress;
- Blade renders referenced controllers/views;
- Next contract behavior is unchanged.

---

## PHASE 3 — Split route files

Split web and API route files physically.

Preserve URL and name behavior.

Add route inventory comparison script under:

```text
tools/validation/compare-routes.*
```

Compare before/after route names/methods/URIs.

### Gate 3

No accidental removed/renamed route.

Any intentionally changed route requires:

- compatibility;
- test;
- documentation.

---

## PHASE 4 — Extract application boundaries carefully

Only after structural parity.

Start with the largest technical-debt classes:

- `BookingFlowService`;
- `SupportChatController`;
- large Provider/Admin controllers;
- PublicCatalog controller.

Do not rewrite from scratch.

Use strangler-style internal extraction:

```text
existing method
→ call new Action/Query
→ tests stay green
→ move next slice
```

Booking extraction order:

1. read/query operations;
2. staff eligibility;
3. availability;
4. booking hold;
5. create booking;
6. finalize;
7. reschedule/cancel;
8. group booking;
9. queue/walk-in;
10. payment handoff.

Keep a compatibility `BookingFlowService` facade until all callers are migrated.

### Gate 4

Booking behavior and state-machine tests green after every extraction slice.

---

## PHASE 5 — Security hardening

Implement Section 15.

Add tests before/with every security fix.

Prioritize:

1. provider subscription authorization;
2. KTP/NIB/private documents;
3. IDOR/provider/branch scope tests;
4. webhook replay/idempotency;
5. rate limiting;
6. origin/private-port architecture;
7. proxy trust;
8. upload validation;
9. audit of sensitive actions.

### Gate 5

Security tests green.

No sensitive documents are newly exposed.

---

## PHASE 6 — Redis / queue / Horizon

1. Add Redis to Docker local/staging topology.
2. Add Redis PHP extension.
3. Configure Laravel connections.
4. Prove cache works.
5. Prove session works.
6. Prove queue works.
7. Move production defaults toward Redis.
8. Install/configure Horizon.
9. Create queue priorities.
10. Separate runtime processes.
11. Add queue tests/health.

Do not change booking DB final-truth semantics to “Redis-only locking”.

### Gate 6

Run multi-process/multi-instance capable checks where possible.

No session loss when requests hit different Laravel instances using shared Redis.

No synchronous queue dependency remains where async processing is intended.

---

## PHASE 7 — Reverb / storage / observability

### Reverb

Verify channel auth and production topology.

### Storage

Introduce Media abstraction and private/public object storage configuration.

### Observability

Implement:

- request ID;
- correlation ID;
- structured logs;
- Horizon;
- Pulse if safe;
- OpenTelemetry-compatible config;
- dashboard templates;
- alerts/runbooks.

Do not pretend external Grafana/Cloudflare/S3 resources exist unless actually provisioned.

### Gate 7

Application works when optional observability backend is unavailable.

Telemetry failure must not break booking/payment.

---

## PHASE 8 — Contract tests / CI / documentation

Finish:

- OpenAPI docs;
- contract tests;
- GitHub workflows;
- architecture docs;
- security docs;
- runbooks;
- CODEOWNERS;
- CONTRIBUTING;
- final validation scripts.

### Gate 8

A new engineer can:

1. clone repo;
2. follow README;
3. start local stack;
4. run migrations;
5. run tests;
6. open customer app;
7. open provider landing;
8. open provider Blade dashboard;
9. open admin Blade dashboard.

---

## PHASE 9 — Production-readiness validation

Run/produce reports for:

- route parity;
- test suite;
- frontend builds;
- migration status;
- Docker build;
- health checks;
- queue;
- Redis;
- Reverb;
- security tests;
- basic load test;
- backup/restore procedure;
- rollback procedure.

Do not claim production ready if an important validation could not be run.

Record exact unresolved risks.

---

# 34. Root Docker target

Create a root compose suitable for local enterprise topology, with profiles or overrides if useful.

Conceptual target:

```yaml
services:
  mysql:
    # MySQL 8

  redis:
    # Redis

  laravel:
    # backend/laravel-core

  horizon:
    # same application image, horizon command

  scheduler:
    # same application image, scheduler command

  reverb:
    # same application image, reverb command

  customer-web:
    # apps/customer-web

  provider-landing:
    # apps/provider-landing

  nginx:
    # local gateway/reverse proxy
```

Do not expose MySQL/Redis publicly in production.

Local developer ports are acceptable.

---

# 35. Environment contract

Update `.env.example` and `.env.production.example` with placeholder values for the final topology.

Document at least:

```env
APP_URL=https://api.takein.id

CUSTOMER_FRONTEND_URL=https://takein.id
PROVIDER_FRONTEND_URL=https://partners.takein.id

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# Do not automatically widen SESSION_DOMAIN to .takein.id
# unless required and threat-modelled.

SANCTUM_STATEFUL_DOMAINS=takein.id,www.takein.id,api.takein.id

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

FILESYSTEM_DISK=<configured storage>

REVERB_HOST=ws.takein.id
REVERB_PORT=443
REVERB_SCHEME=https
```

Provider/Admin Blade sessions are server-rendered and do not require exposing API tokens to JavaScript.

Review cookie/domain behavior carefully before sharing one session cookie across all subdomains.

Prefer narrower cookie scope where practical.

---

# 36. Production host responsibilities

Document:

```text
takein.id
    customer Next.js

partners.takein.id
    provider Next.js landing

provider.takein.id
    Laravel Blade provider UI

admin.takein.id
    Laravel Blade admin UI

api.takein.id
    Laravel external REST API

hooks.takein.id
    external webhook ingress

ws.takein.id
    Reverb WebSocket

assets.takein.id
    CDN/public media
```

Admin ingress should be designed for stronger controls, e.g. edge access policy + Laravel auth/MFA/RBAC.

---

# 37. Quality rules

## 37.1 Controllers

Controllers should eventually become thin:

```text
authorize
validate/request DTO
call Action/Query
return response/view/resource
```

No giant new controller business logic.

## 37.2 Actions

One business use case per action where practical.

Examples:

```text
CreateBookingHold
FinalizeBooking
CancelBooking
ApproveProvider
CreateService
PurchaseSubscription
ProcessPaymentWebhook
```

## 37.3 Queries

Read-heavy operations may use explicit query objects/read models instead of forcing complex reporting into domain mutation services.

## 37.4 Naming

Use names representing business intent.

Avoid generic:

```text
Helper
Manager
CommonService
UtilityService
Handler2
NewService
```

unless the abstraction is genuinely technical/shared.

---

# 38. Performance rules

Do not optimize blindly.

First:

- indexes;
- N+1 detection;
- pagination;
- response size;
- DB query plans;
- caching read-heavy stable data;
- async side effects;
- Redis shared state;
- horizontal Laravel replicas.

Do not enable Octane until benchmark phase.

Do not add microservices because a controller is large.

Large class size is a maintainability problem, not proof of a need for another network service.

---

# 39. Booking concurrency invariant

No refactor may weaken this.

The final booking write path must continue to use transactional integrity.

Conceptual pattern:

```text
request
→ idempotency
→ optional Redis coordination/hold
→ DB transaction
→ row/resource lock
→ recheck conflict
→ write
→ commit
→ async side effects
```

Redis may improve coordination.

MySQL remains the transaction truth.

Add a clear concurrency test report.

---

# 40. Payment invariant

Payment/webhook processing must be:

- signature-verified;
- transactionally consistent;
- valid-state-transition checked;
- idempotent/replay-safe;
- auditable;
- safe when notification/email/broadcast systems fail.

Never mark a payment paid solely because the browser returns from a gateway page.

Gateway server-side notification/verification remains authoritative according to current integration design.

---

# 41. Completion criteria

Codex is not finished when folders merely exist.

The task is complete only when the following are true or explicitly marked blocked:

## Structure

- [ ] Customer Next.js is under `apps/customer-web`.
- [ ] Provider Next.js landing is under `apps/provider-landing`.
- [ ] Laravel is under `backend/laravel-core`.
- [ ] Provider dashboard remains Blade.
- [ ] Admin dashboard remains Blade.
- [ ] Laravel modules exist with real existing classes moved into correct ownership.
- [ ] Shared does not become a business-rule dumping ground.
- [ ] Platform/docs/contracts/test structure exists where implemented.

## Behavior

- [ ] Existing route behavior preserved during structural phases.
- [ ] Existing migrations preserved.
- [ ] Existing tests pass or pre-existing failures are documented.
- [ ] Customer Next build passes.
- [ ] Provider landing build passes.
- [ ] Booking behavior preserved.
- [ ] Payment behavior preserved.
- [ ] Provider/branch authorization preserved.

## Enterprise runtime

- [ ] Redis configuration is available.
- [ ] Session/cache/queue production target uses Redis.
- [ ] Horizon configured.
- [ ] Scheduler isolated.
- [ ] Reverb isolated.
- [ ] production origin is designed behind Nginx/edge.
- [ ] sensitive file storage architecture is private.
- [ ] health/readiness exists.
- [ ] logs have request/correlation IDs.
- [ ] rate limit policies exist.

## Security

- [ ] Subscription authorization audited/fixed.
- [ ] IDOR/BOLA tests added.
- [ ] provider/branch scope tests added.
- [ ] webhook replay tests added.
- [ ] sensitive docs are not intentionally public.
- [ ] secrets absent from repository.
- [ ] upload validation audited.

## Operations

- [ ] README local setup works.
- [ ] staging/production Docker configuration documented.
- [ ] CI pipelines exist.
- [ ] rollback documented.
- [ ] backup/restore documented.
- [ ] unresolved production risks are listed honestly.

---

# 42. Final validation report

At the end, generate:

```text
docs/architecture/FINAL-REFACTOR-REPORT.md
```

Include:

1. what was moved;
2. namespace mappings;
3. behavior intentionally unchanged;
4. security fixes;
5. Redis/Horizon changes;
6. Docker/runtime changes;
7. routes before vs after;
8. tests executed and result;
9. frontend builds executed and result;
10. migrations count/hash/parity;
11. known pre-existing defects;
12. new unresolved risks;
13. follow-up work;
14. commands to run locally;
15. commands to deploy;
16. rollback procedure.

Also update `PHASE-STATUS.md` with every phase:

```text
NOT_STARTED
IN_PROGRESS
DONE
BLOCKED
```

and evidence.

---

# 43. Required working style for Codex

1. Read before editing.
2. Prefer small mechanically verifiable changes.
3. Do not make broad aesthetic rewrites.
4. Preserve current user-facing copy/UI unless required.
5. Never silently drop behavior.
6. Never silence a failing test by weakening/removing the assertion unless the existing test is proven wrong and the reason is documented.
7. After moving a class, search the entire repository for its old namespace.
8. After moving routes, compare route inventory.
9. After moving frontend paths, build both Next apps.
10. After changing Docker, build the affected images.
11. After changing queue/session/cache, test them.
12. After changing auth, add negative authorization tests.
13. Keep `PHASE-STATUS.md` current.
14. Leave the repository runnable at every completed phase.
15. Do not claim an external service has been provisioned when only configuration templates were created.

---

# 44. Start execution now

Perform PHASE 0 immediately.

Then execute the phases in order.

Do not stop after writing a plan.

The final architecture must remain:

```text
TAKEIN
│
├── Customer Next.js
│   └── takein.id
│
├── Provider Landing Next.js
│   └── partners.takein.id
│
└── Laravel Enterprise Core
    ├── provider.takein.id → Provider Blade
    ├── admin.takein.id    → Admin Blade
    ├── api.takein.id      → REST API
    ├── hooks.takein.id    → Webhooks
    ├── ws.takein.id       → Reverb
    │
    ├── Identity
    ├── Customer
    ├── Provider
    ├── Branch
    ├── Catalog
    ├── Staff
    ├── Availability
    ├── Booking
    ├── Checkout
    ├── Payment
    ├── Subscription
    ├── Promotion
    ├── Review
    ├── Notification
    ├── Chat
    ├── Support
    ├── Media
    └── Audit
```

Laravel remains the permanent core backend.

Admin and Provider dashboards remain Blade.

Do not replace this architecture with Go, microservices, gRPC, Kafka, or an unnecessary frontend rewrite.
