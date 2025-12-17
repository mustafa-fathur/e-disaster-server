# e-Disaster (Laravel Admin Web Application)

**e-Disaster** adalah sistem digital terpadu untuk manajemen dan pelaporan bencana di Indonesia.
Repositori ini merupakan komponen web berbasis Laravel yang berfungsi sebagai aplikasi admin dan pusat kendali untuk seluruh data dan aktivitas yang terjadi di sistem e-Disaster.

Aplikasi ini memungkinkan administrator untuk mengelola pengguna (admin, petugas, dan relawan), memantau data bencana, serta memverifikasi laporan lapangan yang dikirim melalui aplikasi mobile.

---

## Instalasi

### Persiapan

Pastikan Anda sudah menginstal:

* PHP 8.4+
* Composer
* MySQL
* Node.js

### Langkah Instalasi

1. Clone repositori:

   ```bash
   git clone https://github.com/username/e-disaster-web.git
   cd e-disaster-web
   ```

2. Install dependensi:

   ```bash
   npm install && npm run build
   composer install
   ```

3. Buat file environment:

   ```bash
   cp .env.example .env
   ```

4. Atur konfigurasi database pada file `.env`:

   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=e_disaster
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. Jalankan migrasi database:

   ```bash
   php artisan migrate
   ```

6. Jalankan seeder (opsional untuk data awal):

   ```bash
   php artisan db:seed
   ```

7. Jalankan server lokal:

   ```bash
   composer run dev
   ```

Aplikasi akan tersedia di:

```
http://127.0.0.1:8000
```

---

## API
Dokumentasi API yang telah dibuat dapat dilihat pada file [API_DOCUMENTATION.md](API_DOCUMENTATION.md). Atau bisa dengan menjalankan project laravel lalu kemudian membuka url:

```
http://127.0.0.1:8000/api/documentation#/

atau

https://e-disaster.fathur.tech/api/documentation#/
```
