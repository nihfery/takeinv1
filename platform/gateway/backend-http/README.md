# Laravel HTTP runtime

`backend-http` is the always-on HTTP frontend for the Laravel runtime. It owns
the existing loopback host contract (`127.0.0.1:8000` by default), serves only
versioned files from `public/` and public objects from the shared storage
volume, and sends every dynamic request to the private `backend:9000` PHP-FPM
listener.

The JSON access log is intentionally queryless. It records `$uri` as `path` and
never records `$request`, `$request_uri`, `$args`, `$query_string`, Referer,
cookies, or authorization. Nginx still forwards `QUERY_STRING` and
`REQUEST_URI` over FastCGI, so Laravel relative signed URLs continue to work.
Do not replace the log format with a conventional combined/request-line format.
The Laravel image also overrides the request access log enabled by the official
PHP-FPM image to `/dev/null`; PHP errors and captured worker output continue to
use stderr.

Only the `backend` service runs the migration entrypoint before starting FPM.
Horizon, scheduler, Reverb, and this Nginx service never execute migrations.
The backend entrypoint normalizes only Laravel's bounded writable directories,
refuses symlink traversal, and repairs root-owned files left by the former
development-server runtime. Long-running Laravel sidecars run as `www-data` so
they do not recreate root-owned framework or log files.

The FastCGI boundary overwrites `REMOTE_ADDR` and `X-Forwarded-For` with Nginx's
immediate peer address. It also overwrites both request/correlation headers with
Nginx's generated request ID. It never accepts a client-supplied forwarding
chain or observability identifier.

Validation from the repository root:

```powershell
powershell -NoProfile -File tools/validation/validate-http-runtime.ps1
docker compose config --quiet
docker compose build backend backend-http
docker compose run --rm --no-deps backend-http nginx -t
```

The deployment edge must continue routing Laravel HTTP traffic to the
`backend-http` service, container port `8080`; `backend:9000` is FastCGI, not
HTTP, and must remain private to the Compose application network.
