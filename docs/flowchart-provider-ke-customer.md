# Flowchart Provider ke Customer

Dokumen ini menjelaskan alur operasional dari **provider pusat** dan **akun cabang** sampai data tersebut tampil, dapat dipesan, lalu dilayani untuk customer. Ini terpisah dari flowchart booking customer utama.

## Struktur organisasi dan batas data

```mermaid
flowchart TB
    Pusat[Provider pusat / pemilik bisnis<br/>users.id = provider_id] --> Branch1[Cabang A<br/>provider_branches]
    Pusat --> Branch2[Cabang B<br/>provider_branches]
    Pusat --> Role[Role & permissions]
    Role --> AccountA[Akun cabang A<br/>users.provider_id = pusat<br/>users.branch_id = Cabang A]
    Role --> AccountB[Akun cabang B<br/>users.provider_id = pusat<br/>users.branch_id = Cabang B]

    AccountA --> ScopeA[Hanya data Cabang A]
    AccountB --> ScopeB[Hanya data Cabang B]
    Pusat --> ScopeAll[Seluruh cabang dalam organisasi]

    Branch1 --> StaffA[Staff Cabang A]
    Branch1 --> ServiceA[Layanan yang ditugaskan ke Cabang A]
    Branch1 --> BookingA[Booking, antrean, pembayaran, customer, review Cabang A]
    StaffA --> Availability[Kelayakan staff dan slot]
    ServiceA --> Availability
    Availability --> BookingA
```

`provider_id` pada akun cabang selalu menunjuk ke provider pusat. Karena itu pusat dapat melihat seluruh data bisnis, sedangkan akun cabang otomatis dibatasi dengan `branch_id` miliknya. Selain pembatasan cabang, akun cabang juga harus memperoleh izin menu dari role yang dibuat pusat.

## Alur dari provider sampai customer bisa booking

```mermaid
flowchart TD
    Start([Provider pusat masuk]) --> Setup{Akun aktif, dokumen terverifikasi,<br/>dan trial/langganan masih berlaku?}
    Setup -->|Tidak| Lock[Profil/onboarding dibuka;<br/>operasional dan booking customer belum tersedia]
    Setup -->|Ya| Org[Kelola organisasi provider]

    Org --> Branch[1. Buat dan aktifkan branch:<br/>nama, alamat, koordinat, foto,<br/>jam kerja, hari kerja/libur]
    Org --> Service[2. Buat/aktifkan layanan:<br/>harga, durasi, metode bayar,<br/>scheduled/queue, galeri]
    Org --> Staff[3. Buat dan aktifkan staff]
    Org --> Accounts[4. Buat role, hak menu,<br/>dan akun untuk cabang tertentu]

    Branch --> AssignStaff[Menugaskan staff ke branch]
    Service --> AssignService[Menetapkan layanan ke satu/lebih branch]
    Staff --> Skills[Menetapkan skill layanan tiap staff]
    Staff --> Schedule[Menetapkan jadwal kerja staff]
    AssignStaff --> Ready
    AssignService --> Ready
    Skills --> Ready
    Schedule --> Ready

    Accounts --> BranchLogin[Akun cabang masuk]
    BranchLogin --> BranchOps[Kelola data dan operasi cabangnya<br/>hanya pada menu yang diizinkan]
    BranchOps --> Ready

    Ready{Branch siap menerima booking?}
    Ready -->|Tidak| Fix[Perbaiki status branch/provider,<br/>layanan, staff, skill, atau jadwal]
    Fix --> Org
    Ready -->|Ya| Public[Branch dan layanan aktif tersedia<br/>di katalog/detail customer]

    Public --> Customer[Customer mencari lokasi/kategori,<br/>membuka detail branch dan staff]
    Customer --> Available[Customer memilih layanan/staff/tanggal;<br/>backend menghitung eligibility dan slot]
    Available --> Book[Customer membuat dan menyelesaikan booking]
    Book --> BranchOps
    BranchOps --> ServiceFlow[Check-in/call antrean -> mulai layanan<br/>-> selesai/cancel/no-show]
    ServiceFlow --> Outcome[Riwayat, pembayaran, customer directory,<br/>review dan dashboard diperbarui]
    Outcome --> Org
```

### Kriteria agar customer dapat melakukan booking

Backend menolak booking bila salah satu syarat berikut tidak terpenuhi:

1. Branch berstatus `active`.
2. Provider berperan `provider`, profilnya `active`, dokumen `verified`, serta masih trial aktif atau punya langganan aktif.
3. Layanan yang dipilih aktif, milik provider, tersedia pada branch, dan mendukung mode scheduled atau queue yang dipilih.
4. Ada staff aktif di branch yang memiliki semua skill layanan dan bekerja pada tanggal tersebut.
5. Untuk booking scheduled, waktu berada pada jam kerja dan tidak bentrok dengan booking/hold aktif.

## Apa yang dapat dikelola: pusat vs cabang

| Area | Provider pusat | Akun cabang (hanya jika menu diizinkan) |
| --- | --- | --- |
| Dashboard | Melihat statistik, pendapatan, booking, staff, dan performa seluruh branch. | Melihat statistik dan performa milik branch sendiri. |
| Profil & dokumen provider | Mengubah profil bisnis, unggah dokumen, onboarding, dan password akun pusat. | Bisa melihat profil dan mengubah password akun sendiri; tidak dapat mengubah profil utama atau dokumen provider. |
| Branch | Membuat, melihat, mengubah, aktif/nonaktif, menugaskan staff, dan menghapus seluruh branch. | Hanya melihat serta mengubah branch sendiri, mengatur staff branch sendiri, dan aktif/nonaktif branch sendiri. Tidak dapat membuat atau menghapus branch. |
| Layanan | Membuat serta mengubah seluruh layanan; dapat menetapkan layanan ke beberapa branch. | Hanya melihat layanan yang berlaku di branch sendiri. Dapat membuat layanan untuk branch sendiri dan mengelola layanan yang terlihat bila diberi menu `services`; penetapan branch tidak dapat menghapus penugasan branch lain. |
| Staff | Membuat, mengubah, aktif/nonaktif, atau menghapus staff di seluruh branch. | Mengelola staff branch sendiri; sistem memaksa `branch_id` ke branch akun tersebut saat membuat staff. |
| Skill & jadwal staff | Mengelola seluruh staff dalam organisasi. | Mengelola skill dan jadwal staff branch sendiri. Ini langsung menentukan staff eligible dan slot customer. |
| Role, permission, akun cabang | Satu-satunya pihak yang dapat membuat/mengubah/nonaktifkan role dan akun cabang. | Tidak dapat mengelola role atau akun cabang. |
| Booking & kalender | Melihat dan menjalankan operasional booking di semua branch. | Melihat dan menjalankan operasional hanya booking branch sendiri. |
| Queue & walk-in | Mengelola antrean dan membuat walk-in seluruh branch. | Mengelola antrean dan membuat walk-in pada branch sendiri. |
| Payment, customer, review | Melihat data seluruh provider. | Hanya melihat transaksi, customer yang pernah booking, dan review dari branch sendiri. |
| Notifikasi, chat, tiket | Menerima notifikasi sesuai menu; dapat chat internal dengan akun cabang. | Menerima notifikasi sesuai menu; dapat chat internal bila berizin. Support chat/tiket memerlukan langganan aktif. |

> Catatan penting: izin menu bukan hanya tampilan. Middleware `provider.menu:*` memeriksa izin server-side. Jadi akun cabang tidak bisa membuka URL fitur yang tidak diizinkan, sekalipun mengetahui alamat halamannya.

## Detail alur akun cabang

```mermaid
flowchart TD
    A([Akun cabang masuk]) --> B{Memiliki branch_id, role aktif,<br/>dan izin menu?}
    B -->|Tidak| Denied[Menu/halaman ditolak atau diarahkan ke dashboard]
    B -->|Ya| Scope[Semua query disaring ke provider pusat + branch_id akun]

    Scope --> M{Menu yang diberikan pusat}
    M --> M1[Services: layanan branch]
    M --> M2[Staff / skills / schedules: staff branch]
    M --> M3[Branch: detail dan staff branch sendiri]
    M --> M4[Bookings / calendar / queue / walk-in]
    M --> M5[Payments / customer / review branch]
    M --> M6[Chat / tiket / notifikasi]

    M1 --> C1[Layanan aktif dan ditugaskan ke branch]
    M2 --> C2[Staff aktif, punya skill, dan jadwal kerja]
    M3 --> C3[Jam operasional/lokasi/status branch terbarui]
    C1 --> Gate
    C2 --> Gate
    C3 --> Gate

    Gate{Customer membuka branch}
    Gate -->|Provider/branch tidak layak| Hidden[Booking ditolak; detail booking tidak tersedia]
    Gate -->|Layak| Catalog[Customer melihat layanan, staff, review, lokasi]
    Catalog --> Slots[Customer meminta slot]
    Slots --> Check[Backend cek service + skill + jadwal + konflik]
    Check -->|Tidak tersedia| Retry[Customer memilih opsi lain]
    Retry --> Slots
    Check -->|Tersedia| Confirm[Customer booking]
    Confirm --> M4
```

## Dampak setiap data provider pada pengalaman customer

```mermaid
flowchart LR
    BranchData[Data branch:<br/>status, lokasi, foto, jam, hari libur] --> Search[Hasil pencarian & detail branch]
    ServiceData[Data layanan:<br/>status, branch, harga, durasi, mode booking] --> Detail[Daftar layanan & total booking]
    StaffData[Data staff:<br/>status, branch, skill, jadwal] --> Slots[Staff eligible & slot tersedia]
    ReviewData[Review branch/staff] --> Trust[Rating & ulasan customer]

    Search --> Detail
    Detail --> Slots
    Slots --> Booking[Customer booking]
    Booking --> Operations[Operasional branch]
    Operations --> ReviewData
```

## Urutan setup yang disarankan untuk provider pusat

1. Lengkapi profil bisnis dan unggah dokumen; tunggu verifikasi admin.
2. Pastikan trial masih aktif atau aktifkan paket langganan.
3. Buat branch, lengkapi lokasi, foto, jam kerja, hari kerja, dan hari libur.
4. Buat layanan aktif dan tetapkan setiap layanan ke branch yang memang menyediakannya.
5. Tambahkan staff aktif ke masing-masing branch.
6. Petakan skill layanan dan jadwal kerja staff.
7. Buat akun cabang dengan role serta menu minimal yang dibutuhkan.
8. Periksa status branch, layanan, staff, dan jadwal sebelum dipublikasikan ke customer.

Dengan urutan ini, customer akan mendapatkan detail salon yang lengkap, hanya melihat staff yang sesuai, serta menerima slot yang benar-benar dapat dilayani oleh branch tersebut.
