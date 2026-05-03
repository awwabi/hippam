<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Pembayaran;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private Pelanggan $pelanggan;
    private Meter $meter;
    private string $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tarif_per_m3' => 3000,
            'printer_width' => '58mm',
        ]);

        $this->operator = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'operator',
        ]);

        $this->pelanggan = Pelanggan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'aktif',
        ]);

        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
        ]);

        $this->periode = now()->format('Y-m');
    }

    public function test_operator_can_view_pemakaian_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pemakaian'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.pemakaian');
    }

    public function test_pemakaian_report_filters_by_periode(): void
    {
        Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'meter_id' => $this->meter->id,
            'periode' => $this->periode,
            'status' => 'konfirmasi',
        ]);

        Pembacaan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'meter_id' => $this->meter->id,
            'periode' => '2025-01',
            'status' => 'konfirmasi',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pemakaian', ['periode' => $this->periode]));

        $response->assertOk();
        $response->assertViewHas('pembacaan');
        $this->assertEquals(1, $response->viewData('pembacaan')->count());
    }

    public function test_operator_can_view_pendapatan_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pendapatan'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.pendapatan');
    }

    public function test_pendapatan_report_shows_totals(): void
    {
        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => $this->periode,
            'total_tagihan' => 50000,
            'status' => 'belum_bayar',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.pendapatan', ['periode' => $this->periode]));

        $response->assertViewHas('totalTagihan');
        $this->assertEquals(50000, $response->viewData('totalTagihan'));
    }

    public function test_operator_can_view_tunggakan_report(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.tunggakan'));

        $response->assertOk();
        $response->assertViewIs('operator.laporan.tunggakan');
    }

    public function test_tunggakan_report_only_shows_unpaid(): void
    {
        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => $this->periode,
            'status' => 'belum_bayar',
            'total_tagihan' => 50000,
        ]);

        Tagihan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pelanggan_id' => $this->pelanggan->id,
            'periode' => '2025-01',
            'status' => 'lunas',
            'total_tagihan' => 30000,
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('laporan.tunggakan'));

        $response->assertViewHas('tagihan');
        $this->assertEquals(1, $response->viewData('tagihan')->count());
    }

    public function test_operator_can_export_pemakaian_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pemakaian', ['periode' => $this->periode, 'format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_operator_can_export_pemakaian_pdf(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pemakaian', ['periode' => $this->periode, 'format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_can_export_pendapatan_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.pendapatan', ['periode' => $this->periode, 'format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_operator_can_export_tunggakan_excel(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('laporan.export.tunggakan', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_super_admin_cannot_access_laporan_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)
            ->get(route('laporan.pemakaian'));

        $response->assertForbidden();
    }
}
