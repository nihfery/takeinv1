# Identity domain

## Ownership

`Identity` owns the `users` aggregate, Laravel auth model, API
register/login/logout/me, and unified Blade login behavior. Tables:
`users`, `admin_profiles` (profile record is presented by admin compatibility
controllers), `personal_access_tokens`, and `password_reset_tokens`.

## Authentication surfaces

- Sanctum protects authenticated API routes.
- Session guards `web`, `admin`, `provider`, and `provider_branch` share the
  Eloquent user provider but use role/scope middleware.
- Provider landing POSTs to Laravel `/provider/signin`, which creates the Blade
  session and redirects to the dashboard.
- Legacy personal access tokens remain supported for compatibility.

Auth is only the first boundary: Provider/Branch authorization, account status,
document status, and menu entitlement are evaluated after authentication. MFA
is not implemented in this repository; stronger admin ingress remains an
external/next-step control.
