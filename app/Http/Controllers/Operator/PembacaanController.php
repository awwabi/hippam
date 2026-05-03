<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use App\Models\Pembacaan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PembacaanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Pembacaan::with('pelanggan', 'meter');

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pembacaan = $query->latest()->paginate(20)->withQueryString();

        $periodes = Pembacaan::query()
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode');

        return view('operator.pembacaan.index', compact('pembacaan', 'periodes'));
    }

    public function create(Request $request): View
    {
        $periode = $request->get('periode', date('Y-m'));

        $pelangganList = Pelanggan::with('meter')
            ->where('status', 'aktif')
            ->whereHas('meter', function ($q) {
                $q->where('status', 'aktif');
            })
            ->get();

        $entries = $pelangganList->map(function ($p) use ($periode) {
            $meter = $p->meter->first();
            $previousReading = Pembacaan::getPreviousReading($p->id, $periode);

            return [
                'pelanggan_id' => $p->id,
                'nama' => $p->nama,
                'nomor_pelanggan' => $p->nomor_pelanggan,
                'meter_id' => $meter ? $meter->id : null,
                'nomor_meter' => $meter ? $meter->nomor_meter : '-',
                'angka_meter_sebelumnya' => $previousReading ?? 0,
            ];
        });

        return view('operator.pembacaan.create', compact('periode', 'entries'));
    }

    public function batchStore(Request $request)
    {
        $validated = $request->validate([
            'periode' => 'required|string|size:7|date_format:Y-m',
            'tanggal_baca' => 'required|date',
            'readings' => 'required|array',
            'readings.*.pelanggan_id' => 'required|exists:pelanggan,id',
            'readings.*.meter_id' => 'required|exists:meters,id',
            'readings.*.angka_meter_sebelumnya' => 'required|numeric|min:0',
            'readings.*.angka_meter_sekarang' => 'required|numeric|min:0',
        ]);

        $periode = $validated['periode'];
        $tanggalBaca = $validated['tanggal_baca'];
        $userId = auth()->id();
        $created = 0;
        $skipped = 0;

        foreach ($validated['readings'] as $reading) {
            if (empty($reading['angka_meter_sekarang']) || $reading['angka_meter_sekarang'] == $reading['angka_meter_sebelumnya']) {
                $skipped++;
                continue;
            }

            $exists = Pembacaan::where('pelanggan_id', $reading['pelanggan_id'])
                ->where('periode', $periode)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Pembacaan::create([
                'pelanggan_id' => $reading['pelanggan_id'],
                'meter_id' => $reading['meter_id'],
                'periode' => $periode,
                'angka_meter_sebelumnya' => $reading['angka_meter_sebelumnya'],
                'angka_meter_sekarang' => $reading['angka_meter_sekarang'],
                'tanggal_baca' => $tanggalBaca,
                'dibaca_oleh' => $userId,
                'status' => 'draft',
            ]);

            $created++;
        }

        $message = "Pembacaan berhasil disimpan: {$created} data.";
        if ($skipped > 0) {
            $message .= " Dilewati: {$skipped}.";
        }

        return redirect()->route('pembacaan.index', ['periode' => $periode])
            ->with('success', $message);
    }

    public function edit(Pembacaan $pembacaan): View
    {
        $this->ensureTenantOwnership($pembacaan);

        return view('operator.pembacaan.edit', compact('pembacaan'));
    }

    public function update(Request $request, Pembacaan $pembacaan)
    {
        $this->ensureTenantOwnership($pembacaan);

        $validated = $request->validate([
            'angka_meter_sebelumnya' => 'required|numeric|min:0',
            'angka_meter_sekarang' => 'required|numeric|min:0',
            'tanggal_baca' => 'required|date',
            'status' => 'required|in:draft,konfirmasi',
            'catatan' => 'nullable|string',
        ]);

        $pembacaan->update($validated);

        return redirect()->route('pembacaan.index')
            ->with('success', 'Pembacaan berhasil diperbarui.');
    }

    private function ensureTenantOwnership(Pembacaan $pembacaan): void
    {
        if ($pembacaan->tenant_id !== app('current.tenant')->id) {
            abort(404);
        }
    }
}
