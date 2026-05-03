<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_unit', 'kode_unit', 'alamat', 'desa', 'kecamatan', 'kabupaten',
        'kontak_pengelola', 'no_telepon', 'email', 'tarif_per_m3',
        'jatuh_tempo_tanggal', 'printer_width', 'is_active',
    ];

    protected $casts = [
        'tarif_per_m3' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class);
    }
}
