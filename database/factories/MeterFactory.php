<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\Pelanggan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeterFactory extends Factory
{
    protected $model = Meter::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'pelanggan_id' => Pelanggan::factory(),
            'nomor_meter' => fake()->unique()->numerify('MT-########'),
            'merek' => fake()->randomElement(['Itron', 'Sensus', 'Zenner', 'Actaris']),
            'status' => 'aktif',
        ];
    }
}
