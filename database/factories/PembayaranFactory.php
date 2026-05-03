<?php

namespace Database\Factories;

use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'tanggal_bayar' => $this->faker->date(),
            'jumlah_bayar' => $this->faker->randomFloat(2, 10000, 200000),
            'metode_bayar' => $this->faker->randomElement(['tunai', 'transfer', 'ewallet']),
            'no_referensi' => null,
            'catatan' => null,
        ];
    }
}
