<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelangganController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Pelanggan::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nomor_pelanggan', 'like', "%{$search}%")
                  ->orWhere('no_telepon', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pelanggan = $query->latest()->paginate(20)->withQueryString();

        return view('operator.pelanggan.index', compact('pelanggan'));
    }

    public function create(): View
    {
        return view('operator.pelanggan.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:20',
            'catatan' => 'nullable|string',
        ]);

        Pelanggan::create($validated);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Pelanggan $pelanggan): View
    {
        $this->ensureTenantOwnership($pelanggan);

        return view('operator.pelanggan.edit', compact('pelanggan'));
    }

    public function update(Request $request, Pelanggan $pelanggan)
    {
        $this->ensureTenantOwnership($pelanggan);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:20',
            'status' => 'required|in:aktif,nonaktif',
            'catatan' => 'nullable|string',
        ]);

        $pelanggan->update($validated);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function meterEdit(Pelanggan $pelanggan): View
    {
        $this->ensureTenantOwnership($pelanggan);
        $meter = $pelanggan->meter()->first();

        return view('operator.pelanggan.meter', compact('pelanggan', 'meter'));
    }

    public function meterStore(Request $request, Pelanggan $pelanggan)
    {
        $this->ensureTenantOwnership($pelanggan);

        $validated = $request->validate([
            'nomor_meter' => 'required|string|max:50|unique:meters,nomor_meter',
            'merek' => 'nullable|string|max:255',
            'tanggal_pemasangan' => 'nullable|date',
            'status' => 'required|in:aktif,rusak,nonaktif',
        ]);

        $pelanggan->meter()->delete();
        $pelanggan->meter()->create($validated);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Meter berhasil disimpan.');
    }

    private function ensureTenantOwnership(Pelanggan $pelanggan): void
    {
        if ($pelanggan->tenant_id !== app('current.tenant')->id) {
            abort(404);
        }
    }
}
