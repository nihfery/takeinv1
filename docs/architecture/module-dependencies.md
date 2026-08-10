# Module dependencies

Dependensi utama pada state implementasi saat ini:

```text
Identity
  -> Customer / Provider profiles

Catalog -----> Branch -----> Provider
   |              |             |
   v              v             v
Staff --------> Availability <- Subscription entitlement
   |              |
   +----------> Booking <-------- Customer activity / Checkout
                  |
                  +-------> Payment -------> Midtrans
                  |
                  +-------> Review

Provider/Admin/Customer actors
  -> Support -> Chat -> Notification -> Reverb
       |         |
       +-------> Media

Sensitive mutations across modules -> Audit
```

## Synchronous dependencies

- Booking memakai availability conflict/eligible-staff logic, catalog service,
  branch/provider scope, customer identity, dan subscription/salon eligibility.
- Payment mengunci payment, gateway transaction, dan booking saat menerapkan
  status authoritative.
- Provider document workflow memakai Media storage dan Audit.
- Support memakai Chat access/presenter, private Media attachment, Notification,
  dan Reverb events.
- Public catalog search menerapkan provider eligibility sebelum service/branch
  ditampilkan.

## Asynchronous/optional dependencies

Queue memakai Redis/Horizon. Notification dan chat broadcast adalah side effect;
commit database tidak boleh dibatalkan hanya karena Reverb atau exporter
telemetry tidak tersedia. Observability exporter bersifat optional/fail-open dan
dibatasi timeout tanpa retry aplikasi.

## Technical dependencies

Laravel framework, Sanctum, Horizon, Reverb, Flysystem S3 adapter, dan Scramble
merupakan dependency backend. Dua frontend menggunakan Next.js/React dan
berkomunikasi melalui kontrak HTTP Laravel. Next server proxy internal mengarah
ke `backend-http:8080` dalam Compose, bukan langsung ke PHP-FPM.

Dependency linting otomatis antar-modul belum diterapkan. Review CODEOWNERS dan
arsitektur tetap menjadi gate manusia untuk mencegah `Shared` menjadi dumping
ground atau menciptakan cycle baru.
