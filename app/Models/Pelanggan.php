<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pelanggan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pelanggan';

    protected $fillable = [
        'tenant_id', 'nama', 'alamat', 'no_telepon',
        'nomor_pelanggan', 'status', 'tanggal_daftar', 'catatan',
    ];

    protected $casts = [
        'tanggal_daftar' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->nomor_pelanggan)) {
                $model->nomor_pelanggan = self::generateNomorPelanggan($model->tenant_id);
            }
        });
    }

    public static function generateNomorPelanggan(int $tenantId): string
    {
        $tenant = Tenant::find($tenantId);
        $prefix = $tenant ? $tenant->kode_unit : 'HPP';
        $last = self::where('tenant_id', $tenantId)->latest('id')->first();
        $seq = $last ? (int) substr($last->nomor_pelanggan, -4) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $seq);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meter(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
