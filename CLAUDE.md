# Panduan Pengembangan — CV Diva Anugrah Utama

Aplikasi web manajemen dan monitoring proyek kontraktor. Repository memakai arsitektur terpisah:
`backend/` (Laravel 12 REST API) dan `frontend/` (React 19 + TypeScript + Vite).

Baca [README.md](README.md) untuk instalasi, [DATABASE.md](DATABASE.md) untuk struktur dan formula
perhitungan, dan [ARCHITECTURE.md](ARCHITECTURE.md) untuk arsitektur serta daftar endpoint.

## Perintah yang sering dipakai

```bash
# Backend
cd backend
php artisan serve                 # http://127.0.0.1:8000
php artisan migrate --seed
php artisan test                  # butuh database cv_diva_anugrah_utama_test
./vendor/bin/pint                 # format kode PHP

# Frontend
cd frontend
npm run dev                       # http://localhost:5173
npx tsc -b                        # pemeriksaan tipe
npm run lint
npm run build
```

Lingkungan pengembangan saat ini: PHP 8.2 (XAMPP) dan MariaDB 10.4 pada **port 3307**.
Ekstensi `gd` wajib aktif di `php.ini` karena dipakai PhpSpreadsheet saat export Excel.

## Konvensi Backend

- **Logika bisnis selalu di `app/Services`.** Controller hanya menerima request, memanggil service, dan
  mengembalikan API Resource. Tidak ada rumus perhitungan di controller maupun model.
- Validasi memakai **Form Request**, otorisasi memakai **Policy** dan middleware `role`.
- Respons memakai **API Resource**; aksi tunggal mengembalikan `{ message, data }`, koleksi
  berpaginasi mengembalikan `{ data, links, meta }`.
- Pesan yang dilihat pengguna ditulis dalam **Bahasa Indonesia**.
- Perubahan skema selalu melalui migration, tidak pernah langsung di phpMyAdmin.
- Penamaan domain memakai istilah Bahasa Indonesia yang dipakai di lapangan (`uraian_pekerjaan`,
  `volume_realisasi`, `bobot`, `deviasi`), sedangkan nama tabel dan relasi mengikuti konvensi Laravel.

## Konvensi Frontend

- Komponen **tidak memanggil Axios langsung**. Alur: komponen → `hooks/queries.ts` → `services/*.ts`
  → `lib/api.ts`.
- Query key terpusat pada objek `qk` di `hooks/queries.ts` agar invalidasi cache konsisten.
- TypeScript ketat; hindari `any`. Tipe bersama berada di `src/types`.
- Impor memakai alias `@/` (contoh: `import { Button } from '@/components/ui/Button'`).
- Warna diambil dari token Tailwind pada `src/index.css` (`bg-primary`, `text-muted`, `bg-navy`, ...),
  bukan nilai heksadesimal langsung di komponen.
- Setiap tampilan data memiliki state `loading`, `empty`, dan `error`.
- Setiap halaman harus tetap terpakai pada lebar layar ponsel: sidebar menjadi drawer, kartu dan form
  satu kolom, tabel laporan digulir horizontal tanpa mengubah struktur kolom.

## Hal yang tidak boleh dilakukan

- Membuat data proyek, progres, bobot, atau laporan secara hardcode maupun acak di frontend.
- Mengandalkan penyembunyian menu sebagai satu-satunya pembatas akses; backend wajib ikut memvalidasi.
- Menambahkan fitur di luar scope PRD: keuangan proyek, penggajian, transaksi pembelian material, atau
  inventory menyeluruh. Data material hanya untuk kebutuhan pelaporan progres.
- Mengubah warna logo perusahaan.
