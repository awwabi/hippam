<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Services\TagihanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Tagihan::with('pelanggan');

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tagihan = $query->latest()->paginate(20)->withQueryString();

        $periodes = Tagihan::query()
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode');

        return view('operator.tagihan.index', compact('tagihan', 'periodes'));
    }

    public function generate(Request $request, TagihanService $service)
    {
        $validated = $request->validate([
            'periode' => 'required|string|size:7|date_format:Y-m',
        ]);

        $tenant = app('current.tenant');
        $results = $service->generateForPeriode($tenant, $validated['periode']);

        $message = "Tagihan berhasil dibuat: {$results['created']} tagihan.";
        if ($results['skipped'] > 0) {
            $message .= " Dilewati: {$results['skipped']}.";
        }

        return redirect()->route('tagihan.index', ['periode' => $validated['periode']])
            ->with('success', $message);
    }

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
}
