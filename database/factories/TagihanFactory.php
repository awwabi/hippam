<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\Pembacaan;
use App\Models\Tagihan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagihanFactory extends Factory
{
    protected $model = Tagihan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'pelanggan_id' => Pelanggan::factory(),
            'pembacaan_id' => Pembacaan::factory(),
            'periode' => fake()->date('Y-m'),
            'volume_m3' => fake()->randomFloat(1, 5, 50),
            'tarif_per_m3' => fake()->randomFloat(2, 1000, 5000),
            'biaya_air' => 0,
            'total_tagihan' => fake()->randomFloat(2, 10000, 200000),
            'status' => 'belum_bayar',
            'tanggal_jatuh_tempo' => fake()->dateTimeBetween('+1 week', '+1 month')->format('Y-m-d'),
            'catatan' => null,
        ];
    }
}
