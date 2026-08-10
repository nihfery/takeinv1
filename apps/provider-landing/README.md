# JasaKu Provider Landing

React landing page untuk provider/mitra JasaKu. Aplikasi ini berdiri sendiri dan bisa di-host terpisah dari Laravel.

## Local Development

Jalankan Laravel backend dari root repository:

```bash
cd backend/laravel-core
php artisan serve --host=0.0.0.0
```

Jalankan provider landing dari root repository di terminal lain:

```bash
cd apps/provider-landing
npm ci
npm run dev
```

Buka:

```text
http://127.0.0.1:5173
```

Untuk akses dari perangkat lain di jaringan lokal yang sama, buka:

```text
http://IP-LAN-KOMPUTER:5173
```

Saat dibuka lewat IP LAN, frontend otomatis mencoba backend di `http://IP-LAN-KOMPUTER:8000`.

## Environment

Copy `.env.example` menjadi `.env` lalu sesuaikan URL backend:

```text
VITE_BACKEND_URL=http://127.0.0.1:8000
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

Jika testing dari perangkat lain, ganti `127.0.0.1` dengan IP LAN komputer server.

Login mitra melakukan POST ke backend Laravel agar session dashboard Blade dibuat, lalu Laravel mengarahkan user ke `/provider/dashboard`.
