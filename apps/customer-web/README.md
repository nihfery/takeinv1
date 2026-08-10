# SalonKu Customer Web

Next.js landing marketplace untuk customer SalonKu. Halaman ini membaca data publik Laravel untuk branch dan lokasi, lalu jatuh ke data demo jika backend belum berjalan.

## Local Development

Jalankan Laravel backend dari root repository:

```bash
cd backend/laravel-core
php artisan serve --host=0.0.0.0
```

Jalankan customer web dari root repository di terminal lain:

```bash
cd apps/customer-web
npm ci
npm run dev
```

Buka:

```text
http://127.0.0.1:5174
```

## Environment

Copy `.env.example` menjadi `.env` lalu sesuaikan URL:

```text
NEXT_PUBLIC_BACKEND_URL=http://127.0.0.1:8000
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api
NEXT_PUBLIC_PROVIDER_FRONTEND_URL=http://127.0.0.1:5173
NEXT_PUBLIC_CUSTOMER_APP_URL=http://127.0.0.1:5174
```

Route utama:

```text
/
/search
```

Build production:

```bash
npm run build
npm run start
```
