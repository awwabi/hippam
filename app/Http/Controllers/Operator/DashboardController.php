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
