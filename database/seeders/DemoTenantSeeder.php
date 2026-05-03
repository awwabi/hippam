<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Admin',
            'email' => 'ululfahmi@gmail.com',
            'password' => bcrypt('WotanBersinar'),
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);

        $tenants = [
            [
                'nama_unit' => 'HIPPAM Wotan',
                'kode_unit' => 'WOT',
                'alamat' => 'Wotan',
                'tarif_per_m3' => 3500,
            ],
            [
                'nama_unit' => 'HIPPAM Bandung',
                'kode_unit' => 'BDG',
                'alamat' => 'Bandung',
                'tarif_per_m3' => 4000,
            ],
        ];

        foreach ($tenants as $data) {
            $tenant = Tenant::create($data);

            $slug = strtolower($data['kode_unit']);

            User::create([
                'name' => "Operator {$data['nama_unit']}",
                'email' => "{$slug}@gmail.com",
                'password' => bcrypt("{$slug}01234"),
                'role' => 'operator',
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
