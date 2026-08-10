# PRD.md — Customer Website Reservasi Salon

**Project:** SalonKu Customer Website  
**Document Type:** Product Requirements Document  
**Version:** 1.0  
**Focus:** Customer-facing reservation website  
**Reference Flow:** Fresha-inspired marketplace booking flow  
**Scope:** Customer website only, not Admin Dashboard and not Provider Dashboard  

---

## 1. Product Overview

SalonKu Customer Website adalah website reservasi salon, beauty, dan wellness yang memungkinkan customer menemukan salon, membandingkan layanan, melihat harga dan durasi, memilih staff/stylist, memilih jadwal kosong, melakukan booking, melakukan pembayaran, menerima notifikasi, mengelola reservasi, dan memberikan review setelah layanan selesai.

Website ini mengambil inspirasi dari alur marketplace booking modern seperti Fresha, yaitu customer dapat melakukan pencarian salon atau layanan, memilih lokasi, melihat daftar bisnis yang tersedia, membuka detail venue, memilih layanan, memilih professional/staff, memilih waktu berdasarkan availability, lalu menyelesaikan booking secara online.

Tujuan utama produk ini adalah membuat proses reservasi menjadi lebih mudah, cepat, transparan, dan tidak bergantung pada chat manual atau telepon.

---

## 2. Product Goals

### 2.1 Tujuan Utama

1. Memudahkan customer menemukan salon dan layanan kecantikan secara online.
2. Memudahkan customer melakukan booking tanpa perlu menghubungi salon secara manual.
3. Menampilkan informasi layanan secara transparan, termasuk harga, durasi, deskripsi, staff, rating, review, dan jadwal tersedia.
4. Memberikan pengalaman customer yang clean, modern, ringan, dan mudah digunakan.
5. Meningkatkan konversi dari visitor menjadi booking.
6. Mendukung pertumbuhan kategori layanan di masa depan, seperti salon, spa, barber, nail care, makeup, massage, dan wedding beauty.

### 2.2 Tujuan Bisnis

1. Menjadi marketplace reservasi salon dan beauty service.
2. Membantu salon mendapatkan customer baru dari pencarian online.
3. Meningkatkan jumlah booking digital.
4. Mengurangi ketergantungan customer pada reservasi manual via WhatsApp atau telepon.
5. Membuka peluang monetisasi dari fee transaksi, promo placement, membership, dan featured listing.

---

## 3. Product Scope

### 3.1 In Scope

Fitur yang termasuk dalam website customer:

1. Landing page customer.
2. Search salon dan layanan.
3. Search by location.
4. Category browsing.
5. Search result page.
6. Filter dan sorting.
7. Salon listing card.
8. Salon detail page.
9. Service menu.
10. Service detail.
11. Service add-ons.
12. Multi-service booking.
13. Staff/professional selection.
14. Date and time selection.
15. Booking summary.
16. Login dan register customer.
17. Checkout.
18. Payment.
19. Booking success page.
20. Activity.
21. Booking detail.
22. Cancel booking.
23. Reschedule booking.
24. Review dan rating.
25. Favorite salon.
26. Promo dan voucher.
27. Notification.
28. Customer profile.
29. Help center basic.
30. Responsive mobile-first website.

### 3.2 Out of Scope

Fitur berikut tidak termasuk dalam PRD customer website ini:

1. Dashboard admin.
2. Dashboard provider atau mitra salon.
3. Manajemen cabang provider.
4. Manajemen staff oleh provider.
5. Manajemen service oleh provider.
6. Laporan keuangan provider.
7. Sistem komisi platform.
8. Panel approval provider.
9. Internal CMS admin.
10. POS kasir provider.

---

## 4. Reference Flow: Fresha-Inspired Customer Journey

Alur customer yang diadaptasi dari Fresha:

1. Customer membuka website.
2. Customer mencari salon, layanan, atau kategori.
3. Customer memasukkan lokasi atau menggunakan lokasi saat ini.
4. Sistem menampilkan daftar salon/venue yang relevan.
5. Customer melihat rating, review, lokasi, kategori, foto, harga mulai dari, dan jadwal tersedia.
6. Customer membuka detail salon.
7. Customer melihat service menu yang dikelompokkan berdasarkan kategori.
8. Customer memilih satu atau beberapa layanan.
9. Customer memilih add-ons jika tersedia.
10. Customer memilih staff/professional atau memilih opsi “Any Staff”.
11. Customer memilih tanggal dan jam berdasarkan slot tersedia.
12. Customer melihat booking summary.
13. Customer login/register jika belum login.
14. Customer memasukkan voucher/promo jika ada.
15. Customer memilih metode pembayaran.
16. Customer melakukan pembayaran atau memilih bayar di tempat jika salon mendukung.
17. Sistem membuat booking.
18. Customer menerima konfirmasi.
19. Customer mendapatkan reminder sebelum jadwal.
20. Customer dapat cancel atau reschedule sesuai kebijakan.
21. Setelah layanan selesai, customer dapat memberikan review.
22. Customer dapat rebook layanan yang sama di kemudian hari.

---

## 5. Target Users

### 5.1 Customer Baru

Customer yang belum pernah menggunakan website dan ingin menemukan salon atau layanan kecantikan.

Kebutuhan:

1. Mudah memahami fungsi website.
2. Bisa mencari salon tanpa login.
3. Bisa melihat harga dan review sebelum booking.
4. Bisa daftar akun dengan cepat.
5. Bisa melakukan booking pertama tanpa kebingungan.

### 5.2 Customer Lama

Customer yang sudah pernah booking dan ingin melakukan booking ulang.

Kebutuhan:

1. Bisa login dengan cepat.
2. Bisa melihat booking sebelumnya.
3. Bisa rebook salon atau layanan favorit.
4. Bisa melihat promo yang relevan.
5. Bisa menyimpan salon favorit.

### 5.3 Customer Berdasarkan Kebutuhan Layanan

Contoh:

1. Customer yang ingin haircut cepat.
2. Customer yang ingin treatment premium.
3. Customer yang ingin salon terdekat.
4. Customer yang ingin layanan dengan promo.
5. Customer yang ingin layanan wedding atau event.
6. Customer yang ingin memilih stylist tertentu.
7. Customer yang ingin booking pada jam tertentu.

---

## 6. Core Value Proposition

1. **Booking tanpa ribet:** customer dapat booking tanpa telepon atau chat manual.
2. **Harga transparan:** harga dan durasi layanan ditampilkan sebelum booking.
3. **Bisa bandingkan salon:** customer dapat membandingkan salon berdasarkan rating, harga, lokasi, promo, dan review.
4. **Jadwal real-time:** customer hanya melihat slot yang tersedia.
5. **Review terpercaya:** review hanya dapat diberikan setelah booking selesai.
6. **Booking 24/7:** customer dapat booking kapan saja.
7. **Mudah rebook:** customer dapat melakukan booking ulang dari riwayat atau favorit.

---

## 7. Information Architecture

Struktur halaman customer website:

```txt
Home
├── Search
│   ├── Search Result
│   ├── Category Result
│   └── Location Result
├── Salon Detail
│   ├── Overview
│   ├── Services
│   ├── Staff
│   ├── Reviews
│   ├── Gallery
│   └── Location
├── Service Detail
├── Booking Flow
│   ├── Select Service
│   ├── Select Add-ons
│   ├── Select Staff
│   ├── Select Date & Time
│   ├── Booking Summary
│   ├── Login/Register Gate
│   ├── Checkout
│   ├── Payment
│   └── Booking Success
├── Activity
│   ├── Upcoming
│   ├── Waiting Payment
│   ├── Completed
│   ├── Cancelled
│   └── Booking Detail
├── Promo
├── Favorites
├── Notifications
├── Profile
└── Help Center
```

---

## 8. Navigation Requirements

### 8.1 Navbar Sebelum Login

Menu:

1. Home
2. Explore
3. Services
4. Promo
5. For Business
6. Login
7. Sign Up

Catatan:

- “For Business” hanya CTA menuju halaman provider/mitra.
- Dashboard provider tidak dibahas dalam PRD ini.

### 8.2 Navbar Setelah Login

Menu:

1. Home
2. Explore
3. Promo
4. Activity
5. Favorites
6. Notifications
7. Profile

### 8.3 Mobile Bottom Navigation

Untuk mobile, gunakan bottom navigation:

1. Home
2. Search
3. Booking
4. Favorite
5. Profile

### 8.4 Navbar Behavior

1. Sticky saat scroll.
2. Background berubah menjadi solid saat user scroll melewati hero.
3. Hover state halus pada desktop.
4. Active state untuk menu yang sedang dibuka.
5. Mobile menggunakan drawer atau bottom sheet untuk menu tambahan.

---

## 9. Page Requirements

---

# 9.1 Home Page / Landing Page

## Tujuan

Home page bertugas memperkenalkan produk, memudahkan customer melakukan pencarian, menampilkan kategori populer, menampilkan salon rekomendasi, dan mendorong customer masuk ke alur booking.

## Komponen Utama

1. Navbar.
2. Hero section.
3. Search bar utama.
4. Category shortcut.
5. Popular services.
6. Recommended near you.
7. Top-rated salons.
8. Promo section.
9. How it works.
10. Benefit section.
11. Testimonial section.
12. Footer.

## Hero Section

### Isi

1. Headline.
2. Subheadline.
3. Search input layanan/salon.
4. Location input.
5. CTA button.
6. Quick category chips.

### Contoh Copywriting

Headline:

> Reservasi Salon Favoritmu Lebih Mudah

Subheadline:

> Cari salon, pilih layanan, tentukan jadwal, dan booking langsung tanpa perlu menunggu balasan.

Placeholder search:

> Cari layanan atau nama salon

Placeholder location:

> Masukkan lokasi

CTA:

> Cari Sekarang

## Category Shortcut

Kategori awal:

1. Haircut
2. Hair Coloring
3. Hair Treatment
4. Nail Care
5. Spa
6. Massage
7. Makeup
8. Barber
9. Waxing
10. Eyebrow & Lashes
11. Facial
12. Wedding Beauty

## How It Works

Tampilkan 3 langkah sederhana:

1. Cari salon atau layanan.
2. Pilih jadwal yang tersedia.
3. Booking dan datang sesuai jadwal.

## Acceptance Criteria

1. User dapat mencari layanan dari hero section.
2. User dapat memilih kategori dari home.
3. User dapat melihat salon rekomendasi.
4. User dapat membuka halaman detail salon.
5. Layout responsif di mobile, tablet, dan desktop.

---

# 9.2 Search Result Page

## Tujuan

Menampilkan daftar salon atau layanan berdasarkan kata kunci, lokasi, kategori, dan filter yang dipilih customer.

## Komponen

1. Sticky search bar.
2. Filter sidebar pada desktop.
3. Filter bottom sheet pada mobile.
4. Sorting dropdown.
5. Result count.
6. Salon cards.
7. Optional map preview.
8. Empty state.
9. Loading skeleton.

## Search Input

Field:

1. Keyword: layanan, salon, kategori.
2. Location: kota, area, atau current location.
3. Date optional.
4. Time optional.

## Filter

Filter wajib:

1. Category.
2. Location radius.
3. Price range.
4. Rating.
5. Availability.
6. Open now.
7. Promo available.
8. Gender staff optional.
9. Facilities optional.
10. Payment method optional.

## Sorting

Sorting options:

1. Recommended.
2. Nearest.
3. Highest rated.
4. Most reviewed.
5. Lowest price.
6. Earliest available.
7. Best deals.
8. Most booked.

## Salon Card Data

Setiap card menampilkan:

1. Salon image.
2. Salon name.
3. Rating.
4. Review count.
5. Address/area.
6. Distance.
7. Categories.
8. Price starts from.
9. Earliest available slot.
10. Promo badge.
11. Favorite button.
12. View details button.
13. Book now button.

## Empty State

Jika tidak ada hasil:

> Salon tidak ditemukan. Coba ubah lokasi, kategori, atau filter pencarian.

CTA:

> Reset Filter

## Acceptance Criteria

1. User dapat melihat daftar salon sesuai pencarian.
2. User dapat menggunakan filter.
3. User dapat menggunakan sorting.
4. User dapat membuka detail salon.
5. User dapat menyimpan salon ke favorite jika sudah login.

---

# 9.3 Salon Detail Page

## Tujuan

Memberikan informasi lengkap tentang salon agar customer yakin untuk melakukan booking.

## Struktur Halaman

1. Gallery/header image.
2. Salon summary.
3. CTA booking sticky.
4. Service menu.
5. Staff section.
6. Reviews.
7. About.
8. Opening hours.
9. Location map.
10. Facilities.
11. Policies.
12. Similar salons.

## Salon Summary

Data:

1. Salon name.
2. Rating.
3. Review count.
4. Address.
5. Distance.
6. Open/closed status.
7. Category tags.
8. Price starts from.
9. Favorite button.
10. Share button.

## Gallery

Requirements:

1. Minimal 1 cover image.
2. Gallery modal saat foto diklik.
3. Support image kategori: interior, staff, service result, product, before-after.
4. Gunakan lazy loading.

## Service Menu

Service menu tampil di halaman detail salon dan menjadi area utama untuk booking.

Kategori service:

1. Popular services.
2. Haircut.
3. Hair coloring.
4. Hair treatment.
5. Nail care.
6. Facial.
7. Massage.
8. Makeup.
9. Wedding package.
10. Add-ons.

## Service Item

Data:

1. Service name.
2. Short description.
3. Duration.
4. Price.
5. Discount price jika ada.
6. Badge popular.
7. Button select.

## Staff Section

Data:

1. Staff photo.
2. Staff name.
3. Role/specialization.
4. Rating.
5. Review count.
6. Next available schedule.
7. Button choose staff.

## Review Section

Data:

1. Average rating.
2. Total reviews.
3. Rating breakdown.
4. Review list.
5. Reviewer name.
6. Service booked.
7. Review date.
8. Review comment.
9. Review image optional.

## Policies

Informasi:

1. Cancellation policy.
2. Reschedule policy.
3. Late arrival policy.
4. Payment policy.
5. Refund policy.

## Acceptance Criteria

1. User dapat melihat informasi salon lengkap.
2. User dapat memilih service dari service menu.
3. User dapat melihat staff.
4. User dapat membaca review.
5. User dapat membuka lokasi salon.
6. User dapat memulai booking dari tombol CTA.

---

# 9.4 Service Detail

## Tujuan

Menjelaskan layanan secara lebih lengkap sebelum customer memilih.

## Data Service Detail

1. Service name.
2. Category.
3. Description.
4. Duration.
5. Price.
6. Discount price.
7. Available staff.
8. Available add-ons.
9. Terms and notes.
10. Related services.
11. Reviews for this service.

## Service Variant

Service dapat memiliki variasi, contoh:

1. Haircut - Junior Stylist.
2. Haircut - Senior Stylist.
3. Haircut - Director Stylist.
4. Hair Coloring - Short Hair.
5. Hair Coloring - Medium Hair.
6. Hair Coloring - Long Hair.

Setiap variant dapat memiliki:

1. Harga berbeda.
2. Durasi berbeda.
3. Staff yang berbeda.
4. Ketersediaan jadwal berbeda.

## Acceptance Criteria

1. User dapat membaca detail layanan.
2. User dapat memilih variant jika tersedia.
3. User dapat melihat harga dan durasi sebelum booking.
4. User dapat lanjut ke booking.

---

# 9.5 Booking Flow

## Tujuan

Membuat alur booking yang jelas, singkat, dan tidak membingungkan.

## Step Booking

```txt
Select Service
→ Select Add-ons
→ Select Staff
→ Select Date & Time
→ Booking Summary
→ Login/Register
→ Checkout
→ Payment
→ Booking Success
```

---

## 9.5.1 Select Service

Customer memilih satu atau beberapa layanan.

Requirements:

1. Customer dapat memilih satu service.
2. Customer dapat memilih beberapa service dalam satu booking.
3. Sistem menghitung total durasi.
4. Sistem menghitung total harga.
5. Customer dapat menghapus service dari cart.
6. Customer dapat melihat service yang sudah dipilih dalam floating summary.

Multi-service example:

1. Haircut - 45 menit - Rp120.000.
2. Hair coloring - 120 menit - Rp450.000.
3. Hair mask add-on - 20 menit - Rp75.000.

Total:

- Durasi: 185 menit.
- Harga: Rp645.000.

---

## 9.5.2 Select Add-ons

Add-ons adalah layanan tambahan opsional yang muncul setelah customer memilih service utama.

Contoh add-ons:

1. Hair wash.
2. Hair mask.
3. Premium conditioner.
4. Nail art.
5. Extra massage 15 minutes.
6. Scalp treatment.

Rules:

1. Add-ons hanya muncul jika terhubung dengan service utama.
2. Add-ons dapat menambah harga.
3. Add-ons dapat menambah durasi.
4. Add-ons boleh bersifat optional.
5. Add-ons dapat dikelompokkan.

UI:

1. Checkbox card.
2. Tampilkan nama add-on.
3. Tampilkan harga tambahan.
4. Tampilkan durasi tambahan.
5. Tampilkan deskripsi singkat.

---

## 9.5.3 Select Staff / Professional

Customer memilih staff/stylist.

Options:

1. Any staff.
2. Specific staff.
3. Best rated staff.
4. Earliest available staff.
5. Recommended staff.

Staff card data:

1. Staff photo.
2. Staff name.
3. Specialization.
4. Rating.
5. Number of reviews.
6. Next available time.
7. Price adjustment if any.

Rules:

1. Jika customer memilih “Any staff”, sistem memilih staff yang tersedia.
2. Jika service lebih dari satu, sistem harus menentukan apakah semua service dilakukan oleh staff yang sama atau bisa staff berbeda.
3. Jika staff tidak tersedia pada tanggal tertentu, slot tidak boleh muncul.
4. Staff bisa memiliki harga dan durasi berbeda.

---

## 9.5.4 Select Date & Time

Customer memilih jadwal berdasarkan availability.

Calendar requirements:

1. Tampilkan 7 hari terdekat secara horizontal.
2. Tampilkan full calendar untuk memilih tanggal lain.
3. Tampilkan slot pagi, siang, sore, malam.
4. Slot unavailable harus disabled.
5. Slot penuh tidak dapat dipilih.
6. Slot expired tidak tampil.
7. Earliest available slot ditandai.

Time slot data:

1. Start time.
2. End time.
3. Staff assigned.
4. Availability status.
5. Price adjustment if smart pricing exists.

UI label:

1. Available.
2. Almost full.
3. Not available.
4. Recommended.

Rules:

1. Sistem harus mencegah double booking.
2. Sistem harus menghitung durasi total jika multi-service.
3. Sistem harus menyesuaikan slot dengan jam operasional salon.
4. Sistem harus menyesuaikan slot dengan shift staff.
5. Sistem harus menyesuaikan slot dengan buffer time jika ada.

---

## 9.5.5 Booking Summary

Ringkasan booking sebelum checkout.

Data:

1. Salon name.
2. Salon address.
3. Service list.
4. Add-ons.
5. Staff.
6. Date.
7. Time.
8. Duration.
9. Subtotal.
10. Discount.
11. Platform fee if any.
12. Tax if any.
13. Total.
14. Cancellation policy.
15. Customer notes.

Customer notes placeholder:

> Tambahkan catatan untuk salon, misalnya preferensi model rambut atau alergi produk tertentu.

Actions:

1. Edit service.
2. Edit staff.
3. Edit time.
4. Continue to checkout.

---

## 9.5.6 Login/Register Gate

Customer boleh browsing tanpa login, tetapi wajib login sebelum booking dibuat.

Rules:

1. Jika belum login, tampilkan modal login/register.
2. Setelah login berhasil, customer kembali ke booking summary.
3. Data booking sementara tidak boleh hilang.
4. Guest checkout dapat dipertimbangkan untuk versi lanjutan, tetapi MVP disarankan wajib login.

---

## 9.5.7 Checkout

Checkout digunakan untuk voucher dan pembayaran.

Data:

1. Booking summary compact.
2. Voucher input.
3. Payment method.
4. Total price.
5. Terms agreement.
6. Pay button.

Voucher rules:

1. Voucher harus divalidasi sebelum total berubah.
2. Voucher invalid menampilkan error.
3. Voucher expired tidak dapat digunakan.
4. Voucher hanya berlaku sesuai salon/service/category jika ada aturan.

---

## 9.5.8 Payment

Payment methods:

1. QRIS.
2. Virtual account.
3. Bank transfer.
4. E-wallet.
5. Credit/debit card.
6. Pay at venue.

Payment status:

1. Unpaid.
2. Waiting Payment.
3. Paid.
4. Failed.
5. Expired.
6. Refunded.
7. Cancelled.

Rules:

1. Booking dapat dibuat setelah payment berhasil atau setelah customer memilih pay at venue.
2. Jika payment belum selesai, booking status menjadi Waiting Payment.
3. Jika payment expired, booking status menjadi Expired.
4. Jika payment gagal, customer dapat retry payment.

---

## 9.5.9 Booking Success

Halaman setelah booking berhasil.

Data:

1. Success message.
2. Booking ID.
3. Salon name.
4. Service name.
5. Staff.
6. Date and time.
7. Total payment.
8. Payment status.
9. Booking status.
10. Add to calendar button.
11. View booking detail button.
12. Back to home button.

Copy example:

> Booking berhasil dibuat. Jangan lupa datang sesuai jadwal yang telah dipilih.

---

# 9.6 Activity Page

## Tujuan

Memudahkan customer melihat dan mengelola reservasi.

## Tabs

1. Upcoming.
2. Waiting Payment.
3. Completed.
4. Cancelled.
5. All.

## Booking Card

Data:

1. Salon image.
2. Salon name.
3. Service name.
4. Date.
5. Time.
6. Status.
7. Payment status.
8. Total.
9. CTA detail.
10. CTA reschedule.
11. CTA cancel.
12. CTA review if completed.
13. CTA rebook if completed.

## Acceptance Criteria

1. User dapat melihat daftar booking.
2. User dapat membuka detail booking.
3. User dapat cancel jika memenuhi aturan.
4. User dapat reschedule jika memenuhi aturan.
5. User dapat review setelah completed.

---

# 9.7 Booking Detail Page

## Data

1. Booking ID.
2. QR code/check-in code optional.
3. Booking status.
4. Payment status.
5. Salon information.
6. Service list.
7. Add-ons.
8. Staff.
9. Date.
10. Time.
11. Duration.
12. Price detail.
13. Voucher.
14. Total.
15. Customer notes.
16. Payment method.
17. Policy.
18. Timeline status.

## Timeline Example

1. Booking created.
2. Payment waiting.
3. Payment paid.
4. Booking confirmed.
5. Appointment reminder sent.
6. Appointment completed.
7. Review submitted.

---

# 9.8 Cancel Booking

## Rules

1. Customer dapat cancel jika booking belum started.
2. Customer tidak dapat cancel jika status completed.
3. Customer tidak dapat cancel jika melewati batas cancellation policy.
4. Jika sudah paid, refund mengikuti policy.
5. Cancel harus meminta alasan.

## Cancel Reasons

1. Jadwal berubah.
2. Salah memilih layanan.
3. Ingin memilih salon lain.
4. Ada keperluan mendadak.
5. Harga tidak sesuai.
6. Lainnya.

## Cancel Confirmation Copy

> Apakah kamu yakin ingin membatalkan booking ini? Tindakan ini tidak dapat dibatalkan.

---

# 9.9 Reschedule Booking

## Rules

1. Customer dapat reschedule jika salon mengizinkan.
2. Customer hanya dapat memilih slot tersedia.
3. Perubahan harga harus ditampilkan jika ada.
4. Jika jadwal baru memiliki harga berbeda, customer harus menyetujui perubahan.
5. Reschedule dapat dibatasi maksimal beberapa kali.

## Flow

1. Customer buka booking detail.
2. Klik reschedule.
3. Pilih tanggal baru.
4. Pilih jam baru.
5. Lihat summary perubahan.
6. Konfirmasi.
7. Sistem update booking.
8. Customer menerima notifikasi.

---

# 9.10 Review & Rating

## Rules

1. Review hanya bisa dibuat setelah booking completed.
2. Satu booking hanya bisa membuat satu review.
3. Review dapat berisi rating, komentar, dan foto.
4. Review tampil pada halaman salon.
5. Review dapat dikaitkan dengan staff.

## Review Fields

1. Rating salon.
2. Rating staff optional.
3. Comment.
4. Photo optional.
5. Service reference.

## Review Prompt Copy

> Bagaimana pengalamanmu di salon ini?

---

# 9.11 Favorites

## Tujuan

Customer dapat menyimpan salon favorit agar mudah booking ulang.

## Requirements

1. Add to favorite dari salon card.
2. Add to favorite dari salon detail.
3. Remove favorite.
4. Favorite page.
5. Rebook dari favorite.

---

# 9.12 Promo & Voucher

## Promo Page

Data promo:

1. Promo image.
2. Promo title.
3. Promo code.
4. Discount value.
5. Minimum transaction.
6. Maximum discount.
7. Applicable salon/service.
8. Expiry date.
9. Terms.

## Voucher Validation

Rules:

1. Kode voucher harus aktif.
2. Kode voucher belum expired.
3. Minimum transaksi terpenuhi.
4. Customer memenuhi syarat.
5. Salon/service sesuai syarat promo.

---

# 9.13 Notifications

## Notification Types

1. Booking created.
2. Payment waiting.
3. Payment success.
4. Booking confirmed.
5. Booking rejected.
6. Booking cancelled.
7. Booking rescheduled.
8. Appointment reminder.
9. Review reminder.
10. Promo notification.

## Channels

1. In-app notification.
2. Email.
3. WhatsApp/SMS optional.
4. Push notification for future mobile app.

---

# 9.14 Customer Profile

## Profile Fields

1. Full name.
2. Email.
3. Phone number.
4. Profile photo.
5. Password.
6. Address.
7. Gender optional.
8. Birth date optional.

## Profile Menu

1. Account information.
2. My booking.
3. Favorites.
4. Payment history.
5. Promo.
6. Notification settings.
7. Help center.
8. Logout.

---

## 10. User Flow Details

### 10.1 New Customer Booking Flow

```txt
Open website
→ Search salon/service
→ Select location
→ View search results
→ Apply filter/sort
→ Open salon detail
→ Select service
→ Select add-ons
→ Select staff
→ Select date & time
→ View booking summary
→ Register/Login
→ Apply voucher
→ Choose payment method
→ Pay/confirm
→ Booking success
→ Receive notification
```

### 10.2 Returning Customer Rebook Flow

```txt
Login
→ Open Activity
→ Select completed booking
→ Click Rebook
→ Confirm service/staff
→ Select new date & time
→ Checkout
→ Booking success
```

### 10.3 Favorite Booking Flow

```txt
Login
→ Open Favorites
→ Select salon
→ Select service
→ Select schedule
→ Checkout
→ Booking success
```

### 10.4 Promo Booking Flow

```txt
Open Promo
→ Select promo
→ View applicable salon/service
→ Choose salon
→ Select service
→ Voucher auto-applied or copied
→ Checkout
→ Payment
```

---

## 11. Functional Requirements

| ID | Requirement | Priority |
|---|---|---|
| FR-001 | Customer dapat mencari salon berdasarkan keyword | Must Have |
| FR-002 | Customer dapat mencari service berdasarkan keyword | Must Have |
| FR-003 | Customer dapat mencari berdasarkan lokasi | Must Have |
| FR-004 | Customer dapat melihat search results | Must Have |
| FR-005 | Customer dapat menggunakan filter | Must Have |
| FR-006 | Customer dapat menggunakan sorting | Must Have |
| FR-007 | Customer dapat membuka salon detail | Must Have |
| FR-008 | Customer dapat melihat service menu | Must Have |
| FR-009 | Customer dapat melihat harga dan durasi service | Must Have |
| FR-010 | Customer dapat memilih satu service | Must Have |
| FR-011 | Customer dapat memilih multi-service | Should Have |
| FR-012 | Customer dapat memilih add-ons | Should Have |
| FR-013 | Customer dapat memilih staff | Must Have |
| FR-014 | Customer dapat memilih Any Staff | Must Have |
| FR-015 | Customer dapat memilih tanggal dan jam | Must Have |
| FR-016 | Sistem hanya menampilkan slot tersedia | Must Have |
| FR-017 | Sistem mencegah double booking | Must Have |
| FR-018 | Customer dapat melihat booking summary | Must Have |
| FR-019 | Customer wajib login/register sebelum konfirmasi booking | Must Have |
| FR-020 | Customer dapat memasukkan voucher | Should Have |
| FR-021 | Customer dapat melakukan pembayaran | Must Have |
| FR-022 | Customer dapat melihat booking success | Must Have |
| FR-023 | Customer dapat melihat Activity | Must Have |
| FR-024 | Customer dapat membuka booking detail | Must Have |
| FR-025 | Customer dapat cancel booking sesuai policy | Should Have |
| FR-026 | Customer dapat reschedule booking sesuai policy | Should Have |
| FR-027 | Customer dapat memberikan review setelah completed | Should Have |
| FR-028 | Customer dapat menyimpan favorite salon | Should Have |
| FR-029 | Customer dapat menerima notifikasi | Should Have |
| FR-030 | Customer dapat mengelola profile | Must Have |

---

## 12. Non-Functional Requirements

### 12.1 Performance

1. Home page harus ringan dan cepat dibuka.
2. Gambar harus menggunakan lazy loading.
3. Search result harus memiliki loading skeleton.
4. API search harus mendukung pagination.
5. Filter tidak boleh membuat halaman terasa berat.
6. Mobile performance menjadi prioritas.

Target:

1. First meaningful content cepat.
2. Search result muncul dalam waktu wajar.
3. Image card tidak membuat layout shift berlebihan.

### 12.2 Security

1. Password harus di-hash.
2. API customer harus menggunakan authentication token/session yang aman.
3. Customer hanya boleh mengakses booking miliknya sendiri.
4. Input harus divalidasi di frontend dan backend.
5. Payment callback harus diverifikasi.
6. Cegah XSS.
7. Cegah CSRF jika menggunakan cookie-based auth.
8. Rate limit untuk login dan voucher validation.
9. Sensitive data tidak boleh ditampilkan di client.

### 12.3 Reliability

1. Booking harus atomic agar tidak terjadi double booking.
2. Payment status harus sinkron dengan booking status.
3. Jika payment gagal, booking tidak boleh menjadi paid.
4. Jika slot sudah diambil user lain, sistem harus meminta user memilih slot baru.

### 12.4 Usability

1. Alur booking maksimal mudah dipahami.
2. CTA harus jelas.
3. Customer dapat kembali ke step sebelumnya.
4. Data pilihan tidak hilang saat customer login.
5. Error message harus manusiawi.
6. Empty state harus membantu.

### 12.5 Responsive Design

Breakpoints:

1. Mobile: 360px - 767px.
2. Tablet: 768px - 1023px.
3. Desktop: 1024px ke atas.

Mobile-first requirements:

1. Search mudah digunakan dengan thumb.
2. Filter menggunakan bottom sheet.
3. Bottom navigation tersedia.
4. Booking summary sticky di bawah.
5. Time slot mudah dipilih.

---

## 13. Booking Status

| Status | Description |
|---|---|
| Pending | Booking dibuat tetapi belum dikonfirmasi atau belum dibayar |
| Waiting Payment | Menunggu pembayaran customer |
| Paid | Pembayaran berhasil |
| Confirmed | Booking dikonfirmasi salon/sistem |
| On Going | Customer sedang menjalani layanan |
| Completed | Layanan selesai |
| Cancelled | Booking dibatalkan customer/provider |
| Rejected | Booking ditolak provider |
| Expired | Booking/payment melewati batas waktu |
| Rescheduled | Booking telah dijadwalkan ulang |

---

## 14. Payment Status

| Status | Description |
|---|---|
| Unpaid | Belum ada pembayaran |
| Waiting Payment | Customer sudah memilih metode pembayaran tetapi belum membayar |
| Paid | Pembayaran berhasil |
| Failed | Pembayaran gagal |
| Expired | Waktu pembayaran habis |
| Refunded | Dana dikembalikan |
| Cancelled | Pembayaran dibatalkan |

---

## 15. Data Model Requirements

### 15.1 Customer

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| name | string | Required |
| email | string | Unique |
| phone | string | Unique optional |
| password | string | Hashed |
| profile_photo | string | Optional |
| address | text | Optional |
| created_at | datetime | Auto |
| updated_at | datetime | Auto |

### 15.2 Salon

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| name | string | Required |
| slug | string | Unique |
| description | text | Optional |
| address | text | Required |
| latitude | decimal | Optional |
| longitude | decimal | Optional |
| phone | string | Optional |
| rating | decimal | Computed |
| review_count | integer | Computed |
| status | enum | active/inactive |
| created_at | datetime | Auto |
| updated_at | datetime | Auto |

### 15.3 Service Category

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| name | string | Required |
| slug | string | Unique |
| icon | string | Optional |
| order | integer | Sorting |
| status | enum | active/inactive |

### 15.4 Service

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| salon_id | FK | Required |
| category_id | FK | Required |
| name | string | Required |
| description | text | Optional |
| duration_minutes | integer | Required |
| price | decimal | Required |
| discount_price | decimal | Optional |
| is_popular | boolean | Optional |
| status | enum | active/inactive |
| created_at | datetime | Auto |
| updated_at | datetime | Auto |

### 15.5 Service Add-on

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| service_id | FK | Required |
| name | string | Required |
| description | text | Optional |
| additional_price | decimal | Default 0 |
| additional_duration | integer | Default 0 |
| status | enum | active/inactive |

### 15.6 Staff

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| salon_id | FK | Required |
| name | string | Required |
| photo | string | Optional |
| specialization | string | Optional |
| rating | decimal | Computed |
| review_count | integer | Computed |
| status | enum | active/inactive |

### 15.7 Booking

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| booking_code | string | Unique |
| customer_id | FK | Required |
| salon_id | FK | Required |
| staff_id | FK | Optional if Any Staff |
| date | date | Required |
| start_time | time | Required |
| end_time | time | Required |
| total_duration | integer | Required |
| subtotal | decimal | Required |
| discount | decimal | Default 0 |
| total | decimal | Required |
| booking_status | enum | Required |
| payment.status | relation | Sumber tunggal status pembayaran |
| notes | text | Optional |
| created_at | datetime | Auto |
| updated_at | datetime | Auto |

### 15.8 Booking Item

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| booking_id | FK | Required |
| service_id | FK | Required |
| service_name | string | Snapshot |
| price | decimal | Snapshot |
| duration_minutes | integer | Snapshot |

### 15.9 Booking Add-on

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| booking_item_id | FK | Required |
| add_on_id | FK | Required |
| add_on_name | string | Snapshot |
| price | decimal | Snapshot |
| duration_minutes | integer | Snapshot |

### 15.10 Payment

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| booking_id | FK | Required |
| method | enum | Required |
| amount | decimal | Required |
| status | enum | Required |
| transaction_reference | string | Optional |
| paid_at | datetime | Optional |
| expired_at | datetime | Optional |
| created_at | datetime | Auto |

### 15.11 Review

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| customer_id | FK | Required |
| salon_id | FK | Required |
| booking_id | FK | Required |
| staff_id | FK | Optional |
| rating | integer | 1-5 |
| comment | text | Optional |
| photo | string | Optional |
| created_at | datetime | Auto |

### 15.12 Promo

| Field | Type | Notes |
|---|---|---|
| id | UUID/BIGINT | Primary key |
| code | string | Unique |
| name | string | Required |
| description | text | Optional |
| discount_type | enum | percentage/fixed |
| discount_value | decimal | Required |
| minimum_transaction | decimal | Optional |
| maximum_discount | decimal | Optional |
| start_date | datetime | Required |
| end_date | datetime | Required |
| status | enum | active/inactive |

---

## 16. API Requirements

### 16.1 Auth

```txt
POST   /api/customer/register
POST   /api/customer/login
POST   /api/customer/logout
GET    /api/customer/profile
PUT    /api/customer/profile
POST   /api/customer/forgot-password
POST   /api/customer/reset-password
```

### 16.2 Salon

```txt
GET    /api/salons
GET    /api/salons/{id}
GET    /api/salons/{id}/services
GET    /api/salons/{id}/staff
GET    /api/salons/{id}/reviews
GET    /api/salons/{id}/available-slots
```

### 16.3 Services

```txt
GET    /api/services
GET    /api/services/{id}
GET    /api/service-categories
GET    /api/services/popular
GET    /api/services/recommended
GET    /api/services/{id}/add-ons
```

### 16.4 Booking

```txt
POST   /api/bookings/validate-slot
POST   /api/bookings
GET    /api/customer/bookings
GET    /api/customer/bookings/{id}
POST   /api/customer/bookings/{id}/cancel
POST   /api/customer/bookings/{id}/reschedule
POST   /api/customer/bookings/{id}/rebook
```

### 16.5 Payment

```txt
POST   /api/payments
GET    /api/payments/{bookingId}
POST   /api/payments/callback
POST   /api/payments/{id}/retry
```

### 16.6 Promo

```txt
GET    /api/promos
GET    /api/customer/promos
POST   /api/promos/validate
```

### 16.7 Favorite

```txt
GET    /api/customer/favorites
POST   /api/customer/favorites
DELETE /api/customer/favorites/{salonId}
```

### 16.8 Review

```txt
POST   /api/reviews
GET    /api/reviews/{salonId}
GET    /api/customer/reviews
```

### 16.9 Notification

```txt
GET    /api/customer/notifications
PUT    /api/customer/notifications/{id}/read
PUT    /api/customer/notifications/read-all
```

---

## 17. UI Component Requirements

### 17.1 Global Components

1. Navbar.
2. Mobile bottom navigation.
3. Footer.
4. Search bar.
5. Location input.
6. Category chips.
7. Button.
8. Input.
9. Select.
10. Modal.
11. Drawer.
12. Bottom sheet.
13. Toast.
14. Loading skeleton.
15. Empty state.
16. Error state.

### 17.2 Customer Components

1. Salon card.
2. Service card.
3. Staff card.
4. Review card.
5. Promo card.
6. Booking summary card.
7. Time slot button.
8. Calendar picker.
9. Payment method card.
10. Booking status badge.
11. Rating badge.
12. Favorite button.
13. Price summary.
14. Policy card.

---

## 18. UI/UX Design Direction

### 18.1 Visual Style

1. Clean.
2. Modern.
3. Minimalis.
4. Cerah.
5. Banyak whitespace.
6. Rounded corners.
7. Soft shadow.
8. Tidak terlalu ramai.
9. Tidak menggunakan gradient berlebihan.
10. Menggunakan font kalem seperti Poppins.

### 18.2 Recommended Color Direction

Gunakan identitas visual yang berbeda dari Fresha.

Rekomendasi:

1. Primary: Soft coral / warm pink.
2. Secondary: Cream / off-white.
3. Accent: Soft purple / peach.
4. Text: Dark gray.
5. Background: White / light beige.
6. Success: Soft green.
7. Warning: Soft yellow/orange.
8. Error: Soft red.

### 18.3 Anti-Plagiarism Design Rules

Hal yang boleh diadaptasi:

1. Alur pencarian.
2. Alur booking.
3. Struktur marketplace.
4. Ide service menu.
5. Konsep rating/review.
6. Konsep availability.
7. Konsep add-ons.
8. Konsep rebook.

Hal yang harus dibuat berbeda:

1. Warna utama.
2. Layout hero.
3. Bentuk card.
4. Icon style.
5. Copywriting.
6. Spacing system.
7. Ilustrasi.
8. Animasi.
9. Nama section.
10. Brand voice.
11. Visual hierarchy.

---

## 19. Error and Empty States

### 19.1 Search Empty

Message:

> Salon tidak ditemukan. Coba ubah lokasi atau kata kunci pencarian.

CTA:

> Reset Filter

### 19.2 Booking Empty

Message:

> Kamu belum memiliki booking. Cari salon favoritmu dan buat reservasi pertama sekarang.

CTA:

> Cari Salon

### 19.3 Favorite Empty

Message:

> Belum ada salon favorit. Simpan salon yang kamu suka agar lebih mudah booking kembali.

CTA:

> Explore Salon

### 19.4 Payment Failed

Message:

> Pembayaran gagal diproses. Silakan coba lagi atau pilih metode pembayaran lain.

CTA:

> Coba Lagi

### 19.5 Slot Taken

Message:

> Jadwal ini baru saja dipilih customer lain. Silakan pilih waktu lain.

CTA:

> Pilih Jadwal Lain

---

## 20. Analytics Requirements

Event yang perlu dilacak:

1. home_viewed.
2. search_submitted.
3. filter_applied.
4. salon_card_clicked.
5. salon_detail_viewed.
6. service_selected.
7. add_on_selected.
8. staff_selected.
9. time_slot_selected.
10. booking_summary_viewed.
11. login_started.
12. register_completed.
13. checkout_started.
14. voucher_applied.
15. payment_started.
16. payment_success.
17. booking_created.
18. booking_cancelled.
19. booking_rescheduled.
20. review_submitted.
21. favorite_added.
22. rebook_clicked.

---

## 21. SEO Requirements

Website customer harus mendukung SEO untuk halaman publik.

Halaman yang harus SEO-friendly:

1. Home.
2. Category page.
3. Search result by city.
4. Salon detail.
5. Service detail.
6. Promo page.

SEO elements:

1. Dynamic title.
2. Meta description.
3. Open Graph image.
4. Canonical URL.
5. Structured data for local business optional.
6. Sitemap.
7. Clean slug.

Contoh slug:

```txt
/salons/jakarta-selatan
/salon/queen-beauty-studio
/services/haircut
/promo/new-customer-discount
```

---

## 22. MVP Prioritization

### 22.1 MVP Phase 1

Fitur wajib untuk versi awal:

1. Home page.
2. Search salon/service.
3. Category browsing.
4. Search result.
5. Filter basic.
6. Salon detail.
7. Service menu.
8. Select service.
9. Select staff / Any Staff.
10. Select date and time.
11. Login/register.
12. Booking summary.
13. Create booking.
14. Payment basic.
15. Booking success.
16. Activity.
17. Booking detail.
18. Basic review.
19. Profile.

### 22.2 MVP Phase 2

Fitur lanjutan:

1. Add-ons.
2. Multi-service booking.
3. Promo/voucher.
4. Favorites.
5. Reschedule booking.
6. Cancel booking.
7. Notification.
8. Payment history.
9. Review with image.

### 22.3 MVP Phase 3

Fitur advanced:

1. AI recommendation.
2. Loyalty point.
3. Membership.
4. Smart pricing.
5. Chat customer-provider.
6. Wedding package builder.
7. Personalized promo.
8. Mobile app integration.
9. Map-based search.
10. Multi-language.

---

## 23. Acceptance Criteria Summary

Produk dinyatakan memenuhi PRD jika:

1. Customer dapat membuka website dengan tampilan responsive.
2. Customer dapat mencari salon atau layanan.
3. Customer dapat melihat search result dengan informasi rating, harga, lokasi, dan jadwal.
4. Customer dapat filter dan sorting hasil pencarian.
5. Customer dapat membuka detail salon.
6. Customer dapat melihat service menu.
7. Customer dapat melihat harga dan durasi layanan.
8. Customer dapat memilih layanan.
9. Customer dapat memilih staff atau Any Staff.
10. Customer dapat memilih tanggal dan jam tersedia.
11. Customer dapat login/register tanpa kehilangan data booking.
12. Customer dapat melihat booking summary.
13. Customer dapat menggunakan voucher jika tersedia.
14. Customer dapat melakukan pembayaran.
15. Customer dapat melihat booking success.
16. Customer dapat melihat Activity.
17. Customer dapat cancel/reschedule sesuai policy.
18. Customer dapat memberikan review setelah layanan selesai.
19. Customer dapat menyimpan favorite salon.
20. Customer dapat mengelola profile.

---

## 24. Recommended Frontend Structure

```txt
src/
├── app/
├── assets/
├── components/
│   ├── common/
│   ├── layout/
│   ├── salon/
│   ├── service/
│   ├── booking/
│   ├── payment/
│   ├── review/
│   └── profile/
├── features/
│   ├── auth/
│   ├── home/
│   ├── search/
│   ├── salon/
│   ├── service/
│   ├── booking/
│   ├── payment/
│   ├── promo/
│   ├── favorite/
│   ├── notification/
│   └── profile/
├── hooks/
├── layouts/
├── pages/
├── services/
├── stores/
├── styles/
├── types/
└── utils/
```

---

## 25. Recommended Route Structure

```txt
/
/explore
/search
/search?keyword=haircut&location=jakarta
/category/:categorySlug
/salon/:salonSlug
/salon/:salonSlug/services/:serviceSlug
/booking/:salonSlug
/payment/:bookingCode
/booking-success/:bookingCode
/activity
/activity/:bookingCode
/promos
/favorites
/notifications
/profile
/help
```

---

## 26. Business Rules

1. Customer dapat browsing tanpa login.
2. Customer wajib login sebelum membuat booking.
3. Slot booking harus dikunci sementara saat customer masuk checkout.
4. Slot lock memiliki expiry time.
5. Jika payment expired, slot dilepas kembali.
6. Booking tidak boleh bentrok dengan booking lain.
7. Review hanya dapat dibuat setelah booking completed.
8. Customer hanya dapat mengakses booking miliknya sendiri.
9. Voucher hanya dapat digunakan jika memenuhi syarat.
10. Cancel dan reschedule mengikuti policy salon.
11. Harga pada booking harus disimpan sebagai snapshot agar tidak berubah jika harga service berubah di kemudian hari.
12. Service name pada booking juga harus disimpan sebagai snapshot.
13. Payment status harus selalu sinkron dengan booking status.

---

## 27. Risks and Considerations

### 27.1 Risk: Double Booking

Mitigation:

1. Lock slot saat checkout.
2. Gunakan transaction/database lock saat create booking.
3. Validate slot lagi sebelum payment.

### 27.2 Risk: Customer Bingung Saat Booking

Mitigation:

1. Gunakan stepper.
2. Tampilkan summary sticky.
3. Gunakan copywriting sederhana.
4. Sediakan edit step.

### 27.3 Risk: Search Result Lambat

Mitigation:

1. Pagination.
2. Index database.
3. Cache popular search.
4. Optimize image.

### 27.4 Risk: Payment Tidak Sinkron

Mitigation:

1. Payment callback verification.
2. Payment status polling.
3. Manual refresh status.
4. Retry payment.

---

## 28. Reference Notes

Dokumen ini mengambil inspirasi dari pola customer booking Fresha, terutama pada bagian marketplace discovery, online booking, service menu, real-time availability, service add-ons, review, dan appointment management. Namun, visual design, brand identity, copywriting, warna, layout, dan komponen UI harus dibuat berbeda agar tidak menjiplak.

Referensi publik yang digunakan:

1. Fresha main customer experience: https://www.fresha.com/
2. Fresha online appointment booking guide: https://www.fresha.com/help-center/knowledge-base/online-profile/599-learn-how-clients-book-appointments-online
3. Fresha service add-ons: https://www.fresha.com/help-center/knowledge-base/catalog/102085-create-service-add-ons
4. Fresha service menu guidance: https://www.fresha.com/blog/salon-service-list-tips-to-get-online-bookings
5. Fresha marketplace discovery: https://www.fresha.com/for-business/salon/getting-discovered-through-the-fresha-marketplace
6. Fresha online booking settings: https://www.fresha.com/help-center/knowledge-base/calendar/22-manage-online-bookings-settings

---

## 29. Final Notes

PRD ini berfokus pada customer website sebagai marketplace reservasi salon. Prinsip utama yang harus dijaga adalah alur booking yang mudah, informasi harga yang transparan, jadwal yang jelas, tampilan yang clean, serta struktur fitur yang mudah dikembangkan untuk kategori layanan baru seperti wedding beauty, spa, barber, nail care, dan treatment premium.
