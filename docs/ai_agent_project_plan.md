# Project Plan & Technical Specification: Web Template Catalog, Live Customizer & WordPress Theme Exporter

> **Instruksi untuk AI Agent:** Dokumen ini adalah _Single Source of Truth_ (PRD & Technical Blueprint) untuk membangun aplikasi katalog template website (Landing Page & Company Profile) dengan fitur _Live Visual Editor_ tanpa login serta _Auto-Exporter ke WordPress Theme_ khusus Admin. Ikuti arsitektur, skema database, spesifikasi API, dan tahapan implementasi di bawah ini secara berurutan.

## 1. Project Overview & Key Business Rules

Aplikasi ini adalah platform **Katalog Website (Landing Page & Company Profile)** yang memungkinkan calon klien (pengunjung) untuk melihat, mengedit langsung isi template tanpa login, menyimpan hasil editannya ke sebuah link khusus, dan memesannya via WhatsApp. Setelah klien cocok dan mengirimkan link hasil editannya, **Admin dapat mengekspor link tersebut secara otomatis menjadi file `.zip` Tema WordPress (WordPress Theme)** yang siap langsung di-upload dan diaktifkan di WordPress.

### Aturan Bisnis Utama (Core Business Rules):

1. **Dukungan Single-Page & Multi-Page Template:**

    - Template bisa berupa **1 Halaman (Single Landing Page)** maupun **Multi-Halaman (Company Profile)** seperti _Home, About Us, Services, Contact_.

    - Elemen seperti Logo, Navbar, dan Footer dapat ditandai sebagai **Global Element** sehingga jika diubah di satu halaman, otomatis berubah di semua halaman dalam template tersebut.

2. **Live Edit Tanpa Login:**

    - Pengunjung dapat langsung mengubah teks, mengganti logo/gambar/foto, mengubah tautan tombol, serta mengganti palet warna website secara _real-time_.

3. **Satu Link Unik (Update / Overwrite, Bukan Buat Link Baru):**

    - Saat pengunjung pertama kali klik **Save** di halaman editor template dasar (`/editor/{slug}`), sistem menghasilkan URL unik (`/p/{unique_code}`).

    - Saat pengunjung membuka kembali `/p/{unique_code}`, mengeditnya lagi, dan menekan **Save**, sistem **menimpa/memperbarui (overwrite)** data pada `unique_code` tersebut (tidak membuat link baru).

4. **Alur Pemesanan Langsung ke WhatsApp:**

    - Tombol **"Pesan Sekarang"** akan otomatis menyimpan perubahan terakhir terlebih dahulu (jika belum disimpan), lalu mengarahkan pengunjung langsung ke WhatsApp Admin (`https://wa.me/...`) dengan teks pesan otomatis yang menyertakan Nama Template dan Link Preview Khusus milik klien.

5. **Auto-Cleanup 30 Hari (Hemat Storage):**

    - Setiap kali URL `/p/{unique_code}` dibuka atau disimpan ulang, kolom `last_accessed_at` diperbarui ke waktu saat itu.

    - Jika sebuah link preview tidak dibuka/diakses dan tidak dikunci Admin selama **30 hari**, sistem otomatis menghapus data preview tersebut beserta seluruh file gambar yang diunggah klien dari storage server.

6. **Mekanisme Upload Template Manual ala WordPress:**

    - Admin membuat template dasar secara manual (HTML, CSS, JS, Assets), membungkusnya ke dalam file `.zip`, lalu mengunggahnya lewat Dashboard Admin. Setelah diunggah, template otomatis tampil di katalog dan siap diedit pengunjung.

7. **Auto-Export ke WordPress Theme (`.zip`) Khusus Admin:**

    - Admin dapat memasukkan URL preview klien (`/p/{unique_code}`) atau memilih langsung dari daftar Preview Klien / Template Dasar di Dashboard Admin, lalu menekan tombol **"Export to WordPress Theme (.zip)"**.

    - Sistem akan mengonversi template beserta seluruh hasil editan klien (teks, warna, logo, gambar yang di-upload, dan halaman multi-page) menjadi paket instalasi **WordPress Theme (`.zip`)** yang valid dan siap dipasang melalui menu `Appearance -> Themes -> Add New -> Upload Theme` di WordPress.

## 2. Tech Stack & Libraries

- **Backend Framework:** Laravel 13.x (PHP 8.4+; lingkungan lokal memakai PHP 8.5) + Laravel Fortify (autentikasi) + Laravel Wayfinder (route TypeScript)

- **Frontend Framework:** React 19 (via Inertia.js v3) + TypeScript (TSX)

- **Styling (Main App, Admin Panel & Editor UI):** **Tailwind CSS v4** + shadcn/ui (Radix) + Lucide Icons, mengikuti tema brand (lihat bagian _Brand & Design System_)

- **State Management (Editor):** Zustand (mengelola state `custom_data` lintas halaman dan status `isDirty`)

- **Database:** MySQL / PostgreSQL

- **File, DOM & Image Handling:**

    - PHP `ZipArchive` (ekstraksi template `.zip` & pembuatan paket `.zip` WordPress Theme)

    - PHP `DOMDocument` & `DOMXPath` (manipulasi HTML saat injeksi preview & kompilasi tema WordPress)

    - `intervention/image` (kompresi gambar upload pengunjung ke `.webp`)

- **Security:** `dompurify` (Frontend XSS Sanitizer) & HTML Purifier / `strip_tags` (Backend)

### Brand & Design System

Identitas visual mengikuti brand `astro-manifestasi` (ungu/violet), tetapi dengan **background putih** (versi terang).

| Token                 | Nilai     | Keterangan                                    |
| --------------------- | --------- | --------------------------------------------- |
| `--primary` / `brand` | `#9275f7` | Warna utama brand (tombol, link, aksen, ring) |
| `--background`        | `#ffffff` | Background utama (putih)                      |
| `--foreground`        | `#1c1e1e` | Teks utama                                    |
| `--muted`             | `#f0f0f0` | Permukaan netral                              |
| `--secondary/accent`  | `#f1edff` | Violet lembut (hover, badge)                  |
| Dark mode (opsional)  | `#1c1e1e` | Background gelap ala astro-manifestasi        |

- Font: **Plus Jakarta Sans** (body) dan **Poppins** (heading), dimuat lewat `bunny()` di `vite.config.ts`.
- Mode tampilan default adalah **terang**; dark mode tetap bisa dipilih di Settings → Appearance.
- Seluruh token didefinisikan sebagai CSS variable di `resources/css/app.css` sehingga komponen shadcn otomatis ikut.

## 3. Core Architecture: ZIP Template & Live Editor Engine

Agar template statis yang dibuat manual dapat diunggah via `.zip`, diedit secara dinamis di dalam React (baik 1 halaman maupun multi-halaman), dan diekspor ke WordPress, sistem menggunakan **Iframe Bridge Architecture** dan **DOM Compiler**.

### A. Struktur File `.zip` Template Dasar (Input dari Admin)

Setiap template yang diunggah Admin ke katalog wajib memiliki struktur berikut di dalam file `.zip`:

```
my-template.zip
├── manifest.json       # Wajib: Konfigurasi halaman, variabel warna, dan metadata
├── thumbnail.jpg       # Wajib: Gambar preview untuk katalog (rasio 16:10 atau 16:9)
├── index.html          # Wajib: Halaman utama (Home / Landing Page)
├── about.html          # Opsional: Halaman tambahan (jika multi-page)
├── services.html       # Opsional: Halaman tambahan (jika multi-page)
├── contact.html        # Opsional: Halaman tambahan (jika multi-page)
└── assets/             # Folder CSS, JS, Font, dan Gambar bawaan template
    ├── css/
    ├── js/
    └── img/

```

### B. Spesifikasi `manifest.json`

File `manifest.json` memberi tahu sistem apakah template tersebut _single-page_ atau _multi-page_, serta daftar warna yang bisa dikustomisasi oleh klien:

```
{
  "name": "ConstructPro - Company Profile Konstruksi",
  "type": "multi-page",
  "pages": [
    { "slug": "home", "title": "Beranda", "file": "index.html" },
    { "slug": "about", "title": "Tentang Kami", "file": "about.html" },
    { "slug": "services", "title": "Layanan", "file": "services.html" },
    { "slug": "contact", "title": "Kontak", "file": "contact.html" }
  ],
  "colors": [
    { "variable": "--color-primary", "label": "Warna Utama", "default": "#2563eb" },
    { "variable": "--color-secondary", "label": "Warna Sekunder", "default": "#0f172a" },
    { "variable": "--color-accent", "label": "Warna Aksen", "default": "#f59e0b" },
    { "variable": "--color-bg", "label": "Warna Background", "default": "#ffffff" }
  ]
}

```

### C. Atribut HTML untuk Elemen yang Bisa Diedit (`data-edit-*`)

Saat Admin membuat file HTML template, Admin menambahkan atribut berikut pada elemen yang diizinkan untuk diedit pengunjung:

1. **Elemen Spesifik Halaman (Default):**

    - **Teks:** `<h1 data-edit-id="hero-title" data-edit-type="text">Membangun Masa Depan</h1>`

    - **Gambar/Foto:** `<img data-edit-id="hero-banner" data-edit-type="image" src="assets/img/banner.jpg" />`

    - **Tombol/Link:** `<a data-edit-id="hero-cta" data-edit-type="link" href="#contact">Hubungi Kami</a>`

2. **Elemen Global (Berlaku di Semua Halaman Multi-Page, misal Logo & Footer):**

    - Tambahkan atribut `data-edit-scope="global"`:

    - `<img data-edit-id="site-logo" data-edit-type="image" data-edit-scope="global" src="assets/img/logo.png" />`

    - `<span data-edit-id="footer-copyright" data-edit-type="text" data-edit-scope="global">© 2026 PT Sinar Abadi</span>`

3. **Variabel Warna CSS:**

    - Di file CSS template (atau Tailwind CDN/Custom CSS), gunakan variabel CSS yang didefinisikan pada `manifest.json`:

    ```
    :root {
      --color-primary: #2563eb;
      --color-secondary: #0f172a;
      --color-accent: #f59e0b;
      --color-bg: #ffffff;
    }

    ```

### D. Cara Kerja `editor-bridge.js` & Navigasi Multi-Halaman

1. Saat halaman template dimuat di dalam `<iframe>`, backend Laravel otomatis menyuntikkan `<base href="/storage/templates/{slug}/">` dan `<script src="/js/editor-bridge.js"></script>`.

2. **Intersepsi Link Multi-Halaman:**

    - Jika pengunjung mengklik link navigasi antar halaman di dalam iframe (misal `<a href="about.html">`), `editor-bridge.js` mencegah reload browser biasa (`e.preventDefault()`) dan mengirim pesan `postMessage({ type: 'NAVIGATE_PAGE', file: 'about.html' })` ke React Parent.

    - React Parent mengganti halaman aktif di iframe sambil tetap mempertahankan _state_ editan (`custom_data`) yang belum maupun sudah disimpan.

3. **Sinkronisasi Real-Time:**

    - Setiap kali iframe selesai memuat halaman (`home`, `about`, dll.), React Parent mengirimkan JSON `custom_data` ke iframe melalui `postMessage({ type: 'APPLY_CUSTOM_DATA', payload: customData, currentPage: 'about' })`.

    - `editor-bridge.js` langsung mengganti CSS variables, teks, gambar, dan link baik yang berskala `global` maupun spesifik halaman tersebut.

## 4. WordPress Theme Auto-Exporter Architecture (Admin Only)

Fitur ini memungkinkan Admin mengubah URL preview klien (`/p/{unique_code}`) maupun template dasar (`/editor/{slug}`) menjadi file `.zip` **WordPress Theme** yang langsung siap pakai di WordPress.

### A. Struktur Output `.zip` WordPress Theme yang Dihasilkan

Saat Admin mengekspor preview (misal dengan kode `k9Xm2pL8qR` atau nama klien), `WordPressThemeExporterService` akan membuat arsip `.zip` dengan struktur standar WordPress berikut:

```
wp-theme-{slug}-{unique_code}.zip
└── {theme-folder-name}/
    ├── style.css                 # Wajib: Header informasi tema WordPress + CSS Custom Colors (:root)
    ├── functions.php             # Wajib: Enqueue CSS/JS, Auto-Create Pages & Customizer API
    ├── screenshot.png            # Otomatis diambil dari thumbnail.jpg milik template
    ├── index.php                 # Fallback utama WordPress (memuat tampilan halaman beranda)
    ├── front-page.php            # Halaman Utama (hasil konversi dari index.html + editan klien)
    ├── page-about.php            # (Jika multi-page) Otomatis dibuat sesuai daftar pages di manifest.json
    ├── page-services.php         # (Jika multi-page) Otomatis dibuat sesuai daftar pages di manifest.json
    ├── page-contact.php          # (Jika multi-page) Otomatis dibuat sesuai daftar pages di manifest.json
    └── assets/                   # Seluruh aset asli template + gambar/logo yang di-upload klien
        ├── css/
        ├── js/
        ├── img/
        └── custom-uploads/       # Berisi file gambar/logo .webp yang diunggah klien

```

### B. Proses Kompilasi & Konversi Otomatis (`WordPressThemeExporterService`)

Ketika Admin menekan tombol **"Export WordPress Theme"**, backend Laravel melakukan 6 langkah otomatis berikut:

1. **Penyalinan Aset & Gambar Klien:**

    - Menyalin seluruh folder `assets/` dari template dasar ke direktori sementara pembuatan tema.

    - Jika yang diekspor adalah link preview klien (`/p/{unique_code}`), sistem menyalin seluruh gambar/logo yang diunggah klien dari `storage/app/public/custom-uploads/{unique_code}/` ke dalam folder `assets/custom-uploads/` di dalam tema WordPress.

2. **Injeksi Editan Klien & Integrasi WordPress Customizer (`get_theme_mod`):**

    - Sistem membaca setiap file HTML (`index.html`, `about.html`, dll.) menggunakan `DOMDocument`.

    - Setiap elemen yang memiliki `data-edit-id` akan diisi dengan nilai yang sudah diedit oleh klien dari `custom_data`.

    - **Nilai Plus (tetap bisa diedit di dalam WordPress):** Selain menampilkan hasil editan klien sebagai nilai _default_, sistem membungkus teks dan gambar tersebut menggunakan fungsi `get_theme_mod()` di file `.php` dan mendaftarkannya secara otomatis di `functions.php` (`customize_register`). Dengan begitu, setelah dipasang di WordPress, Admin atau klien masih bisa mengubah teks, warna, dan gambar langsung lewat menu **WordPress Appearance -> Customize** tanpa menyentuh kodingan!

    - Menghapus seluruh atribut internal editor (`data-edit-id`, `data-edit-type`, `data-edit-scope`, `contenteditable`) agar kode HTML di WordPress bersih dan SEO-friendly.

3. **Penulisan Ulang (Rewriting) Path Aset & Link Navigasi ke PHP WordPress:**

    - Semua atribut `src="..."` dan `href="..."` yang mengarah ke folder `assets/` otomatis diubah menjadi:
      `<?php echo esc_url( get_template_directory_uri() . '/assets/...' ); ?>`

    - Gambar yang telah diganti oleh klien (`/storage/custom-uploads/{unique_code}/file.webp`) otomatis diubah menjadi:
      `<?php echo esc_url( get_template_directory_uri() . '/assets/custom-uploads/file.webp' ); ?>`

    - Untuk template **Multi-Page**, link navigasi antar halaman (contoh: `href="index.html"`, `href="about.html"`, `href="contact.html"`) otomatis dikonversi menjadi URL halaman WordPress:

        - `index.html` $\rightarrow$ `<?php echo esc_url( home_url( '/' ) ); ?>`

        - `about.html` $\rightarrow$ `<?php echo esc_url( home_url( '/about/' ) ); ?>`

4. **Injeksi Hook Standar WordPress:**

    - Menambahkan `<meta charset="<?php bloginfo( 'charset' ); ?>">` di `<head>`.

    - Menyuntikkan `<?php wp_head(); ?>` tepat sebelum tag penutup `</head>` (beserta blok `<style>:root { ... }</style>` yang memuat warna pilihan klien).

    - Menambahkan `<?php body_class(); ?>` pada tag `<body>` dan `<?php wp_body_open(); ?>` tepat setelah `<body>`.

    - Menyuntikkan `<?php wp_footer(); ?>` tepat sebelum tag penutup `</body>`.

5. **Auto-Setup Halaman Multi-Page Saat Tema Diaktifkan (`after_switch_theme`):**

    - Di dalam `functions.php` yang di-generate, terdapat script otomatis pada hook `after_switch_theme`:

        - Saat Admin mengaktifkan tema tersebut di WordPress, tema akan otomatis membuat halaman-halaman yang ada di `manifest.json` (misal: _Beranda, Tentang Kami, Layanan, Kontak_) menggunakan `wp_insert_post()` jika halaman dengan slug tersebut belum ada.

        - Mengatur _Reading Settings_ WordPress (`show_on_front = 'page'`) secara otomatis agar halaman `home` menjadi **Static Front Page**, sehingga website WordPress langsung tampil 100% persis seperti di preview begitu tema diaktifkan!

6. **Pembuatan File `.zip` & Download:**

    - Membungkus folder tema ke dalam file `.zip` dan mengirimkannya sebagai _file download_ ke browser Admin.

## 5. Database Schema

### Tabel 1: `users`

- `id` (PK)

- `name`, `email`, `password`

- `timestamps`

### Tabel 2: `settings`

Menyimpan pengaturan global aplikasi (konfigurasi WhatsApp Admin & metadata author untuk tema WordPress).

- `id` (PK)

- `key` (string, unique) — contoh: `whatsapp_number`, `whatsapp_message_template`, `wp_theme_author`, `wp_theme_author_uri`

- `value` (text)

- `timestamps`

### Tabel 3: `categories`

- `id` (PK)

- `name` (string) — contoh: _Company Profile, Landing Page, F&B, Konstruksi_

- `slug` (string, unique)

- `timestamps`

### Tabel 4: `templates`

- `id` (PK)

- `category_id` (FK -> `categories.id`)

- `name` (string)

- `slug` (string, unique)

- `type` (enum: `'single-page'`, `'multi-page'`)

- `description` (text, nullable)

- `thumbnail_path` (string)

- `storage_path` (string) — lokasi hasil ekstrak di `storage/app/public/templates/{slug}`

- `manifest_data` (json) — isi dari `manifest.json` (daftar halaman & variabel warna default)

- `price` (decimal, nullable) — harga referensi (opsional)

- `is_active` (boolean, default: `true`)

- `timestamps`

### Tabel 5: `customized_previews`

Menyimpan hasil kustomisasi pengunjung tanpa login.

- `id` (PK)

- `template_id` (FK -> `templates.id`, `onDelete('cascade')`)

- `unique_code` (string, 10 karakter, unique, indexed) — contoh: `k9Xm2pL8qR`

- `custom_data` (json) — struktur penyimpanan perubahan:

    ```
    {
      "colors": {
        "--color-primary": "#e11d48",
        "--color-secondary": "#0f172a"
      },
      "global": {
        "texts": {
          "footer-copyright": "© 2026 PT Maju Jaya Bersama"
        },
        "images": {
          "site-logo": "/storage/custom-uploads/k9Xm2pL8qR/logo.webp"
        },
        "links": {}
      },
      "pages": {
        "home": {
          "texts": {
            "hero-title": "Solusi Konstruksi Terpercaya"
          },
          "images": {
            "hero-banner": "/storage/custom-uploads/k9Xm2pL8qR/hero.webp"
          },
          "links": {
            "hero-cta": { "text": "Hubungi Kami", "href": "about.html" }
          }
        },
        "about": {
          "texts": {
            "about-heading": "Sejarah Perusahaan Kami"
          },
          "images": {},
          "links": {}
        }
      }
    }

    ```

- `order_clicked_at` (timestamp, nullable) — terisi ketika pengunjung menekan tombol "Pesan Sekarang"

- `exported_at` (timestamp, nullable) — mencatat kapan terakhir kali preview ini diekspor ke WordPress oleh Admin

- `is_locked_by_admin` (boolean, default: `false`) — jika `true`, tidak akan dihapus oleh cronjob 30 hari

- `last_accessed_at` (timestamp, indexed) — diperbarui setiap kali link dibuka atau di-save

- `expires_at` (timestamp, indexed) — selalu di-set ke `now()->addDays(30)` setiap ada aktivitas buka/edit

- `timestamps`

## 6. Application Routes & User Flows

### A. Public / Client Routes

1. `GET /` — **Catalog & Landing Page**

    - Menampilkan layanan, filter kategori, filter tipe (_Single Page_ / _Multi Page_), dan daftar kartu template.

    - Tombol pada tiap template: **"Coba Live Edit & Preview"**.

2. `GET /editor/{template:slug}` — **Initial Live Editor (Belum Disimpan)**

    - Membuka antarmuka Visual Editor untuk template dasar.

    - Ketika pengguna klik **Save**:

        - Memanggil `POST /api/previews` untuk membuat record baru dan menghasilkan `unique_code`.

        - URL di browser otomatis berubah ke `/p/{unique_code}` (menggunakan `window.history.replaceState` atau Inertia visit) agar jika ia klik Save lagi nanti, sistem melakukan **Update**, bukan membuat link baru.

        - Menampilkan **Popup Modal Simpan** berisi URL khusus (`https://domain.com/p/{unique_code}`), informasi masa aktif 30 hari, tombol **Copy Link**, dan tombol **Pesan via WhatsApp**.

3. `GET /p/{unique_code}` — **Shared Preview & Re-Editor**

    - Memperbarui `last_accessed_at = now()` dan `expires_at = now()->addDays(30)`.

    - Menampilkan website yang sudah diedit oleh klien.

    - Di bagian atas terdapat _Floating Bar / Topbar_ yang bisa disembunyikan (_Toggle Hide Toolbar_ untuk melihat tampilan layar penuh) atau diaktifkan ke **Mode Edit**.

    - **Khusus jika Admin sedang login (`auth()->check()`):** Di Topbar halaman `/p/{unique_code}` juga muncul tombol khusus Admin **"Export ke WordPress (.zip)"** sehingga ketika Admin membuka link kiriman klien dari WhatsApp, Admin bisa langsung klik Export dari halaman itu juga!

    - Ketika pengguna melakukan perubahan dan klik **Save**:

        - Memanggil `PUT /api/previews/{unique_code}` (menimpa data pada link yang sama, **tidak membuat link baru**).

        - Menampilkan Popup Modal berisi link yang sama.

4. `GET /render-template/{template:slug}/{pageFile?}` — **Internal Iframe Renderer**

    - Route khusus sumber `<iframe>` yang membaca file HTML dari folder `storage/app/public/templates/{slug}/{pageFile}`, menyuntikkan `<base>` tag dan `editor-bridge.js`, lalu mengembalikannya sebagai response HTML.

### B. API Endpoints untuk Editor (Guest Accessible + Rate Limited)

1. `POST /api/previews` — Membuat `unique_code` baru saat pertama kali klik Save dari `/editor/{template:slug}`.

2. `PUT /api/previews/{unique_code}` — Memperbarui `custom_data` pada link yang sudah ada (`overwrite`) dan memperpanjang `expires_at` menjadi 30 hari ke depan.

3. `POST /api/previews/upload-image` — Mengunggah gambar/logo dari pengunjung, mengompresinya menjadi `.webp`, dan menyimpannya ke `storage/app/public/custom-uploads/{unique_code}/`.

4. `POST /api/previews/{unique_code}/order-click` — Mencatat `order_clicked_at = now()` dan memperpanjang `expires_at` sebelum mengarahkan pengguna ke URL WhatsApp.

### C. Alur Tombol "Pesan Sekarang" (Direct to WhatsApp)

Ketika klien menekan tombol **"Pesan Sekarang"** di Topbar Editor atau di dalam Popup Save:

1. Jika klien belum pernah menekan Save (masih di `/editor/{slug}`) atau ada perubahan yang belum disimpan (`isDirty === true`), sistem otomatis menjalankan fungsi Save (`POST` atau `PUT`) di belakang layar terlebih dahulu.

2. Setelah `unique_code` dipastikan tersimpan dengan perubahan terbaru, sistem membuka link WhatsApp:

    ```
    https://wa.me/{NOMOR_WA_ADMIN}?text=Halo%20Admin,%20saya%20tertarik%20memesan%20website%20dengan%20template%20*{NAMA_TEMPLATE}*.%0A%0ABerikut%20adalah%20link%20desain%20preview%20yang%20sudah%20saya%20sesuaikan:%0Ahttps://domain.com/p/{unique_code}

    ```

### D. Admin Routes (`middleware: ['auth']`)

1. `GET /admin/dashboard`

    - Ringkasan total template, total link preview aktif, dan daftar klien yang baru saja klik "Pesan Sekarang".

    - **Quick WordPress Exporter Box:** Tersedia kolom input di mana Admin cukup _paste_ URL dari klien (misal `https://domain.com/p/k9Xm2pL8qR` atau kode `k9Xm2pL8qR`), mengatur Nama Tema WordPress (opsional), lalu klik **"Download WordPress Theme (.zip)"**.

2. `GET /admin/templates` — Daftar template, tombol Upload Template `.zip` baru, tombol **Export Template Dasar ke WordPress**, tombol Hapus Template, dan toggle Aktif/Nonaktif.

3. `POST /admin/templates` — Proses upload `.zip`, validasi keamanan isi zip, ekstraksi, dan pembacaan `manifest.json`.

4. `DELETE /admin/templates/{id}` — Menghapus template beserta folder fisiknya di storage.

5. `GET /admin/previews` — Daftar seluruh link kustomisasi pengunjung, dilengkapi:

    - Tombol **"Export ke WordPress (.zip)"** pada setiap baris preview.

    - Tombol **Lock/Pin** agar preview klien yang sedang diproses tidak ikut terhapus otomatis setelah 30 hari.

    - Tombol buka preview & hapus manual.

6. `POST /admin/export/wordpress` — Endpoint eksekusi pembuatan `.zip` WordPress Theme:

    - Menerima input `preview_code_or_url` (untuk mengekspor hasil editan klien) ATAU `template_id` (untuk mengekspor template dasar tanpa editan), serta parameter opsional `theme_name` (misal: `"PT Maju Jaya Theme"`).

    - Memanggil `WordPressThemeExporterService` dan mengembalikan response `BinaryFileResponse` (`response()->download(...)->deleteFileAfterSend(true)`).

7. `GET /admin/settings` & `PUT /admin/settings` — Pengaturan Nomor WhatsApp Admin, format teks pesan order WhatsApp, dan Nama Author default untuk tema WordPress yang diekspor.

## 7. Auto-Cleanup Mechanism (30 Days Expiration)

Untuk memastikan server tidak penuh oleh gambar dan data preview yang ditinggalkan pengunjung:

1. **Perpanjangan Otomatis Saat Dibuka/Diedit:**

    - Setiap request ke `GET /p/{unique_code}` atau `PUT /api/previews/{unique_code}` wajib menjalankan:

        ```
        $preview->update([
            'last_accessed_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        ```

2. **Laravel Scheduled Command (`app/Console/Commands/CleanupExpiredPreviews.php`):**

    - Command signature: `php artisan previews:cleanup`

    - Dijadwalkan berjalan setiap hari (`Schedule::command('previews:cleanup')->daily()`) di `routes/console.php`.

    - Logika pembersihan:

        - Cari semua record di `customized_previews` dengan kondisi:
          `expires_at < now()` DAN `is_locked_by_admin = false`.

        - Untuk setiap record yang ditemukan:

            1. Hapus direktori `storage/app/public/custom-uploads/{unique_code}` beserta seluruh gambar di dalamnya.

            2. Hapus baris data dari tabel `customized_previews`.

        - Bersihkan juga file `.zip` hasil export sementara di `storage/app/temp-exports/` yang berumur lebih dari 1 jam.

## 8. Security & Validation Rules

1. **Proteksi Fitur Export WordPress:**

    - Seluruh route `/admin/export/wordpress` wajib berada di dalam middleware `auth` (hanya Admin yang bisa mengakses dan mengunduh file `.zip` tema WordPress).

2. **Validasi Ketat Upload `.zip` (Admin):**

    - Sebelum mengekstrak `.zip`, periksa seluruh daftar nama file di dalam arsip menggunakan `ZipArchive`.

    - **Tolak ekstraksi** jika ditemukan ekstensi berbahaya seperti `.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.bat`, `.env`, atau `.htaccess`, maupun _path traversal_ (`../`).

    - Hanya izinkan: `.html`, `.htm`, `.css`, `.js`, `.json`, `.txt`, `.md`, gambar (`.jpg`, `.jpeg`, `.png`, `.webp`, `.gif`, `.svg`, `.ico`), dan font (`.woff`, `.woff2`, `.ttf`, `.eot`).

3. **Validasi Upload Gambar Pengunjung (Guest):**

    - Maksimal ukuran file: `2 MB`.

    - Mimes yang diizinkan: `jpeg, png, webp, svg`.

    - Rate limiting: Maksimal `20 uploads per menit` per IP address.

4. **Sanitasi XSS pada `custom_data` & Export PHP:**

    - Semua teks yang diedit pengunjung wajib dibersihkan dari tag `<script>`, `<iframe>`, `onload`, dan `onerror`.

    - Saat mengekspor ke file `.php` tema WordPress, gunakan `addslashes()` / `var_export()` pada string PHP dan `wp_kses_post()` atau `esc_html()` / `esc_url()` agar tidak ada celah _PHP Code Injection_ dari input pengunjung.

## 9. Step-by-Step Implementation Plan for AI Agent

Ikuti urutan fase ini saat menulis kode:

### Phase 1: Foundation, Database & Models

1. Setup project Laravel 13 + Inertia.js v3 (React TSX) + Laravel Fortify + **Tailwind CSS v4**.

2. Buat migration dan model untuk: `Setting`, `Category`, `Template`, dan `CustomizedPreview`.

3. Buat Database Seeder untuk akun Admin default, pengaturan nomor WhatsApp & Author WordPress default, serta kategori awal (_Company Profile, Landing Page, F&B, Konstruksi_).

### Phase 2: Admin Panel & ZIP Template Management

1. Bangun layout Admin Dashboard menggunakan React + Tailwind CSS (Sidebar: Dashboard, Templates, Client Previews, WP Exporter, Settings).

2. Buat `TemplateArchiveService` untuk memvalidasi isi `.zip`, membaca `manifest.json`, mengekstrak file ke `storage/app/public/templates/{slug}`, dan menyimpan metadata ke tabel `templates`.

3. Buat halaman CRUD Template (Upload `.zip`, Lihat daftar halaman & warna yang terdeteksi dari `manifest.json`, Toggle status aktif, Hapus template).

4. Buat halaman Pengaturan WhatsApp & WordPress Theme Author (`/admin/settings`).

### Phase 3: Sample Templates Creation (Single-Page & Multi-Page)

1. Buat 2 contoh template siap pakai di dalam folder `resources/sample-templates/` (dan sediakan tombol "Seed Sample Templates"):

    - **Template 1 (Single-Page Landing Page):** Halaman jasa/produk modern dengan variabel warna CSS dan atribut `data-edit-*`.

    - **Template 2 (Multi-Page Company Profile):** Memiliki `index.html`, `about.html`, `services.html`, dan `contact.html` dengan logo & footer beratribut `data-edit-scope="global"`.

### Phase 4: Iframe Renderer & `editor-bridge.js`

1. Buat controller `TemplateRenderController` untuk menyajikan halaman HTML template dengan injeksi `<base>` tag dan script `/js/editor-bridge.js`.

2. Tulis `/public/js/editor-bridge.js` yang menangani:

    - Highlighting elemen `data-edit-id` saat di-hover pada **Mode Edit**.

    - Inline text editing (`contenteditable`) saat teks diklik.

    - Pemicu modal ganti gambar & edit tombol/link saat elemen gambar/link diklik.

    - Intersepsi klik link navigasi antar halaman (`about.html`, dll.) agar terhubung dengan _Page Switcher_ React.

    - Penerapan `custom_data` (warna CSS `:root`, elemen `global`, dan elemen per `page`).

### Phase 5: React Live Customizer UI & Save/WhatsApp Flow

1. Bangun halaman Editor dengan Tailwind CSS (`resources/js/pages/editor/workspace.tsx`) yang digunakan baik oleh `/editor/{slug}` maupun `/p/{unique_code}`.

2. Buat Topbar Editor dengan fitur:

    - **Page Switcher Dropdown** (otomatis muncul jika template bertipe `multi-page`).

    - **Device Viewport Switcher** (Desktop 100%, Tablet 768px, Mobile 375px).

    - **Color Palette Popover** (membaca daftar variabel warna dari `manifest_data`).

    - **Mode Toggle** (Mode Edit vs Mode Preview Bersih).

    - **Tombol Save** (memanggil `POST` jika belum punya `unique_code`, atau `PUT` untuk _overwrite_ jika sudah punya `unique_code`, lalu memunculkan Modal Link Unik).

    - **Tombol Pesan Sekarang (WhatsApp)** (auto-save + redirect ke `wa.me` membawa URL `/p/{unique_code}`).

    - **Tombol Admin Export WP** (hanya tampil jika Admin sedang login saat membuka halaman preview).

### Phase 6: WordPress Theme Auto-Exporter (`WordPressThemeExporterService`)

1. Buat service `app/Services/WordPressThemeExporterService.php` yang bertugas:

    - Menerima objek `CustomizedPreview` (atau `Template` dasar) beserta nama tema opsional.

    - Membuat direktori build sementara di `storage/app/temp-exports/{build_id}/`.

    - Men-generate `style.css` lengkap dengan header WordPress Theme dan variabel warna CSS `:root` yang sudah disesuaikan klien.

    - Men-generate `functions.php` yang memuat:

        - `after_switch_theme` hook untuk otomatis membuat halaman WordPress (_Home, About, Services, Contact_) dan menyetel Static Front Page.

        - `customize_register` hook agar semua variabel warna, teks, dan gambar tetap bisa diedit melalui menu _Appearance -> Customize_ di WordPress.

    - Mengonversi setiap file `.html` (`index.html` $\rightarrow$ `front-page.php` & `index.php`, `about.html` $\rightarrow$ `page-about.php`, dst.) menggunakan `DOMDocument`:

        - Menerapkan perubahan teks, gambar, dan link dari `custom_data` serta membungkusnya dengan `get_theme_mod()`.

        - Mengubah seluruh path `assets/...` dan gambar upload klien menjadi `<?php echo esc_url( get_template_directory_uri() . '/...' ); ?>`.

        - Mengubah link antar halaman `.html` menjadi `<?php echo esc_url( home_url( '/...' ) ); ?>`.

        - Menyuntikkan `wp_head()`, `body_class()`, `wp_body_open()`, dan `wp_footer()`.

    - Menyalin semua aset template dan gambar upload klien ke folder `assets/` tema, lalu mengompresinya menjadi file `.zip` siap unduh.

2. Buat controller `Admin\WordPressExportController` dan integrasikan tombol Export di halaman Dashboard Admin (input URL langsung), halaman Daftar Preview Klien, dan halaman Daftar Template.

### Phase 7: Public Catalog & 30-Day Auto-Cleanup

1. Bangun halaman depan Katalog (`resources/js/pages/catalog/index.tsx`) menggunakan Tailwind CSS dengan pencarian, filter kategori, dan badge penanda _Single-Page_ / _Multi-Page_.

2. Buat Console Command `previews:cleanup` untuk menghapus otomatis preview dan folder gambar yang melewati batas 30 hari (`expires_at < now()`), lalu daftarkan ke scheduler harian Laravel.
