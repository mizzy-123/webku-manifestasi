# Sprint Execution Plan: Web Template Catalog, Live Customizer & WordPress Theme Exporter

> **Instruksi untuk AI Agent:** Dokumen ini adalah panduan eksekusi bertahap (_Sprint-by-Sprint_) yang berpasangan dengan `docs/ai_agent_project_plan.md`. **Kerjakan fitur secara berurutan mulai dari Sprint 1 hingga Sprint 6.** Jangan melompat ke sprint berikutnya sebelum seluruh _Definition of Done (DoD)_ dan _Bug Prevention Checklist_ pada sprint yang sedang berjalan selesai dan teruji tanpa _error_.

---

## 🛡️ Aturan Kerja AI Agent (Zero-Bug Workflow Protocol)

1. **Satu Sprint Satu Waktu:** Fokus hanya pada ruang lingkup (_scope_) sprint yang diminta oleh pengguna.
2. **Dilarang Membuat Placeholder Kosong untuk Fitur Sprint Saat Ini:** Setiap kode yang ditulis dalam sprint aktif harus berfungsi penuh (_working code_).
3. **Verifikasi Mandiri Sebelum Lapor Selesai:**
    - Jalankan `php artisan route:list` untuk memastikan tidak ada _controller_ atau _method_ yang salah ketik.
    - Jalankan `npm run build` untuk memastikan tidak ada _syntax error_ atau _missing import_ pada komponen React/Inertia dan Tailwind CSS.
4. **Konsistensi Stack:** Gunakan **Laravel 13.x** (PHP 8.4+), **Laravel Fortify**, **Inertia.js v3 (React + TypeScript/TSX)**, dan **Tailwind CSS v4**.

---

## 📋 Peta Jalan Sprint (Sprint Roadmap)

- **Sprint 1:** Foundation, Database Migrations, Models, Seeders & Admin Auth
- **Sprint 2:** Admin Panel, Settings & Secure ZIP Template Engine (Single & Multi-Page)
- **Sprint 3:** Public Catalog UI & Iframe Template Renderer Engine
- **Sprint 4:** `editor-bridge.js` & React Live Customizer Workspace (Visual Editing)
- **Sprint 5:** Save/Overwrite URL (`/p/{unique_code}`), WebP Image Upload & Direct WhatsApp Order
- **Sprint 6:** WordPress Theme Auto-Exporter (`.zip`) & 30-Day Auto-Cleanup Scheduler

---

## 🚀 SPRINT 1: Foundation, Database Migrations, Models, Seeders & Admin Auth

**Tujuan Utama:** Menyiapkan fondasi proyek Laravel 13 + Inertia v3 React (TypeScript) + Tailwind CSS v4 + Fortify, skema database lengkap, relasi antar model, seeder data awal, serta autentikasi Admin.

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `database/migrations/xxxx_create_settings_table.php`
- `database/migrations/xxxx_create_categories_table.php`
- `database/migrations/xxxx_create_templates_table.php`
- `database/migrations/xxxx_create_customized_previews_table.php`
- `app/Models/Setting.php`
- `app/Models/Category.php`
- `app/Models/Template.php`
- `app/Models/CustomizedPreview.php`
- `database/seeders/DatabaseSeeder.php`
- `database/factories/{Setting,Category,Template,CustomizedPreview}Factory.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `resources/js/pages/admin/dashboard.tsx`
- `routes/web.php`, `config/fortify.php`, `resources/css/app.css` (tema brand)

### 2. Rincian Tugas (Task List)

1. **Inisialisasi & Package Installation:**
    - Starter kit **Laravel React (Inertia v3 + Fortify + Tailwind v4 + Wayfinder)** sudah terpasang; tidak memakai Breeze.
    - Instal package frontend tambahan: `npm install zustand dompurify` (`lucide-react`, `clsx`, `tailwind-merge` sudah ada di `package.json`).
    - Instal package backend: `composer require intervention/image` (terpasang v4.x).
    - Jalankan `php artisan storage:link` agar folder `storage/app/public` dapat diakses publik.
2. **Pembuatan Migration & Model:**
    - **`Setting`:** Kolom `key` (unique), `value` (text, nullable). Sediakan helper statis `Setting::getValue($key, $default)` dan `Setting::setValue($key, $value)`.
    - **`Category`:** Kolom `name`, `slug` (unique). Relasi `hasMany(Template::class)`.
    - **`Template`:** Kolom `category_id` (constrained), `name`, `slug` (unique), `type` (`single-page` / `multi-page`), `description` (nullable), `thumbnail_path`, `storage_path`, `manifest_data` (json), `price` (decimal 12,2 nullable), `is_active` (boolean default true). Cast `manifest_data` sebagai `'array'` dan `is_active` sebagai `'boolean'`.
    - **`CustomizedPreview`:** Kolom `template_id` (constrained cascadeOnDelete), `unique_code` (string 10, unique, index), `custom_data` (json), `order_clicked_at` (timestamp nullable), `exported_at` (timestamp nullable), `is_locked_by_admin` (boolean default false), `last_accessed_at` (timestamp indexed), `expires_at` (timestamp indexed). Cast `custom_data` sebagai `'array'`.
3. **Database Seeder:**
    - Buat akun Admin default: `admin@admin.com` / password: `password`.
    - Buat default `settings`:
        - `whatsapp_number`: `'6281234567890'`
        - `whatsapp_message_template`: `'Halo Admin, saya tertarik memesan website dengan template *{template_name}*. Berikut link desain yang sudah saya sesuaikan: {preview_url}'`
        - `wp_theme_author`: `'WebCatalog Studio'`
        - `wp_theme_author_uri`: `'https://example.com'`
    - Buat default `categories`: _Landing Page_, _Company Profile_, _F&B & Restoran_, _Konstruksi & Properti_, _Jasa & Konsultan_.
4. **Autentikasi Admin (Fortify):**
    - Semua user yang login dianggap Admin, maka **registrasi publik dimatikan** (`Features::registration()` dihapus dari `config/fortify.php`).
    - `home` Fortify diarahkan ke `/admin/dashboard` (route bernama `dashboard`, middleware `auth` + `verified`).
    - Halaman `/admin/dashboard` menampilkan ringkasan nyata (total template, kategori, preview aktif, klik order).
5. **Tema Brand:** terapkan palet di `resources/css/app.css` sesuai bagian _Brand & Design System_ di `docs/ai_agent_project_plan.md`.

### 3. Pencegahan Bug (Bug Prevention Checklist)

- Pastikan `manifest_data` pada model `Template` dan `custom_data` pada model `CustomizedPreview` sudah di-cast ke `'array'` di dalam method `casts()` Laravel 13 agar tidak terjadi _double JSON encoding_.
- Pastikan `unique_code` memiliki index database untuk pencarian cepat.

### 4. Definition of Done (DoD)

- [x] Perintah `php artisan migrate:fresh --seed` berjalan sukses tanpa error.
- [x] Login Admin di `/login` berhasil dan mengarah ke `/admin/dashboard`.
- [x] `npm run build` berjalan sukses tanpa error kompilasi Tailwind/React (`npm run types:check` juga bersih).
- [x] Test Pest (`tests/Feature/Admin`, `tests/Feature/Models`) lulus.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 1):

> "Baca file `docs/ai_agent_project_plan.md` dan `docs/sprint_execution_plan.md`. Sekarang kerjakan **SPRINT 1** secara lengkap. Buat seluruh migration, model dengan relasi dan JSON casting yang tepat, helper pada model Setting, DatabaseSeeder, serta pastikan konfigurasi Inertia React dan Tailwind CSS berjalan tanpa error. Jangan kerjakan Sprint 2 dulu sebelum Sprint 1 terverifikasi."

---

## 🚀 SPRINT 2: Admin Panel, Settings & Secure ZIP Template Engine

**Tujuan Utama:** Membangun Dashboard Admin berbasis Tailwind CSS, sistem upload & ekstraksi template `.zip` yang aman (mendukung _Single-Page_ & _Multi-Page_), serta generator 2 contoh template siap pakai (_Sample Templates_).

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `app/Services/TemplateArchiveService.php`
- `app/Services/SampleTemplateGeneratorService.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/TemplateController.php`
- `app/Http/Controllers/Admin/SettingController.php`
- `resources/js/components/app-sidebar.tsx` (modifikasi: menu admin)
- `resources/js/pages/admin/dashboard.tsx`
- `resources/js/pages/admin/templates/index.tsx`
- `resources/js/pages/admin/settings/edit.tsx`

### 2. Rincian Tugas (Task List)

1. **Admin Layout (sidebar `app-sidebar.tsx`):** _(layout sidebar bawaan `layouts/app-layout.tsx` + `components/app-sidebar.tsx` sudah ada dari Sprint 1; extend, jangan buat dari nol)_
    - Buat sidebar responsif dengan Tailwind CSS berisi menu: _Dashboard_, _Kelola Template_, _Pengaturan_, dan tombol _Lihat Katalog Publik_. Menu _Preview Klien_ ditambahkan di Sprint 5 saat halamannya tersedia.
2. **Service Upload & Validasi ZIP (`TemplateArchiveService.php`):**
    - Terima file `.zip`, buka dengan `ZipArchive`.
    - **Keamanan Wajib:** Loop seluruh entry di dalam ZIP. Tolak dan batalkan jika ada nama file yang mengandung `../` atau berekstensi terlarang (`.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.bat`, `.env`, `.htaccess`).
    - Pastikan di dalam root ZIP (atau 1 level sub-folder) terdapat `manifest.json`, `index.html`, dan `thumbnail.jpg` (atau `.png`/`.webp`).
    - Parse `manifest.json`, validasi struktur JSON-nya (`name`, `type`, `pages`, `colors`).
    - Ekstrak seluruh file ke `storage/app/public/templates/{slug}/`.
    - Simpan record ke tabel `templates`. Saat template dihapus, hapus juga seluruh folder fisiknya di `storage/app/public/templates/{slug}/`.
3. **Sample Template Generator (`SampleTemplateGeneratorService.php`):**
    - Sediakan tombol **"Generate 2 Sample Templates"** di halaman Admin Template agar sistem bisa langsung diuji tanpa harus membuat file `.zip` manual terlebih dahulu:
        1. **Sample 1 (Single-Page Landing Page):** _SaaS / Agency Landing Page_ (`index.html`, `manifest.json`, `thumbnail.jpg`, `assets/css/style.css`) lengkap dengan atribut `data-edit-id`, `data-edit-type`, dan CSS variables `:root`.
        2. **Sample 2 (Multi-Page Company Profile):** _ConstructPro Company Profile_ (`index.html`, `about.html`, `services.html`, `contact.html`, `manifest.json`, `thumbnail.jpg`, `assets/css/style.css`) lengkap dengan elemen global (`data-edit-scope="global"` pada logo navbar dan teks footer) serta navigasi antar halaman.
4. **Halaman Pengaturan Admin (`/admin/settings`):**
    - Form untuk memperbarui nomor WhatsApp Admin, template pesan WhatsApp, dan nama Author untuk tema WordPress.

### 3. Pencegahan Bug (Bug Prevention Checklist)

- **Masalah Sub-Folder saat Compress ZIP:** Sering kali ketika Admin melakukan _compress_ folder di Windows/Mac, file di dalam `.zip` berada di dalam sub-folder `nama-folder/manifest.json` bukan langsung di root `manifest.json`. `TemplateArchiveService` wajib mendeteksi hal ini secara otomatis dan mengekstrak isinya langsung ke `storage/app/public/templates/{slug}/` (tanpa sub-folder ganda).
- Pastikan setiap file HTML di dalam `manifest.json` (`pages[].file`) benar-benar ada di dalam ZIP sebelum menyimpan ke database.

### 4. Definition of Done (DoD)

- [x] Admin dapat klik "Generate 2 Sample Templates" dan 2 template (1 Single-Page, 1 Multi-Page) langsung muncul di daftar template beserta thumbnail-nya.
- [x] Admin dapat mengunggah file `.zip` template baru, menonaktifkan/mengaktifkan template, dan menghapus template beserta file fisiknya.
- [x] Admin dapat mengubah nomor WhatsApp di menu Pengaturan.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 2):

> "Sekarang kerjakan **SPRINT 2** sesuai `docs/sprint_execution_plan.md` dan `docs/ai_agent_project_plan.md`. Buat `admin-layout.tsx`, halaman Dashboard, CRUD Template dengan `TemplateArchiveService` (termasuk penanganan sub-folder di dalam ZIP dan validasi keamanan), `SampleTemplateGeneratorService` untuk membuat 2 sample template (Single-Page & Multi-Page lengkap dengan `manifest.json` dan `data-edit-*`), serta halaman Pengaturan WhatsApp/WP Author."

---

## 🚀 SPRINT 3: Public Catalog UI & Iframe Template Renderer Engine

**Tujuan Utama:** Membangun halaman depan Katalog Publik yang menarik bagi calon klien, serta membuat mesin perender HTML template (`TemplateRenderController`) yang menyuntikkan `<base>` tag dan script jembatan editor ke dalam `<iframe>`.

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `app/Http/Controllers/CatalogController.php`
- `app/Http/Controllers/TemplateRenderController.php`
- `resources/js/pages/catalog/index.tsx`

### 2. Rincian Tugas (Task List)

1. **Halaman Katalog Publik (`GET /` -> `catalog/index.tsx`):**
    - Hero section modern dengan penjelasan cara kerja (1. Pilih Template -> 2. Edit Sesuka Hati Tanpa Login -> 3. Simpan Link & Pesan via WhatsApp).
    - Filter berdasarkan Kategori, Filter berdasarkan Tipe (_Semua_, _Single-Page_, _Multi-Page_), dan kolom Pencarian.
    - Grid kartu template yang menampilkan Thumbnail, Badge Tipe (_Single-Page_ / _Multi-Page_ beserta jumlah halaman), Kategori, Nama Template, dan tombol CTA **"Live Preview & Edit Sekarang"** yang mengarah ke `/editor/{template:slug}`.
2. **Iframe Renderer (`GET /render-template/{template:slug}/{pageFile?}`):**
    - Buat `TemplateRenderController@show`.
    - Parameter `{pageFile?}` default ke `'index.html'`. Validasi agar `pageFile` hanya boleh mengakses daftar halaman yang terdaftar di `manifest_data['pages']` (mencegah _Directory Traversal_).
    - Baca isi file HTML dari `storage/app/public/templates/{slug}/{pageFile}`.
    - Suntikkan tepat setelah tag `<head>`:
        ```html
        <base href="{asset('storage/templates/' . $template->slug . '/')}" />
        ```
    - Suntikkan tepat sebelum tag `</body>`:
        ```html
        <script src="{asset('js/editor-bridge.js')}"></script>
        ```
    - Kembalikan sebagai response dengan header `Content-Type: text/html; charset=UTF-8`.

### 3. Pencegahan Bug (Bug Prevention Checklist)

- **Path CSS/JS/Gambar Rusak di dalam Iframe:** Pastikan `<base href="...">` selalu diakhiri dengan garis miring `/` (trailing slash), yaitu `/storage/templates/{slug}/`, agar seluruh path relatif seperti `assets/css/style.css` atau `assets/img/logo.png` di dalam template otomatis mengarah ke folder storage template yang benar.
- Hindari menggunakan `DOMDocument::loadHTML` yang merusak tag HTML5 pada saat render iframe; gunakan _regex/string injection_ yang presisi pada `<head>` dan `</body>` agar struktur asli HTML5 template tetap 100% utuh.

### 4. Definition of Done (DoD)

- [x] Halaman utama `/` menampilkan katalog template dengan filter kategori, filter tipe halaman, dan pencarian yang berfungsi mulus.
- [x] Membuka `/render-template/{slug}/index.html` dan `/render-template/{slug}/about.html` di browser menampilkan halaman template dengan seluruh CSS dan gambar yang termuat sempurna.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 3):

> "Sekarang kerjakan **SPRINT 3** sesuai `docs/sprint_execution_plan.md`. Buat `CatalogController` dan halaman `catalog/index.tsx` menggunakan Tailwind CSS dengan fitur pencarian, filter kategori, dan filter tipe template. Buat juga `TemplateRenderController` untuk menyajikan halaman HTML template dengan injeksi `<base href>` (pastikan diakhiri trailing slash) dan `<script src='/js/editor-bridge.js'>`."

---

## 🚀 SPRINT 4: `editor-bridge.js` & React Live Customizer Workspace

**Tujuan Utama:** Membangun fitur inti _Live Visual Editor_ tanpa login. Pengunjung bisa mengedit teks secara langsung (_inline_), mengganti warna website lewat _color picker_, mengubah gambar/logo, mengedit teks & tujuan tombol/link, serta berpindah halaman pada template _multi-page_ tanpa kehilangan perubahan.

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `public/js/editor-bridge.js`
- `resources/js/stores/use-editor-store.ts`
- `app/Http/Controllers/EditorController.php`
- `resources/js/pages/editor/workspace.tsx`
- `resources/js/components/editor/topbar.tsx`
- `resources/js/components/editor/color-palette-popover.tsx`
- `resources/js/components/editor/image-edit-modal.tsx`
- `resources/js/components/editor/link-edit-modal.tsx`

### 2. Rincian Tugas (Task List)

1. **Zustand State Store (`use-editor-store.ts`):**
    - Menyimpan struktur `customData`:
        - `colors`: `{ "--color-primary": "#...", ... }`
        - `global`: `{ texts: {}, images: {}, links: {} }`
        - `pages`: `{ home: { texts: {}, images: {}, links: {} }, about: { ... } }`
    - Menyimpan state UI: `currentPageSlug` (default `'home'`), `isEditMode` (boolean, default `true`), `viewport` (`'desktop'` | `'tablet'` | `'mobile'`), `isDirty` (boolean penanda ada editan yang belum di-save), `activeElement` (data elemen gambar/link yang sedang diklik untuk diedit di modal).
2. **Script Jembatan Iframe (`public/js/editor-bridge.js`):**
    - Menyuntikkan CSS internal untuk efek _outline_ biru putus-putus dan _badge_ kecil (`Edit Teks`, `Ganti Gambar`, `Edit Tombol`) saat elemen `[data-edit-id]` di-hover pada `isEditMode = true`.
    - **Inline Text Editing:** Untuk elemen `[data-edit-type="text"]`, aktifkan `contentEditable = "true"` saat `isEditMode = true`. Saat teks berubah (`input` / `blur`), kirim pesan ke React Parent:
        ```javascript
        window.parent.postMessage(
            {
                type: 'TEXT_UPDATED',
                id: el.dataset.editId,
                scope: el.dataset.editScope || 'page',
                value: el.innerHTML,
            },
            '*',
        );
        ```
    - **Klik Gambar & Link:** Saat elemen `[data-edit-type="image"]` atau `[data-edit-type="link"]` diklik dalam Mode Edit, cegah aksi default (`e.preventDefault()`) dan kirim `postMessage` (`IMAGE_CLICKED` atau `LINK_CLICKED`) beserta atribut saat ini ke React Parent agar Modal muncul.
    - **Intersepsi Navigasi Multi-Page:** Saat pengguna mengklik tag `<a href="...">` yang mengarah ke file `.html` lokal (misal `about.html`, `services.html`), cegah reload default dan kirim `postMessage({ type: 'NAVIGATE_PAGE', file: href })` ke React Parent.
    - **Penerima State (`APPLY_CUSTOM_DATA` & `SET_EDIT_MODE`):**
        - Saat iframe selesai dimuat, kirim `postMessage({ type: 'IFRAME_READY' })` ke React Parent.
        - Ketika menerima `APPLY_CUSTOM_DATA` dari React Parent, terapkan seluruh variabel warna ke `document.documentElement.style.setProperty(...)`, lalu terapkan perubahan `global` dan perubahan halaman aktif (`pages[currentPageSlug]`) ke elemen `[data-edit-id]` yang sesuai.
3. **Halaman Workspace Editor (`editor/workspace.tsx`):**
    - Menampilkan `Topbar` (Page Switcher jika multi-page, Viewport Switcher Desktop/Tablet/Mobile, Tombol Pilih Warna, Toggle Mode Edit vs Preview Bersih, Tombol Reset, Tombol Simpan, dan Tombol Pesan Sekarang).
    - Menampilkan area kanvas `<iframe>` di tengah layar yang ukurannya menyesuaikan pilihan _Viewport_ (`w-full`, `w-[768px]`, atau `w-[375px]`).

### 3. Pencegahan Bug (Bug Prevention Checklist)

- **Cursor Lompat Saat Mengetik di `contentEditable`:** Jangan mengirim balik `APPLY_CUSTOM_DATA` dari React ke iframe setiap kali menerima event `input` (`TEXT_UPDATED`), karena menimpa ulang `innerHTML` saat pengguna sedang mengetik akan membuat kursor kembali ke awal teks! Cukup simpan perubahan teks tersebut di Zustand store, dan hanya kirim `APPLY_CUSTOM_DATA` saat iframe pertama kali muat (`IFRAME_READY`), saat pindah halaman, atau saat gambar/link/warna diubah dari React.
- **Sinkronisasi Elemen Global (`data-edit-scope="global"`):** Pastikan saat pengguna mengganti logo atau teks footer yang memiliki `data-edit-scope="global"` di halaman _Beranda_, lalu pindah ke halaman _Tentang Kami_ (`about.html`), perubahan logo dan footer tersebut otomatis diterapkan di halaman _Tentang Kami_.

### 4. Definition of Done (DoD)

- [ ] Membuka `/editor/{slug}` menampilkan halaman template di dalam kanvas editor.
- [ ] Klik langsung pada judul/paragraf memungkinkan edit teks secara _real-time_ tanpa kursor melompat.
- [ ] Mengubah warna di Color Picker langsung mengubah warna tombol/background di dalam template detik itu juga.
- [ ] Pada template Multi-Page, berpindah dari halaman _Beranda_ ke _Tentang Kami_ lewat dropdown maupun klik menu di dalam template berjalan mulus tanpa menghilangkan editan sebelumnya.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 4):

> "Sekarang kerjakan **SPRINT 4** sesuai `docs/sprint_execution_plan.md` dan `docs/ai_agent_project_plan.md`. Buat `public/js/editor-bridge.js`, Zustand store `use-editor-store.ts`, `EditorController`, serta halaman `editor/workspace.tsx` beserta komponen Topbar, color-palette-popover, image-edit-modal, dan link-edit-modal. Perhatikan pencegahan bug agar kursor tidak melompat saat inline text editing dan pastikan elemen `data-edit-scope='global'` tersinkronisasi lintas halaman."

---

## 🚀 SPRINT 5: Save/Overwrite URL (`/p/{unique_code}`), WebP Image Upload & Direct WhatsApp Order

**Tujuan Utama:** Menyelesaikan alur penyimpanan kustomisasi pengunjung ke database (membuat URL unik saat pertama kali disimpan, dan **menimpa/overwrite** URL yang sama saat disimpan ulang), fitur upload logo/gambar oleh pengunjung tanpa login, manajemen daftar Preview di Admin, serta integrasi tombol **Pesan Sekarang** langsung ke WhatsApp.

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `app/Http/Controllers/Api/PreviewApiController.php`
- `app/Http/Controllers/SharedPreviewController.php`
- `app/Http/Controllers/Admin/CustomizedPreviewController.php`
- `resources/js/components/editor/save-success-modal.tsx`
- `resources/js/pages/admin/previews/index.tsx`

### 2. Rincian Tugas (Task List)

1. **Upload Gambar Pengunjung (`POST /api/previews/upload-image`):**
    - Validasi file gambar (maksimal `2MB`, format `jpg, jpeg, png, webp, svg`).
    - Jika pengunjung belum memiliki `unique_code` saat mengunggah gambar pertama kali, buatkan record `CustomizedPreview` terlebih dahulu atau gunakan folder `unique_code` baru yang langsung dikembalikan ke frontend.
    - Untuk gambar raster (`jpg, png, webp`), kompres dan konversi ke `.webp` menggunakan `Intervention\Image` (kualitas 80%, lebar maksimal 1920px). Untuk `.svg`, sanitasi dan simpan langsung.
    - Simpan ke `storage/app/public/custom-uploads/{unique_code}/{filename}.webp` dan kembalikan URL publiknya.
2. **Mekanisme Save & Overwrite (`PreviewApiController.php`):**
    - **Simpan Pertama Kali (`POST /api/previews`):**
        - Membuat `unique_code` acak 10 karakter alfanumerik yang unik.
        - Menyimpan `custom_data` (setelah dibersihkan dari tag berbahaya seperti `<script>`), menyetel `last_accessed_at = now()` dan `expires_at = now()->addDays(30)`.
        - Frontend menerima `unique_code`, menyimpan ke store, dan memperbarui URL address bar browser menjadi `/p/{unique_code}` menggunakan `window.history.replaceState({}, '', '/p/' + unique_code)`.
    - **Simpan Berikutnya / Re-Edit (`PUT /api/previews/{unique_code}`):**
        - **PENTING:** Karena `unique_code` sudah ada di state, klik tombol **Save** berikutnya wajib memanggil `PUT /api/previews/{unique_code}` untuk **menimpa (overwrite)** data pada link yang sama, **bukan** membuat link baru.
        - Memperbarui `last_accessed_at = now()` dan memperpanjang `expires_at = now()->addDays(30)`.
    - Menampilkan `save-success-modal.tsx` yang berisi URL lengkap (`https://domain.com/p/{unique_code}`), tombol **Salin Link**, informasi masa aktif 30 hari, dan tombol **Lanjut Pesan via WhatsApp**.
3. **Halaman Shared Preview (`GET /p/{unique_code}` -> `SharedPreviewController.php`):**
    - Cari preview berdasarkan `unique_code` (jika tidak ditemukan/sudah kedaluwarsa, tampilkan halaman informasi bahwa link preview tidak ditemukan atau telah melewati masa aktif 30 hari).
    - Update `last_accessed_at = now()` dan `expires_at = now()->addDays(30)`.
    - Render komponen `editor/workspace.tsx` dengan mengirimkan `initialCustomData` milik `unique_code` tersebut sehingga pengunjung atau klien bisa melihat hasilnya dan langsung mengeditnya kembali.
4. **Alur Tombol "Pesan Sekarang" (Direct to WhatsApp):**
    - Saat tombol **"Pesan Sekarang"** diklik (baik di Topbar maupun di dalam Modal Save):
        1. Jika belum pernah disimpan atau ada perubahan baru (`isDirty`), jalankan proses Save (`POST` atau `PUT`) secara otomatis terlebih dahulu.
        2. Panggil endpoint `POST /api/previews/{unique_code}/order-click` untuk mencatat `order_clicked_at = now()`.
        3. Buka URL WhatsApp Admin (`https://wa.me/{nomor_wa}?text=...`) di tab baru dengan format pesan yang memuat Nama Template dan URL `/p/{unique_code}`.
5. **Halaman Admin Daftar Preview Klien (`/admin/previews`):**
    - Tabel daftar seluruh kustomisasi pengunjung (Kode Unik, Nama Template, Status Klik Order WA, Terakhir Diakses, Tanggal Kedaluwarsa).
    - Tombol **Lock/Unlock** (`is_locked_by_admin`) agar link klien yang sudah order tidak terhapus otomatis setelah 30 hari.

### 3. Pencegahan Bug (Bug Prevention Checklist)

- Pastikan saat pengguna berada di `/editor/{slug}`, mengupload logo terlebih dahulu sebelum pernah klik tombol Save, sistem otomatis menginisialisasi `unique_code` sehingga file logo masuk ke folder `/storage/custom-uploads/{unique_code}/` dan saat pengguna menekan tombol Save, sistem menggunakan `unique_code` yang sama tersebut.
- Pastikan sanitasi HTML pada teks mengizinkan tag formatting aman seperti `<br>`, `<b>`, `<strong>`, `<i>`, `<em>`, `<span>` tetapi memblokir `<script>`, `<iframe>`, `onload`, dan `onerror`.

### 4. Definition of Done (DoD)

- [ ] Pengunjung dapat mengunggah logo/foto sendiri dari komputer dan gambar langsung berubah di dalam template.
- [ ] Klik Save pertama kali menghasilkan URL `/p/{unique_code}` dan memunculkan Popup Link.
- [ ] Membuka `/p/{unique_code}`, mengedit tulisan/gambar lagi, lalu klik Save **memperbarui link yang sama** (tidak membuat link baru).
- [ ] Klik "Pesan Sekarang" otomatis menyimpan perubahan terakhir dan membuka WhatsApp Admin dengan link `/p/{unique_code}`.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 5):

> "Sekarang kerjakan **SPRINT 5** sesuai `docs/sprint_execution_plan.md` dan `docs/ai_agent_project_plan.md`. Buat `PreviewApiController` (untuk `POST` save pertama, `PUT` overwrite save berikutnya, upload gambar ke `.webp`, dan pencatatan klik order WhatsApp), `SharedPreviewController` untuk route `/p/{unique_code}`, `save-success-modal.tsx`, serta halaman Admin `/admin/previews` dengan fitur Lock/Pin preview."

---

## 🚀 SPRINT 6: WordPress Theme Auto-Exporter (`.zip`) & 30-Day Auto-Cleanup Scheduler

**Tujuan Utama:** Membangun mesin otomatis khusus Admin untuk mengekspor URL preview klien (`/p/{unique_code}`) maupun template dasar menjadi file instalasi **WordPress Theme (`.zip`)** yang langsung siap di-upload dan diaktifkan di WordPress, serta menyelesaikan fitur hapus otomatis (_auto-cleanup_) 30 hari.

### 1. Daftar File yang Harus Dibuat / Dimodifikasi

- `app/Services/WordPressThemeExporterService.php`
- `app/Http/Controllers/Admin/WordPressExportController.php`
- `app/Console/Commands/CleanupExpiredPreviews.php`
- `routes/console.php`
- Modifikasi `resources/js/pages/admin/dashboard.tsx` (Quick WP Exporter Box)
- Modifikasi `resources/js/pages/admin/previews/index.tsx` & `pages/admin/templates/index.tsx` (Tombol Export WP)
- Modifikasi `resources/js/components/editor/topbar.tsx` (Tombol Export WP khusus jika Admin sedang login)

### 2. Rincian Tugas (Task List)

1. **Mesin Konversi Tema WordPress (`WordPressThemeExporterService.php`):**
    - Menerima objek `CustomizedPreview` (atau `Template` dasar) serta parameter opsional `themeName`.
    - Buat folder kerja sementara di `storage/app/temp-exports/{theme_slug}-{timestamp}/{theme_slug}/`.
    - **Langkah 1 (Salin Aset):**
        - Salin seluruh isi folder `assets/` dari template dasar ke folder `assets/` tema.
        - Jika berasal dari `CustomizedPreview`, salin seluruh gambar kustom dari `storage/app/public/custom-uploads/{unique_code}/` ke dalam `assets/custom-uploads/` di folder tema.
        - Salin `thumbnail.jpg` milik template menjadi `screenshot.png` di root folder tema.
    - **Langkah 2 (Generate `style.css`):**
        - Buat header komentar standar WordPress (`Theme Name`, `Author`, `Description`, `Version: 1.0.0`, `Text Domain`).
        - Tambahkan blok `:root { ... }` berisi variabel warna default yang sudah ditimpa dengan pilihan warna klien dari `custom_data['colors']`.
    - **Langkah 3 (Konversi File HTML ke Template PHP WordPress):**
        - Loop daftar halaman di `manifest_data['pages']`:
            - Halaman dengan slug `'home'` (atau `index.html`) dikonversi menjadi `front-page.php` dan disalin juga sebagai `index.php` (fallback wajib WordPress).
            - Halaman lain (misal slug `'about'`, `'services'`, `'contact'`) dikonversi menjadi `page-{slug}.php` (contoh: `page-about.php`, `page-services.php`, `page-contact.php`).
        - Pada setiap file halaman:
            1. Terapkan nilai editan klien (`global` dan `pages[slug]`) ke masing-masing elemen `[data-edit-id]`.
            2. Bungkus teks/gambar/link yang memiliki `data-edit-id` dengan fungsi WordPress `get_theme_mod('{setting_id}', '{nilai_editan_klien}')` agar setelah dipasang di WordPress tetap bisa diedit lewat menu **Appearance -> Customize**.
            3. Hapus atribut internal editor (`data-edit-id`, `data-edit-type`, `data-edit-scope`, `contenteditable`).
            4. Ubah semua path relatif `assets/...` menjadi `<?php echo esc_url( get_template_directory_uri() . '/assets/...' ); ?>`.
            5. Ubah semua path gambar kustom `/storage/custom-uploads/{unique_code}/...` menjadi `<?php echo esc_url( get_template_directory_uri() . '/assets/custom-uploads/...' ); ?>`.
            6. Ubah link navigasi antar halaman (`index.html`, `about.html`, dll.) menjadi URL WordPress:
                - `index.html` -> `<?php echo esc_url( home_url( '/' ) ); ?>`
                - `{page_slug}.html` -> `<?php echo esc_url( home_url( '/{page_slug}/' ) ); ?>`
            7. Suntikkan `<?php wp_head(); ?>` sebelum `</head>` (beserta output dynamic CSS variable dari `get_theme_mod` warna), `<?php body_class(); ?>` pada `<body>`, `<?php wp_body_open(); ?>` setelah `<body>`, dan `<?php wp_footer(); ?>` sebelum `</body>`.
    - **Langkah 4 (Generate `functions.php`):**
        - Tambahkan `add_theme_support('title-tag')` dan `add_theme_support('post-thumbnails')`.
        - Tambahkan hook `after_switch_theme`: otomatis membuat halaman WordPress (`wp_insert_post`) untuk setiap item di `manifest_data['pages']` jika belum ada, lalu mengatur `update_option('show_on_front', 'page')` dan `update_option('page_on_front', $homePageId)` agar halaman depan langsung tampil persis seperti preview saat tema diaktifkan.
        - Tambahkan hook `customize_register`: daftarkan Panel/Section untuk **Warna Tema**, **Elemen Global**, dan **Konten Halaman** sesuai semua `setting_id` yang digunakan pada `get_theme_mod()`.
    - **Langkah 5 (Bungkus ke `.zip`):**
        - Kompres folder `{theme_slug}` ke dalam file `{theme_slug}.zip`, hapus folder kerja sementara, dan kirimkan file `.zip` untuk diunduh Admin.
2. **Integrasi UI Export WordPress (Khusus Admin):**
    - **Di Dashboard Admin (`/admin/dashboard`):** Buat kotak **"Quick Export to WordPress Theme"** di mana Admin cukup _paste_ URL preview dari klien (contoh: `https://domain.com/p/k9Xm2pL8qR` atau kode `k9Xm2pL8qR`), isi Nama Tema (opsional), lalu klik **"Download WordPress Theme (.zip)"**.
    - **Di Tabel Preview Klien (`/admin/previews`) & Tabel Template (`/admin/templates`):** Sediakan tombol langsung **"Export WP (.zip)"**.
    - **Di Topbar Editor (`/p/{unique_code}`):** Jika Admin sedang login (`auth.user !== null`), tampilkan tombol **"Export WP (.zip)"** di bar atas halaman preview.
3. **30-Day Auto-Cleanup Command (`CleanupExpiredPreviews.php`):**
    - Buat command `php artisan previews:cleanup`.
    - Cari semua `CustomizedPreview` yang `expires_at < now()` dan `is_locked_by_admin = false`.
    - Hapus folder `storage/app/public/custom-uploads/{unique_code}` beserta isinya, lalu hapus record dari database.
    - Hapus juga file sisa di `storage/app/temp-exports/` yang berumur lebih dari 1 jam.
    - Daftarkan `Schedule::command('previews:cleanup')->daily()` di `routes/console.php`.

### 3. Pencegahan Bug (Bug Prevention Checklist)

- **Encoding UTF-8 & Tag PHP pada `DOMDocument`:** Saat memproses HTML dengan `DOMDocument` di PHP, gunakan `mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')` atau prefix `<?xml encoding="utf-8" ?>` agar karakter simbol (seperti `©`, mata uang `Rp`, atau emoji) tidak berubah menjadi karakter aneh (`Ã‚Â©`). Selain itu, gunakan placeholder teks (misal `__WP_TEMPLATE_URI__`) saat memanipulasi atribut di `DOMDocument`, baru lakukan `str_replace` menjadi tag `<?php ... ?>` setelah `saveHTML()` selesai agar tag `<?php` tidak di-encode menjadi `&lt;?php` oleh `DOMDocument`.
- **Struktur Folder di Dalam ZIP WordPress:** Pastikan ketika file `.zip` dibuka, di dalamnya terdapat **1 folder utama** `{theme-slug}/` yang berisi `style.css`, `index.php`, `functions.php`, dst., sehingga WordPress langsung mengenalinya sebagai tema yang valid saat di-upload lewat menu _Appearance -> Themes -> Add New_.

### 4. Definition of Done (DoD)

- [ ] Admin dapat mem-_paste_ URL `/p/{unique_code}` di Dashboard dan langsung mengunduh file `.zip` Tema WordPress.
- [ ] File `.zip` hasil export memiliki `style.css`, `functions.php`, `index.php`, `front-page.php`, `page-{slug}.php`, `screenshot.png`, dan folder `assets/custom-uploads/` berisi gambar yang diunggah klien.
- [ ] Kode PHP di dalam file hasil export bersih, tidak ada `&lt;?php` yang ter-escape secara salah, dan siap langsung diaktifkan di WordPress.
- [ ] Perintah `php artisan previews:cleanup` berjalan lancar menghapus preview yang sudah melewati `expires_at` dan tidak dikunci Admin.

### 💬 Prompt untuk AI Agent (Copy-Paste untuk Mulai Sprint 6):

> "Sekarang kerjakan **SPRINT 6** sesuai `docs/sprint_execution_plan.md` dan `docs/ai_agent_project_plan.md`. Buat `WordPressThemeExporterService.php` (pastikan menggunakan placeholder sebelum `saveHTML()` agar tag `<?php` dan encoding UTF-8 tidak rusak oleh `DOMDocument`), `WordPressExportController.php`, integrasikan tombol Export WordPress di Dashboard Admin (Quick URL Exporter), halaman Previews, halaman Templates, dan Topbar Editor (khusus Admin login), serta buat command `previews:cleanup` untuk auto-delete 30 hari."
