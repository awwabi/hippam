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
