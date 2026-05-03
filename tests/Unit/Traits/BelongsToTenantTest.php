<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_filters_by_current_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Pelanggan::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Pelanggan::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

        app()->instance('current.tenant', $tenant1);

        $results = Pelanggan::withoutEagerLoads()->get();

        $this->assertCount(3, $results);
        $this->assertTrue($results->every(fn ($p) => $p->tenant_id === $tenant1->id));
    }

    public function test_creating_model_auto_sets_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current.tenant', $tenant);

        $pelanggan = Pelanggan::factory()->make(['tenant_id' => null]);
        $pelanggan->save();

        $this->assertEquals($tenant->id, $pelanggan->fresh()->tenant_id);
    }
}
