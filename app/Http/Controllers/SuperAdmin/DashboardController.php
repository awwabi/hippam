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
