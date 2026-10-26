# Webku

Katalog template website (landing page dan company profile) dengan editor visual tanpa login. Pengunjung mengubah teks, gambar, warna, dan link, menyimpan hasilnya ke URL `/p/{kode}`, lalu memesan lewat WhatsApp. Admin mengekspor hasil itu menjadi tema WordPress (`.zip`).

## Kebutuhan

- PHP 8.3 atau lebih baru (proyek ini dijalankan dengan PHP 8.5)
- Composer
- Node.js 20 atau lebih baru dan npm
- MySQL 8 atau MariaDB. SQLite juga bisa dipakai jika driver-nya aktif
- Ekstensi PHP: `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, dan `zip`

`gd` dipakai untuk mengubah gambar unggahan menjadi WebP. `zip` dipakai untuk membuka template dan membuat file tema WordPress. Di Laragon, aktifkan keduanya di `php.ini` (hapus tanda `;` di depan `extension=gd` dan `extension=zip`), lalu restart Apache.

## Instalasi di Laragon

1. Letakkan proyek di `C:\laragon\www\webku`.
2. Buat database kosong, misalnya `webku`, lewat HeidiSQL.
3. Salin lingkungan dan pasang dependensi:

```bash
copy .env.example .env
composer install
php artisan key:generate
npm install
```

4. Atur `.env`:

```dotenv
APP_NAME=Webku
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=webku
DB_USERNAME=root
DB_PASSWORD=
```

`APP_URL` harus sama dengan alamat yang dibuka di browser. Gambar template dilayani dari `{APP_URL}/storage`.

5. Siapkan database, akun admin, dan aset frontend:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

Seeder membuat:

- Admin: `admin@admin.com` / `password`. Ganti password ini setelah login pertama.
- Nomor WhatsApp `6281234567890` dan teks pesan default. Ubah di menu **Pengaturan**.
- Lima kategori awal: Landing Page, Company Profile, F&B & Restoran, Konstruksi & Properti, dan Jasa & Konsultan.

6. Jalankan aplikasi.

Untuk pengembangan (server, antrean, dan Vite sekaligus):

```bash
composer run dev
```

Buka `http://localhost:8000`.

Kalau hanya ingin memakai hasil build tanpa Vite:

```bash
php artisan serve
```

Di Laragon, situs juga bisa dibuka lewat virtual host Apache. Samakan `APP_URL` dengan alamat host itu.

### Pembersihan preview 30 hari

Preview yang lewat masa aktif dan tidak dikunci admin dihapus oleh perintah:

```bash
php artisan previews:cleanup
```

Perintah ini sudah dijadwalkan harian. Agar berjalan sendiri, daftarkan scheduler Laravel, misalnya Task Scheduler Windows yang menjalankan `php artisan schedule:run` setiap menit dari folder proyek.

### Tes

```bash
php artisan test
```

Kalau PHP tidak memuat driver SQLite, jalankan:

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
```

## Akun dan menu admin

Registrasi publik dimatikan. Hanya akun yang sudah ada yang bisa masuk lewat `/login`.

| Menu            | Fungsi                                                         |
| --------------- | -------------------------------------------------------------- |
| Dashboard       | Ringkasan dan ekspor cepat tema WordPress dari URL `/p/{kode}` |
| Kelola Template | Unggah ZIP, ganti gambar katalog, aktifkan atau hapus template |
| Kategori        | Tambah, ubah, dan hapus kategori                               |
| Preview Klien   | Daftar desain tersimpan, kunci preview, ekspor WordPress       |
| Pengaturan      | Nomor WhatsApp, teks pesan, dan nama author tema               |

Kategori yang masih punya template tidak bisa dihapus. Kategori baru langsung muncul di form unggah. Di katalog publik, kategori hanya tampil setelah ada template aktif di dalamnya.

## Membuat template

Template adalah situs statis biasa: HTML, CSS, gambar, dan opsional JavaScript. Tidak ada PHP di dalam ZIP.

Contoh siap pakai ada di `resources/upload-templates/jelajah-liburan` dan `resources/upload-templates/warung-kopi-senja`.

### Struktur folder

```text
nama-template/
├── manifest.json
├── thumbnail.jpg
├── index.html
├── about.html              # hanya untuk multi-page
└── assets/
    ├── css/style.css
    ├── js/main.js          # opsional
    └── img/
```

File wajib di root (atau satu folder di dalam ZIP):

- `manifest.json`
- `index.html`
- `thumbnail.jpg`, `thumbnail.jpeg`, `thumbnail.png`, atau `thumbnail.webp`

### manifest.json

```json
{
    "name": "Jelajah - Landing Page Liburan",
    "type": "single-page",
    "description": "Landing page perjalanan ke luar negeri.",
    "pages": [{ "slug": "home", "title": "Beranda", "file": "index.html" }],
    "colors": [
        {
            "variable": "--color-primary",
            "label": "Warna Utama",
            "default": "#9275f7"
        },
        {
            "variable": "--color-secondary",
            "label": "Warna Sekunder",
            "default": "#1c1e1e"
        },
        {
            "variable": "--color-accent",
            "label": "Warna Aksen",
            "default": "#f59e0b"
        },
        {
            "variable": "--color-bg",
            "label": "Warna Background",
            "default": "#ffffff"
        }
    ]
}
```

Aturan isinya:

- `name` menjadi nama di katalog. Slug URL dibuat dari nama ini.
- `type` hanya `single-page` atau `multi-page`.
- `single-page` harus punya tepat satu halaman. `multi-page` minimal dua.
- Setiap halaman punya `slug` (`a-z`, angka, tanda `-`), `title`, dan `file` berakhiran `.html` atau `.htm`.
- Salah satu `file` wajib `index.html`, dan file itu harus ada di ZIP.
- `colors` minimal satu. `variable` berbentuk `--nama-warna`. `default` adalah warna hex `#rgb` atau `#rrggbb`.
- `description` di manifest dipakai kalau kolom deskripsi pada form unggah dikosongkan.

Contoh multi-page:

```json
"type": "multi-page",
"pages": [
    { "slug": "home", "title": "Beranda", "file": "index.html" },
    { "slug": "about", "title": "Tentang Kami", "file": "about.html" },
    { "slug": "contact", "title": "Kontak", "file": "contact.html" }
]
```

Tautan antar halaman memakai nama file, misalnya `href="about.html"`. Editor mengubahnya menjadi perpindahan halaman tanpa menghilangkan editan. Tautan `https://...`, `mailto:`, `tel:`, dan `#jangkar` dibiarkan.

### HTML yang bisa diedit

Tambahkan atribut pada elemen yang boleh diubah pengunjung. `data-edit-id` harus unik di halaman itu.

```html
<h1 data-edit-id="hero-title" data-edit-type="text">Liburan ke Luar Negeri</h1>

<img
    data-edit-id="hero-banner"
    data-edit-type="image"
    src="assets/img/hero.jpg"
    alt="Pemandangan"
/>

<a data-edit-id="hero-cta" data-edit-type="link" href="contact.html"
    >Pesan Sekarang</a
>
```

Elemen yang sama di setiap halaman (logo, nama merek, footer) diberi `data-edit-scope="global"`:

```html
<img
    data-edit-id="site-logo"
    data-edit-type="image"
    data-edit-scope="global"
    src="assets/img/logo.svg"
    alt="Logo"
/>
```

Tanpa atribut itu, teks dan gambar tetap tampil, tetapi tidak bisa diedit di editor.

### CSS

Pakai variabel yang sama dengan `manifest.json`, dan tulis path secara relatif:

```css
:root {
    --color-primary: #9275f7;
    --color-secondary: #1c1e1e;
    --color-accent: #f59e0b;
    --color-bg: #ffffff;
}

.btn {
    background: var(--color-primary);
}
```

Di HTML:

```html
<link rel="stylesheet" href="assets/css/style.css" />
```

Jangan memakai URL absolut seperti `/css/style.css` atau `http://localhost/...`. Editor menyisipkan `<base>` agar path relatif tetap mengarah ke folder template.

### Gambar di dalam template

Letakkan gambar di `assets/img/` dan panggil dengan path relatif, misalnya `assets/img/hero.jpg`. Format yang diterima: `jpg`, `jpeg`, `png`, `webp`, `gif`, `svg`, dan `ico`. Font yang diterima: `woff`, `woff2`, `ttf`, dan `eot`.

### Thumbnail katalog

`thumbnail.jpg` adalah gambar kartu di katalog. Rasio yang enak dilihat 16:9, misalnya 1600×900. Kartu menampilkan seluruh gambar (`object-contain`), jadi foto horizontal tidak dipotong. Gambar ini juga menjadi `screenshot.png` saat template diekspor ke WordPress.

Setelah template terunggah, gambar kartu masih bisa diganti dari **Kelola Template** tanpa mengunggah ulang ZIP. Unggahan itu menerima JPG, PNG, atau WebP maksimal 4 MB, lalu disimpan sebagai WebP lebar maksimal 1600 piksel.

### File yang ditolak

ZIP ditolak jika berisi `.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.bat`, `.env`, atau `.htaccess`. Maksimal 20 MB, 2000 file, dan 100 MB setelah diekstrak.

## Mengunggah template

1. Pilih semua isi folder template (`manifest.json`, `index.html`, `thumbnail.jpg`, `assets`, dan halaman lain). Jangan ikut sertakan file sampah seperti `.DS_Store`.
2. Kompres menjadi `.zip`. Boleh langsung file-filenya di root ZIP, atau satu folder pembungkus. Kalau ada lebih dari satu folder di root dan `manifest.json` tidak kelihatan, unggahan ditolak.
3. Login sebagai admin, buka **Kelola Template**.
4. Pilih file ZIP, pilih kategori, isi harga kalau perlu, dan isi deskripsi atau kosongkan agar memakai deskripsi manifest.
5. Harga diketik sebagai angka dan tampil dalam rupiah. `1500000` menjadi `Rp 1.500.000`. Yang disimpan tetap angkanya.
6. Klik **Unggah Template**.

Template langsung aktif dan muncul di katalog `/`. Nonaktifkan dari kartu yang sama kalau belum ingin ditampilkan. Template nonaktif tetap bisa dibuka admin.

Tombol **Generate 2 Sample Templates** membuat dua contoh bawaan (satu halaman dan banyak halaman) tanpa ZIP.

## Alur setelah template tayang

1. Pengunjung membuka katalog dan menekan **Live Preview & Edit Sekarang**.
2. Di editor, teks diketik langsung. Gambar dan tombol diubah lewat modal. Warna diubah dari palet.
3. **Simpan** pertama membuat URL `/p/{kode}` yang berlaku 30 hari sejak terakhir dibuka atau disimpan. Simpan berikutnya menimpa link yang sama.
4. **Pesan Sekarang** menyimpan perubahan lalu membuka WhatsApp admin dengan nama template dan URL preview.
5. Admin mengunduh tema WordPress dari Dashboard (tempel URL atau kode), dari tabel Preview Klien, dari tabel Template, atau dari tombol **Export WP** di editor saat admin sedang login.
