# Shared infrastructure

`App\Shared` is reserved for technical capabilities reusable by multiple modules, such as clocks, idempotency, locking, storage, logging, telemetry, and validation.

It must not own Booking, Provider, Payment, or other business rules. Existing technical helpers stay in `App\Support` until a tested relocation is justified; no empty directory forest is created here.
