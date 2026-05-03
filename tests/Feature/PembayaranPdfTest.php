<?php

namespace Tests\Feature;

use App\Models\Pembayaran;
use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;

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
    }

    public function test_operator_can_download_receipt_pdf(): void
    {
        $pembayaran = $this->createPaidPembayaran();

        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.cetak', $pembayaran));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_reprint_receipt(): void
    {
        $pembayaran = $this->createPaidPembayaran();

        $response = $this->actingAs($this->operator)
            ->get(route('pembayaran.cetak-ulang', $pembayaran));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_download_invoice_pdf(): void
    {
        $tagihan = $this->createUnpaidTagihan();

        $response = $this->actingAs($this->operator)
            ->get(route('tagihan.cetak-invoice', $tagihan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    private function createPaidPembayaran(): Pembayaran
    {
        $tagihan = $this->createUnpaidTagihan();

        return Pembayaran::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tagihan_id' => $tagihan->id,
            'pelanggan_id' => $tagihan->pelanggan_id,
            'jumlah_bayar' => $tagihan->total_tagihan,
            'petugas_kasir' => $this->operator->id,
        ]);
    }

    private function createUnpaidTagihan(): Tagihan
    {
        $pelanggan = Pelanggan::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id, 'pelanggan_id' => $pelanggan->id]);
        $pembacaan = Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'meter_id' => $meter->id,
            'status' => 'konfirmasi',
        ]);

        return Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $pelanggan->id,
            'pembacaan_id' => $pembacaan->id,
            'total_tagihan' => 52000,
            'status' => 'belum_bayar',
        ]);
    }
}
