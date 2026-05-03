# Phase 3 — Laporan & Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build operator dashboard with summary cards and 6-month revenue chart, super admin consolidated dashboard, three report pages (pemakaian, pendapatan, tunggakan) with filters, and PDF/Excel export for each report.

**Architecture:** Build on Phase 1+2 multi-tenant foundation. Operator dashboard queries are auto-scoped by `BelongsToTenant` global scope. Super Admin dashboard bypasses tenant scope using `withoutGlobalScope('tenant')` for cross-tenant aggregation. Reports use Chart.js for dashboard charts, maatwebsite/excel for Excel exports, and dompdf for PDF report exports.

**Tech Stack:** Laravel 13, PHP 8.3+, MySQL 8, Blade + Tailwind CSS + Alpine.js, Chart.js, maatwebsite/excel 3.x, barryvdh/laravel-dompdf 3.x, PHPUnit

---

## File Structure

### New Files
- `app/Http/Controllers/Operator/DashboardController.php` — Operator dashboard with summary stats + chart data
- `app/Http/Controllers/SuperAdmin/DashboardController.php` — Super Admin cross-tenant dashboard
- `app/Http/Controllers/Operator/LaporanController.php` — Three reports: pemakaian, pendapatan, tunggakan + export
- `app/Exports/PemakaianExport.php` — Excel export for usage report
- `app/Exports/PendapatanExport.php` — Excel export for revenue report
- `app/Exports/TunggakanExport.php` — Excel export for outstanding report
- `resources/views/operator/dashboard.blade.php` — Operator dashboard with 4 cards + Chart.js revenue chart + tunggakan list
- `resources/views/super-admin/dashboard.blade.php` — Super Admin dashboard with 3 cards + per-tenant breakdown table
- `resources/views/operator/laporan/pemakaian.blade.php` — Water usage report with filters
- `resources/views/operator/laporan/pendapatan.blade.php` — Revenue report with filters
- `resources/views/operator/laporan/tunggakan.blade.php` — Outstanding bills report
- `resources/views/laporan/pdf/pemakaian.blade.php` — PDF template for usage report
- `resources/views/laporan/pdf/pendapatan.blade.php` — PDF template for revenue report
- `resources/views/laporan/pdf/tunggakan.blade.php` — PDF template for outstanding report
- `tests/Feature/DashboardTest.php` — Dashboard feature tests
- `tests/Feature/LaporanTest.php` — Report and export feature tests

### Modified Files
- `package.json` — Add chart.js dependency
- `resources/js/app.js` — Import Chart.js (register globally for Alpine.js pages)
- `resources/views/layouts/navigation.blade.php` — Add Laporan nav link for operator
- `routes/web.php` — Add dashboard and laporan routes
- `resources/views/dashboard.blade.php` — Replace Breeze stub with role-based redirect

---

## Existing Patterns Reference (MUST follow)

### Controller Pattern
```
File: app/Http/Controllers/Operator/PembayaranController.php
- Constructor: $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
- Tenant context: app('current.tenant')
- Ownership check: $this->ensureTenantOwnership($model) → abort(404) if mismatch
- Redirect: redirect()->route('xxx.index')->with('success', '...')
```

### Super Admin Pattern
```
File: app/Http/Controllers/SuperAdmin/TenantController.php
- Constructor: $this->middleware('auth'); + $this->authorizeResource(Tenant::class, 'tenant');
- Route group: middleware('can:viewAny,App\Models\Tenant')->prefix('super-admin')
- No tenant scope — queries across all tenants
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

### Model Pattern (for cross-tenant queries)
```
// Super Admin bypasses tenant scope:
Tagihan::withoutGlobalScope('tenant')
    ->selectRaw('tenant_id, SUM(total_tagihan) as total')
    ->groupBy('tenant_id')
    ->get();
```

### Multi-Tenancy
```
File: app/Traits/BelongsToTenant.php
- Global scope 'tenant' filters by app('current.tenant')->id
- Auto-sets tenant_id on create when app('current.tenant') exists
- Super Admin has null tenant_id — no global scope applied
- To query cross-tenant: Model::withoutGlobalScope('tenant')
```

---

## TODOs

### Task 1: Install Chart.js

**Files:**
- Modify: `package.json`
- Modify: `resources/js/app.js`

**What to do:**
Install Chart.js via npm and import it in the app's JavaScript entry point so it's available globally for all Blade views.

- [ ] **Step 1: Install chart.js**

```bash
cd /Users/labba.awwabi/Personal/hippam
npm install chart.js --save
```

- [ ] **Step 2: Import Chart.js in app.js**

Add to `resources/js/app.js` (after the existing Alpine.js import block):

```js
import Chart from 'chart.js/auto';

// Make Chart.js available globally for Blade views
window.Chart = Chart;
```

- [ ] **Step 3: Build assets**

```bash
npm run build
```

Expected: Build succeeds without errors. `chart.js` appears in `package.json` dependencies.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/app.js public/build/
git commit -m "chore: install chart.js for dashboard charts"
```

---

### Task 2: Create Operator DashboardController

**Files:**
- Create: `app/Http/Controllers/Operator/DashboardController.php`

**What to do:**
Create the operator dashboard controller that computes 4 summary stats and 6-month revenue chart data. Follow the existing operator controller pattern with `['auth', 'tenant.resolve', 'tenant.ensure']` middleware.

Summary cards:
1. Total pelanggan aktif (status = 'aktif')
2. Pendapatan bulan ini (SUM of jumlah_bayar from pembayaran where tanggal_bayar is in current month)
3. Tagihan belum lunas (COUNT of tagihan where status in ('belum_bayar', 'cicilan'))
4. Total tagihan bulan ini (SUM of total_tagihan where periode = current YYYY-MM)

Chart data: Monthly revenue for last 6 months. Each point = SUM(jumlah_bayar) from pembayaran grouped by month.

```php
<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function __invoke(): View
    {
        $tenant = app('current.tenant');
        $currentPeriode = now()->format('Y-m');

        // 1. Total pelanggan aktif
        $totalPelangganAktif = Pelanggan::where('status', 'aktif')->count();

        // 2. Pendapatan bulan ini
        $pendapatanBulanIni = Pembayaran::whereYear('tanggal_bayar', now()->year)
            ->whereMonth('tanggal_bayar', now()->month)
            ->sum('jumlah_bayar');

        // 3. Tagihan belum lunas
        $tagihanBelumLunas = Tagihan::whereIn('status', ['belum_bayar', 'cicilan'])->count();

        // 4. Total tagihan bulan ini
        $totalTagihanBulanIni = Tagihan::where('periode', $currentPeriode)
            ->sum('total_tagihan');

        // 5. Grafik pendapatan 6 bulan terakhir
        $chartData = $this->getRevenueChartData();

        // 6. Top 5 tunggakan terbaru
        $tunggakanList = Tagihan::with('pelanggan')
            ->whereIn('status', ['belum_bayar', 'cicilan'])
            ->orderByRaw('tanggal_jatuh_tempo ASC')
            ->limit(5)
            ->get();

        return view('operator.dashboard', compact(
            'totalPelangganAktif',
            'pendapatanBulanIni',
            'tagihanBelumLunas',
            'totalTagihanBulanIni',
            'chartData',
            'tunggakanList',
        ));
    }

    private function getRevenueChartData(): array
    {
        $months = [];
        $revenues = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->translatedFormat('F Y');

            $revenue = Pembayaran::whereYear('tanggal_bayar', $date->year)
                ->whereMonth('tanggal_bayar', $date->month)
                ->sum('jumlah_bayar');

            $revenues[] = (float) $revenue;
        }

        return [
            'labels' => $months,
            'data' => $revenues,
        ];
    }
}
```

- [ ] **Step 1: Create controller file**

Create `app/Http/Controllers/Operator/DashboardController.php` with the code above.

- [ ] **Step 2: Verify controller loads**

Run: `php artisan tinker --execute="echo new \App\Http\Controllers\Operator\DashboardController;"`
Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Operator/DashboardController.php
git commit -m "feat(dashboard): add Operator DashboardController with summary stats and chart data"
```

---

### Task 3: Create Super Admin DashboardController

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/DashboardController.php`

**What to do:**
Create the super admin dashboard controller that aggregates data across ALL tenants. Must bypass `BelongsToTenant` global scope. Follow the existing SuperAdmin controller pattern.

Summary cards:
1. Jumlah tenant aktif
2. Total pelanggan semua tenant
3. Total pendapatan bulan ini (cross-tenant)

Per-tenant breakdown table: Each tenant with their pelanggan count, revenue this month, and unpaid tagihan count.

```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(): View
    {
        // 1. Jumlah tenant aktif
        $jumlahTenant = Tenant::where('is_active', true)->count();

        // 2. Total pelanggan (cross-tenant)
        $totalPelanggan = Pelanggan::withoutGlobalScope('tenant')
            ->where('status', 'aktif')
            ->count();

        // 3. Total pendapatan bulan ini (cross-tenant)
        $totalPendapatan = Pembayaran::withoutGlobalScope('tenant')
            ->whereYear('tanggal_bayar', now()->year)
            ->whereMonth('tanggal_bayar', now()->month)
            ->sum('jumlah_bayar');

        // 4. Per-tenant breakdown
        $tenantBreakdown = Tenant::where('is_active', true)
            ->withCount(['pelanggan' => function ($query) {
                $query->where('status', 'aktif');
            }])
            ->get()
            ->map(function ($tenant) {
                $pendapatan = Pembayaran::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereYear('tanggal_bayar', now()->year)
                    ->whereMonth('tanggal_bayar', now()->month)
                    ->sum('jumlah_bayar');

                $belumLunas = Tagihan::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['belum_bayar', 'cicilan'])
                    ->count();

                $tenant->pendapatan_bulan_ini = $pendapatan;
                $tenant->belum_lunas = $belumLunas;

                return $tenant;
            });

        return view('super-admin.dashboard', compact(
            'jumlahTenant',
            'totalPelanggan',
            'totalPendapatan',
            'tenantBreakdown',
        ));
    }
}
```

- [ ] **Step 1: Create controller file**

Create `app/Http/Controllers/SuperAdmin/DashboardController.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/SuperAdmin/DashboardController.php
git commit -m "feat(dashboard): add SuperAdmin DashboardController with cross-tenant aggregation"
```

---

### Task 4: Create Operator Dashboard View

**Files:**
- Create: `resources/views/operator/dashboard.blade.php`

**What to do:**
Create the operator dashboard view with 4 summary cards, a Chart.js bar chart for 6-month revenue trend, and a top 5 tunggakan list. Follow the existing view pattern from `resources/views/operator/pelanggan/index.blade.php`.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard — {{ app('current.tenant')->nama_unit }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Pelanggan Aktif</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalPelangganAktif }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Pendapatan Bulan Ini</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">Rp {{ number_format($pendapatanBulanIni, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Tagihan Belum Lunas</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ $tagihanBelumLunas }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Tagihan Bulan Ini</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">Rp {{ number_format($totalTagihanBulanIni, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Revenue Chart --}}
                <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tren Pendapatan 6 Bulan</h3>
                    <div class="relative" style="height: 300px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                {{-- Tunggakan List --}}
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Tunggakan Terbaru</h3>
                    @if($tunggakanList->isEmpty())
                        <p class="text-sm text-gray-500">Tidak ada tunggakan.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($tunggakanList as $t)
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</p>
                                        <p class="text-xs text-gray-500">Periode: {{ $t->periode }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-red-600">Rp {{ number_format($t->sisaTagihan(), 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <a href="{{ route('laporan.tunggakan') }}" class="text-sm text-primary-600 hover:text-primary-700 font-medium">Lihat semua &rarr;</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: @json($chartData['labels']),
                    datasets: [{
                        label: 'Pendapatan (Rp)',
                        data: @json($chartData['data']),
                        backgroundColor: 'rgba(37, 99, 235, 0.7)',
                        borderColor: 'rgba(37, 99, 235, 1)',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + (value / 1000) + 'rb';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 1: Create view file**

Create `resources/views/operator/dashboard.blade.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/operator/dashboard.blade.php
git commit -m "feat(dashboard): add Operator dashboard view with chart and tunggakan list"
```

---

### Task 5: Create Super Admin Dashboard View

**Files:**
- Create: `resources/views/super-admin/dashboard.blade.php`

**What to do:**
Create the super admin dashboard view with 3 summary cards (jumlah tenant, total pelanggan, total pendapatan) and a per-tenant breakdown table. Follow the super-admin view pattern from `resources/views/super-admin/tenants/index.blade.php`.

```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard Super Admin
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Jumlah Unit HIPPAM</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $jumlahTenant }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Pelanggan Aktif</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalPelanggan }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Pendapatan Bulan Ini</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Per-Tenant Breakdown --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Ringkasan Per Unit</h3>
                </div>
                @if($tenantBreakdown->isEmpty())
                    <div class="p-8 text-center text-gray-500">Belum ada data tenant.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pendapatan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Belum Lunas</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tenantBreakdown as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $t->nama_unit }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $t->desa ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-right text-gray-900">{{ $t->pelanggan_count }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-medium text-gray-900">Rp {{ number_format($t->pendapatan_bulan_ini, 0, ',', '.') }}</td>
                                        <td class="px-6 py-4 text-sm text-right">
                                            @if($t->belum_lunas > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $t->belum_lunas }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 1: Create view file**

Create `resources/views/super-admin/dashboard.blade.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/super-admin/dashboard.blade.php
git commit -m "feat(dashboard): add SuperAdmin dashboard view with tenant breakdown"
```

---

### Task 6: Create LaporanController (Operator)

**Files:**
- Create: `app/Http/Controllers/Operator/LaporanController.php`

**What to do:**
Create the laporan controller with 3 report actions (pemakaian, pendapatan, tunggakan) and 3 export actions (one per report, each supporting PDF and Excel format). Follow the operator controller pattern.

Business rules:
- Pemakaian report: Volume per pelanggan, filter by periode (YYYY-MM), shows volume rata-rata
- Pendapatan report: Total tagihan vs total terbayar, filter by date range and grouping (harian/mingguan/bulanan)
- Tunggakan report: All tagihan with status belum_bayar/cicilan, filter by periode, sort by jumlah/periode

```php
<?php

namespace App\Http\Controllers\Operator;

use App\Exports\PemakaianExport;
use App\Exports\PendapatanExport;
use App\Exports\TunggakanExport;
use App\Http\Controllers\Controller;
use App\Models\Pembacaan;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    // ─── PEMAKAIAN ───────────────────────────────────────────

    public function pemakaian(Request $request): View
    {
        $periode = $request->input('periode', now()->format('Y-m'));

        $pembacaan = Pembacaan::with(['pelanggan', 'meter'])
            ->where('periode', $periode)
            ->where('status', 'konfirmasi')
            ->orderBy('volume_m3', 'desc')
            ->paginate(50)
            ->withQueryString();

        $totalVolume = Pembacaan::where('periode', $periode)
            ->where('status', 'konfirmasi')
            ->sum('volume_m3');

        $rataRata = $pembacaan->total() > 0
            ? $totalVolume / Pembacaan::where('periode', $periode)->where('status', 'konfirmasi')->count()
            : 0;

        return view('operator.laporan.pemakaian', compact('pembacaan', 'periode', 'totalVolume', 'rataRata'));
    }

    // ─── PENDAPATAN ──────────────────────────────────────────

    public function pendapatan(Request $request): View
    {
        $periode = $request->input('periode', now()->format('Y-m'));
        $grouping = $request->input('grouping', 'bulanan');

        $tagihan = Tagihan::with('pelanggan')
            ->where('periode', $periode)
            ->orderBy('total_tagihan', 'desc')
            ->paginate(50)
            ->withQueryString();

        $totalTagihan = Tagihan::where('periode', $periode)->sum('total_tagihan');
        $totalTerbayar = Pembayaran::whereHas('tagihan', function ($q) use ($periode) {
            $q->where('periode', $periode);
        })->sum('jumlah_bayar');
        $totalBelumBayar = max(0, $totalTagihan - $totalTerbayar);

        return view('operator.laporan.pendapatan', compact(
            'tagihan', 'periode', 'grouping',
            'totalTagihan', 'totalTerbayar', 'totalBelumBayar',
        ));
    }

    // ─── TUNGGAKAN ───────────────────────────────────────────

    public function tunggakan(Request $request): View
    {
        $query = Tagihan::with('pelanggan')
            ->whereIn('status', ['belum_bayar', 'cicilan']);

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        $sort = $request->input('sort', 'tanggal_jatuh_tempo');
        $direction = $request->input('direction', 'asc');

        if ($sort === 'jumlah') {
            $query->orderBy('total_tagihan', $direction === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('tanggal_jatuh_tempo', $direction === 'asc' ? 'asc' : 'desc');
        }

        $tagihan = $query->paginate(50)->withQueryString();

        $totalTunggakan = Tagihan::whereIn('status', ['belum_bayar', 'cicilan'])
            ->when($request->filled('periode'), fn($q) => $q->where('periode', $request->periode))
            ->sum('total_tagihan');

        return view('operator.laporan.tunggakan', compact('tagihan', 'totalTunggakan'));
    }

    // ─── EXPORTS ─────────────────────────────────────────────

    public function exportPemakaian(Request $request)
    {
        $periode = $request->input('periode', now()->format('Y-m'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return $this->exportPemakaianPdf($periode);
        }

        return Excel::download(
            new PemakaianExport($periode),
            "laporan-pemakaian-{$periode}.xlsx"
        );
    }

    public function exportPendapatan(Request $request)
    {
        $periode = $request->input('periode', now()->format('Y-m'));
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return $this->exportPendapatanPdf($periode);
        }

        return Excel::download(
            new PendapatanExport($periode),
            "laporan-pendapatan-{$periode}.xlsx"
        );
    }

    public function exportTunggakan(Request $request)
    {
        $periode = $request->input('periode');
        $format = $request->input('format', 'xlsx');

        if ($format === 'pdf') {
            return $this->exportTunggakanPdf($periode);
        }

        $filename = $periode ? "laporan-tunggakan-{$periode}.xlsx" : 'laporan-tunggakan-semua.xlsx';

        return Excel::download(
            new TunggakanExport($periode),
            $filename
        );
    }

    // ─── PDF EXPORT HELPERS ──────────────────────────────────

    private function exportPemakaianPdf(string $periode)
    {
        $tenant = app('current.tenant');
        $pembacaan = Pembacaan::with(['pelanggan', 'meter'])
            ->where('periode', $periode)
            ->where('status', 'konfirmasi')
            ->orderBy('volume_m3', 'desc')
            ->get();

        $totalVolume = $pembacaan->sum('volume_m3');

        $pdf = Pdf::loadView('laporan.pdf.pemakaian', compact('pembacaan', 'periode', 'tenant', 'totalVolume'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("laporan-pemakaian-{$periode}.pdf");
    }

    private function exportPendapatanPdf(string $periode)
    {
        $tenant = app('current.tenant');
        $tagihan = Tagihan::with('pelanggan')
            ->where('periode', $periode)
            ->orderBy('total_tagihan', 'desc')
            ->get();

        $totalTagihan = $tagihan->sum('total_tagihan');
        $totalTerbayar = Pembayaran::whereHas('tagihan', fn($q) => $q->where('periode', $periode))->sum('jumlah_bayar');

        $pdf = Pdf::loadView('laporan.pdf.pendapatan', compact('tagihan', 'periode', 'tenant', 'totalTagihan', 'totalTerbayar'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("laporan-pendapatan-{$periode}.pdf");
    }

    private function exportTunggakanPdf(?string $periode)
    {
        $tenant = app('current.tenant');
        $query = Tagihan::with('pelanggan')
            ->whereIn('status', ['belum_bayar', 'cicilan']);

        if ($periode) {
            $query->where('periode', $periode);
        }

        $tagihan = $query->orderBy('tanggal_jatuh_tempo', 'asc')->get();
        $totalTunggakan = $tagihan->sum('total_tagihan');

        $pdf = Pdf::loadView('laporan.pdf.tunggakan', compact('tagihan', 'periode', 'tenant', 'totalTunggakan'))
            ->setPaper('a4', 'landscape');

        $filePeriode = $periode ?? 'semua';
        return $pdf->download("laporan-tunggakan-{$filePeriode}.pdf");
    }
}
```

- [ ] **Step 1: Create controller file**

Create `app/Http/Controllers/Operator/LaporanController.php` with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/Operator/LaporanController.php
git commit -m "feat(laporan): add LaporanController with pemakaian, pendapatan, tunggakan reports and exports"
```

---

### Task 7: Create Report Views

**Files:**
- Create: `resources/views/operator/laporan/pemakaian.blade.php`
- Create: `resources/views/operator/laporan/pendapatan.blade.php`
- Create: `resources/views/operator/laporan/tunggakan.blade.php`

**What to do:**
Create the three report view files following the established view pattern.

**`resources/views/operator/laporan/pemakaian.blade.php`:**
```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Pemakaian Air</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Filter --}}
            <form method="GET" action="{{ route('laporan.pemakaian') }}" class="mb-6 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <input type="month" name="periode" value="{{ $periode }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Tampilkan</button>
                <a href="{{ route('laporan.export.pemakaian', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">Export Excel</a>
                <a href="{{ route('laporan.export.pemakaian', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">Export PDF</a>
            </form>

            {{-- Summary --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Volume</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($totalVolume, 1) }} m³</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Rata-rata</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($rataRata, 1) }} m³</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Jumlah Pelanggan</p>
                    <p class="text-xl font-bold text-gray-900">{{ $pembacaan->total() }}</p>
                </div>
            </div>

            {{-- Table --}}
            @if($pembacaan->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Tidak ada data untuk periode ini.</div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Meter</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Meter Sebelumnya</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Meter Sekarang</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Volume (m³)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($pembacaan as $p)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $p->pelanggan->nomor_pelanggan }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $p->meter->nomor_meter ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($p->angka_meter_sebelumnya, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($p->angka_meter_sekarang, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($p->volume_m3, 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-6">{{ $pembacaan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

**`resources/views/operator/laporan/pendapatan.blade.php`:**
```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Pendapatan</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Filter --}}
            <form method="GET" action="{{ route('laporan.pendapatan') }}" class="mb-6 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <input type="month" name="periode" value="{{ $periode }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Tampilkan</button>
                <a href="{{ route('laporan.export.pendapatan', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">Export Excel</a>
                <a href="{{ route('laporan.export.pendapatan', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">Export PDF</a>
            </form>

            {{-- Summary --}}
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Tagihan</p>
                    <p class="text-xl font-bold text-gray-900">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Terbayar</p>
                    <p class="text-xl font-bold text-green-600">Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Belum Terbayar</p>
                    <p class="text-xl font-bold text-red-600">Rp {{ number_format($totalBelumBayar, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Table --}}
            @if($tagihan->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Tidak ada data untuk periode ini.</div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Volume (m³)</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Tagihan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($tagihan as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $t->pelanggan->nomor_pelanggan }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($t->volume_m3, 1) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3">
                                            @if($t->status === 'lunas')
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                                            @elseif($t->status === 'cicilan')
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                                            @else
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-6">{{ $tagihan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

**`resources/views/operator/laporan/tunggakan.blade.php`:**
```html
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Tunggakan</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Filter --}}
            <form method="GET" action="{{ route('laporan.tunggakan') }}" class="mb-6 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <input type="month" name="periode" value="{{ request('periode') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <select name="sort" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    <option value="tanggal_jatuh_tempo" {{ request('sort', 'tanggal_jatuh_tempo') === 'tanggal_jatuh_tempo' ? 'selected' : '' }}>Urut: Jatuh Tempo</option>
                    <option value="jumlah" {{ request('sort') === 'jumlah' ? 'selected' : '' }}>Urut: Jumlah Terbesar</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Tampilkan</button>
                <a href="{{ route('laporan.export.tunggakan', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">Export Excel</a>
                <a href="{{ route('laporan.export.tunggakan', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">Export PDF</a>
            </form>

            {{-- Summary --}}
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <p class="text-sm text-gray-500">Total Tunggakan</p>
                <p class="text-2xl font-bold text-red-600">Rp {{ number_format($totalTunggakan, 0, ',', '.') }}</p>
            </div>

            {{-- Table --}}
            @if($tagihan->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Tidak ada tunggakan.</div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Periode</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Tagihan</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sudah Dibayar</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Sisa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jatuh Tempo</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($tagihan as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $t->pelanggan->nomor_pelanggan }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $t->periode }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-green-600">Rp {{ number_format($t->totalDibayar(), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-red-600">Rp {{ number_format($t->sisaTagihan(), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $t->tanggal_jatuh_tempo->format('d M Y') }}</td>
                                        <td class="px-4 py-3">
                                            @if($t->tanggal_jatuh_tempo->isPast())
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Lewat Jatuh Tempo</span>
                                            @elseif($t->status === 'cicilan')
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                                            @else
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Belum Bayar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-6">{{ $tagihan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 1: Create pemakaian view**

Create `resources/views/operator/laporan/pemakaian.blade.php` with the code above.

- [ ] **Step 2: Create pendapatan view**

Create `resources/views/operator/laporan/pendapatan.blade.php` with the code above.

- [ ] **Step 3: Create tunggakan view**

Create `resources/views/operator/laporan/tunggakan.blade.php` with the code above.

- [ ] **Step 4: Commit**

```bash
git add resources/views/operator/laporan/
git commit -m "feat(laporan): add pemakaian, pendapatan, and tunggakan report views"
```

---

### Task 8: Create Export Classes (Excel)

**Files:**
- Create: `app/Exports/PemakaianExport.php`
- Create: `app/Exports/PendapatanExport.php`
- Create: `app/Exports/TunggakanExport.php`

**What to do:**
Create three maatwebsite/excel export classes. Each uses `FromCollection` and `WithHeadings` for clean Excel output.

**`app/Exports/PemakaianExport.php`:**
```php
<?php

namespace App\Exports;

use App\Models\Pembacaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PemakaianExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $periode;

    public function __construct(string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        return Pembacaan::with(['pelanggan', 'meter'])
            ->where('periode', $this->periode)
            ->where('status', 'konfirmasi')
            ->orderBy('volume_m3', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'No. Meter',
            'Meter Sebelumnya',
            'Meter Sekarang',
            'Volume (m³)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->meter->nomor_meter ?? '-',
            $row->angka_meter_sebelumnya,
            $row->angka_meter_sekarang,
            $row->volume_m3,
        ];
    }
}
```

**`app/Exports/PendapatanExport.php`:**
```php
<?php

namespace App\Exports;

use App\Models\Tagihan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PendapatanExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $periode;

    public function __construct(string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        return Tagihan::with('pelanggan')
            ->where('periode', $this->periode)
            ->orderBy('total_tagihan', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'Periode',
            'Volume (m³)',
            'Total Tagihan',
            'Sudah Dibayar',
            'Sisa',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->periode,
            $row->volume_m3,
            $row->total_tagihan,
            $row->totalDibayar(),
            $row->sisaTagihan(),
            $row->status,
        ];
    }
}
```

**`app/Exports/TunggakanExport.php`:**
```php
<?php

namespace App\Exports;

use App\Models\Tagihan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TunggakanExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?string $periode;

    public function __construct(?string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        $query = Tagihan::with('pelanggan')
            ->whereIn('status', ['belum_bayar', 'cicilan']);

        if ($this->periode) {
            $query->where('periode', $this->periode);
        }

        return $query->orderBy('tanggal_jatuh_tempo', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'Periode',
            'Total Tagihan',
            'Sudah Dibayar',
            'Sisa',
            'Jatuh Tempo',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->periode,
            $row->total_tagihan,
            $row->totalDibayar(),
            $row->sisaTagihan(),
            $row->tanggal_jatuh_tempo->format('d/m/Y'),
            $row->status,
        ];
    }
}
```

- [ ] **Step 1: Create export directory and files**

Create the `app/Exports/` directory and all three export files with the code above.

- [ ] **Step 2: Commit**

```bash
git add app/Exports/
git commit -m "feat(export): add Excel export classes for pemakaian, pendapatan, tunggakan"
```

---

### Task 9: Create PDF Report Templates

**Files:**
- Create: `resources/views/laporan/pdf/pemakaian.blade.php`
- Create: `resources/views/laporan/pdf/pendapatan.blade.php`
- Create: `resources/views/laporan/pdf/tunggakan.blade.php`

**What to do:**
Create three PDF report templates for dompdf. Each is a clean A4 landscape table with a header showing the tenant name and report title. Follow the print template approach from `resources/views/prints/receipt.blade.php` (inline CSS, no external dependencies).

**`resources/views/laporan/pdf/pemakaian.blade.php`:**
```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 15mm; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 12px; text-align: center; color: #666; margin-bottom: 10px; }
        .summary { margin-bottom: 15px; }
        .summary span { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>Laporan Pemakaian Air</h1>
    <h2>{{ $tenant->nama_unit }} — Periode: {{ $periode }}</h2>

    <div class="summary">
        <span>Total Volume:</span> {{ number_format($totalVolume, 1) }} m³ &nbsp;|&nbsp;
        <span>Jumlah Pelanggan:</span> {{ $pembacaan->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Pelanggan</th>
                <th>Nama</th>
                <th>No. Meter</th>
                <th class="text-right">Meter Sebelumnya</th>
                <th class="text-right">Meter Sekarang</th>
                <th class="text-right">Volume (m³)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pembacaan as $p)
            <tr>
                <td>{{ $p->pelanggan->nomor_pelanggan }}</td>
                <td>{{ $p->pelanggan->nama }}</td>
                <td>{{ $p->meter->nomor_meter ?? '-' }}</td>
                <td class="text-right">{{ number_format($p->angka_meter_sebelumnya, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->angka_meter_sekarang, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->volume_m3, 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak dari Sistem HIPPAM — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
```

**`resources/views/laporan/pdf/pendapatan.blade.php`:**
```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 15mm; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 12px; text-align: center; color: #666; margin-bottom: 10px; }
        .summary { margin-bottom: 15px; }
        .summary span { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>Laporan Pendapatan</h1>
    <h2>{{ $tenant->nama_unit }} — Periode: {{ $periode }}</h2>

    <div class="summary">
        <span>Total Tagihan:</span> Rp {{ number_format($totalTagihan, 0, ',', '.') }} &nbsp;|&nbsp;
        <span>Total Terbayar:</span> Rp {{ number_format($totalTerbayar, 0, ',', '.') }} &nbsp;|&nbsp;
        <span>Belum Terbayar:</span> Rp {{ number_format($totalTagihan - $totalTerbayar, 0, ',', '.') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Pelanggan</th>
                <th>Nama</th>
                <th class="text-right">Volume (m³)</th>
                <th class="text-right">Total Tagihan</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tagihan as $t)
            <tr>
                <td>{{ $t->pelanggan->nomor_pelanggan }}</td>
                <td>{{ $t->pelanggan->nama }}</td>
                <td class="text-right">{{ number_format($t->volume_m3, 1) }}</td>
                <td class="text-right">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $t->status)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak dari Sistem HIPPAM — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
```

**`resources/views/laporan/pdf/tunggakan.blade.php`:**
```html
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 15mm; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 12px; text-align: center; color: #666; margin-bottom: 10px; }
        .summary { margin-bottom: 15px; }
        .summary span { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>Laporan Tunggakan</h1>
    <h2>{{ $tenant->nama_unit }}@if($periode) — Periode: {{ $periode }}@endif</h2>

    <div class="summary">
        <span>Total Tunggakan:</span> Rp {{ number_format($totalTunggakan, 0, ',', '.') }} &nbsp;|&nbsp;
        <span>Jumlah Tagihan:</span> {{ $tagihan->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Pelanggan</th>
                <th>Nama</th>
                <th>Periode</th>
                <th class="text-right">Total Tagihan</th>
                <th class="text-right">Sudah Dibayar</th>
                <th class="text-right">Sisa</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tagihan as $t)
            <tr>
                <td>{{ $t->pelanggan->nomor_pelanggan }}</td>
                <td>{{ $t->pelanggan->nama }}</td>
                <td>{{ $t->periode }}</td>
                <td class="text-right">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($t->totalDibayar(), 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($t->sisaTagihan(), 0, ',', '.') }}</td>
                <td>{{ $t->tanggal_jatuh_tempo->format('d/m/Y') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $t->status)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak dari Sistem HIPPAM — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
```

- [ ] **Step 1: Create PDF template directory and files**

Create `resources/views/laporan/pdf/` directory and all three template files with the code above.

- [ ] **Step 2: Commit**

```bash
git add resources/views/laporan/
git commit -m "feat(export): add PDF report templates for pemakaian, pendapatan, tunggakan"
```

---

### Task 10: Update Routes and Navigation

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Modify: `resources/views/dashboard.blade.php` (replace Breeze stub with role-based redirect)

**What to do:**
Add dashboard and laporan routes, update navigation with Laporan link, and replace the default Breeze dashboard stub with a role-aware redirect controller.

**Routes to add to `routes/web.php`:**

Add these imports at the top:
```php
use App\Http\Controllers\Operator\DashboardController as OperatorDashboard;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\Operator\LaporanController;
```

Add inside the `Route::middleware('auth')->group(...)` block, BEFORE the existing super-admin group:

```php
// Dashboard redirect (replaces Breeze stub)
Route::get('/dashboard', function () {
    if (auth()->user()->isSuperAdmin()) {
        return redirect()->route('super-admin.dashboard');
    }
    return redirect()->route('operator.dashboard');
})->name('dashboard');

// Super Admin Dashboard
Route::get('/super-admin/dashboard', [SuperAdminDashboard::class, '__invoke'])->name('super-admin.dashboard');
```

Add inside the `Route::middleware(['tenant.resolve', 'tenant.ensure'])->group(...)` block, after the existing pembayaran routes:

```php
// Operator Dashboard
Route::get('/dashboard/operator', [OperatorDashboard::class, '__invoke'])->name('operator.dashboard');

// Laporan
Route::get('/laporan/pemakaian', [LaporanController::class, 'pemakaian'])->name('laporan.pemakaian');
Route::get('/laporan/pendapatan', [LaporanController::class, 'pendapatan'])->name('laporan.pendapatan');
Route::get('/laporan/tunggakan', [LaporanController::class, 'tunggakan'])->name('laporan.tunggakan');
Route::get('/laporan/export/pemakaian', [LaporanController::class, 'exportPemakaian'])->name('laporan.export.pemakaian');
Route::get('/laporan/export/pendapatan', [LaporanController::class, 'exportPendapatan'])->name('laporan.export.pendapatan');
Route::get('/laporan/export/tunggakan', [LaporanController::class, 'exportTunggakan'])->name('laporan.export.tunggakan');
```

**Navigation update in `resources/views/layouts/navigation.blade.php`:**

Add after the Pembayaran `<x-nav-link>` (around line 29-31), inside the `@if(auth()->user()->tenant_id)` block:

Desktop nav (line 31, before `@endif`):
```html
<x-nav-link :href="route('laporan.pemakaian')" :active="request()->routeIs('laporan.*')">
    Laporan
</x-nav-link>
```

Mobile nav (line 103, before the second `@endif`):
```html
<x-responsive-nav-link :href="route('laporan.pemakaian')" :active="request()->routeIs('laporan.*')">
    Laporan
</x-responsive-nav-link>
```

**Replace `resources/views/dashboard.blade.php`:**

```html
{{-- This file is now a redirect handled by the route closure in web.php --}}
{{-- The /dashboard route redirects to either /dashboard/operator or /super-admin/dashboard --}}
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    Mengalihkan...
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 1: Update web.php with all new routes**

Add the imports and routes to `routes/web.php` as described above.

- [ ] **Step 2: Update navigation with Laporan link**

Add Laporan nav links in both desktop and mobile sections of `resources/views/layouts/navigation.blade.php`.

- [ ] **Step 3: Update dashboard.blade.php stub**

Update `resources/views/dashboard.blade.php` with the redirect note.

- [ ] **Step 4: Verify routes registered**

Run: `php artisan route:list --path=dashboard` and `php artisan route:list --path=laporan`
Expected: operator.dashboard, super-admin.dashboard, and 6 laporan routes listed.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php resources/views/layouts/navigation.blade.php resources/views/dashboard.blade.php
git commit -m "feat: add dashboard and laporan routes with navigation updates"
```

---

### Task 11: Write Dashboard Tests

**Files:**
- Create: `tests/Feature/DashboardTest.php`

**What to do:**
Write feature tests for both operator and super admin dashboards. Verify data scoping (operator only sees own tenant data, super admin sees all). Follow the test pattern from `tests/Feature/PembayaranStoreTest.php`.

```php
<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3000,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'super_admin',
        ]);
    }

    // ─── Operator Dashboard ─────────────────────────────────

    public function test_operator_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('operator.dashboard'));

        $response->assertOk();
        $response->assertViewIs('operator.dashboard');
    }

    public function test_operator_dashboard_shows_summary_cards(): void
    {
        $pelanggan = Pelanggan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('operator.dashboard'));

        $response->assertViewHas('totalPelangganAktif', 1);
    }

    public function test_operator_dashboard_redirects_from_default_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('dashboard'));

        $response->assertRedirect(route('operator.dashboard'));
    }

    public function test_operator_cannot_access_super_admin_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('super-admin.dashboard'));

        $response->assertForbidden();
    }

    // ─── Super Admin Dashboard ──────────────────────────────

    public function test_super_admin_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'));

        $response->assertOk();
        $response->assertViewIs('super-admin.dashboard');
    }

    public function test_super_admin_dashboard_redirects_from_default_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('dashboard'));

        $response->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_super_admin_sees_cross_tenant_data(): void
    {
        $tenant2 = Tenant::factory()->create();

        Pelanggan::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktif']);
        Pelanggan::factory()->create(['tenant_id' => $tenant2->id, 'status' => 'aktif']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'));

        $response->assertViewHas('totalPelanggan', 2);
    }

    public function test_super_admin_cannot_access_operator_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('operator.dashboard'));

        $response->assertForbidden();
    }
}
```

- [ ] **Step 1: Create test file**

Create `tests/Feature/DashboardTest.php` with the code above.

- [ ] **Step 2: Run dashboard tests**

Run: `php artisan test --filter DashboardTest`
Expected: All 7 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/DashboardTest.php
git commit -m "test(dashboard): add feature tests for operator and super admin dashboards"
```

---

### Task 12: Write Laporan Tests

**Files:**
- Create: `tests/Feature/LaporanTest.php`

**What to do:**
Write feature tests for all three report views and their exports (Excel + PDF). Verify tenant scoping in reports.

```php
<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Pembayaran;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private Pelanggan $pelanggan;
    private Meter $meter;
    private string $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3000,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $this->pelanggan = Pelanggan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'aktif',
        ]);

        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
        ]);

        $this->periode = now()->format('Y-m');
    }

    // ─── Pemakaian Report ───────────────────────────────────

    public function test_operator_can_view_pemakaian_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pemakaian'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.pemakaian');
    }

    public function test_pemakaian_report_filters_by_periode(): void
    {
        Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'meter_id' => $this->meter->id,
            'periode' => $this->periode,
            'status' => 'konfirmasi',
        ]);

        Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'meter_id' => $this->meter->id,
            'periode' => '2025-01',
            'status' => 'konfirmasi',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pemakaian', ['periode' => $this->periode]));

        $response->assertOk();
        $response->assertViewHas('pembacaan');
        $this->assertEquals(1, $response->viewData('pembacaan')->count());
    }

    // ─── Pendapatan Report ──────────────────────────────────

    public function test_operator_can_view_pendapatan_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pendapatan'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.pendapatan');
    }

    public function test_pendapatan_report_shows_totals(): void
    {
        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => $this->periode,
            'total_tagihan' => 50000,
            'status' => 'belum_bayar',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pendapatan', ['periode' => $this->periode]));

        $response->assertViewHas('totalTagihan');
        $this->assertEquals(50000, $response->viewData('totalTagihan'));
    }

    // ─── Tunggakan Report ───────────────────────────────────

    public function test_operator_can_view_tunggakan_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.tunggakan'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.tunggakan');
    }

    public function test_tunggakan_report_only_shows_unpaid(): void
    {
        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => $this->periode,
            'status' => 'belum_bayar',
            'total_tagihan' => 50000,
        ]);

        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => $this->periode,
            'status' => 'lunas',
            'total_tagihan' => 30000,
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.tunggakan'));

        $response->assertViewHas('tagihan');
        $this->assertEquals(1, $response->viewData('tagihan')->count());
    }

    // ─── Exports ────────────────────────────────────────────

    public function test_operator_can_export_pemakaian_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pemakaian', ['periode' => $this->periode, 'format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_operator_can_export_pemakaian_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pemakaian', ['periode' => $this->periode, 'format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_export_pendapatan_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pendapatan', ['periode' => $this->periode, 'format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_operator_can_export_tunggakan_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.tunggakan', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // ─── Access Control ─────────────────────────────────────

    public function test_super_admin_cannot_access_laporan_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get(route('laporan.pemakaian'));

        $response->assertForbidden();
    }
}
```

- [ ] **Step 1: Create test file**

Create `tests/Feature/LaporanTest.php` with the code above.

- [ ] **Step 2: Run laporan tests**

Run: `php artisan test --filter LaporanTest`
Expected: All 10 tests pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/LaporanTest.php
git commit -m "test(laporan): add feature tests for reports and exports"
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
- `feat(dashboard):` for dashboard features
- `feat(laporan):` for report features
- `feat(export):` for export classes
- `test(dashboard):` for dashboard tests
- `test(laporan):` for report tests

Suggested branch name: `feature/phase3-laporan-dashboard`

---

## Success Criteria

1. Operator dashboard shows 4 summary cards with live data
2. Operator dashboard shows 6-month revenue trend chart (Chart.js)
3. Operator dashboard shows top 5 tunggakan list
4. Super Admin dashboard shows consolidated totals across all tenants
5. Super Admin dashboard shows per-tenant breakdown table
6. Laporan Pemakaian filters by periode and shows volume per pelanggan
7. Laporan Pendapatan filters by date range (harian/mingguan/bulanan) and shows tagihan vs terbayar
8. Laporan Tunggakan lists all belum_bayar/cicilan tagihan sorted by amount
9. All three reports export to Excel (.xlsx) with correct data
10. All three reports export to PDF with tabular layout
11. Navigation shows Laporan link for operator users
12. All tenant data is properly scoped (no cross-tenant leakage for operator)
13. Super Admin sees all tenants' data (cross-tenant queries work)
14. All tests pass: `php artisan test`
