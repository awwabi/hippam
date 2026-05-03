<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nama' => fake()->name(),
            'alamat' => fake()->address(),
            'no_telepon' => fake()->phoneNumber(),
            'status' => 'aktif',
        ];
    }
}
