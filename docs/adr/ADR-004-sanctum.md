# ADR-004: Laravel Sanctum for API authentication

- Status: Accepted
- Date: 2026-08-10

## Context

Customer Next.js memerlukan API authenticated, sementara dashboard admin dan
provider adalah Blade server-rendered. Repository juga mempunyai compatibility
untuk personal access token existing.

## Decision

Gunakan Sanctum untuk `/api/*` authenticated. Browser first-party dapat memakai
stateful cookie pada host yang diallowlist; personal access token existing tetap
didukung sesuai test compatibility. Blade memakai session guards `admin`,
`provider`, dan `provider_branch`; token tidak diekspos ke JavaScript dashboard.
Cookie default host-only dan CSRF tetap diterapkan kecuali dua compatibility
route yang tercatat di bootstrap.

## Consequences

Cross-origin cookie memerlukan CORS/CSRF/Sanctum host yang tepat. Shared
`SESSION_DOMAIN=.takein.id` tidak diaktifkan otomatis karena memperbesar blast
radius. Provider/branch scope tetap membutuhkan authorization setelah auth.
