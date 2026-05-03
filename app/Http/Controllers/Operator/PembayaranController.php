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
