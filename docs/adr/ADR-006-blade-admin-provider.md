# ADR-006: Keep admin and provider dashboards on Blade

- Status: Accepted
- Date: 2026-08-10

## Context

Dashboard admin/provider memiliki route, session guard, middleware, view, dan
business workflow Laravel yang sudah digunakan. Migrasi UI penuh akan
memperbesar scope dan risiko auth/behavior regression.

## Decision

Pertahankan `admin.takein.id` dan `provider.takein.id` sebagai Laravel Blade.
Provider public landing tetap aplikasi terpisah dan sign-in membuat session
Laravel sebelum redirect ke dashboard. Dashboard tidak menyimpan API token di
browser.

## Consequences

Authorization dan rendering tetap dekat dengan core. Asset/view Blade masih
dibuild/didistribusikan bersama image backend. Bila SPA dashboard dipertimbangkan
di masa depan, dibutuhkan ADR dan contract/auth migration terpisah.
