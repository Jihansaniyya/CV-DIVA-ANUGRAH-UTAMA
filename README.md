# CV Diva Anugrah Utama — Aplikasi Manajemen & Monitoring Proyek Kontraktor

Aplikasi web untuk mengelola dan memantau proyek kontraktor: data pengguna, data proyek, data
pekerjaan, rencana pekerjaan per periode, pembentukan Kurva S, pencatatan progres aktual di lapangan,
serta pembuatan laporan harian, mingguan, bulanan, dan milestone lengkap dengan export Excel dan Word.

- **Backend:** Laravel 12 REST API (Sanctum, Eloquent, Form Request, Policy, Middleware, API Resource)
- **Frontend:** React 19 + TypeScript + Vite + Tailwind CSS 4 + React Router + TanStack Query + Recharts + Lucide
- **Database:** MySQL/MariaDB
- **Export:** Laravel Excel (Maatwebsite) untuk `.xlsx`, PHPWord untuk `.docx`

Dokumen pendukung: [DATABASE.md](DATABASE.md) (struktur & formula) dan [ARCHITECTURE.md](ARCHITECTURE.md)
(arsitektur, alur data, otorisasi).

---

## 1. Struktur Project

```
CV-DIVA-ANUGRAH-UTAMA/
├── backend/                  # Laravel 12 REST API
│   ├── app/
│   │   ├── Enums/            # RoleCode, ProjectStatus, ReportStatus, ReportType
│   │   ├── Exports/          # Export Excel (harian, mingguan, bulanan)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   ├── Middleware/   # EnsureUserHasRole
│   │   │   ├── Requests/     # Form Request (validasi)
│   │   │   └── Resources/    # API Resource
│   │   ├── Models/
│   │   ├── Policies/
│   │   └── Services/         # Seluruh logika bisnis & perhitungan
│   ├── database/{migrations,seeders,factories}/
│   ├── routes/api.php
│   └── tests/Feature/
├── frontend/                 # React + TypeScript
│   ├── src/
│   │   ├── assets/           # Logo perusahaan
│   │   ├── components/{ui,layout,charts,reports}/
│   │   ├── hooks/            # useAuth, useToast, queries (TanStack Query)
│   │   ├── lib/              # Axios instance & penanganan error
│   │   ├── pages/
│   │   ├── routes/           # ProtectedRoute & RoleRoute
│   │   ├── services/         # Service layer seluruh akses API
│   │   ├── types/            # Tipe TypeScript bersama
│   │   └── utils/            # Format angka, persen, rupiah, tanggal
│   └── vite.config.ts
├── README.md
├── DATABASE.md
└── ARCHITECTURE.md
```

## 2. Kebutuhan Sistem

| Komponen | Versi minimal | Catatan |
|---|---|---|
| PHP | 8.2 | Ekstensi wajib: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, **`gd`** (dipakai PhpSpreadsheet) |
| Composer | 2.x | |
| Node.js | 20+ | npm 10+ |
| MySQL / MariaDB | MySQL 8 / MariaDB 10.4 | |

> **Aktifkan `gd`:** buka `php.ini`, hapus tanda `;` pada baris `;extension=gd`, lalu restart terminal.
> Tanpa ekstensi ini export Excel gagal.

## 3. Instalasi Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Buat database, lalu sesuaikan `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306          # XAMPP/MariaDB bawaan komputer ini memakai 3307
DB_DATABASE=cv_diva_anugrah_utama
DB_USERNAME=root
DB_PASSWORD=

APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
FILESYSTEM_DISK=public
```

```sql
CREATE DATABASE cv_diva_anugrah_utama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Jalankan migrasi, seeder, dan symlink storage:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve          # http://127.0.0.1:8000
```

### Akun hasil seeder

| Peran | Username | Password |
|---|---|---|
| Admin | `admin` | `password123` |
| QS | `qs.nisa` | `password123` |
| QS | `qs.jihan` | `password123` |
| Kontraktor | `kontraktor.aid` | `password123` |
| Kontraktor | `kontraktor.muiz` | `password123` |

Seeder membuat 3 proyek contoh. Proyek pertama memakai **data nyata dari laporan referensi**
(Pembuatan Penutup Parit RT 09 Kelurahan Bontang Baru) sehingga bobot, rencana mingguan, dan Kurva S
yang dihasilkan aplikasi dapat dibandingkan langsung dengan dokumen aslinya. Proyek kedua berjalan
dengan realisasi di bawah rencana (deviasi negatif), proyek ketiga belum dimulai.

## 4. Instalasi Frontend

```bash
cd frontend
npm install
cp .env.example .env
npm run dev               # http://localhost:5173
```

Isi `frontend/.env`:

```env
VITE_API_URL=http://127.0.0.1:8000/api
VITE_STORAGE_URL=http://127.0.0.1:8000/storage
```

Build produksi:

```bash
npm run build             # hasil di frontend/dist
npm run preview
```

## 5. Menjalankan Aplikasi

Jalankan dua terminal:

```bash
# Terminal 1
cd backend && php artisan serve

# Terminal 2
cd frontend && npm run dev
```

Buka `http://localhost:5173`, login, lalu aplikasi mengarahkan ke dashboard sesuai peran.

## 6. Penyimpanan File

Foto progres dan dokumen laporan disimpan melalui Laravel Storage pada disk `public`:

- Foto progres: `storage/app/public/progress/{project_id}/{report_id}/`
- Dokumen laporan: `storage/app/public/reports/{project_id}/`

Database hanya menyimpan path file. Jalankan `php artisan storage:link` agar file dapat diakses dari
`http://127.0.0.1:8000/storage/...`.

## 7. Pengujian

Backend memakai database MySQL terpisah (`cv_diva_anugrah_utama_test`, dikonfigurasi di `phpunit.xml`):

```sql
CREATE DATABASE cv_diva_anugrah_utama_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
cd backend
php artisan test                       # seluruh feature test
php artisan test --filter=ReportTest   # satu berkas
```

Cakupan pengujian: login/logout, otorisasi per peran, CRUD proyek, CRUD pekerjaan, perhitungan bobot,
validasi rencana pekerjaan, input progres (termasuk unggah foto, material, kendala), perhitungan
progres & Kurva S, deviasi, laporan harian/mingguan/bulanan, dan export Excel/Word.

Frontend:

```bash
cd frontend
npx tsc -b          # pemeriksaan tipe
npm run lint
npm run build
```

## 8. Peran dan Hak Akses

| Kemampuan | Admin | QS | Kontraktor |
|---|:--:|:--:|:--:|
| Login / logout | ✔ | ✔ | ✔ |
| Kelola pengguna & status akun | ✔ | — | — |
| Kelola proyek, pekerjaan, periode, rencana, milestone | ✔ | — | — |
| Melihat proyek | semua | hanya yang ditugaskan | semua |
| Input progres aktual, foto, material, kendala | ✔ | ✔ | — |
| Melihat Kurva S & deviasi | ✔ | melalui detail proyek | ✔ |
| Melihat laporan & export Excel/Word | ✔ | ✔ | ✔ |

Pembatasan dilakukan di backend (middleware `role` + Policy), bukan sekadar menyembunyikan menu di
frontend. Percobaan mengakses endpoint peran lain mengembalikan HTTP 403.

## 9. Alur Penggunaan

1. **Admin** membuat proyek → sistem membentuk periode mingguan otomatis → Admin menentukan QS,
   kelompok pekerjaan, dan data pekerjaan (volume, satuan, harga satuan) → sistem menghitung bobot →
   Admin mengisi rencana target volume per periode → sistem menghitung target progres dan Kurva S.
2. **QS** membuka proyek yang ditugaskan → memilih pekerjaan → mengisi volume realisasi, keterangan,
   material, kendala, alasan keterlambatan, tindak lanjut, dan foto → mengirim laporan.
3. **Sistem** menghitung progres aktual, mengakumulasikannya per periode, membandingkan dengan rencana,
   dan menghitung deviasi.
4. **Kontraktor** memantau progres, Kurva S, deviasi, dokumentasi, dan laporan, serta mengekspornya ke
   Excel atau Word.

## 10. Responsif

Tampilan diuji untuk desktop, tablet, dan smartphone:

- Sidebar menjadi drawer pada layar kecil.
- Kartu dan form menjadi satu kolom.
- Tabel proyek berubah menjadi daftar kartu di bawah lebar `md`.
- Tabel laporan mempertahankan struktur kolom aslinya dan digulir horizontal.
- Chart Kurva S memakai `ResponsiveContainer`.

## 11. Batasan (sesuai PRD)

Aplikasi **tidak** mencakup pengelolaan keuangan proyek (pembayaran, penggajian, transaksi pembelian
material), pengadaan material, maupun inventory menyeluruh. Data material hanya dicatat sebagai bagian
dari pelaporan progres. Perhitungan progres dan Kurva S sepenuhnya bergantung pada data yang
dimasukkan pengguna dan tidak menggantikan pemeriksaan langsung di lapangan.
