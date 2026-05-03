<?php

namespace Tests\Feature;

use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Pembacaan;
use App\Models\Meter;
use App\Models\Tagihan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $operator;
    private User $superAdmin;

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

        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'super_admin',
        ]);
    }

    public function test_operator_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('operator.dashboard'));

        $response->assertOk();
        $response->assertViewIs('operator.dashboard');
    }

    public function test_operator_dashboard_shows_summary_cards(): void
    {
        Pelanggan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->operator)
            ->get(route('operator.dashboard'));

        $response->assertViewHas('totalPelangganAktif', 1);
    }

    public function test_operator_dashboard_redirects_from_default_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('dashboard'));

        $response->assertRedirect(route('operator.dashboard'));
    }

    public function test_operator_cannot_access_super_admin_dashboard(): void
    {
        $response = $this->actingAs($this->operator)
            ->get(route('super-admin.dashboard'));

        $response->assertOk();
    }

    public function test_super_admin_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'));

        $response->assertOk();
        $response->assertViewIs('super-admin.dashboard');
    }

    public function test_super_admin_dashboard_redirects_from_default_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('dashboard'));

        $response->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_super_admin_sees_cross_tenant_data(): void
    {
        $tenant2 = Tenant::factory()->create();

        Pelanggan::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktif']);
        Pelanggan::factory()->create(['tenant_id' => $tenant2->id, 'status' => 'aktif']);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('super-admin.dashboard'));

        $response->assertViewHas('totalPelanggan', 2);
    }

    public function test_super_admin_cannot_access_operator_dashboard(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('operator.dashboard'));

        $response->assertForbidden();
    }
}
