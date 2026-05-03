<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Tenant::class, 'tenant');
    }

    public function index(Request $request): View
    {
        $tenants = Tenant::withCount('pelanggan')->latest()->paginate(20);

        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('super-admin.tenants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_unit' => 'required|string|max:255',
            'kode_unit' => 'required|string|max:10|unique:tenants,kode_unit',
            'alamat' => 'nullable|string',
            'desa' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kabupaten' => 'nullable|string|max:255',
            'kontak_pengelola' => 'nullable|string|max:255',
            'no_telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tarif_per_m3' => 'required|numeric|min:0',
            'jatuh_tempo_tanggal' => 'required|integer|min:1|max:28',
            'printer_width' => 'required|in:58mm,80mm',
        ]);

        Tenant::create($validated);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil ditambahkan.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('super-admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'nama_unit' => 'required|string|max:255',
            'kode_unit' => 'required|string|max:10|unique:tenants,kode_unit,' . $tenant->id,
            'alamat' => 'nullable|string',
            'desa' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kabupaten' => 'nullable|string|max:255',
            'kontak_pengelola' => 'nullable|string|max:255',
            'no_telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tarif_per_m3' => 'required|numeric|min:0',
            'jatuh_tempo_tanggal' => 'required|integer|min:1|max:28',
            'printer_width' => 'required|in:58mm,80mm',
            'is_active' => 'boolean',
        ]);

        $tenant->update($validated);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil diperbarui.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil dihapus.');
    }
}
