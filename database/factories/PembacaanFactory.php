<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\Pembacaan;
use App\Models\Pelanggan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PembacaanFactory extends Factory
{
    protected $model = Pembacaan::class;

    public function definition(): array
    {
        $sebelumnya = fake()->randomFloat(1, 0, 500);
        $sekarang = $sebelumnya + fake()->randomFloat(1, 0, 50);

        return [
            'tenant_id' => Tenant::factory(),
            'pelanggan_id' => Pelanggan::factory(),
            'meter_id' => Meter::factory(),
            'periode' => fake()->date('Y-m'),
            'angka_meter_sebelumnya' => $sebelumnya,
            'angka_meter_sekarang' => $sekarang,
            'tanggal_baca' => fake()->date(),
            'dibaca_oleh' => User::factory(),
            'status' => 'draft',
        ];
    }
}
