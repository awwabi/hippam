<?php

use App\Http\Controllers\Operator\PelangganController;
use App\Http\Controllers\Operator\PembacaanController;
use App\Http\Controllers\Operator\TagihanController;
use App\Http\Controllers\Operator\PembayaranController;
use App\Http\Controllers\SuperAdmin\OperatorController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\Operator\DashboardController as OperatorDashboard;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\Operator\LaporanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    // Dashboard redirect (replaces Breeze stub)
    Route::get('/dashboard', function () {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }
        return redirect()->route('operator.dashboard');
    })->name('dashboard');

    // Super Admin Dashboard
    Route::get('/super-admin/dashboard', [SuperAdminDashboard::class, '__invoke'])->name('super-admin.dashboard');

    Route::middleware('can:viewAny,App\Models\Tenant')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

        Route::get('/operators', [OperatorController::class, 'index'])->name('operators.index');
        Route::get('/operators/create', [OperatorController::class, 'create'])->name('operators.create');
        Route::post('/operators', [OperatorController::class, 'store'])->name('operators.store');
        Route::delete('/operators/{user}', [OperatorController::class, 'destroy'])->name('operators.destroy');
    });

    Route::middleware(['tenant.resolve', 'tenant.ensure'])->group(function () {
        Route::get('/pelanggan', [PelangganController::class, 'index'])->name('pelanggan.index');
        Route::get('/pelanggan/create', [PelangganController::class, 'create'])->name('pelanggan.create');
        Route::post('/pelanggan', [PelangganController::class, 'store'])->name('pelanggan.store');
        Route::get('/pelanggan/{pelanggan}/edit', [PelangganController::class, 'edit'])->name('pelanggan.edit');
        Route::put('/pelanggan/{pelanggan}', [PelangganController::class, 'update'])->name('pelanggan.update');
        Route::get('/pelanggan/{pelanggan}/meter', [PelangganController::class, 'meterEdit'])->name('pelanggan.meter');
        Route::post('/pelanggan/{pelanggan}/meter', [PelangganController::class, 'meterStore'])->name('pelanggan.meter.store');

        Route::get('/pembacaan', [PembacaanController::class, 'index'])->name('pembacaan.index');
        Route::get('/pembacaan/create', [PembacaanController::class, 'create'])->name('pembacaan.create');
        Route::post('/pembacaan/batch', [PembacaanController::class, 'batchStore'])->name('pembacaan.batch');
        Route::get('/pembacaan/{pembacaan}/edit', [PembacaanController::class, 'edit'])->name('pembacaan.edit');
        Route::put('/pembacaan/{pembacaan}', [PembacaanController::class, 'update'])->name('pembacaan.update');

        Route::get('/tagihan', [TagihanController::class, 'index'])->name('tagihan.index');
        Route::post('/tagihan/generate', [TagihanController::class, 'generate'])->name('tagihan.generate');

        // Pembayaran and Tagihan detail routes for operators
        Route::get('/tagihan/{tagihan}', [TagihanController::class, 'show'])->name('tagihan.show');
        Route::get('/tagihan/{tagihan}/cetak-invoice', [PembayaranController::class, 'cetakInvoice'])->name('tagihan.cetak-invoice');

        Route::get('/pembayaran', [PembayaranController::class, 'index'])->name('pembayaran.index');
        Route::get('/tagihan/{tagihan}/bayar', [PembayaranController::class, 'create'])->name('pembayaran.create');
        Route::post('/tagihan/{tagihan}/bayar', [PembayaranController::class, 'store'])->name('pembayaran.store');
        Route::get('/pembayaran/{pembayaran}/cetak', [PembayaranController::class, 'cetak'])->name('pembayaran.cetak');
        Route::get('/pembayaran/{pembayaran}/cetak-ulang', [PembayaranController::class, 'cetakUlang'])->name('pembayaran.cetak-ulang');

        // Operator Dashboard
        Route::get('/dashboard/operator', [OperatorDashboard::class, '__invoke'])->name('operator.dashboard');

        // Laporan
        Route::get('/laporan/pemakaian', [LaporanController::class, 'pemakaian'])->name('laporan.pemakaian');
        Route::get('/laporan/pendapatan', [LaporanController::class, 'pendapatan'])->name('laporan.pendapatan');
        Route::get('/laporan/tunggakan', [LaporanController::class, 'tunggakan'])->name('laporan.tunggakan');
        Route::get('/laporan/export/pemakaian', [LaporanController::class, 'exportPemakaian'])->name('laporan.export.pemakaian');
        Route::get('/laporan/export/pendapatan', [LaporanController::class, 'exportPendapatan'])->name('laporan.export.pendapatan');
        Route::get('/laporan/export/tunggakan', [LaporanController::class, 'exportTunggakan'])->name('laporan.export.tunggakan');
    });
});
