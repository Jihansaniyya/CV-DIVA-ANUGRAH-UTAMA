# Arsitektur Aplikasi

Aplikasi memisahkan frontend dan backend. React menangani seluruh antarmuka, Laravel menangani REST
API dan seluruh logika bisnis, MySQL menjadi satu-satunya sumber data.

```
Browser (React + TypeScript, Vite)
   │  HTTP JSON / multipart, header Authorization: Bearer <token>
   ▼
Laravel REST API  ── Sanctum ──► autentikasi token
   │                 Middleware role ──► otorisasi peran
   │                 Policy          ──► otorisasi objek (proyek, laporan)
   ▼
Service Layer (bobot, periode, rencana, progres, Kurva S, laporan, export)
   ▼
Eloquent ORM ──► MySQL
   │
   └──► Laravel Storage (foto progres, dokumen laporan)
```

---

## 1. Arsitektur Backend (Laravel)

### 1.1 Lapisan

| Lapisan | Direktori | Tanggung jawab |
|---|---|---|
| Routing | `routes/api.php` | Definisi endpoint, pemasangan middleware peran |
| Controller | `app/Http/Controllers/Api` | Menerima request, memanggil service, mengembalikan Resource. Tidak memuat rumus |
| Form Request | `app/Http/Requests` | Validasi input dan otorisasi awal |
| Policy | `app/Policies` | Otorisasi berbasis objek (siapa boleh melihat/mengubah proyek atau laporan) |
| Middleware | `app/Http/Middleware/EnsureUserHasRole` | Pembatasan peran dan pemeriksaan status akun |
| Service | `app/Services` | Seluruh logika bisnis dan perhitungan |
| Resource | `app/Http/Resources` | Bentuk respons JSON yang konsisten |
| Model | `app/Models` | Relasi Eloquent, cast, scope |
| Export | `app/Exports` + `WordExportService` | Pembentukan berkas Excel dan Word |

### 1.2 Service Layer

| Service | Tanggung jawab |
|---|---|
| `WeightCalculatorService` | Menghitung `harga_pekerjaan` dan `bobot`, serta menyelaraskan seluruh data turunan (rencana dan realisasi) saat pekerjaan berubah |
| `ProjectScheduleService` | Membentuk periode mingguan dari tanggal pelaksanaan, memetakan tanggal laporan ke periode |
| `WorkPlanService` | Menyimpan rencana per periode, memvalidasi total target volume, menyusun matriks rencana untuk frontend |
| `ProgressService` | Menyimpan laporan progres beserta detail, foto, material, kendala; memvalidasi sisa volume; mengirim laporan |
| `CurveSService` | Menyusun titik Kurva S, progres rencana/aktual, deviasi, dan akumulasi realisasi per pekerjaan |
| `ReportService` | Menyusun data laporan harian, mingguan, bulanan, dan milestone sesuai format dokumen resmi |
| `WordExportService` | Membentuk dokumen `.docx` dengan PHPWord |
| `DashboardService` | Menyusun ringkasan dashboard per peran |

Controller tidak pernah menghitung bobot, progres, maupun deviasi. Hal ini menjaga satu sumber
kebenaran perhitungan sehingga tampilan layar, laporan, dan hasil export selalu konsisten.

### 1.3 Penanganan Error

`bootstrap/app.php` mengubah seluruh exception pada rute `api/*` menjadi JSON berbahasa Indonesia:

| Status | Pesan |
|---|---|
| 401 | Sesi kamu telah berakhir. Silakan login kembali. |
| 403 | Kamu tidak memiliki akses ke halaman ini. |
| 404 | Data yang kamu cari tidak ditemukan. |
| 422 | Ditangani Laravel sehingga daftar error per field tetap dikembalikan |
| 500 | Terjadi kesalahan pada server. |

## 2. Autentikasi dan Otorisasi

### 2.1 Alur autentikasi

```
React: POST /api/login {username, password}
   └─► AuthController: verifikasi kredensial + status akun aktif
        └─► Sanctum: createToken() → plain text token
             └─► React menyimpan token di localStorage, memasangnya pada setiap request
                  └─► GET /api/me memulihkan sesi saat halaman dimuat ulang
                       └─► Redirect ke dashboard sesuai peran
```

Token dikirim melalui header `Authorization: Bearer`. Interceptor Axios menangkap respons 401,
menghapus token, dan mengarahkan pengguna ke halaman login.

### 2.2 Tiga lapis otorisasi

1. **Middleware `role`** — membatasi endpoint berdasarkan peran (`role:ADMIN`, `role:ADMIN,QS`) dan
   menolak akun yang dinonaktifkan.
2. **Policy** — `ProjectPolicy`, `ProgressReportPolicy`, `UserPolicy` menentukan hak atas objek
   tertentu, misalnya QS hanya boleh mengubah laporannya sendiri yang masih berstatus `DRAFT`.
3. **Query scope** — `Project::visibleTo($user)` membatasi daftar proyek yang dikembalikan sehingga QS
   hanya menerima proyek yang ditugaskan kepadanya.

Frontend hanya menyembunyikan menu sebagai kenyamanan tampilan; percobaan mengakses endpoint peran
lain secara manual tetap ditolak backend dengan HTTP 403.

## 3. Arsitektur Frontend (React)

### 3.1 Lapisan

| Lapisan | Direktori | Tanggung jawab |
|---|---|---|
| Entry | `src/main.tsx` | QueryClient, Router, AuthProvider, ToastProvider |
| Routing | `src/routes` | `ProtectedRoute` (wajib login) dan `RoleRoute` (pembatasan peran) |
| Layout | `src/components/layout` | Sidebar (drawer pada mobile), Navbar, AppLayout |
| Halaman | `src/pages` | Satu berkas per halaman; tab detail proyek dipisah ke `pages/projects/tabs` |
| Komponen | `src/components/{ui,charts,reports}` | Komponen reusable |
| Hooks | `src/hooks` | `useAuth`, `useToast`, dan seluruh query TanStack |
| Service | `src/services` | Satu-satunya tempat pemanggilan API |
| Types | `src/types` | Tipe bersama, tanpa `any` |
| Utils | `src/utils` | Format angka, persen, rupiah, tanggal gaya Indonesia |

### 3.2 Service layer dan TanStack Query

Komponen tidak pernah memanggil Axios secara langsung. Alur data:

```
Komponen ──► hooks/queries.ts (useProjects, useCurveS, ...) ──► services/*.ts ──► lib/api.ts (Axios) ──► Laravel
```

`hooks/queries.ts` memusatkan seluruh query key (`qk`) sehingga invalidasi cache konsisten. Contoh:
menyimpan laporan progres meng-invalidasi `['progress']`, `qk.dashboard`, dan `qk.curve(projectId)`
agar dashboard dan Kurva S ikut diperbarui.

Mutasi memakai `useMutation` dengan penanganan error terpusat: error 422 dipetakan ke pesan per field
melalui `errorValidasi()`, error lain ditampilkan sebagai toast melalui `pesanError()`.

### 3.3 Komponen reusable

`Button`, `Input`, `Select`, `Textarea`, `DatePicker`, `Modal`, `ConfirmDialog`, `Badge`,
`StatusBadge`, `ReportStatusBadge`, `DeviationBadge`, `Card`, `Table`/`TableWrap`/`Th`/`Td`,
`Pagination`, `ProgressBar`, `Tabs`, `FileUpload`, `Toast`, `LoadingState`, `EmptyState`,
`ErrorState`, `KpiCard`, `CurveSChart`, `StatusDonut`, `ProgressComparisonChart`,
`ReportHeader`/`ReportSignature`, `WeeklyReportTable`, `MonthlyReportTable`, `DailyReportTable`.

### 3.4 Desain

Palet warna mengikuti design reference: primary `#D71920`, navy `#071A52`, background `#F5F7FA`,
card `#FFFFFF`, teks `#172033`/`#64748B`, success `#16A344`, warning `#F59E0B`, error `#DC2626`.
Token warna didefinisikan sekali pada `src/index.css` melalui `@theme` Tailwind 4. Warna logo
perusahaan tidak diubah.

Responsif: sidebar menjadi drawer di bawah `lg`, kartu dan form menjadi satu kolom, tabel proyek
berubah menjadi daftar kartu di bawah `md`, tabel laporan mempertahankan struktur kolom dan digulir
horizontal, chart memakai `ResponsiveContainer`.

## 4. Alur Data Utama

### 4.1 Pembentukan rencana dan Kurva S

```
Admin membuat/mengubah tanggal proyek
   └─► ProjectScheduleService: periode M-I, M-II, ... disinkronkan dari durasi proyek
        (periode berlebih dihapus, rentang pekerjaan dipotong, laporan progres dipetakan ulang)
Admin menambah pekerjaan (volume, satuan, harga satuan, Periode Mulai, Periode Selesai)
   ├─► WeightCalculatorService: harga_pekerjaan dan bobot dihitung ulang untuk seluruh pekerjaan
   └─► WorkPlanService: volume dibagi rata ke periode aktif sebagai target awal
Admin menyesuaikan target volume pada periode aktif
   └─► WorkPlanService: target_persentase dan target_bobot dihitung, total divalidasi
        └─► CurveSService: rencana kumulatif per periode → garis rencana pada Kurva S (tanpa tombol generate)
```

### 4.2 Pencatatan progres

```
QS mengisi form progres (multipart/form-data)
   └─► StoreProgressReportRequest: validasi field, tipe dan ukuran foto
        └─► ProgressService:
              • periode ditentukan dari tanggal laporan
              • persentase dan bobot realisasi dihitung per pekerjaan
              • sisa volume divalidasi
              • foto disimpan ke Laravel Storage, path dicatat di database
              • material dan kendala disimpan sebagai relasi
        └─► Status DIKIRIM membuat laporan ikut dihitung pada progres aktual
```

### 4.3 Monitoring kontraktor

```
GET /api/projects/{id}/curve-s
   └─► CurveSService.build(): titik rencana/realisasi kumulatif, deviasi, milestone
        └─► Recharts menggambar dua garis dan menandai milestone
GET /api/progress?project_id=...
   └─► Laporan QS, foto, material, kendala, alasan keterlambatan, tindak lanjut
```

Kontraktor tidak memiliki endpoint yang dapat mengubah data progres QS.

## 5. Pembentukan Laporan

`ReportService` menyusun struktur data yang sama untuk tampilan layar maupun export sehingga angka
selalu identik.

| Jenis | Sumber data | Isi khas |
|---|---|---|
| Harian | `progress_reports` pada rentang tanggal | Pekerjaan, volume realisasi, material, kendala, foto, lokasi, waktu pelaporan |
| Mingguan | Akumulasi `progress_details` terhadap periode berjalan | Realisasi minggu lalu / minggu ini / s/d minggu ini (volume + bobot), rencana kumulatif, deviasi |
| Bulanan | `work_plans` + akumulasi realisasi | Grid jangka waktu per minggu, rencana mingguan & kumulatif, realisasi mingguan & kumulatif, deviasi, realisasi bulan lalu / bulan ini / s/d bulan ini |
| Milestone | `milestones` + Kurva S | Target vs capaian tiap tahapan penting pada kurva progres |

Baris dikelompokkan per kategori pekerjaan (A, B, ...) beserta subtotal, lalu ditutup baris JUMLAH,
mengikuti dokumen laporan resmi.

### Export

- **Excel** — `Maatwebsite\Excel` dengan kelas export yang membangun array dua dimensi dan mengatur
  merge sel, border, format angka, serta orientasi landscape. Struktur kolom mengikuti dokumen asli,
  termasuk kolom grup REALISASI MINGGU LALU / MINGGU INI / S/D MINGGU INI pada laporan mingguan dan
  grid jangka waktu pada laporan bulanan.
- **Word** — PHPWord membangun tabel sebenarnya (bukan tangkapan layar), lengkap dengan blok identitas
  proyek, baris rekap rencana/realisasi/deviasi, dan blok tanda tangan Konsultan Pengawas serta
  Kontraktor Pelaksana.

Setiap export dicatat pada tabel `report_documents` sehingga riwayat dokumen dapat ditelusuri dan
diunduh ulang.

## 6. Daftar Endpoint API

| Metode | Endpoint | Akses |
|---|---|---|
| POST | `/api/login` | publik (dibatasi 10 percobaan/menit) |
| GET | `/api/me` | terautentikasi |
| POST | `/api/logout` | terautentikasi |
| GET | `/api/dashboard` | terautentikasi (isi menyesuaikan peran) |
| GET | `/api/roles`, `/api/units` | terautentikasi |
| POST | `/api/units` | Admin |
| GET/POST/PUT/DELETE | `/api/users`, `/api/users/{user}` | Admin |
| PATCH | `/api/users/{user}/toggle-active` | Admin |
| GET | `/api/projects`, `/api/projects/{project}` | terautentikasi (QS dibatasi penugasan) |
| POST/PUT/DELETE | `/api/projects`, `/api/projects/{project}` | Admin |
| GET | `/api/projects/{project}/work-categories` | terautentikasi |
| POST/PUT/DELETE | `/api/projects/{project}/work-categories[/{workCategory}]` | Admin |
| GET | `/api/projects/{project}/work-items[/{workItem}]` | terautentikasi |
| POST/PUT/DELETE | `/api/projects/{project}/work-items[/{workItem}]` | Admin |
| POST | `/api/projects/{project}/work-items/batch` (kelompok baru/yang ada + daftar pekerjaan sekaligus) | Admin |
| GET | `/api/projects/{project}/periods` (hanya baca; dibentuk otomatis) | terautentikasi |
| GET | `/api/projects/{project}/work-plans` | terautentikasi |
| POST | `/api/projects/{project}/work-plans` | Admin |
| GET | `/api/projects/{project}/milestones` | terautentikasi |
| POST/PUT/DELETE | `/api/projects/{project}/milestones[/{milestone}]` | Admin |
| GET | `/api/projects/{project}/curve-s` | terautentikasi |
| GET | `/api/progress`, `/api/progress/{progress}` | terautentikasi |
| POST | `/api/progress` | Admin, QS |
| POST | `/api/progress/{progress}` | Admin, QS (pembaruan, mendukung unggah foto) |
| PATCH | `/api/progress/{progress}/submit` | Admin, QS |
| DELETE | `/api/progress/{progress}`, `/api/progress-photos/{photo}` | Admin, QS |
| GET | `/api/reports/daily`, `/weekly`, `/monthly`, `/milestone`, `/documents` | terautentikasi |
| POST | `/api/reports/export/excel`, `/api/reports/export/word` | terautentikasi |

Seluruh respons memakai API Resource sehingga bentuk JSON konsisten: koleksi berpaginasi
mengembalikan `{ data, links, meta }`, sedangkan aksi tunggal mengembalikan `{ message, data }`.

## 7. Keputusan Teknis yang Didokumentasikan

1. **Laravel 12 (bukan 13).** PHP yang tersedia pada lingkungan pengembangan adalah 8.2, sedangkan
   Laravel 13 mensyaratkan PHP 8.3. Laravel 12 dipilih agar aplikasi berjalan tanpa memaksa
   pemasangan PHP baru pada sistem.
2. **Autentikasi token Sanctum (bukan mode cookie/SPA stateful).** Frontend dan backend berjalan pada
   origin berbeda; mode token menghilangkan kebutuhan konfigurasi CSRF lintas domain tanpa mengurangi
   keamanan karena otorisasi tetap dilakukan backend.
3. **Periode mingguan sebagai satuan dasar.** Dokumen laporan memakai kolom "MINGGU KE", sehingga
   periode mingguan menjadi satuan paling kecil yang dapat diagregasi menjadi bulanan.
4. **`jangka_waktu_hari` sebagai isian SPK.** Dokumen referensi menyebut 45 hari kalender sementara
   grid pelaksanaannya 6 minggu. Nilai ini karenanya disimpan sebagai data SPK dan tidak dipaksa sama
   dengan selisih tanggal; bila dikosongkan, sistem menghitungnya dari tanggal pelaksanaan.
5. **Bobot selalu dihitung sistem.** Harga satuan wajib diisi Admin; bobot pekerjaan dihitung dari
   harga pekerjaan terhadap total harga proyek dan tidak dapat diinput manual. Sistem tidak pernah
   mengarang nilai harga.
6. **`projects.qs_user_id` dipertahankan bersama `project_assignments`.** PRD menyebut satu QS
   penanggung jawab per proyek, sedangkan tabel penugasan memberi ruang untuk QS tambahan. Keduanya
   disinkronkan otomatis saat proyek disimpan.
7. **Kontraktor melihat seluruh proyek.** PRD tidak membatasi kontraktor pada proyek tertentu karena
   perannya adalah pemantauan perusahaan; pembatasan per proyek dapat ditambahkan melalui
   `project_assignments` bila kemudian dibutuhkan.
