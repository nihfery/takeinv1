# Authorization

Authentication never implies tenant/resource access.

## Actor boundaries

- Admin routes require the admin role/guard and privileged controller checks.
- Customer API queries scope profile/activity/booking/payment/review to the
  authenticated customer, never a caller-provided customer ID.
- Provider operations resolve the owner tenant. Owner accounts have no parent
  `provider_id`; branch accounts resolve to their provider owner and assigned
  branch.
- `EnsureProviderApiAccess` checks provider role, owner-only operations,
  active/document-verified state for operational resources, and named menu
  permissions.
- Blade middleware applies account active, document verified, menu permission,
  guard, and branch scope before provider operations.

Resource lookup must include tenant/branch conditions or explicit ownership
checks before returning data. Route model binding by itself is insufficient.
Tests cover provider API cross-tenant access, branch scope, sensitive provider
documents, private chat attachment, subscription owner restrictions, and
Reverb channel authorization.

## Realtime and files

Notification channels require `user.id == channel.id`. Chat channel/download
authorization additionally checks actor role, provider tenant, participant,
active/verified/menu state, and approved/open thread lifecycle. A valid signed
URL proves integrity/expiry only; it never replaces actor/tenant authorization.

## Review checklist

- Derive provider/customer identity from the authenticated principal.
- Apply tenant scope before `findOrFail`, update, export, or download.
- Test guest, wrong role, wrong tenant, wrong branch, inactive/unverified,
  missing menu, expired signature, and invalid state.
- Record privileged lifecycle mutations/access through Audit where the risk
  model requires it.
