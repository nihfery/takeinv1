# SalonKu Database Architecture

Dokumen ini menjelaskan struktur transaksi setelah normalisasi schema pada 24 Juli 2026.

## Prinsip

1. Setiap fakta bisnis hanya memiliki satu sumber data.
2. Snapshot harga dan durasi disimpan pada pivot transaksi agar perubahan katalog tidak mengubah histori.
3. Data kondisional dipisahkan ke tabel khusus, bukan menghasilkan banyak kolom kosong pada tabel utama.
4. Activity customer hanya mengindeks booking final; detailnya selalu dibaca melalui relasi booking.
5. Nilai `NULL` dipertahankan hanya bila memang mewakili kondisi bisnis yang belum terjadi atau bersifat opsional.

## Booking

### `bookings`

Menyimpan kepala transaksi, jadwal utama, status proses layanan, total transaksi, pemilik, branch, dan staff utama.

Kolom yang dihapus:

- `service_id`: layanan sudah berada di `booking_services`.
- `booking_time`: jadwal utama sudah berada di `start_time`.
- `amount`: total transaksi sudah berada di `total_price`.
- `payment_status`: status pembayaran bersumber dari `payments.status`.

Kolom nullable yang tetap diperlukan:

- `booking_date`, `start_time`, `estimated_end_time`: booking antrean dapat belum memiliki jam pasti.
- `customer_id`: booking walk-in dapat dibuat tanpa akun customer.
- `branch_id`, `staff_id`: relasi dibuat nullable agar histori tetap ada bila cabang atau staff dihapus.
- `actual_start_time`, `actual_end_time`, `checked_in_at`, `completed_at`: baru terisi sesuai lifecycle layanan.
- `hold_expires_at`, `expired_at`: hanya berlaku untuk hold atau transaksi kedaluwarsa.

### `booking_services`

Satu-satunya sumber layanan milik booking.

- pasangan `booking_id + service_id` unik;
- `price` adalah snapshot harga saat booking;
- `estimated_duration` adalah snapshot durasi saat booking.

### `booking_participants`

Khusus menyimpan peserta booking grup (`bookings.participant_count > 1`), termasuk
pemesan utama sebagai posisi pertama. Booking personal tidak membuat row di tabel
ini karena identitas customer, professional, jadwal, dan totalnya sudah tersimpan
pada `bookings`.

Untuk grup dengan satu pilihan bersama, tabel ini menyimpan identitas setiap orang.
Untuk grup dengan pilihan terpisah, tabel ini juga menyimpan professional, tanggal,
jam, durasi, dan total masing-masing peserta.

### `booking_participant_services`

Sumber layanan per peserta hanya ketika anggota grup memilih layanan secara
terpisah. Harga dan durasi juga berupa snapshot transaksi. Booking personal selalu
membaca layanan dari `booking_services`.

## Payment

### `payments`

Menyimpan satu pembayaran umum untuk satu booking:

- `booking_id` unik;
- `payment_type`;
- `amount`;
- `status`;
- `payment_method`;
- `paid_at`.

`amount` tidak menduplikasi `bookings.total_price`: pada pembayaran DP, nilai pembayaran memang lebih kecil dari total booking.

### `payment_gateway_transactions`

Hanya dibuat untuk pembayaran online. Tabel ini menampung channel, ID transaksi provider, status provider, kode pembayaran, QR/deeplink, expiry, serta payload audit gateway.

Pembayaran `pay_at_salon` tidak membuat row gateway sehingga tidak menghasilkan sekumpulan kolom gateway yang kosong di `payments`.

## Customer Activity

### `customer_activities`

Tabel indeks yang hanya berisi:

- `customer_id`;
- `booking_id` unik;
- timestamps.

Row dibuat otomatis saat booking keluar dari status hold dan masuk ke lifecycle booking final. Nama salon, layanan, total, jadwal, professional, peserta, dan payment tidak disalin; endpoint Activity mengambil semuanya melalui relasi `bookings`.

## Reviews

### `branch_reviews`

Berisi `booking_id`, `rating`, `comment`, dan timestamps. Customer, provider, dan branch diturunkan dari booking.

### `staff_reviews`

Berisi `booking_id`, `staff_id`, `rating`, `comment`, dan timestamps. Customer, provider, dan branch diturunkan dari booking.

## Aturan Integritas

- Satu booking memiliki maksimal satu payment.
- Satu booking memiliki maksimal satu customer activity.
- Satu booking memiliki maksimal satu branch review.
- Satu staff hanya dapat direview sekali pada booking yang sama.
- Menghapus booking menghapus pivot layanan, peserta, payment, activity, dan review melalui foreign key cascade.
- Booking hold tidak masuk Activity.
- Booking `pending_payment`, `confirmed`, `waiting`, `checked_in`, `in_progress`, `rescheduled`, dan histori sesudahnya masuk Activity.
