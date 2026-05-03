<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $operators = User::where('role', 'operator')
            ->with('tenant')
            ->latest()
            ->paginate(20);

        return view('super-admin.operators.index', compact('operators'));
    }

    public function create(): View
    {
        $tenants = Tenant::where('is_active', true)->orderBy('nama_unit')->get();

        return view('super-admin.operators.create', compact('tenants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['role'] = 'operator';

        User::create($validated);

        return redirect()->route('super-admin.operators.index')
            ->with('success', 'Operator berhasil ditambahkan.');
    }

    public function destroy(User $user)
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Tidak bisa menghapus Super Admin.');
        }

        $user->delete();

        return redirect()->route('super-admin.operators.index')
            ->with('success', 'Operator berhasil dihapus.');
    }
}
