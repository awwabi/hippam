<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'nama_unit' => fake()->company() . ' HIPPAM',
            'kode_unit' => strtoupper(fake()->unique()->lexify('???')),
            'alamat' => fake()->address(),
            'desa' => fake()->city(),
            'kecamatan' => fake()->city(),
            'kabupaten' => fake()->city(),
            'kontak_pengelola' => fake()->name(),
            'no_telepon' => fake()->phoneNumber(),
            'tarif_per_m3' => fake()->randomElement([2000, 3000, 3500, 4000, 5000]),
            'is_active' => true,
        ];
    }
}
