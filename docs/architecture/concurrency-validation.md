# Booking and payment concurrency validation

## Gate

```bash
cd backend/laravel-core
php artisan test --testsuite=Concurrency --colors=never
```

Suite `tests/Concurrency/BookingConcurrencyTest.php` secara eksplisit menolak
driver selain MySQL. Setiap skenario membuat beberapa child PHP process melalui
Symfony Process. Worker masing-masing boot Laravel dan menulis readiness marker;
parent baru melepas satu filesystem barrier setelah seluruh worker siap. Ini
menghasilkan write contention sungguhan ke schema MySQL yang sama, bukan loop
sequential atau mock lock.

Jangan menjalankan suite ini paralel dengan suite/test job lain pada database
`salonku_testing_fresh`: `DatabaseTruncation` mengelola fixture schema dan test
lain dapat merusak determinisme barrier.

## Scenarios implemented

| Scenario | Contention | Expected invariant |
| --- | --- | --- |
| Capacity one | 6 customer processes membuat booking pada staff/slot yang sama | Tepat 1 commit, 5 validation failure, 1 active booking |
| Same idempotency key | 4 retries customer yang sama | Semua mendapat hasil sukses yang menunjuk 1 booking |
| Finalize retry | 4 finalize pada hold yang sama | Status final satu kali; hanya 1 payment dan 1 customer activity |
| Coupon quota | 2 booking menggunakan coupon quantity 1 | Tepat 1 commit; `used_count=1`; golden total `94,500` |
| Competing reschedule | 2 booking pindah ke staff/slot yang sama | Tepat 1 reschedule berhasil dan 1 ditolak |
| Duplicate payment status | 4 settlement updates pada payment yang sama | Paid/confirmed konsisten; side effect customer activity tunggal |

## Recorded result

Pada validation Phase 8 tanggal 2026-08-10, **tiga eksekusi penuh berturut-turut**
atas suite ini lulus, masing-masing dengan **6 tests dan 75 assertions**. Golden
assertion coupon juga memverifikasi total akhir `94,500`, bukan hanya jumlah row.
Hasil ini membuktikan invariant pada environment test MySQL saat itu; ini bukan
load test, capacity benchmark, atau bukti latency production. Load test dan
observasi database production-like tetap gate terpisah.

`BookingFlowService` memberi transaction create/finalize/reschedule retry yang
dibatasi **5 attempts** agar satu worker yang menjadi InnoDB deadlock victim
dapat mengulang transaction lengkap. Retry tidak memperlemah single-winner:
row lock, unique/idempotency contract, quota check, availability revalidation,
dan transaction commit tetap menentukan hasil.

## Failure interpretation

Kegagalan child process, timeout readiness/barrier, hasil worker tanpa JSON,
lebih dari satu booking pada kapasitas satu, quota oversell, atau side effect
ganda adalah release blocker. Jangan menaikkan timeout atau menurunkan assertion
untuk menyembunyikan deadlock/race; simpan output, periksa MySQL lock/deadlock
log, reproduksi pada schema test bersih, lalu perbaiki transaction/lock/unique
constraint pada domain pemilik.
