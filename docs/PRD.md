# PRD: HIPPAM — Sistem Informasi Pengelolaan Air Minum

## 1. Ikhtisar Proyek

### 1.1 Latar Belakang

**HIPPAM (Himpunan Penduduk Pemakai Air Minum)** adalah organisasi masyarakat yang mengelola penyediaan air bersih di wilayah pedesaan Indonesia. HIPPAM berfungsi sebagai alternatif bagi masyarakat yang belum terjangkau PDAM (Perusahaan Daerah Air Minum). Setiap unit HIPPAM biasanya melayani satu desa, mengelola sumber air (sumur bor/mata air), dan mendistribusikan air melalui jaringan perpipaan ke rumah-rumah warga.

Saat ini, mayoritas unit HIPPAM masih mengelola pencatatan pemakaian air dan penagihan secara manual — menggunakan buku tulis atau spreadsheet. Hal ini menyebabkan:

- **Ketidakakuratan data** pemakaian air dan pembayaran
- **Proses penagihan lambat** karena harus mendatangi setiap pelanggan
- **Kesulitan membuat laporan** untuk pengurus dan pemerintah desa
- **Tidak ada rekam jejak digital** yang memudahkan audit

### 1.2 Tujuan Aplikasi

Membangun aplikasi web berbasis **Laravel 13** yang membantu unit HIPPAM mengelola:

1. **Data pelanggan** dan meter air
2. **Pencatatan pemakaian air** bulanan berdasarkan meter reading
3. **Pembuatan tagihan** otomatis berdasarkan volume pemakaian
4. **Pelacakan pembayaran** (lunas, belum lunas, cicilan)
5. **Pencetakan invoice/kwitansi** melalui printer thermal
6. **Laporan dan dashboard** untuk monitoring

### 1.3 Arsitektur Multi-Tenant

Aplikasi bersifat **multi-tenant** dengan pendekatan **row-level isolation** — satu database, satu codebase, data dipisahkan per unit HIPPAM menggunakan `tenant_id` pada setiap tabel.

**Satu tenant = satu unit HIPPAM** (biasanya satu desa).

---

## 2. Pengguna Sistem

### 2.1 Peran Pengguna

| Peran | Deskripsi | Akses |
|-------|-----------|-------|
| **Super Admin** | Mengelola seluruh unit HIPPAM, mendaftarkan tenant baru, melihat laporan lintas tenant | Semua tenant, pengaturan sistem |
| **Operator HIPPAM** | Mengelola operasional harian satu unit HIPPAM: pelanggan, meter, tagihan, pembayaran, cetak invoice | Hanya tenant miliknya |

### 2.2 Alur Pengguna

#### Super Admin
1. Mendaftarkan unit HIPPAM baru (nama, alamat, kontak, tarif air)
2. Membuat akun Operator untuk setiap unit HIPPAM
3. Memantau dashboard ringkasan seluruh unit
4. Melihat dan mengekspor laporan konsolidasi

#### Operator HIPPAM
1. Mengelola data pelanggan (tambah, ubah, nonaktifkan)
2. Mencatat pembacaan meter air setiap bulan
3. Membuat/meninjau tagihan bulanan
4. Mencatat pembayaran dari pelanggan
5. Mencetak kwitansi/invoice via printer thermal
6. Melihat laporan pemakaian dan pendapatan

---

## 3. Ruang Lingkup Fitur

### 3.1 Manajemen Tenant (Super Admin)

| Fitur | Deskripsi |
|-------|-----------|
| CRUD Unit HIPPAM | Nama unit, alamat, desa, kecamatan, kabupaten, kontak pengelola |
| Konfigurasi Tarif | Setiap tenant memiliki tarif per m³ sendiri (flat rate) |
| Manajemen Operator | Buat akun operator, assign ke tenant |
| Dashboard Super Admin | Ringkasan: jumlah tenant, total pelanggan, total pendapatan bulan ini |

### 3.2 Manajemen Pelanggan

| Fitur | Deskripsi |
|-------|-----------|
| CRUD Pelanggan | Nama, alamat, no. telepon, nomor pelanggan (auto-generated), status (aktif/nonaktif) |
| Pemasangan Meter | Nomor meter, merek, tanggal pemasangan, status meter. Relasi 1:1 dengan pelanggan. |
| Riwayat Pemakaian | Historis pembacaan meter per pelanggan |

**Data Pelanggan:**
- `id`
- `tenant_id`
- `nama`
- `alamat`
- `no_telepon`
- `nomor_pelanggan` (auto, format: `HPP-{tenant_code}-{seq}`)
- `status` (aktif/nonaktif)
- `tanggal_daftar`
- `catatan`

### 3.3 Pencatatan Pemakaian Air (Meterage)

| Fitur | Deskripsi |
|-------|-----------|
| Pembacaan Meter Bulanan | Input: angka meter saat ini (sebelumnya auto-fetch dari periode lalu), tanggal baca |
| Hitung Otomatis | Volume = angka_meter_sekarang - angka_meter_sebelumnya (auto) |
| Validasi | Deteksi anomali (pemakaian negatif, lonjakan tidak wajar) — tampilkan warning, tetap bisa simpan |
| Batch Entry | Input beberapa pembacaan sekaligus |

**Data Pembacaan:**
- `id`
- `tenant_id`
- `pelanggan_id`
- `meter_id`
- `periode` (YYYY-MM)
- `angka_meter_sebelumnya`
- `angka_meter_sekarang`
- `volume_m3` (calculated)
- `tanggal_baca`
- `petugas_baca` (user_id)
- `status` (draft/konfirmasi)
- `catatan`

### 3.4 Penagihan

| Fitur | Deskripsi |
|-------|-----------|
| Generate Tagihan Bulanan | Otomatis dari data pembacaan meter × tarif tenant |
| Komponen Tagihan | Biaya air = volume × tarif per m³ (flat). Tidak ada biaya administrasi tambahan. |
| Status Tagihan | Belum bayar, Lunas, Cicilan, Batal |
| Tanggal Jatuh Tempo | Auto-calculated: tanggal X bulan berikutnya (configurable per tenant) |
| Pengingat | Daftar pelanggan yang belum bayar (bisa filter per periode, highlight lewat jatuh tempo) |
| Koreksi Tagihan | Operator dapat melakukan penyesuaian jika ada kesalahan |

**Data Tagihan:**
- `id`
- `tenant_id`
- `pelanggan_id`
- `pembacaan_id`
- `periode` (YYYY-MM)
- `volume_m3`
- `tarif_per_m3`
- `biaya_air` (= volume_m3 × tarif_per_m3)
- `total_tagihan` (= biaya_air, disimpan untuk audit trail jika tarif berubah di masa depan)
- `status` (belum_bayar/lunas/cicilan/batal)
- `tanggal_jatuh_tempo`
- `catatan`

### 3.5 Pembayaran

| Fitur | Deskripsi |
|-------|-----------|
| Catat Pembayaran | Tanggal bayar, jumlah bayar, metode bayar (tunai/transfer) |
| Partial Payment | Mendukung pembayaran cicilan |
| Koneksi ke Tagihan | Setiap pembayaran terkait dengan satu tagihan |
| Riwayat Pembayaran | Daftar semua pembayaran per pelanggan |
| cetak Kwitansi | Cetak ulang kwitansi untuk pembayaran yang sudah dicatat |

**Data Pembayaran:**
- `id`
- `tenant_id`
- `tagihan_id`
- `pelanggan_id`
- `tanggal_bayar`
- `jumlah_bayar`
- `metode_bayar` (tunai/transfer/ewallet)
- `no_referensi` (opsional, untuk transfer)
- `petugas_kasir` (user_id)
- `catatan`

### 3.6 Pencetakan Invoice/Kwitansi

| Fitur | Deskripsi |
|-------|-----------|
| Cetak Kwitansi Pembayaran | Download PDF kwitansi, cetak dari device operator via native print dialog |
| Cetak Invoice Tagihan | Download PDF invoice tagihan yang belum dibayar sebagai pengingat. Template sama dengan kwitansi, status menunjukkan "BELUM BAYAR". |
| Cetak Ulang | Download ulang PDF dari riwayat pembayaran |

**Arsitektur Pencetakan:**
- **Generate PDF** server-side menggunakan `barryvdh/laravel-dompdf`
- **Download PDF** ke device operator (HP/tablet/PC)
- **Cetak** via native print dialog device (Settings > Print > pilih printer Bluetooth/WiFi/USB)
- **Tidak ada ketergantungan Bluetooth** di sisi aplikasi — sepenuhnya diserahkan ke OS/device operator
- **Cross-platform**: Bekerja di Android, iOS, Windows, macOS — semua browser

**Lebar Kertas:**
- Support **58mm** dan **80mm**, konfigurasi per tenant di settings
- Layout template menyesuaikan otomatis berdasarkan konfigurasi tenant

**Konten Struk (confirmed layout):**

```
__________________________________________________________________
                      STRUK PEMBAYARAN AIR
            HIPPAM - Himpunan Penduduk Pemakai Air Minum
                        3/5/2026 10.28.47
__________________________________________________________________

DATA PELANGGAN
Nama:                                                           Ku
No. Meter:                                                     112
Periode:                                                  Mei 2026
__________________________________________________________________

PEMBACAAN METER
Meter Sebelumnya:                                            0 m³
Meter Sekarang:                                             17 m³
Konsumsi:                                                   17 m³
__________________________________________________________________

PERHITUNGAN TARIF
Tarif:                                            Rp 3.059/m³
Total Tagihan:                                     Rp 52.000
__________________________________________________________________

PEMBAYARAN
Jumlah Dibayar:                                    Rp 52.000
Tanggal Bayar:                                       2/5/2026
__________________________________________________________________

---------------------------
|      Status: LUNAS      |
---------------------------

                 Terima kasih atas pembayaran Anda
                       Semoga lancar selalu
                    Dicetak oleh Sistem HIPPAM
__________________________________________________________________
```

**Spesifikasi Layout:**
- Semua section dipisahkan garis horizontal (`________`)
- Label rata kiri, nilai rata kanan (right-aligned)
- Label section uppercase (DATA PELANGGAN, PEMBACAAN METER, dst.)
- Status tagihan ditampilkan di kotak bordered di tengah
- Footer: 3 baris centered (terima kasih, doa, nama sistem)
- Format angka: `Rp 52.000` (titik sebagai ribuan)
- Format tanggal: `d/m/Y` (contoh: `2/5/2026`)
- Format periode: `MMMM YYYY` dalam Bahasa Indonesia (contoh: `Mei 2026`)

### 3.7 Laporan & Dashboard

| Fitur | Deskripsi |
|-------|-----------|
| Dashboard Operator | Ringkasan: total pelanggan aktif, pendapatan bulan ini, tagihan belum lunas, grafik tren 6 bulan |
| Laporan Pemakaian Air | Per pelanggan, per periode, per wilayah — volume pemakaian rata-rata |
| Laporan Pendapatan | Harian, mingguan, bulanan — total tagihan vs total terbayar |
| Laporan Tunggakan | Daftar pelanggan dengan tagihan belum lunas, sort by jumlah/periode |
| Laporan Pelanggan | Daftar pelanggan baru, pelanggan nonaktif, per alamat |
| Export | Export laporan ke PDF dan Excel/CSV |

---

## 4. Arsitektur Teknis

### 4.1 Tech Stack

| Komponen | Teknologi |
|----------|-----------|
| Framework | **Laravel 13** (PHP 8.3+) |
| Database | **MySQL 8** |
| Frontend | **Blade** + **Tailwind CSS** + **Alpine.js** (dark/light theme toggle) |
| Caching/Queue | Redis |
| Auth | Laravel Breeze (session-based) |
| Thermal Print | `barryvdh/laravel-dompdf` — generate PDF struk, download, cetak via native print dialog |
| Excel/CSV | `maatwebsite/excel` |
| Charting | **Chart.js** (lightweight, works well with Alpine.js) |

### 4.2 Multi-Tenancy: Row-Level Isolation

**Strategi**: Satu database, `tenant_id` pada setiap tabel yang dimiliki tenant.

```
┌─────────────────────────────────────────────┐
│              Single Database                │
├─────────────────────────────────────────────┤
│  tenants                                    │
│  ├── id: 1 (HIPPAM Sari Tirto)              │
│  ├── id: 2 (HIPPAM Gumirih)                 │
│  └── id: 3 (HIPPAM Kepohbaru)              │
├─────────────────────────────────────────────┤
│  pelanggan  (semua punya tenant_id)         │
│  ├── tenant_id: 1 → 120 pelanggan          │
│  ├── tenant_id: 2 → 85 pelanggan           │
│  └── tenant_id: 3 → 200 pelanggan          │
├─────────────────────────────────────────────┤
│  pembacaan, tagihan, pembayaran             │
│  (semua punya tenant_id)                    │
└─────────────────────────────────────────────┘
```

**Implementasi:**

1. **`BelongsToTenant` trait** — Global scope otomatis menyaring query berdasarkan `tenant_id`
2. **Auto-set `tenant_id`** — Saat create record, `tenant_id` otomatis terisi dari current tenant
3. **`ResolveTenant` middleware** — Set current tenant ke container berdasarkan user yang login
4. **Composite index** — Setiap tabel punya index `[tenant_id, kolom_frequently_queried]`

### 4.3 Struktur Database (Core Tables)

```
tenants
├── id, nama_unit, kode_unit, alamat, desa, kecamatan, kabupaten
├── kontak_pengelola, no_telepon, email
├── tarif_per_m3 (decimal) — flat rate
├── jatuh_tempo_tanggal (integer) — tanggal jatuh tempo tiap bulan (contoh: 20)
├── printer_width (58mm/80mm) — untuk format PDF struk
└── timestamps

users
├── id, name, email, password
├── tenant_id (nullable — super admin punya null)
├── role (super_admin / operator)
└── timestamps

pelanggan (customers)
├── id, tenant_id
├── nama, alamat, no_telepon
├── nomor_pelanggan (unique per tenant)
├── status (aktif/nonaktif)
└── timestamps, catatan

meters
├── id, tenant_id, pelanggan_id
├── nomor_meter, merek, tanggal_pemasangan
├── status (aktif/rusak/nonaktif)
└── timestamps

pembacaan (meter_readings)
├── id, tenant_id, pelanggan_id, meter_id
├── periode (YYYY-MM), angka_meter_sebelumnya, angka_meter_sekarang
├── volume_m3, tanggal_baca
├── dibaca_oleh (user_id), status, catatan
└── timestamps

tagihan (bills)
├── id, tenant_id, pelanggan_id, pembacaan_id
├── periode (YYYY-MM), volume_m3, tarif_per_m3
├── biaya_air (= volume_m3 × tarif_per_m3), total_tagihan
├── status (belum_bayar/lunas/cicilan/batal), tanggal_jatuh_tempo, catatan
└── timestamps

pembayaran (payments)
├── id, tenant_id, tagihan_id, pelanggan_id
├── tanggal_bayar, jumlah_bayar, metode_bayar
├── no_referensi, petugas_kasir (user_id), catatan
└── timestamps
```

### 4.4 Struktur Aplikasi

```
app/
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Pelanggan.php           # use BelongsToTenant
│   ├── Meter.php               # use BelongsToTenant
│   ├── Pembacaan.php           # use BelongsToTenant
│   ├── Tagihan.php             # use BelongsToTenant
│   └── Pembayaran.php          # use BelongsToTenant
├── Traits/
│   └── BelongsToTenant.php     # Global scope + auto-set tenant_id
├── Http/
│   ├── Middleware/
│   │   ├── ResolveTenant.php   # Set current tenant from auth user
│   │   └── EnsureTenant.php    # Require active tenant context
│   └── Controllers/
│       ├── SuperAdmin/
│       │   ├── TenantController.php
│       │   └── DashboardController.php
│       └── Operator/
│           ├── DashboardController.php
│           ├── PelangganController.php
│           ├── MeterController.php
│           ├── PembacaanController.php
│           ├── TagihanController.php
│           ├── PembayaranController.php
│           └── LaporanController.php
├── Services/
│   ├── TagihanService.php      # Generate tagihan from pembacaan
│   ├── PembayaranService.php   # Process payment, update tagihan status
│   └── ReceiptPdfService.php    # Generate PDF struk/kwitansi
└── Exports/
    ├── PemakaianExport.php
    └── PendapatanExport.php

resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php       # Main layout (Tailwind + Alpine.js)
│   ├── super-admin/
│   │   ├── dashboard.blade.php
│   │   └── tenants/
│   ├── operator/
│   │   ├── dashboard.blade.php
│   │   ├── pelanggan/
│   │   ├── meter/
│   │   ├── pembacaan/
│   │   ├── tagihan/
│   │   ├── pembayaran/
│   │   └── laporan/
│   └── prints/
│       └── kwitansi.blade.php  # Thermal receipt template
└── css/app.css                 # Tailwind entry point
```

### 4.5 Rute Utama

```
# Auth
GET|POST  /login
POST      /logout

# Super Admin
GET       /super-admin/dashboard
GET|POST  /super-admin/tenants
GET|POST  /super-admin/tenants/{id}/edit
GET|POST  /super-admin/tenants/{id}/operator

# Operator — Dashboard
GET       /dashboard

# Operator — Pelanggan
GET       /pelanggan                    # List
GET|POST  /pelanggan/create             # Create
GET|POST  /pelanggan/{id}/edit          # Edit
DELETE    /pelanggan/{id}               # Delete

# Operator — Meter
GET|POST  /pelanggan/{id}/meter         # Add/edit meter

# Operator — Pembacaan
GET       /pembacaan                    # List by periode
POST      /pembacaan/batch              # Batch entry
GET|POST  /pembacaan/{id}/edit          # Edit reading

# Operator — Tagihan
GET       /tagihan                      # List with filter
POST      /tagihan/generate             # Generate from pembacaan
GET       /tagihan/{id}                 # Detail

# Operator — Pembayaran
POST      /pembayaran                   # Record payment
GET       /pembayaran/{id}/cetak        # Print receipt
GET       /pembayaran/{id}/cetak-ulang  # Reprint receipt

# Operator — Laporan
GET       /laporan/pemakaian            # Usage report
GET       /laporan/pendapatan           # Revenue report
GET       /laporan/tunggakan            # Outstanding report
GET       /laporan/export/{type}        # Export PDF/Excel
```

---

## 5. UI/UX

### 5.1 Bahasa

Seluruh UI dalam **Bahasa Indonesia**.

### 5.2 Tech Frontend

- **Blade** + **Tailwind CSS** + **Alpine.js**
- Server-rendered, ringan, tanpa build complexity Node.js yang berat
- Cocok untuk aplikasi CRUD sederhana seperti HIPPAM

### 5.3 Prinsip Desain

- **Clean & minimal** — Lots of whitespace, fokus ke konten dan readability. Referensi: Linear, Notion
- **Mobile-first responsive** — Operator mungkin akses via tablet/HP
- **Navigasi**: Bottom tab bar (mobile), sidebar (desktop)
- **Warna**: Biru sebagai **aksen** — sidebar/header biru, area konten netral (putih/abu-abu). Biru tidak mendominasi seluruh halaman.
- **Font**: Inter (clean, readable, mendukung Bahasa Indonesia)
- **Target pengguna**: Pengelola HIPPAM tingkat desa — UI harus intuitif, bukan overwhelming

### 5.4 Dark/Light Theme

- **Default**: Light theme
- **Toggle**: Dark mode switch di sidebar atau header (simpan preferensi per user)
- **Implementasi**: Tailwind CSS `dark:` variant + Alpine.js untuk toggle state
- **Dark theme**: Background gelap (gray-900/gray-800), teks terang, biru tetap sebagai aksen
- **Light theme**: Background putih/gray-50, teks gelap, biru tetap sebagai aksen
- **PDF struk**: Selalu light mode (untuk akurasi cetak)

### 5.5 Mobile-First

- **Mobile-first design** — layout di-desain untuk layar HP/tablet terlebih dahulu, lalu scale up untuk desktop
- Operator mengakses dari HP/tablet — bukan PC/kantor
- Navigasi: bottom tab bar (mobile), sidebar (desktop)
- Touch-friendly: tombol besar, tap target minimum 44px
- Form input: keyboard-friendly, tanggal picker native, number input yang mudah di-tap

### 5.6 Halaman Utama

| Halaman | Deskripsi |
|---------|-----------|
| Login | Form email + password, branding HIPPAM |
| Dashboard Operator | 4 kartu ringkasan + grafik pendapatan 6 bulan + daftar tunggakan |
| Dashboard Super Admin | Jumlah tenant, total pelanggan, pendapatan konsolidasi |
| Daftar Pelanggan | Tabel dengan search, filter status, pagination |
| Form Pelanggan | Single page form, validasi real-time |
| Input Pembacaan | Tabel inline-edit, batch mode untuk input cepat |
| Daftar Tagihan | Tabel dengan filter periode + status, highlight tunggakan |
| Form Pembayaran | Input jumlah, metode bayar, tombol cetak langsung |
| Laporan | Filter + tabel + grafik + tombol export |

---

## 6. Non-Functional Requirements

### 6.1 Performa

- Halaman harus load dalam < 2 detik pada koneksi 4G
- Batch input pembacaan 100+ meter dalam satu request
- Download PDF kwitansi < 2 detik dari klik

### 6.2 Keamanan

- Password hash (bcrypt)
- Session-based auth (Laravel Breeze)
- Tenant isolation — operator hanya bisa akses data tenant-nya
- Input validation & sanitization di backend
- CSRF protection (default Laravel)

### 6.3 Data Policy

- **Duplicate prevention**: Tidak boleh ada dua pembacaan untuk pelanggan + periode yang sama (unique constraint)
- **Operator**: Bisa cancel tagihan/pembacaan (status → batal), tidak bisa delete
- **Super admin**: Bisa soft-delete semua data (pelanggan, tagihan, pembayaran)
- **Pelanggan nonaktif**: Tagihan yang sudah ada tetap tersimpan, tidak ada tagihan baru yang digenerate
- **MVP scope**: Tidak termasuk forgot password, change password, user profile — super admin reset manual

### 6.4 Skalabilitas

- Menangani 50+ tenant, masing-masing 200+ pelanggan
- Satu database cukup untuk fase awal
- Path upgrade ke database-per-tenant jika diperlukan (menggunakan stancl/tenancy)

### 6.5 Ketersediaan

- Aplikasi bisa berjalan di VPS shared (2 vCPU, 2GB RAM)
- Tidak memerlukan server khusus
- Operator mengakses dari **HP/tablet** via browser (Android/iOS/PC)
- Printer thermal Bluetooth terhubung langsung ke device operator (di luar aplikasi)
- Tidak memerlukan PC/laptop di lokasi HIPPAM

---

## 7. Fase Pengembangan

### Fase 1 — Foundation (MVP)
- Setup multi-tenancy (BelongsToTenant trait, middleware, migrations)
- Auth (login, Super Admin, Operator)
- CRUD Pelanggan & Meter
- Input Pembacaan Meter
- Generate Tagihan dari pembacaan

### Fase 2 — Pembayaran & Cetak
- Catat Pembayaran (tunai, transfer)
- Update status tagihan otomatis
- Download PDF kwitansi pembayaran
- Download ulang kwitansi dari riwayat

### Fase 3 — Laporan & Dashboard
- Dashboard Operator (ringkasan + grafik)
- Dashboard Super Admin (konsolidasi)
- Laporan pemakaian, pendapatan, tunggakan
- Export PDF & Excel

### Fase 4 — Enhancement (Nice-to-have)
- Notifikasi tunggakan
- Pencatatan anomali meter
- Integrasi pembayaran digital (QRIS)
- Multi-meter per pelanggan (jika kebutuhan muncul)
- Mobile app untuk petugas keliling

---

## 8. Keputusan yang Sudah Dikonfirmasi

| # | Pertanyaan | Keputusan |
|---|-----------|-----------|
| 1 | Tarif air | **Flat per m³** — setiap tenant atur nominal sendiri |
| 2 | Biaya administrasi | **Tidak ada** — tagihan = volume × tarif saja |
| 3 | Periode tagihan | **Bulanan (tetap)** — periode YYYY-MM |
| 4 | Petugas baca meter | **Operator saja** — tidak perlu role terpisah |
| 5 | Ketersediaan printer | **Sudah punya** — tiap HIPPAM sudah ada printer thermal Bluetooth |
| 6 | Koneksi printer | **Tidak di-handle app** — generate PDF, download, cetak via native print dialog |
| 7 | Anomali meter | **Warning saja** — tampilkan peringatan, tetap bisa simpan |
| 8 | Meter per pelanggan | **1:1** — satu pelanggan = satu meter |
| 9 | UI Framework | **Custom Blade** + Tailwind CSS + Alpine.js (no Filament) |
| 10 | Design style | **Clean & minimal, mobile-first** (referensi: Linear, Notion) |
| 11 | Color theme | **Biru sebagai aksen** — sidebar/header biru, area konten netral |
| 12 | Dark/light theme | **Keduanya** — default light, toggle dark mode, preferensi per user |
| 13 | Navigasi mobile | **Bottom tab bar** (mobile), **sidebar** (desktop) |
| 14 | Lebar kertas struk | **58mm & 80mm**, konfigurasi per tenant |
| 15 | Desain struk | **Confirmed** — garis pemisah section, label kiri/nilai kanan, status boxed, footer centered |
| 16 | Database | **MySQL 8** |
| 17 | Angka meter sebelumnya | **Auto-fetch** dari periode sebelumnya |
| 18 | Tanggal jatuh tempo | **Auto: tanggal X bulan berikutnya**, configurable per tenant |
| 19 | Forgot password / profile | **Skip for MVP** — super admin reset manual |
| 20 | Delete policy | **Cancel** untuk operator, **soft-delete** untuk super admin |
