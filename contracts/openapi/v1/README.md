# TAKEIN OpenAPI v1 contracts

This directory documents the API that Laravel exposes today. The `v1` directory is an ownership/versioning boundary only: production compatibility URLs remain under `/api/...`, and the specifications deliberately do not add an `/api/v1` prefix.

| Contract | Active boundary |
|---|---|
| `public.yaml` | Catalog, discovery, coupons, liveness, and readiness |
| `auth.yaml` | Customer/provider registration and shared authentication |
| `customer.yaml` | Customer profile, activity, booking, review, and payment |
| `provider.yaml` | Provider profile, branch, service, staff, and subscription APIs |
| `admin.yaml` | Administrator booking, catalog, coupon, provider, and customer APIs |
| `partner.yaml` | Reserved; the current route registrar intentionally exposes no paths |
| `webhooks.yaml` | Midtrans notification ingress |

The files use JSON serialization, which is a valid YAML 1.2 representation and remains directly consumable by JSON-aware OpenAPI tooling. Existing response shapes vary by controller, so the first compatibility contract records exact methods, paths, request fields, trust boundaries, and status codes while leaving heterogeneous response objects open. It does not invent a new response envelope.

Each operation carries `x-laravel-route-name` and `x-authentication`. Provider operations also carry the effective `x-provider-permission`. These extensions are checked against Laravel's booted route collection by the contract gate.

Run the gate from the repository root:

```bash
php tests/contract/validate-openapi.php
```

The command fails when an active Laravel API method/path is missing or extra, a route name differs, a local schema reference is broken, authentication metadata drifts, provider permission metadata is wrong, or a signed document route omits its signature parameter. It also compares every request body and response status that Scramble can infer from the current controllers, while the explicit schemas cover controller validation that static inference cannot see.

When an API field or route changes, update the owning contract in the same change. Do not introduce `/api/v1` aliases here until compatibility routes, clients, and regression tests exist.
