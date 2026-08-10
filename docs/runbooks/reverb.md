# Laravel Reverb runbook

## Runtime contract

The production path is:

```text
browser origin (takein.id / provider.takein.id / admin.takein.id)
  -> wss://ws.takein.id/app/{REVERB_APP_KEY}
  -> edge or Nginx WebSocket proxy
  -> private reverb:8080 process
  -> Redis database 6 when scale-out is enabled
```

Laravel HTTP, Horizon, the scheduler, and Reverb use the same application image,
but run as separate processes. Laravel publishes events directly to the private
`reverb:8080` endpoint; browser configuration exposes only `ws.takein.id:443`.

This repository contains topology templates only. It does not provision DNS,
TLS certificates, a public load balancer, Cloudflare, or other edge resources.
The root Compose file exposes the template as the opt-in `gateway` profile for
local validation; it remains loopback-bound by default. The official Nginx
entrypoint renders the public `server_name` from the same `REVERB_HOST` contract
used by browser configuration.

## Required configuration

- Give `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` independent,
  high-entropy production values.
- Set `REVERB_HOST=ws.takein.id`, `REVERB_PORT=443`, and
  `REVERB_SCHEME=https` for browser clients.
- Set the internal publisher endpoint to `REVERB_BROADCAST_HOST=reverb`, port
  `8080`, and scheme `http` on the private network.
- Set `REVERB_ALLOWED_ORIGINS` to an explicit comma-separated hostname list,
  for example `takein.id,www.takein.id,provider.takein.id,admin.takein.id`.
  Entries are hostnames only: do not add schemes, ports, paths, or wildcards.
- Keep `REVERB_SCALING_ENABLED=true` when multiple Reverb processes are used and
  reserve `REDIS_REVERB_DB=6` for Reverb coordination.

Client-originated events are disabled in application configuration. Chat and
notification state changes must continue to go through authorized Laravel HTTP
endpoints and server-originated broadcast events.

## Authorization invariants

- `/broadcasting/auth` uses the existing Laravel session guards.
- A user can subscribe only to their own user/notification channel.
- Provider/admin chat subscriptions require an approved, open thread.
- A provider owner or branch account must be an explicit thread participant,
  belong to the owning provider tenant, have the `chat` menu permission, and
  belong to an active provider with verified documents.
- Admins may subscribe only to approved, open provider-admin support threads.
- Customers, guests, cross-tenant providers, and inactive/unverified providers
  cannot subscribe to chat threads.

The authorization endpoint remains on the Laravel HTTPS origin; it is not
proxied through the public WebSocket-only location.

## Deployment checks

1. Build/configure the Laravel application and run the automated Reverb channel
   authorization and configuration tests.
2. Run `nginx -t` against the rendered gateway configuration.
3. Confirm the Reverb process is healthy and listening privately on port 8080.
4. From each allowed browser origin, establish `wss://ws.takein.id` and authorize
   an owned private channel.
5. Repeat with a disallowed Origin and an unauthorized/cross-tenant channel;
   both must fail.
6. Confirm Laravel workers publish through the private endpoint while browser
   configuration still contains the public hostname.
7. If scaling is enabled, connect clients to separate Reverb instances and
   verify that Redis propagates the same server-originated event.

For the local Compose topology, start or validate the gateway explicitly:

```sh
docker compose --env-file platform/deploy/dokploy.env.example --profile gateway config
docker compose --env-file platform/deploy/dokploy.env.example --profile gateway up -d nginx
```

## Troubleshooting and rollback

- `Origin not allowed`: compare the browser `Origin` hostname with
  `REVERB_ALLOWED_ORIGINS`; never solve this by adding `*`.
- `403` from `/broadcasting/auth`: verify the expected Laravel session cookie,
  guard, provider status/document status, menu permission, tenant, participant,
  and thread lifecycle state.
- Connection closes near one minute: verify proxy read/send timeouts remain
  longer than `REVERB_APP_PING_INTERVAL`.
- Broadcasts publish but browsers receive nothing: check that server-side
  `REVERB_BROADCAST_*` uses the private service while browser `REVERB_*` uses
  the public endpoint, then check Redis scale-out configuration.

To roll back an edge change, restore the last validated gateway configuration
and reload Nginx gracefully. Do not expose Reverb's `/apps` publish API as a
workaround. Application booking/payment commits remain authoritative even when
Reverb is unavailable; queued broadcast failures must be retried or rescued and
must not reverse committed business state.
