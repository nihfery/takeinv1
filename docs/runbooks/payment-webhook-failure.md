# Payment webhook failure

## Invariants

The browser return/manual action is never production payment authority.
`POST /api/midtrans/notification` must verify the Midtrans signature, fetch
authoritative status server-to-server, match the order and financial fields,
apply an allowed transaction/row-lock transition, and audit the outcome.

## Triage

Capture request/correlation ID, timestamp, order ID (not credentials/signature),
HTTP status, exception class, affected booking/subscription ID, and deploy
version. Check edge routing for `hooks.takein.id`, Laravel structured logs,
`audit_logs`, Horizon failures if applicable, MySQL/Redis readiness, and Midtrans
dashboard/API authoritative status.

Interpret common outcomes:

- `403`: invalid/missing signature or wrong server key/environment.
- `404`: local order mapping absent; do not create a payment from the webhook.
- `422`: order/amount/currency/status mismatch; treat as integrity incident.
- `5xx`/timeout: dependency or application failure; Midtrans may retry.

## Recover

Keep `ALLOW_CUSTOMER_MANUAL_PAYMENT_CONFIRMATION=false`. Fix ingress/config/code
first. Re-deliver the exact original notification only through an approved
Midtrans retry/tool or allow the gateway retry; duplicate processing is designed
to be idempotent, but operators still reconcile before replay. Never forge a new
signature/payload or update payment rows directly.

For every affected order, compare Midtrans authoritative status to local
`payments`/`payment_gateway_transactions` or provider subscription state and its
booking/activity side effects. Paid/refunded/late-settlement cases require the
existing allowed transition path and audit record. Escalate mismatched amount,
currency, order ID, unexpected regression, or unsigned traffic as a security
incident.

Exit only when ingress is healthy, duplicate notification remains single-effect,
all window orders are reconciled, booking/subscription state is consistent, and
no browser/manual path was used as authority.
