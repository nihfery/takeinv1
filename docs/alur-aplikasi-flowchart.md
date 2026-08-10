# Alur Aplikasi YouYaku

Dokumen ini memetakan alur yang **sudah diimplementasikan** di repository, per 1 Agustus 2026. Aplikasi memiliki tiga aktor: customer, provider (pemilik/akun cabang), dan admin. Backend Laravel menjadi sumber kebenaran untuk otorisasi, ketersediaan slot, status booking, transaksi, notifikasi, dan chat; customer landing dan provider landing berjalan sebagai frontend React terpisah.

> Catatan implementasi: pembayaran customer belum menjalankan proses charge Midtrans dari endpoint customer. Endpoint `payment/confirm` menandai pembayaran berhasil secara manual. Infrastruktur `MidtransService` dan webhook `/api/midtrans/notification` telah ada untuk menyelaraskan status transaksi bila gateway dipakai.

## 1. Peta alur utama

```mermaid
flowchart TD
    Start([Pengunjung membuka aplikasi]) --> Entry{Pintu masuk}
    Entry -->|Customer landing React| Customer[Telusuri salon / layanan / staff]
    Entry -->|Provider landing React| ProviderReg[Daftar atau masuk sebagai provider]
    Entry -->|Admin Blade| AdminLogin[Masuk admin]

    Customer --> PublicAPI[API katalog publik Laravel]
    PublicAPI --> Detail[Detail branch, layanan, staff, ulasan]
    Detail --> Book[Alur booking customer]
    Book --> BookingDB[(Booking + Payment + Activity)]
    BookingDB --> ProviderOps[Dashboard provider: jadwal / antrean / layanan]
    ProviderOps --> AdminOps[Admin memantau booking, provider, customer]

    ProviderReg --> Onboard[Profil, dokumen, trial/langganan]
    Onboard --> ProviderOps
    AdminLogin --> AdminOps

    BookingDB --> Notify[(App notifications)]
    Notify --> Realtime[Broadcast private channel bila Reverb tersedia]
    ProviderOps --> Chat[Chat/tiket dukungan provider]
    AdminOps --> Chat
```

## 2. Registrasi, login, dan hak akses

```mermaid
flowchart TD
    A([Pengguna memilih daftar/masuk]) --> B{Peran}

    B -->|Customer: daftar| C1[Validasi nama, email unik, password min. 8]
    C1 --> C2[Buat users.role=customer + customer_profiles.status=active dalam transaksi]
    C2 --> C3[Buat sesi web]
    C3 --> C4[Customer dapat mengelola profil dan booking]

    B -->|Customer: masuk| C5[Validasi kredensial]
    C5 --> C6{Profil customer aktif?}
    C6 -->|Tidak| C7[Ditolak]
    C6 -->|Ya| C3

    B -->|Provider: daftar| P1[Validasi identitas, username/email unik, password]
    P1 --> P2[Buat users.role=provider]
    P2 --> P3[Buat provider_profiles: inactive, document pending, trial 14 hari]
    P3 --> P4[Notifikasi pendaftaran dikirim ke admin]
    P4 --> P5[Provider melengkapi profil dan mengunggah dokumen]
    P5 --> P6[Admin meninjau dokumen dan status akun]
    P6 --> P7{Dokumen terverifikasi dan akun aktif?}
    P7 -->|Belum| P8[Akses menu terbatas ke profil/dashboard]
    P7 -->|Ya| P9[Akses menu operasional sesuai role]

    B -->|Provider: masuk| P10[Validasi kredensial]
    P10 --> P11{Akun inactive tetapi dokumen sudah verified?}
    P11 -->|Ya| P12[Ditolak: akun belum diaktifkan admin]
    P11 -->|Tidak| P7

    B -->|Admin: masuk| A1[Autentikasi guard admin]
    A1 --> A2[Dashboard admin]

    P9 --> Role{Pemilik atau akun cabang?}
    Role -->|Pemilik| Owner[Semua menu; kelola role dan izin]
    Role -->|Akun cabang| Branch[Hanya menu yang diizinkan dan data sesuai branch]
```

Kontrol akses provider setelah login:

- Dokumen belum terverifikasi diarahkan ke profil; menu operasional dibuka setelah lolos middleware verifikasi.
- Masa trial aktif membuka operasional tetapi membatasi setiap resource: branch, layanan, staff, role, dan walk-in masing-masing maksimum 5 data.
- Trial habis tanpa langganan aktif mengunci menu yang membutuhkan verifikasi dan branch tidak dapat dibooking customer.
- Chat/tiket dukungan hanya tersedia bagi provider dengan langganan aktif.

## 3. Customer: pencarian sampai booking

```mermaid
flowchart TD
    S([Customer membuka landing]) --> Q[Search/filter kategori atau lokasi]
    Q --> Katalog[GET kategori, lokasi, branch, layanan, staff, review]
    Katalog --> PilihBranch[Pilih branch / salon]
    PilihBranch --> Detail[Halaman detail: layanan, staff, foto, lokasi, review]
    Detail --> Mulai[Pilih Book now]
    Mulai --> Mode{Tipe booking}

    Mode -->|Scheduled| L1[Pilih satu/lebih layanan]
    Mode -->|Queue| Q1[Pilih layanan untuk antrean]
    L1 --> L2[Pilih staff tertentu atau siapa saja]
    L2 --> L3[Pilih tanggal]
    L3 --> Cek[POST check-availability / GraphQL]
    Q1 --> Cek

    Cek --> Validasi[Backend melepas hold kedaluwarsa lalu validasi branch, provider, layanan, skill staff, jadwal kerja, durasi, booking aktif dan hold aktif]
    Validasi --> Ada{Pilihan tersedia?}
    Ada -->|Tidak| Ulang[Pilih layanan/staff/tanggal/jam lain]
    Ulang --> Cek
    Ada -->|Ya, scheduled| L4[Pilih jam; hanya tersimpan sebagai draft frontend]
    Ada -->|Ya, queue| Q2[Tampilkan estimasi antrean]
    L4 --> Login{Sudah login sebagai customer?}
    Q2 --> Login
    Login -->|Belum| Auth[Daftar/masuk lalu kembali ke alur]
    Auth --> Login
    Login -->|Sudah| Hold[Klik lanjut/review: POST bookings]

    Hold --> Lock[Transaction: lock staff dan booking/hold terkait; cek overlap ulang]
    Lock --> Bentrok{Slot masih valid?}
    Bentrok -->|Tidak| Rebut[Respons gagal: slot baru dipilih customer lain]
    Rebut --> Cek
    Bentrok -->|Ya| Pending[Create booking status=pending_hold, hold 3 menit, snapshot layanan/harga/durasi]
    Pending --> Review[Halaman review: detail, peserta, voucher, catatan, metode bayar, countdown]
    Review --> Waktu{Hold masih aktif?}
    Waktu -->|Tidak| Expired[status=expired_hold; slot dilepas]
    Expired --> Cek
    Waktu -->|Ya| Final[POST bookings/{id}/finalize]
    Final --> FinalLock[Lock booking, cek kepemilikan/expiry/konflik lagi, hitung ulang harga dan validasi kupon]
    FinalLock --> PayType{Metode pembayaran}
    PayType -->|Bayar di salon| Confirmed[booking=confirmed; payment=unpaid]
    PayType -->|DP / full payment| PendingPay[booking=pending_payment; payment=pending]
    Confirmed --> Activity[Index customer_activity dibuat + notifikasi provider]
    PendingPay --> Activity
```

Rincian validasi availability: branch harus aktif; provider harus aktif, dokumennya verified, serta masih trial atau berlangganan aktif; setiap layanan harus aktif, milik provider, tersedia di branch, dan mendukung tipe booking; staff harus aktif, terhubung ke branch, memiliki seluruh skill layanan, dan bekerja pada tanggal tersebut. Untuk scheduled booking, slot dihitung dari jam kerja dan menolak interval yang bertabrakan dengan booking/hold aktif atau peserta grup lain.

## 4. Status booking, pembayaran, dan operasional layanan

```mermaid
stateDiagram-v2
    [*] --> pending_hold: Customer membuat hold 3 menit
    pending_hold --> expired_hold: Waktu hold habis
    pending_hold --> customer_cancelled: Customer membatalkan
    pending_hold --> confirmed: Finalize scheduled + bayar di salon
    pending_hold --> waiting: Finalize queue + bayar di salon
    pending_hold --> pending_payment: Finalize + DP/full payment

    pending_payment --> confirmed: Pembayaran paid scheduled
    pending_payment --> waiting: Pembayaran paid queue/walk-in
    pending_payment --> payment_expired: Payment expired atau failed
    pending_payment --> cancelled: Customer/provider membatalkan

    confirmed --> checked_in: Provider check-in / call queue
    confirmed --> rescheduled: Customer reschedule
    confirmed --> cancelled: Pembatalan
    confirmed --> no_show: Provider menandai no-show
    waiting --> checked_in: Provider call / check-in
    waiting --> cancelled
    waiting --> no_show
    rescheduled --> checked_in
    rescheduled --> cancelled
    checked_in --> in_progress: Provider mulai layanan
    checked_in --> cancelled
    checked_in --> no_show
    in_progress --> completed: Provider menyelesaikan layanan
    in_progress --> cancelled

    expired_hold --> [*]
    payment_expired --> [*]
    customer_cancelled --> [*]
    cancelled --> [*]
    no_show --> [*]
    completed --> [*]
```

```mermaid
flowchart LR
    PP[booking=pending_payment<br/>payment=pending] --> Gateway{Status pembayaran}
    Gateway -->|Settlement/capture sukses| Paid[payment=paid<br/>booking=confirmed untuk scheduled<br/>atau waiting untuk queue/walk-in]
    Gateway -->|Pending| PP
    Gateway -->|Expire / deny / cancel / failure| PE[payment=expired/failed<br/>booking=payment_expired<br/>slot dilepas]

    Done[Provider menekan Complete] --> COD{Metode bayar di salon?}
    COD -->|Ya| CODPaid[payment=paid dan amount=total booking]
    COD -->|Tidak| Finish[booking=completed]
    CODPaid --> Finish
```

Aturan penting:

- Status yang memblokir slot meliputi `open`, `pending`, `pending_hold`, `pending_payment`, `confirmed`, `waiting`, `checked_in`, `inprogress`/`in_progress`, dan `rescheduled`.
- Pengecekan bentrok memakai interval waktu, bukan sekadar jam mulai yang sama. Pada create hold dan finalize, backend mengunci record terkait dan mengecek ulang di dalam database transaction.
- Klik ulang request dengan `idempotency_key` dapat mengembalikan booking yang sudah ada selama booking tersebut belum tertutup.
- Customer dapat memperpanjang hold aktif 3 menit lagi, membatalkan booking yang masih cancellable, atau reschedule booking yang statusnya diizinkan. Setiap perubahan notifikasi ke provider.
- Setelah booking final (minimal `pending_payment` atau `confirmed`), sistem membuat satu indeks `customer_activities`; hold tidak masuk aktivitas customer.

## 5. Antrean, walk-in, booking grup, dan ulasan

```mermaid
flowchart TD
    A{Jenis booking} -->|Queue dari customer| Q1[Backend memilih staff eligible bila diperlukan]
    Q1 --> Q2[Generate queue_number dan status waiting setelah booking final]
    Q2 --> Q3[Provider membuka Queue lalu Call/Check-in]
    Q3 --> Q4[Start: assign staff bila belum ada]
    Q4 --> Q5[in_progress]
    Q5 --> Q6[Complete / cancel / no-show]

    A -->|Walk-in dari provider| W1[Provider isi pelanggan, branch, layanan, staff opsional, catatan, tipe bayar]
    W1 --> W2[Cek entitlement trial manual_bookings]
    W2 -->|Melebihi limit / trial habis| W3[Ditolak]
    W2 -->|Diizinkan| W4[Create booking walk_in + nomor antrean]
    W4 --> Q3

    A -->|Booking grup| G1[Customer mengirim participant_count 2-5]
    G1 --> G2{Pilihan tiap peserta terpisah?}
    G2 -->|Tidak| G3[Satu pilihan booking, data tamu disimpan sebagai peserta]
    G2 -->|Ya| G4[Setiap peserta membawa layanan/staff/tanggal/jam sendiri]
    G3 --> G5[Lock dan cek konflik untuk seluruh peserta]
    G4 --> G5
    G5 --> G6[Snapshot booking_participants dan layanan peserta]

    Q6 --> Review{Booking selesai?}
    Review -->|Ya| R1[Customer kirim review berdasarkan booking code]
    R1 --> R2[Simpan branch_review dan/atau staff_review satu kali per booking]
```

## 6. Provider: pengelolaan bisnis dan eksekusi booking

```mermaid
flowchart TD
    P([Provider dashboard]) --> C{Menu berizin}
    C --> S[Layanan: buat/ubah/hapus, harga, durasi, galeri, status, branch]
    C --> B[Branch: buat/ubah, lokasi, jam kerja, staff, status]
    C --> T[Staff: buat/ubah/status + skill layanan + jadwal kerja]
    C --> R[Role & permissions: hanya pemilik]
    C --> O[Bookings, kalender, queue, walk-in]
    C --> F[Transaksi, customer directory, review]
    C --> Com[Notifikasi, chat, tiket]

    S --> Catalog[(Katalog branch)]
    B --> Catalog
    T --> Availability[(Eligibility & availability booking)]
    Catalog --> Availability
    Availability --> O

    O --> Ops{Aksi booking}
    Ops -->|Call / check-in| I1[checked_in]
    Ops -->|Start| I2[in_progress; staff=busy]
    Ops -->|Complete| I3[completed; staff=available; bayar di salon jadi paid]
    Ops -->|Cancel / no-show| I4[Slot/staff dibebaskan bila relevan]
```

## 7. Admin, notifikasi, dan chat/tiket

```mermaid
flowchart TD
    Admin([Admin dashboard]) --> A1[Monitor ringkasan dan ekspor laporan]
    Admin --> A2[Booking dan kalender seluruh provider]
    Admin --> A3[Kelola kategori layanan, layanan, kupon]
    Admin --> A4[Kelola provider dan customer]
    A4 --> Verify[Ubah status provider / status dokumen]
    Verify --> Access[Menentukan branch dapat dipublikasikan dan provider dapat beroperasi]
    Admin --> A5[Notifikasi dan pusat tiket/chat]

    ProviderTicket[Provider berlangganan aktif membuat tiket] --> Pending[ticket=pending]
    Pending --> Decision{Admin review}
    Decision -->|Approve| Approved[ticket=approved; chat admin-provider dibuka]
    Decision -->|Reject| Rejected[ticket=rejected; alasan dikirim]
    Approved --> Message[Pesan teks/lampiran tersimpan]
    Message --> Broadcast[Broadcast channel private chat.thread.id]
    Approved --> Close{Admin/pemilik provider menutup chat?}
    Close -->|Ya| Closed[ticket=closed; buat tiket baru untuk membuka lagi]
    Close -->|Tidak| Message

    Internal[Provider membuka chat internal dengan akun cabang] --> InternalChat[Thread internal dibuat/dibuka ulang dengan ticket=approved]

    Event[Booking baru, cancel, reschedule, provider daftar, tiket] --> N1[Buat app_notifications untuk penerima]
    N1 --> N2[Broadcast notifications.user.id bila realtime tersedia]
    N2 --> N3[Notifikasi tetap tersimpan di database jika broadcast gagal]
```

## 8. Data yang menjadi sumber kebenaran

| Domain | Data utama | Catatan alur |
| --- | --- | --- |
| Identitas & akses | `users`, `customer_profiles`, `provider_profiles`, `admin_profiles`, provider roles/permissions | `users.role` menentukan peran; akun cabang tetap berada di organisasi provider pemilik. |
| Katalog | `provider_branches`, `services`, `service_categories`, `provider_staffs`, skill dan schedule staff | Dipakai untuk katalog publik dan kalkulasi eligibility/slot. |
| Booking | `bookings`, `booking_services`, `booking_participants`, `booking_participant_services` | Pivot menyimpan snapshot harga/durasi agar perubahan katalog tidak mengubah histori. |
| Pembayaran | `payments`, `payment_gateway_transactions` | Satu booking maksimal satu payment; data gateway hanya dibuat untuk pembayaran online. |
| Riwayat & ulasan | `customer_activities`, `branch_reviews`, `staff_reviews` | Activity hanya dibuat untuk booking final; review terkait booking yang bersangkutan. |
| Komunikasi | `app_notifications`, `chat_threads`, `chat_messages` | Pesan dan notifikasi bertahan di database, kemudian dibroadcast real-time bila tersedia. |

## 9. Daftar halaman/fitur yang dicakup

- Customer: landing, pencarian, detail salon/layanan/staff, auth, profil, booking, pembayaran, booking success/detail, activity, promo, favorites (state frontend).
- Provider: dashboard, profil/onboarding/dokumen, layanan, branch, staff, skills, schedules, role/permission, booking, kalender, queue, walk-in, payments, customer, review, notifikasi, chat, tiket, trial expired.
- Admin: login, dashboard/ekspor, booking, kalender, service/category, kupon, provider + verifikasi dokumen, customer/user, profil, notifikasi, chat, dan tiket.

## 10. Batasan implementasi yang perlu diperhatikan

1. Endpoint subscription saat ini hanya membuat tagihan/langganan pending dan order ID; pembuatan transaksi gateway subscription di endpoint tersebut masih berupa placeholder. Webhook dapat mengubah langganan menjadi aktif bila menerima notifikasi Midtrans valid.
2. Endpoint payment customer `charge` dan `status` masih mengembalikan mode pembayaran manual; `confirmByCode` dapat menandai DP/full payment sebagai lunas. Diagram menunjukkan jalur Midtrans karena service dan webhook sudah tersedia, tetapi integrasi charge customer belum dipanggil dari controller tersebut.
3. Status legacy `pending` masih diperlakukan setara dengan `pending_hold` di beberapa query agar data lama tetap aman; status baru yang digunakan alur adalah `pending_hold`.
