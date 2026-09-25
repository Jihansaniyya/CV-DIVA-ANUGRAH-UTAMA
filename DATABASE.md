# Struktur Database

Database: **MySQL / MariaDB**, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
Seluruh struktur dibentuk melalui Laravel Migration (`backend/database/migrations`).

---

## 1. Hasil Validasi terhadap ERD Draft

ERD yang diberikan diperlakukan sebagai draft. Berikut koreksi yang diterapkan beserta alasannya.

| # | Kondisi pada ERD draft | Masalah | Koreksi yang diterapkan |
|---|---|---|---|
| 1 | `proyek.nama_qs` disimpan bersama FK `id_user` | Duplikasi data dari `users.nama`; menimbulkan anomali pembaruan | Kolom nama dihapus, cukup FK `projects.qs_user_id` |
| 2 | `proyek` tidak memiliki `nama_proyek` | Header laporan mewajibkan kolom "PEKERJAAN" | Ditambahkan `nama_proyek` |
| 3 | `pekerjaan.nama_satuan` bersama FK `id_satuan` | Duplikasi dari tabel `satuan` | Kolom nama dihapus, relasi ke `units` |
| 4 | `pekerjaan.target_volume` & `target_progres_persen` | Target disimpan per pekerjaan, padahal rencana bersifat **pekerjaan × periode**. Kurva S dan grid mingguan pada laporan bulanan tidak dapat dibentuk | Dipindahkan ke tabel `periods` dan `work_plans` |
| 5 | `laporan_progres` memuat `id_pekerjaan` langsung | Satu laporan harian dapat mencakup beberapa pekerjaan; foto/material/kendala berada pada level yang salah | Dipecah menjadi `progress_reports` (header) dan `progress_details` (per pekerjaan) |
| 6 | Tidak ada tabel material | PRD mewajibkan pencatatan material pada pelaporan progres | Ditambahkan `progress_materials` |
| 7 | `kendala_progres` berelasi satu-ke-satu | Satu laporan dapat memuat lebih dari satu kendala | Diubah menjadi satu-ke-banyak `progress_issues` + kolom `status` |
| 8 | Tidak ada data harga | Laporan mingguan & bulanan memuat Harga Satuan dan Harga Pekerjaan; bobot dihitung dari harga | Ditambahkan `harga_satuan` dan `harga_pekerjaan` (nullable) |
| 9 | Tidak ada pengelompokan pekerjaan | Laporan memakai kelompok A, B, ... beserta subtotal ("JUMLAH PEKERJAAN PENDAHULUAN") | Ditambahkan `work_categories` |
| 10 | Tidak ada milestone dan tabel peran | Diminta PRD (milestone pada kurva progres) dan kebutuhan hak akses | Ditambahkan `milestones` dan `roles` |
| 11 | `proyek` tidak menyimpan kontraktor/konsultan | Header dan blok tanda tangan laporan membutuhkannya | Ditambahkan `kontraktor_pelaksana`, `konsultan_pengawas`, `nama_site_engineer`, `nama_pelaksana_lapangan` |
| 12 | `dokumen_laporan` | Konsep dipertahankan | Dinamai `report_documents` mengikuti konvensi Laravel |

Yang **dipertahankan** dari ERD draft: satu tabel `users` dengan peran (tidak ada tabel QS terpisah),
`satuan` sebagai master data, penyimpanan path foto pada tabel tersendiri, dan pencatatan dokumen
laporan yang pernah digenerate.

## 2. Daftar Tabel

### `roles`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK bigint | |
| `code` | varchar(30) unique | `ADMIN`, `QS`, `KONTRAKTOR` |
| `name`, `description` | varchar | |

### `users`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `role_id` | FK → `roles.id` | `restrictOnDelete` |
| `name` | varchar | |
| `username` | varchar(50) unique | dipakai untuk login |
| `email` | varchar unique nullable | |
| `phone` | varchar(30) nullable | |
| `password` | varchar | hash bcrypt |
| `is_active` | boolean | mengaktifkan/menonaktifkan akun |
| `last_login_at` | timestamp nullable | |

### `units` (satuan pekerjaan)
`id`, `code` (unique: m3, m2, m1, kg, ton, ls, bh, unit, ttk), `name`.

### `projects`
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `nama_proyek` | varchar | |
| `nomor_spk` | varchar nullable | |
| `lokasi` | varchar | |
| `sumber_dana` | varchar nullable | |
| `tahun_anggaran` | year nullable | |
| `tanggal_spk` | date nullable | |
| `tanggal_mulai`, `tanggal_selesai` | date | dasar pembentukan periode |
| `jangka_waktu_hari` | smallint nullable | sesuai SPK; bila kosong dihitung dari tanggal pelaksanaan |
| `kontraktor_pelaksana`, `konsultan_pengawas` | varchar (wajib diisi pada form) | header laporan |
| `nama_site_engineer` (wajib diisi pada form), `nama_pelaksana_lapangan` | varchar | blok tanda tangan laporan |
| `qs_user_id` | FK → `users.id` nullable | QS penanggung jawab utama |
| `created_by` | FK → `users.id` nullable | |
| `status` | enum | `BELUM_DIMULAI`, `BERJALAN`, `SELESAI`, `TERLAMBAT` |
| `keterangan` | text nullable | |
| `deleted_at` | soft delete | |

### `project_assignments`
`id`, `project_id` FK, `user_id` FK, `peran` enum(`QS`,`KONTRAKTOR`), `is_primary` boolean.
Unique `(project_id, user_id)`. Dipakai untuk penugasan tambahan; QS penanggung jawab utama tetap
tercatat pada `projects.qs_user_id` dan disinkronkan ke tabel ini.

### `work_categories`
`id`, `project_id` FK, `kode` (A, B, ...), `nama`, `urutan`. Menjadi pengelompokan baris pada laporan.

### `work_items` (data pekerjaan)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | PK | |
| `project_id` | FK cascade | |
| `work_category_id` | FK nullable | |
| `unit_id` | FK → `units.id` | |
| `uraian_pekerjaan` | varchar | |
| `volume` | decimal(15,3) | volume rencana |
| `harga_satuan` | decimal(18,2) nullable | |
| `harga_pekerjaan` | decimal(18,2) nullable | hasil hitung `volume × harga_satuan` |
| `bobot` | decimal(9,4) | bobot efektif (%) hasil perhitungan |
| `period_mulai_id`, `period_selesai_id` | FK → `periods.id` nullable | Periode Mulai/Selesai (mis. M-II s/d M-V); menentukan periode aktif rencana |
| `urutan` | smallint | urutan tampil pada laporan |

### `periods` (periode pelaksanaan)
`id`, `project_id` FK, `urutan` (unique bersama `project_id`), `nama_periode` ("M-I", "M-II", ...),
`bulan_ke`, `minggu_ke`, `minggu_ke_bulan`, `tanggal_mulai`, `tanggal_selesai`.

### `work_plans` (rencana pekerjaan per periode)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `project_id`, `work_item_id`, `period_id` | FK | unique `(work_item_id, period_id)` |
| `target_volume` | decimal(15,4) | target volume periode tersebut; hanya pada periode aktif pekerjaan |
| `target_persentase` | decimal(9,4) | terhadap volume pekerjaan |
| `target_bobot` | decimal(9,4) | kontribusi ke progres proyek |
| `catatan` | text nullable | |

### `milestones`
`id`, `project_id` FK, `period_id` FK nullable, `nama`, `deskripsi`, `tanggal_target`,
`target_persentase`, `status` enum(`BELUM_TERCAPAI`,`TERCAPAI`,`TERLAMBAT`).

### `progress_reports` (header laporan progres harian)
`id`, `project_id` FK, `user_id` FK (QS pelapor), `period_id` FK nullable (diisi otomatis dari
`tanggal_laporan`), `tanggal_laporan`, `keterangan`, `lokasi`, `cuaca`,
`status` enum(`DRAFT`,`DIKIRIM`), `dikirim_pada`.

### `progress_details` (realisasi per pekerjaan)
`id`, `progress_report_id` FK, `work_item_id` FK, `volume_realisasi` decimal(15,3) **(bukan kumulatif)**,
`persentase_realisasi` decimal(9,4), `bobot_realisasi` decimal(9,4), `keterangan`.
Unique `(progress_report_id, work_item_id)`.

### `progress_photos`
`id`, `progress_report_id` FK, `progress_detail_id` FK nullable, `file_path`, `original_name`,
`file_size`, `caption`, `diunggah_pada`.

### `progress_materials`
`id`, `progress_report_id` FK, `nama_material`, `jumlah` decimal(15,3), `unit_id` FK nullable,
`satuan` varchar nullable, `keterangan`.

### `progress_issues`
`id`, `progress_report_id` FK, `work_item_id` FK nullable,
`jenis_kendala` enum(`CUACA`,`MATERIAL`,`TENAGA_KERJA`,`PERALATAN`,`TEKNIS`,`LAINNYA`),
`deskripsi`, `alasan_keterlambatan`, `tindak_lanjut`,
`status` enum(`TERBUKA`,`DALAM_PENANGANAN`,`SELESAI`).

### `report_documents`
`id`, `project_id` FK, `user_id` FK nullable, `tipe_laporan` enum(`HARIAN`,`MINGGUAN`,`BULANAN`,`MILESTONE`),
`format` enum(`EXCEL`,`WORD`), `periode_mulai`, `periode_selesai`, `file_path`, `file_name`, `digenerate_pada`.

## 3. Relasi dan Kardinalitas

```
roles 1 ──< N users
users 1 ──< N projects            (sebagai QS penanggung jawab: projects.qs_user_id)
users N >──< N projects           (melalui project_assignments)
users 1 ──< N progress_reports    (QS pelapor)

projects 1 ──< N work_categories
projects 1 ──< N work_items
projects 1 ──< N periods
projects 1 ──< N work_plans
projects 1 ──< N milestones
projects 1 ──< N progress_reports
projects 1 ──< N report_documents

units    1 ──< N work_items
units    1 ──< N progress_materials

work_categories 1 ──< N work_items
work_items      1 ──< N work_plans
periods         1 ──< N work_plans          (unique work_item_id + period_id)
periods         1 ──< N progress_reports
periods         1 ──< N milestones

work_items      1 ──< N progress_details
progress_reports 1 ──< N progress_details
progress_reports 1 ──< N progress_photos
progress_reports 1 ──< N progress_materials
progress_reports 1 ──< N progress_issues
progress_details 1 ──< N progress_photos    (opsional, foto per pekerjaan)
```

Aturan penghapusan: pekerjaan, periode, rencana, dan laporan dihapus mengikuti proyeknya (`cascade`);
`units` dan `roles` memakai `restrict` agar master data tidak terhapus saat masih dipakai; referensi ke
pengguna memakai `nullOnDelete` agar riwayat proyek tetap utuh.

## 4. Formula Perhitungan

Seluruh formula diimplementasikan pada `backend/app/Services`, bukan pada controller maupun frontend.

### 4.1 Bobot pekerjaan — `WeightCalculatorService`

```
harga_pekerjaan(i) = volume(i) × harga_satuan(i)
bobot(i)           = harga_pekerjaan(i) / Σ harga_pekerjaan × 100
```

Formula ini mengikuti kolom BOBOT (%) pada laporan mingguan/bulanan CV Diva Anugrah Utama.
`harga_satuan` wajib diisi Admin, sedangkan `harga_pekerjaan` dan `bobot` selalu hasil perhitungan
(read-only). Bobot tidak dapat diinput manual. Sistem tidak pernah membangkitkan nilai harga maupun
bobot secara acak.

Perubahan volume atau harga memicu perhitungan ulang bobot sekaligus menyelaraskan `work_plans.target_bobot`
dan `progress_details.bobot_realisasi` agar data turunan tetap konsisten.

### 4.2 Rencana pekerjaan — `WorkPlanService`

```
target_persentase = target_volume / volume pekerjaan × 100
target_bobot      = target_persentase × bobot pekerjaan / 100
rencana periode   = Σ target_bobot seluruh pekerjaan pada periode tersebut
rencana kumulatif = penjumlahan berjalan rencana periode sejak periode pertama
```

Admin hanya mengisi `target_volume` per periode; `target_persentase` dan `target_bobot` dihitung sistem.
Setara dengan `target_bobot = target_volume / volume pekerjaan × bobot pekerjaan`, sehingga bila seluruh
volume sudah direncanakan, `Σ target_bobot` satu pekerjaan sama dengan bobot pekerjaannya.
Contoh Beton K-250 (volume 23,87 m³, bobot 40,35%): target M-II..M-V = 4 / 8 / 7 / 4,87 m³ menghasilkan
bobot rencana 6,76 / 13,52 / 11,83 / 8,24 % (total 40,35%).

Batasan: `Σ target_volume` seluruh periode untuk satu pekerjaan tidak boleh melebihi volume rencananya
(`sisa = volume − Σ target_volume` tidak boleh negatif), dan target hanya boleh diisi pada periode aktif
pekerjaan (§4.7). Jumlah periode mengikuti durasi proyek (§4.6).

### 4.3 Progres aktual — `ProgressService`

```
persentase_realisasi = volume_realisasi / volume pekerjaan × 100
bobot_realisasi      = persentase_realisasi × bobot pekerjaan / 100
progres proyek       = Σ bobot_realisasi seluruh laporan berstatus DIKIRIM
```

Laporan berstatus `DRAFT` tidak ikut dihitung. Realisasi kumulatif sebuah pekerjaan tidak boleh
melebihi volume rencananya.

### 4.4 Kurva S dan deviasi — `CurveSService`

```
rencana kumulatif(p)  = Σ target_bobot periode 1..p
realisasi kumulatif(p) = Σ bobot_realisasi laporan DIKIRIM pada periode 1..p
deviasi(p)            = realisasi kumulatif(p) − rencana kumulatif(p)
```

Deviasi negatif berarti realisasi tertinggal dari rencana. Garis realisasi hanya digambar sampai
periode yang sudah berjalan (`tanggal_mulai <= hari ini`) agar periode yang belum dilaksanakan tidak
terbaca sebagai realisasi 0%.

### 4.5 Akumulasi laporan — `ReportService`

Contoh sesuai kebutuhan laporan (volume pekerjaan 100 m³):

```
realisasi minggu lalu    = Σ volume laporan DIKIRIM sejak awal proyek s/d sehari sebelum periode berjalan   → 40 m³
realisasi minggu ini     = Σ volume laporan DIKIRIM dalam rentang periode berjalan                          → 20 m³
realisasi s/d minggu ini = minggu lalu + minggu ini                                                          → 60 m³
persentase               = 60 / 100 × 100%                                                                   → 60%
bobot realisasi          = persentase × bobot pekerjaan / 100
```

Prinsip yang sama dipakai pada laporan bulanan dengan istilah *realisasi bulan lalu*, *realisasi bulan
ini*, dan *realisasi s/d bulan ini*.

### 4.6 Periode pelaksanaan — `ProjectScheduleService`

Periode dasar aplikasi adalah **mingguan** (7 hari kalender) berlabel `M-I`, `M-II`, ... mengikuti kolom
"MINGGU KE" pada laporan bulanan. Jumlah periode = `ceil(jumlah hari pelaksanaan / 7)`, misalnya 45 hari
= 7 minggu (M-I s/d M-VII). Pengelompokan bulan memakai 4 minggu per bulan (BULAN I memuat minggu I–IV,
BULAN II memuat minggu V–VIII).

Periode proyek adalah sumber tunggal bagi Data Pekerjaan, Rencana, Progres, Kurva S, dan Laporan, dan
tidak dapat dibuat atau dihapus manual. Setiap perubahan tanggal proyek menyinkronkan periode:

- periode yang sudah ada dipertahankan (id tetap) dan tanggalnya diperbarui; periode baru ditambahkan;
- bila durasi berkurang, periode berlebih dihapus beserta rencananya, Periode Mulai/Selesai pekerjaan
  yang melewati periode terakhir dipotong, dan target volume pekerjaan tersebut dibagi rata ulang;
- `progress_reports.period_id` dipetakan ulang dari `tanggal_laporan`.

### 4.7 Periode aktif dan target awal pekerjaan — `WorkPlanService`

Admin memilih Periode Mulai dan Periode Selesai. Periode aktif = seluruh periode di antara keduanya
(inklusif); target volume hanya dapat diisi pada periode aktif.

```
target awal per periode = volume / jumlah periode aktif      (sisa pembulatan ke periode terakhir)
contoh Beton K-250      = 23,87 / 4 (M-II s/d M-V) = 5,9675 m³ per minggu
```

Periode Mulai/Selesai diubah → target dibagi rata ulang. Hanya volume diubah → setiap target
diskalakan proporsional (`target × volume baru / volume lama`) sehingga pola rencana tetap terjaga.

## 5. Verifikasi terhadap Dokumen Referensi

Seeder proyek pertama memakai data asli laporan referensi. Hasil perhitungan aplikasi:

| Uraian | Volume | Harga Satuan | Harga Pekerjaan | Bobot aplikasi | Bobot dokumen |
|---|---:|---:|---:|---:|---:|
| Papan Nama Kegiatan | 1,00 | 500.000,00 | 500.000,00 | 0,2905 | 0,29 |
| Biaya keselamatan & kesehatan kerja | 1,00 | 1.470.000,00 | 1.470.000,00 | 0,8540 | 0,85 |
| Normalisasi Saluran | 22,68 | 179.299,99 | 4.066.523,77 | 2,3624 | 2,36 |
| Pek. Beton K-250 Ready Mix | 23,87 | 2.909.892,81 | 69.459.141,37 | 40,3518 | 40,35 |
| Pek. Pembesian Polos | 1.874,37 | 25.096,50 | 47.040.126,71 | 27,3276 | 27,33 |
| Pek. Bekisting | 65,26 | 301.680,49 | 19.687.668,78 | 11,4374 | 11,44 |
| Mainhole (Grill) | 53,00 | 564.349,49 | 29.910.522,97 | 17,3763 | 17,38 |
| **Jumlah** | | | **172.133.983,60** | **100,00** | **100,00** |

Rencana mingguan yang dihasilkan: 3,50 / 19,78 / 19,78 / 19,78 / 19,78 / 17,38 dengan kumulatif
3,50 / 23,28 / 43,06 / 62,84 / 82,62 / 100,00 — identik dengan dokumen laporan bulanan referensi.
