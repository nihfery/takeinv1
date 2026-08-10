# Nginx gateway templates

These files describe the intended private-origin topology. They are templates,
not evidence that DNS, TLS certificates, Cloudflare, or another edge service has
been provisioned.

For the Reverb gateway, mount `nginx.conf` as `/etc/nginx/nginx.conf` and the
`sites/` directory as `/etc/nginx/templates`. The official image renders
`websocket.conf.template` into an empty tmpfs-backed `/etc/nginx/conf.d` using
`NGINX_REVERB_PUBLIC_HOST`, which Compose derives from `REVERB_HOST`.
`NGINX_ENVSUBST_FILTER` is deliberately restricted to that placeholder so
native Nginx variables remain untouched. The gateway and the `reverb` service
must share a private application network so the upstream `reverb:8080` resolves
without exposing the server-side publish API.

Validate the rendered configuration before deployment:

```sh
nginx -t -c /etc/nginx/nginx.conf
```

The public edge should route `ws.takein.id` to this gateway, preserve Upgrade
headers, overwrite forwarding headers, and terminate HTTPS so browser clients
use `wss://ws.takein.id`. If client IP restoration is needed, configure
`set_real_ip_from` only for the exact current edge CIDRs; the repository
template intentionally does not trust arbitrary inbound forwarding chains.
