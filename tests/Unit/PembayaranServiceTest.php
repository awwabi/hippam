<?php

namespace Tests\Unit;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PembayaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembayaranServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tagihan $tagihan;
    private User $operator;
    private PembayaranService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3059,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $pelanggan = Pelanggan::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id, 'pelanggan_id' => $pelanggan->id]);
        $pembacaan = Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'meter_id' => $meter->id,
            'volume_m3' => 17,
            'status' => 'konfirmasi',
        ]);

        $this->tagihan = Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'volume_m3' => 17,
            'tarif_per_m3' => 3059,
            'total_tagihan' => 52003,
            'status' => 'belum_bayar',
        ]);

        $this->service = new PembayaranService();
    }

    public function test_full_payment_sets_status_lunas(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $pembayaran = $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 52003,
            'metode_bayar' => 'tunai',
        ]);

        $this->assertDatabaseHas('pembayaran', [
            'id' => $pembayaran->id,
            'jumlah_bayar' => 52003,
            'tagihan_id' => $this->tagihan->id,
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
    }

    public function test_partial_payment_sets_status_cicilan(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 25000,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('cicilan', $this->tagihan->status);
        $this->assertEquals(27003, (float) $this->tagihan->sisaTagihan());
    }

    public function test_second_payment_completes_to_lunas(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 25000,
            'metode_bayar' => 'tunai',
        ]);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 27003,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
        $this->assertEquals(0, (float) $this->tagihan->sisaTagihan());
    }

    public function test_overpayment_rejected(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 999999,
            'metode_bayar' => 'tunai',
        ]);
    }

    public function test_payment_on_cancelled_tagihan_rejected(): void
    {
        $this->actingAs($this->operator);
        app()->instance('current.tenant', $this->tenant);

        $this->tagihan->update(['status' => 'batal']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->processPayment($this->tagihan, [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah_bayar' => 10000,
            'metode_bayar' => 'tunai',
        ]);
    }
}
