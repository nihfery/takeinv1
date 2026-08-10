# Reverb unavailable

Realtime loss must not reverse committed booking, payment, chat message, or
notification rows. Users may not receive live updates and should refresh/reload
where the UI supports it.

## Triage

```bash
docker compose --env-file <approved-env-file> ps reverb nginx redis backend
docker compose --env-file <approved-env-file> logs --since=15m reverb nginx backend
docker compose --env-file <approved-env-file> exec reverb \
  php -r 'exit(@fsockopen("127.0.0.1", 8080) ? 0 : 1);'
```

Check Redis, Reverb credentials, public-vs-private host configuration, allowed
Origin hostname, channel auth 403s, edge WebSocket upgrade/timeouts, DNS/TLS,
and whether the failure is connect, authorize, publish, or fan-out. Never solve
an origin/auth failure with wildcard origins, client events, public `/apps`, or
disabled tenant checks.

Restart/fail over only through the approved process manager. After recovery,
test an allowed and disallowed origin, owned and cross-tenant channel, two
instances when scaling is enabled, and server-originated chat/notification
delivery. Persistent rows are the catch-up source; do not synthesize database
state from missed WebSocket events.

The full topology, configuration, authorization, and rollback procedure is in
`docs/runbooks/reverb.md`. DNS/TLS/edge and production multi-instance resources
are external and not provisioned by this repository.
