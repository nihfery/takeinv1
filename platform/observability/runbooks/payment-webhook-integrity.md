# Runbook: invalid payment webhook spike

1. Preserve request ID, correlation ID, source IP, gateway order ID, signature
   validation result, and timestamp. Never preserve server keys, signatures,
   full raw payloads, contact PII, or authorization headers in logs.
2. Check edge/gateway rate limits and recent source ranges without weakening
   application signature verification.
3. Query the authoritative Midtrans transaction status for affected order IDs.
4. Do not mark bookings paid from an invalid, replayed, or client-supplied
   payload.
5. If abuse is confirmed, apply a narrow edge rule and keep the origin private.
6. Verify idempotent webhook replay tests and audit records before closing.
