# GetFiles API & Client Template

Repository ini menyimpan sistem backend (API) untuk platform **GetFiles** serta source code template front-end (Video Player) yang akan didistribusikan kepada para Client / Affiliate.

---

## 📁 Struktur Direktori

- **`/` (Root Folder)**: Berisi script **Backend API** (Native PHP) untuk menangani request dari database, sinkronisasi ke Cloudflare R2, dan Bunny CDN.
- **`/client-template/`**: Berisi **Frontend Source Code** (Node.js/Vanilla JS) yang akan di-build (obfuscate) sebelum diserahkan ke client.

---

## 🚀 1. Panduan Setup Backend (API Server)

API ini tidak menggunakan framework khusus (Native PHP), sehingga sangat ringan.

### Persyaratan:
- Web Server (Apache/Nginx/LiteSpeed)
- PHP 7.4 atau lebih baru
- MySQL Database

### Cara Install:
1. Clone repository ini di server / hosting Anda.
2. Buat database MySQL baru.
3. Jalankan script `setup_db.php` sekali di browser untuk membuat tabel otomatis, **LALU HAPUS FILE TERSEBUT** dari server demi keamanan.
4. Buat file bernama `config.php` di root folder. **(JANGAN PERNAH PUSH FILE INI KE GITHUB!)**.
5. Isi `config.php` dengan template berikut dan sesuaikan kredensialnya:

```php
<?php
// config.php - Konfigurasi Utama API

// --- KONFIGURASI DATABASE ---
define('DB_HOST', 'localhost');
define('DB_USER', 'user_db_anda');
define('DB_PASS', 'password_db_anda');
define('DB_NAME', 'nama_db_anda');

// --- CLOUDFLARE R2 (STORAGE BARU) ---
define('R2_ACCOUNT_ID', '...'); 
define('R2_ACCESS_KEY_ID', '...');
define('R2_SECRET_ACCESS_KEY', '...');
define('R2_BUCKET_NAME', '...');
define('R2_PUBLIC_URL', 'pub-xxxx.r2.dev'); 

// --- BUNNY.NET (LEGACY / SYNC / THUMBNAIL) ---
define('BUNNY_LIBRARY_ID', '...');
define('BUNNY_API_KEY', '...');
define('BUNNY_STORAGE_ZONE', '...');
define('BUNNY_STORAGE_API_KEY', '...');
define('BUNNY_STORAGE_ENDPOINT', 'sg.storage.bunnycdn.com');
define('BUNNY_PULL_ZONE_URL', 'getfiles.b-cdn.net');

// --- KEAMANAN ADMIN PANEL ---
define('ADMIN_UPLOAD_PASSWORD', 'rahasia_admin');
```

---

## 🎨 2. Panduan Build Client Template

Folder `client-template` HANYA dikelola oleh tim developer internal. Client/Affiliate **tidak boleh** menerima file asli (`app.js` ori), melainkan hanya menerima file hasil kompilasi/build (`dist`).

### Persyaratan:
- Node.js terinstall di laptop/komputer developer.

### Cara Build & Distribusi ke Client:
1. Buka terminal, masuk ke folder client:
   ```bash
   cd client-template
   ```
2. Install modul dependencies (Hanya perlu sekali setelah clone):
   ```bash
   npm install
   ```
3. Lakukan build / obfuscate script:
   ```bash
   npm run build
   ```
4. **CARA DISTRIBUSI**: Buka folder `client-template/dist/`. Ambil **semua isi** dari folder `dist/` tersebut (terdiri dari `index.html`, `404.html`, `config.js`, `app.js`, dan folder `assets`). Berikan file-file tersebut kepada Client untuk di-upload ke hosting/Github Pages mereka masing-masing.

---

### ⚠️ Catatan Keamanan untuk Tim Developer
1. **Dilarang keras** mengubah status Repository ini menjadi Public.
2. Jika terpaksa Public, **pastikan file `config.php` terdaftar di `.gitignore`** (Ini sudah dilakukan secara otomatis).
3. Jangan menaruh API Key/Token rahasia apapun langsung di dalam folder `client-template/dist/` atau `config.js`, karena file tersebut akan dibaca oleh browser publik.
