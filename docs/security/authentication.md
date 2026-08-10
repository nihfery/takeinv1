# Authentication

## Current mechanisms

- Laravel Sanctum protects authenticated API routes. First-party browser
  requests can authenticate through the Laravel session when their hostname is
  in `SANCTUM_STATEFUL_DOMAINS`.
- API login can issue a personal access token only for a non-customer account
  when `issue_token=true`; legacy token compatibility is covered by tests.
- Blade uses session guards: `admin`, `provider`, and `provider_branch` plus the
  default web guard. Session IDs are stored in Redis in production.
- Login and registration are rate-limited by normalized identity plus IP.
- Successful login regenerates the session; logout invalidates session state
  and the current token where applicable.

## Cookie contract

Production uses encrypted Redis sessions, `Secure`, `HttpOnly`, and
`SameSite=Lax`. Prefer a host-only cookie (`SESSION_DOMAIN` blank). Do not widen
to `.takein.id` merely because multiple subdomains exist; Blade and API/frontend
flows should use explicit host/CORS/Sanctum configuration.

CSRF remains enabled globally. Compatibility exceptions currently include
`provider/signin` and API customer/provider registration routes because public
frontends submit across the existing boundary. These routes still validate and
rate-limit input, but the exception is a known attack-surface decision and
should be narrowed if the frontend deployment can adopt the Sanctum CSRF-cookie
flow without breaking compatibility.

## Production requirements

- Valid high-entropy `APP_KEY`; `app:deployment-check` rejects invalid keys.
- Unique session/Redis/Reverb/Midtrans secrets from a secret manager.
- HTTPS only and private Laravel origin.
- No demo seeding/login hints in staging or production.
- Admin MFA/identity-aware edge access is recommended but not implemented by
  this repository; do not claim it is active.
