# Phase 2 — Pembayaran & Cetak Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add payment recording with automatic tagihan status updates, PDF receipt generation for thermal printers (58mm/80mm), and invoice PDF for unpaid tagihan.

**Architecture:** Build on Phase 1's multi-tenant foundation. Each payment (Pembayaran) ties to one Tagihan. Multiple payments per tagihan are supported (cicilan). Payment processing uses DB transactions with row-level locking to prevent race conditions. PDF receipts use dompdf with custom thermal paper sizes.

**Tech Stack:** Laravel 13, PHP 8.3+, MySQL 8, Blade + Tailwind CSS + Alpine.js, barryvdh/laravel-dompdf 3.x, PHPUnit

---

## File Structure

### New Files
- `database/migrations/2026_05_03_110000_create_pembayaran_table.php` — Payment table
- `app/Models/Pembayaran.php` — Payment model with BelongsToTenant trait
- `app/Services/PembayaranService.php` — Payment processing + tagihan status update logic
- `app/Services/ReceiptPdfService.php` — PDF generation for receipts and invoices
- `app/Http/Controllers/Operator/PembayaranController.php` — Payment CRUD + receipt endpoints
- `app/Http/Requests/StorePembayaranRequest.php` — Payment validation form request
- `resources/views/operator/pembayaran/create.blade.php` — Payment form
- `resources/views/operator/pembayaran/index.blade.php` — Payment history list
- `resources/views/prints/receipt.blade.php` — Thermal receipt/invoice PDF template

### Modified Files
- `routes/web.php` — Add pembayaran routes
- `resources/views/operator/tagihan/index.blade.php` — Add "Bayar" action button, "Cetak Invoice" link
- `resources/views/layouts/navigation.blade.php` — Add "Pembayaran" nav link

### Test Files (tests-after)
- `tests/Feature/PembayaranStoreTest.php` — Payment creation feature tests
- `tests/Feature/PembayaranPdfTest.php` — PDF generation feature tests
- `tests/Unit/PembayaranServiceTest.php` — Payment service unit tests

---

## Existing Patterns Reference (MUST follow)

### Controller Pattern
```
File: app/Http/Controllers/Operator/PelangganController.php
- Constructor: $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
- Ownership check: $this->ensureTenantOwnership($model) → abort(404) if mismatch
- Redirect: redirect()->route('xxx.index')->with('success', '...')
```

### Model Pattern
```
File: app/Models/Tagihan.php
- Use BelongsToTenant, HasFactory traits
- Protected $table = 'tagihan';
- Protected $fillable = [..., 'tenant_id', ...];
- Protected $casts for decimals and dates
- BelongsTo relationships for foreign keys
```

### View Pattern
```
File: resources/views/operator/pelanggan/index.blade.php
- <x-app-layout> wrapper
- max-w-7xl container, responsive grid
- Mobile: card layout (md:hidden), Desktop: table (hidden md:block)
- Status badges: inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium
- Success/error flash messages via session('success') / session('error')
- Pagination via {{ $items->links() }}
- Currency: 'Rp ' . number_format($amount, 0, ',', '.')
```

### Thermal Receipt Specs (from PRD §3.6)
```
Paper: 58mm (164.5pt width) or 80mm (227pt width) — from tenant.printer_width
Font: Monospace (Courier New), 12px
Layout: Horizontal lines separating sections, labels left-aligned, values right-aligned
Sections: HEADER → DATA PELANGGAN → PEMBACAAN METER → PERHITUNGAN TARIF → PEMBAYARAN → STATUS (boxed) → FOOTER
Status: Centered in bordered box
Footer: 3 lines centered (terima kasih, doa, nama sistem)
Currency: Rp X.XXX (titik as thousands separator)
Date: d/m/Y format
Period: Indonesian month name + YYYY (Mei 2026)
```

---

## TODOs

### Task 1: Create Pembayaran Migration

**Files:**
- Create: `database/migrations/2026_05_03_110000_create_pembayaran_table.php`

**What to do:**
Create migration for `pembayaran` table with columns matching PRD §3.5 data specification.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->date('tanggal_bayar');
            $table->decimal('jumlah_bayar', 12, 2);
            $table->enum('metode_bayar', ['tunai', 'transfer', 'ewallet']);
            $table->string('no_referensi', 100)->nullable();
            $table->foreignId('petugas_kasir')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tanggal_bayar']);
            $table->index(['tenant_id', 'tagihan_id']);
            $table->index(['tenant_id', 'pelanggan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
```

- [ ] **Step 1: Create migration file**

Run: `php artisan make:migration create_pembayaran_table`
Then replace contents with the migration above.

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: Migration runs without errors, `pembayaran` table created.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_03_110000_create_pembayaran_table.php
git commit -m "feat(pembayaran): add pembayaran table migration"
```

---

### Task 2: Create Pembayaran Model

**Files:**
- Create: `app/Models/Pembayaran.php`

**What to do:**
Create the Pembayaran model following the pattern from `app/Models/Tagihan.php`. Use BelongsToTenant trait. Define relationships to Tagihan, Pelanggan, and User (petugas_kasir).

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tenant_id', 'tagihan_id', 'pelanggan_id',
        'tanggal_bayar', 'jumlah_bayar', 'metode_bayar',
        'no_referensi', 'petugas_kasir', 'catatan',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'jumlah_bayar' => 'decimal:2',
    ];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function petugasKasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_kasir');
    }
}
```

- [ ] **Step 1: Create model file**

Create `app/Models/Pembayaran.php` with the code above.

- [ ] **Step 2: Verify model loads**

Run: `php artisan tinker --execute="echo new \App\Models\Pembayaran;"`
Expected: No errors, model instantiates.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Pembayaran.php
git commit -m "feat(pembayaran): add Pembayaran model with tenant scope"
```

---

### Task 3: Create PembayaranService

**Files:**
- Create: `app/Services/PembayaranService.php`

**What to do:**
Create service that processes payments with DB transactions and row-level locking. Auto-updates tagihan status based on total payments vs total tagihan.

Business rules:
- One payment per tagihan per request (no bulk)
- Multiple payments per tagihan allowed (cicilan support)
- Reject overpayments (jumlah_bayar > sisaTagihan)
- Auto-update tagihan status: belum_bayar → cicilan (partial) or lunas (full)
- Use DB transaction + lockForUpdate on tagihan row

```php
<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranService
{
    public function processPayment(Tagihan $tagihan, array $data): Pembayaran
    {
        return DB::transaction(function () use ($tagihan, $data) {
            // Lock the tagihan row to prevent race conditions
            $tagihan = Tagihan::lockForUpdate()->findOrFail($tagihan->id);

            // Refresh computed values after lock
            $sisaTagihan = max(0, (float) $tagihan->total_tagihan - (float) $tagihan->totalDibayar());

            // Reject overpayment
            if ((float) $data['jumlah_bayar'] > $sisaTagihan) {
                throw new \InvalidArgumentException(
                    "Jumlah bayar (Rp " . number_format($data['jumlah_bayar'], 0, ',', '.') .
                    ") melebihi sisa tagihan (Rp " . number_format($sisaTagihan, 0, ',', '.') . ")."
                );
            }

            // Reject payment on cancelled tagihan
            if ($tagihan->status === 'batal') {
                throw new \InvalidArgumentException('Tagihan sudah dibatalkan.');
            }

            // Create payment
            $pembayaran = Pembayaran::create([
                'tenant_id' => $tagihan->tenant_id,
                'tagihan_id' => $tagihan->id,
                'pelanggan_id' => $tagihan->pelanggan_id,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'jumlah_bayar' => $data['jumlah_bayar'],
                'metode_bayar' => $data['metode_bayar'],
                'no_referensi' => $data['no_referensi'] ?? null,
                'petugas_kasir' => Auth::id(),
                'catatan' => $data['catatan'] ?? null,
            ]);

            // Update tagihan status
            $totalDibayar = (float) $tagihan->totalDibayar() + (float) $data['jumlah_bayar'];
            $totalTagihan = (float) $tagihan->total_tagihan;

            if ($totalDibayar >= $totalTagihan) {
                $tagihan->update(['status' => 'lunas']);
            } else {
                $tagihan->update(['status' => 'cicilan']);
            }

            return $pembayaran;
        });
    }
}
```

- [ ] **Step 1: Create service file**

Create `app/Services/PembayaranService.php` with the code above.

- [ ] **Step 2: Verify service loads**

Run: `php artisan tinker --execute="echo new \App\Services\PembayaranService;"`
Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add app/Services/PembayaranService.php
git commit -m "feat(pembayaran): add PembayaranService with transaction locking"
```

---

### Task 4: Create StorePembayaranRequest Validation

**Files:**
- Create: `app/Http/Requests/StorePembayaranRequest.php`

**What to do:**
Create form request for payment validation. no_referensi required when metode_bayar is transfer or ewallet.

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_bayar' => 'required|date',
            'jumlah_bayar' => 'required|numeric|min:1',
            'metode_bayar' => 'required|in:tunai,transfer,ewallet',
            'no_referensi' => 'nullable|string|max:100|required_if:metode_bayar,transfer,ewallet',
            'catatan' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_bayar.required' => 'Tanggal bayar wajib diisi.',
            'tanggal_bayar.date' => 'Format tanggal tidak valid.',
            'jumlah_bayar.required' => 'Jumlah bayar wajib diisi.',
            'jumlah_bayar.numeric' => 'Jumlah bayar harus berupa angka.',
            'jumlah_bayar.min' => 'Jumlah bayar minimal Rp 1.',
            'metode_bayar.required' => 'Metode bayar wajib dipilih.',
            'metode_bayar.in' => 'Metode bayar tidak valid.',
            'no_referensi.required_if' => 'No. referensi wajib untuk pembayaran transfer/e-wallet.',
            'catatan.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
```

- [ ] **Step 1: Create request file**

Create `app/Http/Requests/StorePembayaranRequest.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/StorePembayaranRequest.php
git commit -m "feat(pembayaran): add payment validation form request"
```

---

### Task 5: Create PembayaranController

**Files:**
- Create: `app/Http/Controllers/Operator/PembayaranController.php`

**What to do:**
Create controller with 4 actions: `create` (payment form), `store` (process payment), `index` (payment history), `cetak` (download PDF receipt), `cetakInvoice` (download invoice PDF for unpaid tagihan).

Follow the pattern from `app/Http/Controllers/Operator/TagihanController.php`:
- Constructor middleware: `['auth', 'tenant.resolve', 'tenant.ensure']`
- Use `app('current.tenant')` for tenant context
- Ensure tenant ownership via the tagihan's tenant_id

```php
<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePembayaranRequest;
use App\Models\Tagihan;
use App\Models\Pembayaran;
use App\Services\PembayaranService;
use App\Services\ReceiptPdfService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembayaranController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Pembayaran::with(['tagihan', 'pelanggan', 'petugasKasir']);

        if ($request->filled('tanggal_mulai')) {
            $query->where('tanggal_bayar', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_akhir')) {
            $query->where('tanggal_bayar', '<=', $request->tanggal_akhir);
        }
        if ($request->filled('metode_bayar')) {
            $query->where('metode_bayar', $request->metode_bayar);
        }

        $pembayaran = $query->latest()->paginate(20)->withQueryString();

        return view('operator.pembayaran.index', compact('pembayaran'));
    }

    public function create(Tagihan $tagihan): View
    {
        $this->ensureTenantOwnership($tagihan);
        $tagihan->load('pelanggan', 'pembacaan');

        if ($tagihan->status === 'batal') {
            abort(404);
        }

        return view('operator.pembayaran.create', compact('tagihan'));
    }

    public function store(StorePembayaranRequest $request, Tagihan $tagihan, PembayaranService $service)
    {
        $this->ensureTenantOwnership($tagihan);

        try {
            $pembayaran = $service->processPayment($tagihan, $request->validated());

            return redirect()->route('pembayaran.cetak', $pembayaran)
                ->with('success', 'Pembayaran berhasil dicatat.');
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cetak(Pembayaran $pembayaran, ReceiptPdfService $pdfService)
    {
        $this->ensurePembayaranOwnership($pembayaran);
        $pembayaran->load(['tagihan.pelanggan', 'tagihan.pembacaan.meter', 'petugasKasir']);

        $tenant = app('current.tenant');

        return $pdfService->generateReceipt($pembayaran, $tenant);
    }

    public function cetakUlang(Pembayaran $pembayaran, ReceiptPdfService $pdfService)
    {
        $this->ensurePembayaranOwnership($pembayaran);
        $pembayaran->load(['tagihan.pelanggan', 'tagihan.pembacaan.meter', 'petugasKasir']);

        $tenant = app('current.tenant');

        return $pdfService->generateReceipt($pembayaran, $tenant);
    }

    public function cetakInvoice(Tagihan $tagihan, ReceiptPdfService $pdfService)
    {
        $this->ensureTenantOwnership($tagihan);
        $tagihan->load(['pelanggan', 'pembacaan.meter']);

        $tenant = app('current.tenant');

        return $pdfService->generateInvoice($tagihan, $tenant);
    }

    private function ensureTenantOwnership(Tagihan $tagihan): void
    {
        if ($tagihan->tenant_id !== app('current.tenant')->id) {
            abort(404);
        }
    }

    private function ensurePembayaranOwnership(Pembayaran $pembayaran): void
    {
        if ($pembayaran->tenant_id !== app('current.tenant')->id) {
            abort(404);
        }
    }
}
```

- [ ] **Step 1: Create controller file**

Create `app/Http/Controllers/Operator/PembayaranController.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Operator/PembayaranController.php
git commit -m "feat(pembayaran): add PembayaranController with receipt/invoice endpoints"
```

---

### Task 6: Create ReceiptPdfService

**Files:**
- Create: `app/Services/ReceiptPdfService.php`

**What to do:**
Create PDF generation service using dompdf. Support 58mm (164.5pt) and 80mm (227pt) thermal paper. Two methods: `generateReceipt()` for payment receipts and `generateInvoice()` for unpaid tagihan.

Paper size mapping from tenant.printer_width:
- `'58mm'` → width = 164.5pt
- `'80mm'` → width = 227pt
- Use two-pass height calculation for auto-height

```php
<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReceiptPdfService
{
    private const PAPER_WIDTHS = [
        '58mm' => 164.5,
        '80mm' => 227,
    ];

    public function generateReceipt(Pembayaran $pembayaran, Tenant $tenant)
    {
        $width = self::PAPER_WIDTHS[$tenant->printer_width] ?? 164.5;

        $tagihan = $pembayaran->tagihan;
        $pelanggan = $tagihan->pelanggan;
        $pembacaan = $tagihan->pembacaan;
        $meter = $pembacaan->meter ?? null;

        $data = [
            'tenant' => $tenant,
            'pembayaran' => $pembayaran,
            'tagihan' => $tagihan,
            'pelanggan' => $pelanggan,
            'pembacaan' => $pembacaan,
            'meter' => $meter,
            'isInvoice' => false,
            'statusLabel' => $this->getStatusLabel($tagihan),
            'printedAt' => Carbon::now()->format('j/n/Y H.i.s'),
            'periodeLabel' => $this->formatPeriode($tagihan->periode),
        ];

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, 800], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        // Two-pass for auto-height
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $height = $dompdf->get_canvas()->get_height() + 20;

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, $height], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        return $pdf->download("kwitansi-{$pembayaran->id}.pdf");
    }

    public function generateInvoice(Tagihan $tagihan, Tenant $tenant)
    {
        $width = self::PAPER_WIDTHS[$tenant->printer_width] ?? 164.5;

        $pelanggan = $tagihan->pelanggan;
        $pembacaan = $tagihan->pembacaan;
        $meter = $pembacaan->meter ?? null;

        $data = [
            'tenant' => $tenant,
            'pembayaran' => null,
            'tagihan' => $tagihan,
            'pelanggan' => $pelanggan,
            'pembacaan' => $pembacaan,
            'meter' => $meter,
            'isInvoice' => true,
            'statusLabel' => 'BELUM BAYAR',
            'printedAt' => Carbon::now()->format('j/n/Y H.i.s'),
            'periodeLabel' => $this->formatPeriode($tagihan->periode),
        ];

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, 800], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $height = $dompdf->get_canvas()->get_height() + 20;

        $pdf = Pdf::loadView('prints.receipt', $data)
            ->setPaper([0, 0, $width, $height], 'portrait')
            ->setOption(['dpi' => 72, 'defaultFont' => 'Courier']);

        return $pdf->download("invoice-{$tagihan->id}.pdf");
    }

    private function getStatusLabel(Tagihan $tagihan): string
    {
        return match ($tagihan->status) {
            'lunas' => 'LUNAS',
            'cicilan' => 'CICILAN',
            'belum_bayar' => 'BELUM BAYAR',
            default => strtoupper($tagihan->status),
        };
    }

    private function formatPeriode(string $periode): string
    {
        $months = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
            '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
        ];

        [$year, $month] = explode('-', $periode);

        return ($months[$month] ?? $month) . ' ' . $year;
    }
}
```

- [ ] **Step 1: Create service file**

Create `app/Services/ReceiptPdfService.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Services/ReceiptPdfService.php
git commit -m "feat(pembayaran): add ReceiptPdfService for thermal receipt generation"
```

---

### Task 7: Create Receipt/Invoice PDF Blade Template

**Files:**
- Create: `resources/views/prints/receipt.blade.php`

**What to do:**
Create the thermal receipt template following the EXACT layout from PRD §3.6. This template serves dual purpose: kwitansi (when `$pembayaran` exists) and invoice (when `$isInvoice` is true).

The receipt layout per PRD:
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

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
        }
        .receipt {
            padding: 2mm;
        }
        .center {
            text-align: center;
        }
        .separator {
            border-top: 1px solid #000;
            margin: 4px 0;
        }
        .section-title {
            font-weight: bold;
            margin: 4px 0 2px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
        }
        .row .label {
            text-align: left;
        }
        .row .value {
            text-align: right;
        }
        .status-box {
            border: 1px solid #000;
            padding: 4px 0;
            text-align: center;
            font-weight: bold;
            margin: 6px auto;
            width: 80%;
        }
        .footer {
            text-align: center;
            margin-top: 6px;
        }
        .footer p {
            margin: 1px 0;
        }
    </style>
</head>
<body>
    <div class="receipt">
        {{-- HEADER --}}
        <div class="separator"></div>
        <p class="center" style="font-weight: bold;">STRUK PEMBAYARAN AIR</p>
        <p class="center">HIPPAM - {{ $tenant->nama_unit }}</p>
        <p class="center">{{ $printedAt }}</p>
        <div class="separator"></div>

        {{-- DATA PELANGGAN --}}
        <p class="section-title">DATA PELANGGAN</p>
        <div class="row">
            <span class="label">Nama:</span>
            <span class="value">{{ $pelanggan->nama }}</span>
        </div>
        <div class="row">
            <span class="label">No. Meter:</span>
            <span class="value">{{ $meter->nomor_meter ?? '-' }}</span>
        </div>
        <div class="row">
            <span class="label">Periode:</span>
            <span class="value">{{ $periodeLabel }}</span>
        </div>
        <div class="separator"></div>

        {{-- PEMBACAAN METER --}}
        <p class="section-title">PEMBACAAN METER</p>
        <div class="row">
            <span class="label">Meter Sebelumnya:</span>
            <span class="value">{{ number_format($pembacaan->angka_meter_sebelumnya, 0, ',', '.') }} m³</span>
        </div>
        <div class="row">
            <span class="label">Meter Sekarang:</span>
            <span class="value">{{ number_format($pembacaan->angka_meter_sekarang, 0, ',', '.') }} m³</span>
        </div>
        <div class="row">
            <span class="label">Konsumsi:</span>
            <span class="value">{{ number_format($tagihan->volume_m3, 0, ',', '.') }} m³</span>
        </div>
        <div class="separator"></div>

        {{-- PERHITUNGAN TARIF --}}
        <p class="section-title">PERHITUNGAN TARIF</p>
        <div class="row">
            <span class="label">Tarif:</span>
            <span class="value">Rp {{ number_format($tagihan->tarif_per_m3, 0, ',', '.') }}/m³</span>
        </div>
        <div class="row">
            <span class="label">Total Tagihan:</span>
            <span class="value">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</span>
        </div>
        <div class="separator"></div>

        {{-- PEMBAYARAN --}}
        <p class="section-title">PEMBAYARAN</p>
        @if($pembayaran && !$isInvoice)
            <div class="row">
                <span class="label">Jumlah Dibayar:</span>
                <span class="value">Rp {{ number_format($pembayaran->jumlah_bayar, 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span class="label">Tanggal Bayar:</span>
                <span class="value">{{ $pembayaran->tanggal_bayar->format('j/n/Y') }}</span>
            </div>
        @else
            <div class="row">
                <span class="label">Jumlah Dibayar:</span>
                <span class="value">Rp 0</span>
            </div>
            <div class="row">
                <span class="label">Tanggal Bayar:</span>
                <span class="value">-</span>
            </div>
        @endif
        <div class="separator"></div>

        {{-- STATUS --}}
        <div class="status-box">Status: {{ $statusLabel }}</div>

        {{-- FOOTER --}}
        <div class="footer">
            <p>Terima kasih atas pembayaran Anda</p>
            <p>Semoga lancar selalu</p>
            <p>Dicetak oleh Sistem HIPPAM</p>
        </div>
        <div class="separator"></div>
    </div>
</body>
</html>
```

- [ ] **Step 1: Create prints directory and template**

Create `resources/views/prints/receipt.blade.php` with the code above.

- [ ] **Step 2: Verify template renders**

Run: `php artisan tinker --execute="echo view('prints.receipt', ['tenant' => \App\Models\Tenant::first(), 'pembayaran' => null, 'tagihan' => \App\Models\Tagihan::first(), 'pelanggan' => \App\Models\Pelanggan::first(), 'pembacaan' => \App\Models\Pembacaan::first(), 'meter' => null, 'isInvoice' => true, 'statusLabel' => 'BELUM BAYAR', 'printedAt' => now()->format('j/n/Y H.i.s'), 'periodeLabel' => 'Mei 2026'])->render();"`
Expected: HTML output with receipt structure.

- [ ] **Step 3: Commit**

```bash
git add resources/views/prints/receipt.blade.php
git commit -m "feat(pembayaran): add thermal receipt/invoice PDF template"
```

---

### Task 8: Create Payment Form View

**Files:**
- Create: `resources/views/operator/pembayaran/create.blade.php`

**What to do:**
Create payment form for a specific tagihan. Shows tagihan details (pelanggan name, periode, total, sisa tagihan) and payment input fields. Follow existing form pattern from `resources/views/operator/pelanggan/create.blade.php`.

```html
<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Catat Pembayaran</h1>

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Tagihan Summary --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Detail Tagihan</h2>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">No. Pelanggan</div>
                <div class="font-mono text-gray-900">{{ $tagihan->pelanggan->nomor_pelanggan }}</div>
                <div class="text-gray-500">Nama</div>
                <div class="font-medium text-gray-900">{{ $tagihan->pelanggan->nama }}</div>
                <div class="text-gray-500">Periode</div>
                <div class="text-gray-900">{{ $tagihan->periode }}</div>
                <div class="text-gray-500">Volume</div>
                <div class="text-gray-900">{{ number_format($tagihan->volume_m3, 1) }} m³</div>
                <div class="text-gray-500">Total Tagihan</div>
                <div class="font-medium text-gray-900">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</div>
                <div class="text-gray-500">Sudah Dibayar</div>
                <div class="text-gray-900">Rp {{ number_format($tagihan->totalDibayar(), 0, ',', '.') }}</div>
                <div class="text-gray-500 font-medium">Sisa Tagihan</div>
                <div class="font-bold text-red-600">Rp {{ number_format($tagihan->sisaTagihan(), 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Payment Form --}}
        <form method="POST" action="{{ route('pembayaran.store', $tagihan) }}" class="bg-white rounded-lg shadow p-6">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="jumlah_bayar" :value="'Jumlah Bayar'" />
                    <x-text-input id="jumlah_bayar" name="jumlah_bayar" type="number" step="1" min="1" max="{{ $tagihan->sisaTagihan() }}"
                        :value="old('jumlah_bayar', $tagihan->sisaTagihan())"
                        class="mt-1 block w-full" placeholder="Masukkan jumlah bayar" />
                    <x-input-error :messages="$errors->get('jumlah_bayar')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="tanggal_bayar" :value="'Tanggal Bayar'" />
                    <x-text-input id="tanggal_bayar" name="tanggal_bayar" type="date"
                        :value="old('tanggal_bayar', now()->format('Y-m-d'))"
                        class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('tanggal_bayar')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="metode_bayar" :value="'Metode Bayar'" />
                    <select id="metode_bayar" name="metode_bayar" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="tunai" {{ old('metode_bayar', 'tunai') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                        <option value="transfer" {{ old('metode_bayar') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                        <option value="ewallet" {{ old('metode_bayar') === 'ewallet' ? 'selected' : '' }}>E-Wallet</option>
                    </select>
                    <x-input-error :messages="$errors->get('metode_bayar')" class="mt-2" />
                </div>

                <div id="referensi-field">
                    <x-input-label for="no_referensi" :value="'No. Referensi'" />
                    <x-text-input id="no_referensi" name="no_referensi" type="text"
                        :value="old('no_referensi')"
                        class="mt-1 block w-full" placeholder="Opsional untuk tunai" />
                    <x-input-error :messages="$errors->get('no_referensi')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="catatan" :value="'Catatan'" />
                    <textarea id="catatan" name="catatan" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Opsional">{{ old('catatan') }}</textarea>
                    <x-input-error :messages="$errors->get('catatan')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                    Simpan Pembayaran
                </button>
                <a href="{{ route('tagihan.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                    Batal
                </a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.getElementById('metode_bayar').addEventListener('change', function() {
            const refField = document.getElementById('referensi-field');
            const refInput = document.getElementById('no_referensi');
            if (this.value === 'tunai') {
                refInput.required = false;
                refInput.placeholder = 'Opsional untuk tunai';
            } else {
                refInput.required = true;
                refInput.placeholder = 'Wajib diisi untuk ' + this.value;
            }
        });
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 1: Create view file**

Create `resources/views/operator/pembayaran/create.blade.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/operator/pembayaran/create.blade.php
git commit -m "feat(pembayaran): add payment form view"
```

---

### Task 9: Create Payment History View

**Files:**
- Create: `resources/views/operator/pembayaran/index.blade.php`

**What to do:**
Create payment history list view following the pattern from `resources/views/operator/tagihan/index.blade.php`. Shows all payments with filter by date range and payment method. Each row has a "Cetak Ulang" button.

```html
<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Pembayaran</h1>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" action="{{ route('pembayaran.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3">
            <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="date" name="tanggal_akhir" value="{{ request('tanggal_akhir') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <select name="metode_bayar" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Metode</option>
                <option value="tunai" {{ request('metode_bayar') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                <option value="transfer" {{ request('metode_bayar') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                <option value="ewallet" {{ request('metode_bayar') === 'ewallet' ? 'selected' : '' }}>E-Wallet</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Filter</button>
        </form>

        @if($pembayaran->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Belum ada data pembayaran.
            </div>
        @else
            <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pelanggan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Bayar</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($pembayaran as $p)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $p->tanggal_bayar->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $p->pelanggan->nomor_pelanggan }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $p->tagihan->periode }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        @if($p->metode_bayar === 'tunai')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Tunai</span>
                                        @elseif($p->metode_bayar === 'transfer')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Transfer</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">E-Wallet</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <a href="{{ route('pembayaran.cetak-ulang', $p) }}" class="text-primary-600 hover:text-primary-700 font-medium">Cetak Ulang</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="md:hidden space-y-3">
                @foreach($pembayaran as $p)
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</h3>
                                <p class="text-xs text-gray-500 font-mono">{{ $p->pelanggan->nomor_pelanggan }}</p>
                            </div>
                            <span class="text-sm font-medium text-gray-900">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p>{{ $p->tanggal_bayar->format('d M Y') }} · {{ ucfirst($p->metode_bayar) }}</p>
                            <p>Periode: {{ $p->tagihan->periode }}</p>
                        </div>
                        <div class="mt-2 border-t border-gray-100 pt-2">
                            <a href="{{ route('pembayaran.cetak-ulang', $p) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Cetak Ulang</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            {{ $pembayaran->links() }}
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 1: Create view file**

Create `resources/views/operator/pembayaran/index.blade.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/operator/pembayaran/index.blade.php
git commit -m "feat(pembayaran): add payment history list view"
```

---

### Task 10: Update Routes

**Files:**
- Modify: `routes/web.php`

**What to do:**
Add pembayaran routes inside the tenant-scoped middleware group. Also add tagihan detail route for the "Bayar" link.

Current `routes/web.php` has the tenant group ending at line 48. Add these routes inside the `Route::middleware(['tenant.resolve', 'tenant.ensure'])` group, after the existing tagihan routes:

```php
// Add these imports at top of file:
use App\Http\Controllers\Operator\PembayaranController;

// Add these routes inside the tenant.resolve/tenant.ensure group, after tagihan routes:

Route::get('/tagihan/{tagihan}', [TagihanController::class, 'show'])->name('tagihan.show');
Route::get('/tagihan/{tagihan}/cetak-invoice', [PembayaranController::class, 'cetakInvoice'])->name('tagihan.cetak-invoice');

Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
Route::get('/tagihan/{tagihan}/bayar', [PembayaranController::class, 'create'])->name('pembayaran.create');
Route::post('/tagihan/{tagihan}/bayar', [PembayaranController::class, 'store'])->name('pembayaran.store');
Route::get('/pembayaran/{pembayaran}/cetak', [PembayaranController::class, 'cetak'])->name('pembayaran.cetak');
Route::get('/pembayaran/{pembayaran}/cetak-ulang', [PembayaranController::class, 'cetakUlang'])->name('pembayaran.cetak-ulang');
```

Also add `show` method to `TagihanController`:

```php
// Add to app/Http/Controllers/Operator/TagihanController.php:

public function show(Tagihan $tagihan): View
{
    $this->ensureTenantOwnership($tagihan);
    $tagihan->load(['pelanggan', 'pembacaan.meter', 'pembayaran.petugasKasir']);

    return view('operator.tagihan.show', compact('tagihan'));
}

private function ensureTenantOwnership(Tagihan $tagihan): void
{
    if ($tagihan->tenant_id !== app('current.tenant')->id) {
        abort(404);
    }
}
```

- [ ] **Step 1: Update web.php with new routes**

Add the import and routes to `routes/web.php` in the tenant middleware group.

- [ ] **Step 2: Add show method and ownership check to TagihanController**

Add the `show()` method and `ensureTenantOwnership()` private method to `app/Http/Controllers/Operator/TagihanController.php`.

- [ ] **Step 3: Verify routes registered**

Run: `php artisan route:list --path=pembayaran`
Expected: All 5 pembayaran routes + tagihan.show + tagihan.cetak-invoice listed.

- [ ] **Step 4: Commit**

```bash
git add routes/web.php app/Http/Controllers/Operator/TagihanController.php
git commit -m "feat(pembayaran): add payment and invoice routes"
```

---

### Task 11: Create Tagihan Detail View

**Files:**
- Create: `resources/views/operator/tagihan/show.blade.php`

**What to do:**
Create tagihan detail page showing full tagihan info, payment history for this tagihan, and action buttons (Bayar, Cetak Invoice). This is needed for the payment flow — after clicking "Bayar" on the tagihan list, user sees the detail page with payment button.

```html
<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Detail Tagihan</h1>
            <a href="{{ route('tagihan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Daftar</a>
        </div>

        {{-- Tagihan Info --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Pelanggan</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->pelanggan->nama }}</p>
                    <p class="text-xs text-gray-500 font-mono">{{ $tagihan->pelanggan->nomor_pelanggan }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Periode</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->periode }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Volume</p>
                    <p class="font-medium text-gray-900">{{ number_format($tagihan->volume_m3, 1) }} m³</p>
                </div>
                <div>
                    <p class="text-gray-500">Tarif per m³</p>
                    <p class="font-medium text-gray-900">Rp {{ number_format($tagihan->tarif_per_m3, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Total Tagihan</p>
                    <p class="text-lg font-bold text-gray-900">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Status</p>
                    @if($tagihan->status === 'belum_bayar')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                    @elseif($tagihan->status === 'lunas')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                    @elseif($tagihan->status === 'cicilan')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Batal</span>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500">Jatuh Tempo</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->tanggal_jatuh_tempo->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Sisa Tagihan</p>
                    <p class="text-lg font-bold {{ $tagihan->sisaTagihan() > 0 ? 'text-red-600' : 'text-green-600' }}">Rp {{ number_format($tagihan->sisaTagihan(), 0, ',', '.') }}</p>
                </div>
            </div>

            @if($tagihan->status !== 'lunas' && $tagihan->status !== 'batal')
                <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
                    <a href="{{ route('pembayaran.create', $tagihan) }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                        Bayar
                    </a>
                    <a href="{{ route('tagihan.cetak-invoice', $tagihan) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                        Cetak Invoice
                    </a>
                </div>
            @endif
        </div>

        {{-- Payment History for this Tagihan --}}
        @if($tagihan->pembayaran->isNotEmpty())
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Riwayat Pembayaran</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tagihan->pembayaran as $p)
                            <tr>
                                <td class="px-6 py-3 text-sm">{{ $p->tanggal_bayar->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-sm text-right">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                <td class="px-6 py-3 text-sm">{{ ucfirst($p->metode_bayar) }}</td>
                                <td class="px-6 py-3 text-sm text-right">
                                    <a href="{{ route('pembayaran.cetak', $p) }}" class="text-primary-600 hover:text-primary-700">Cetak</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 1: Create tagihan detail view**

Create `resources/views/operator/tagihan/show.blade.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/operator/tagihan/show.blade.php
git commit -m "feat(pembayaran): add tagihan detail view with payment actions"
```

---

### Task 12: Update Tagihan Index View with Action Buttons

**Files:**
- Modify: `resources/views/operator/tagihan/index.blade.php`

**What to do:**
Add "Bayar" and "Cetak Invoice" action buttons to each tagihan row in the table and mobile cards. Add an "Aksi" column header. Show "Bayar" only for belum_bayar/cicilan status. Show "Cetak Invoice" for belum_bayar.

**Desktop table changes** — Add column header after Status column:
```html
{{-- Add to thead, after Status th: --}}
<th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>

{{-- Add to each tbody tr, after Status td: --}}
<td class="px-4 py-3 text-right text-sm space-x-2">
    @if($t->status === 'belum_bayar' || $t->status === 'cicilan')
        <a href="{{ route('pembayaran.create', $t) }}" class="text-primary-600 hover:text-primary-700 font-medium">Bayar</a>
    @endif
    @if($t->status === 'belum_bayar')
        <a href="{{ route('tagihan.cetak-invoice', $t) }}" class="text-gray-600 hover:text-gray-700 font-medium">Invoice</a>
    @endif
    <a href="{{ route('tagihan.show', $t) }}" class="text-gray-600 hover:text-gray-700 font-medium">Detail</a>
</td>
```

**Mobile card changes** — Add action links at bottom of each card:
```html
{{-- Add after the last <p> in mobile card, before closing div: --}}
<div class="flex items-center gap-3 border-t border-gray-100 pt-3 mt-2">
    @if($t->status === 'belum_bayar' || $t->status === 'cicilan')
        <a href="{{ route('pembayaran.create', $t) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Bayar</a>
    @endif
    @if($t->status === 'belum_bayar')
        <a href="{{ route('tagihan.cetak-invoice', $t) }}" class="text-gray-600 hover:text-gray-700 text-xs font-medium">Invoice</a>
    @endif
    <a href="{{ route('tagihan.show', $t) }}" class="text-gray-600 hover:text-gray-700 text-xs font-medium">Detail</a>
</div>
```

- [ ] **Step 1: Add Aksi column to desktop table header**

Add the `<th>` for Aksi in the `<thead>` section.

- [ ] **Step 2: Add action buttons to each desktop table row**

Add the `<td>` with Bayar/Invoice/Detail links after the Status `<td>` in the `<tbody>`.

- [ ] **Step 3: Add action buttons to mobile cards**

Add the action links div to each mobile card.

- [ ] **Step 4: Verify page loads**

Run: `php artisan route:list --path=tagihan`
Expected: tagihan.show route exists (added in Task 10).

- [ ] **Step 5: Commit**

```bash
git add resources/views/operator/tagihan/index.blade.php
git commit -m "feat(pembayaran): add Bayar/Invoice/Detail actions to tagihan list"
```

---

### Task 13: Update Navigation with Pembayaran Link

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

**What to do:**
Add "Pembayaran" navigation link to the desktop and mobile navigation. Place it after the existing "Dashboard" link, following the same pattern using `<x-nav-link>` and `<x-responsive-nav-link>` components.

Only show for operator users (users with tenant_id).

**Desktop nav** — After the Dashboard `<x-nav-link>` (around line 16-18):
```html
{{-- Add after Dashboard nav-link: --}}
@auth
    @if(auth()->user()->tenant_id)
        <x-nav-link :href="route('pelanggan.index')" :active="request()->routeIs('pelanggan.*')">
            Pelanggan
        </x-nav-link>
        <x-nav-link :href="route('pembacaan.index')" :active="request()->routeIs('pembacaan.*')">
            Pembacaan
        </x-nav-link>
        <x-nav-link :href="route('tagihan.index')" :active="request()->routeIs('tagihan.*')">
            Tagihan
        </x-nav-link>
        <x-nav-link :href="route('pembayaran.index')" :active="request()->routeIs('pembayaran.*')">
            Pembayaran
        </x-nav-link>
    @endif
@endauth
```

**Mobile nav** — After the Dashboard `<x-responsive-nav-link>` (around line 70-72), add the same links:
```html
@auth
    @if(auth()->user()->tenant_id)
        <x-responsive-nav-link :href="route('pelanggan.index')" :active="request()->routeIs('pelanggan.*')">
            Pelanggan
        </x-responsive-nav-link>
        <x-responsive-nav-link :href="route('pembacaan.index')" :active="request()->routeIs('pembacaan.*')">
            Pembacaan
        </x-responsive-nav-link>
        <x-responsive-nav-link :href="route('tagihan.index')" :active="request()->routeIs('tagihan.*')">
            Tagihan
        </x-responsive-nav-link>
        <x-responsive-nav-link :href="route('pembayaran.index')" :active="request()->routeIs('pembayaran.*')">
            Pembayaran
        </x-responsive-nav-link>
    @endif
@endauth
```

- [ ] **Step 1: Add operator nav links to desktop navigation**

Edit `resources/views/layouts/navigation.blade.php` to add Pelanggan, Pembacaan, Tagihan, Pembayaran links in the desktop nav section.

- [ ] **Step 2: Add operator nav links to mobile navigation**

Add the same links in the responsive/mobile nav section.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/navigation.blade.php
git commit -m "feat(navigation): add operator nav links for all modules"
```

---

### Task 14: Write Tests — PembayaranService Unit Tests

**Files:**
- Create: `tests/Unit/PembayaranServiceTest.php`

**What to do:**
Write unit tests for the payment service covering: successful full payment → lunas, partial payment → cicilan, second payment → lunas, overpayment rejection, cancelled tagihan rejection.

```php
<?php

namespace Tests\Unit;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembayaranServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tagihan $tagihan;
    private User $operator;
    private PembayaranService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3059,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $pelanggan = Pelanggan::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id, 'pelanggan_id' => $pelanggan->id]);
        $pembacaan = Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'meter_id' => $meter->id,
            'volume_m3' => 17,
            'status' => 'konfirmasi',
        ]);

        $this->tagihan = Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'volume_m3' => 17,
            'tarif_per_m3' => 3059,
            'total_tagihan' => 52003,
            'status' => 'belum_bayar',
        ]);

        $this->service = new PembayaranService();
    }

    public function test_full_payment_sets_status_lunas(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $pembayaran = $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 52003,
            'metode_bayar' => 'tunai',
        ]);

        $this->assertDatabaseHas('pembayaran', [
            'id' => $pembayaran->id,
            'jumlah_bayar' => 52003,
            'tagihan_id' => $this->tagihan->id,
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
    }

    public function test_partial_payment_sets_status_cicilan(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 25000,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('cicilan', $this->tagihan->status);
        $this->assertEquals(27003, $this->tagihan->sisaTagihan());
    }

    public function test_second_payment_completes_to_lunas(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 25000,
            'metode_bayar' => 'tunai',
        ]);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 27003,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
        $this->assertEquals(0, $this->tagihan->sisaTagihan());
    }

    public function test_overpayment_rejected(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 999999,
            'metode_bayar' => 'tunai',
        ]);
    }

    public function test_payment_on_cancelled_tagihan_rejected(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->tagihan->update(['status' => 'batal']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 10000,
            'metode_bayar' => 'tunai',
        ]);
    }
}
```

Note: You'll need to create model factories for Tenant, Pelanggan, Meter, Pembacaan, and Tagihan (if they don't exist). Check `database/factories/` first.

- [ ] **Step 1: Verify/create model factories**

Check if factories exist for Tenant, Pelanggan, Meter, Pembacaan, Tagihan. Create any missing ones following Laravel factory conventions.

- [ ] **Step 2: Create test file**

Create `tests/Unit/PembayaranServiceTest.php` with the code above.

- [ ] **Step 3: Run tests**

Run: `php artisan test --filter PembayaranServiceTest`
Expected: All 5 tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/PembayaranServiceTest.php database/factories/
git commit -m "test(pembayaran): add PembayaranService unit tests"
```

---

### Task 15: Write Tests — Pembayaran Feature Tests

**Files:**
- Create: `tests/Feature/PembayaranStoreTest.php`
- Create: `tests/Feature/PembayaranPdfTest.php`

**What to do:**
Write feature tests for the payment controller endpoints: store payment, list payments, download receipt PDF, download invoice PDF.

```php
<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranStoreTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private Tagihan $tagihan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3059,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $pelanggan = Pelanggan::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id, 'pelanggan_id' => $pelanggan->id]);
        $pembacaan = Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'meter_id' => $meter->id,
            'status' => 'konfirmasi',
        ]);

        $this->tagihan = Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'total_tagihan' => 52000,
            'status' => 'belum_bayar',
        ]);
    }

    public function test_operator_can_record_payment(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), [
                'tanggal_bayar' => now()->toDateString(),
                'jumlah_bayar' => 52000,
                'metode_bayar' => 'tunai',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pembayaran', [
            'tagihan_id' => $this->tagihan->id,
            'jumlah_bayar' => 52000,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
    }

    public function test_payment_validates_required_fields(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), []);

        $response->assertSessionHasErrors(['tanggal_bayar', 'jumlah_bayar', 'metode_bayar']);
    }

    public function test_transfer_payment_requires_reference(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), [
                'tanggal_bayar' => now()->toDateString(),
                'jumlah_bayar' => 52000,
                'metode_bayar' => 'transfer',
            ]);

        $response->assertSessionHasErrors(['no_referensi']);
    }

    public function test_operator_can_view_payment_list(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.index'));

        $response->assertOk();
        $response->assertViewIs('operator.pembayaran.index');
    }

    public function test_operator_can_view_payment_form(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.create', $this->tagihan));

        $response->assertOk();
        $response->assertViewIs('operator.pembayaran.create');
    }

    public function test_super_admin_cannot_access_payment_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get(route('pembayaran.index'));

        $response->assertForbidden();
    }
}
```

```php
<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3059,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);
    }

    public function test_operator_can_download_receipt_pdf(): void
    {
        $pembayaran = $this->createPaidPembayaran();

        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.cetak', $pembayaran));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_reprint_receipt(): void
    {
        $pembayaran = $this->createPaidPembayaran();

        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.cetak-ulang', $pembayaran));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_download_invoice_pdf(): void
    {
        $tagihan = $this->createUnpaidTagihan();

        $response = $this->actingAs($this->operator)
            ->get(route('tagihan.cetak-invoice', $tagihan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function createPaidPembayaran(): Pembayaran
    {
        $tagihan = $this->createUnpaidTagihan();

        return Pembayaran::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tagihan_id' => $tagihan->id,
            'pelanggan_id' => $tagihan->pelanggan_id,
            'jumlah_bayar' => $tagihan->total_tagihan,
            'petugas_kasir' => $this->operator->id,
        ]);
    }

    private function createUnpaidTagihan(): Tagihan
    {
        $pelanggan = Pelanggan::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id, 'pelanggan_id' => $pelanggan->id]);
        $pembacaan = Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'meter_id' => $meter->id,
            'status' => 'konfirmasi',
        ]);

        return Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'total_tagihan' => 52000,
            'status' => 'belum_bayar',
        ]);
    }
}
```

Note: You'll also need a `Pembayaran` factory at `database/factories/PembayaranFactory.php`.

- [ ] **Step 1: Create PembayaranFactory**

Create factory at `database/factories/PembayaranFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'tanggal_bayar' => $this->faker->date(),
            'jumlah_bayar' => $this->faker->randomFloat(2, 10000, 200000),
            'metode_bayar' => $this->faker->randomElement(['tunai', 'transfer', 'ewallet']),
            'no_referensi' => null,
            'catatan' => null,
        ];
    }
}
```

- [ ] **Step 2: Create feature test files**

Create `tests/Feature/PembayaranStoreTest.php` and `tests/Feature/PembayaranPdfTest.php` with the code above.

- [ ] **Step 3: Run all tests**

Run: `php artisan test --filter Pembayaran`
Expected: All tests pass (service unit tests + feature tests).

- [ ] **Step 4: Commit**

```bash
git add tests/ database/factories/PembayaranFactory.php
git commit -m "test(pembayaran): add feature tests for payment and PDF endpoints"
```

---

## Final Verification Wave (MANDATORY — after ALL implementation tasks)

> 4 review agents run in PARALLEL. ALL must APPROVE. Present consolidated results to user and get explicit "okay" before completing.
> **Do NOT auto-proceed after verification. Wait for user's explicit approval before marking work complete.**
> **Never mark F1-F4 as checked before getting user's okay.** Rejection or user feedback -> fix -> re-run -> present again -> wait for okay.

- [ ] F1. Plan Compliance Audit — oracle
- [ ] F2. Code Quality Review — unspecified-high
- [ ] F3. Real Manual QA — unspecified-high (+ playwright if UI)
- [ ] F4. Scope Fidelity Check — deep

---

## Commit Strategy

Each task commits independently with conventional commit format:
- `feat(pembayaran):` for new features
- `test(pembayaran):` for test files
- `fix(pembayaran):` for bug fixes

Suggested branch name: `feature/phase2-pembayaran-cetak`

---

## Success Criteria

1. Operator can record a payment against any active tagihan
2. Tagihan status auto-updates: belum_bayar → cicilan (partial) → lunas (full)
3. Overpayments are rejected with clear error message
4. Payment on cancelled tagihan is rejected
5. PDF kwitansi downloads for 58mm and 80mm thermal paper
6. Invoice PDF downloads for unpaid tagihan
7. Receipt reprint works from payment history
8. All tenant data is properly scoped (no cross-tenant leakage)
9. Navigation shows Pembayaran link for operator users
10. All tests pass: `php artisan test`
