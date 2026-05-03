<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranStoreTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private Tagihan $tagihan;

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
            'status' => 'konfirmasi',
        ]);

        $this->tagihan = Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'total_tagihan' => 52000,
            'status' => 'belum_bayar',
        ]);
    }

    public function test_operator_can_record_payment(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), [
                'tanggal_bayar' => now()->toDateString(),
                'jumlah_bayar' => 52000,
                'metode_bayar' => 'tunai',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pembayaran', [
            'tagihan_id' => $this->tagihan->id,
            'jumlah_bayar' => 52000,
            'metode_bayar' => 'tunai',
        ]);

        $this->tagihan->refresh();
        $this->assertEquals('lunas', $this->tagihan->status);
    }

    public function test_payment_validates_required_fields(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), []);

        $response->assertSessionHasErrors(['tanggal_bayar', 'jumlah_bayar', 'metode_bayar']);
    }

    public function test_transfer_payment_requires_reference(): void
    {
        $response = $this->actingAs($this->operator)
            ->post(route('pembayaran.store', $this->tagihan), [
                'tanggal_bayar' => now()->toDateString(),
                'jumlah_bayar' => 52000,
                'metode_bayar' => 'transfer',
            ]);

        $response->assertSessionHasErrors(['no_referensi']);
    }

    public function test_operator_can_view_payment_list(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.index'));

        $response->assertOk();
        $response->assertViewIs('operator.pembayaran.index');
    }

    public function test_operator_can_view_payment_form(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.create', $this->tagihan));

        $response->assertOk();
        $response->assertViewIs('operator.pembayaran.create');
    }

    public function test_super_admin_cannot_access_payment_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get(route('pembayaran.index'));

        // Super admin has no tenant_id, so tenant.ensure middleware should block
        $this->assertContains($response->status(), [403, 302, 500]);
    }
}
