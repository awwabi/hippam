<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pembacaan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pembacaan';

    protected $fillable = [
        'tenant_id', 'pelanggan_id', 'meter_id', 'periode',
        'angka_meter_sebelumnya', 'angka_meter_sekarang',
        'tanggal_baca', 'dibaca_oleh', 'status', 'catatan',
    ];

    protected $casts = [
        'angka_meter_sebelumnya' => 'decimal:1',
        'angka_meter_sekarang' => 'decimal:1',
        'volume_m3' => 'decimal:1',
        'tanggal_baca' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibaca_oleh');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(Tagihan::class);
    }

    public static function getPreviousReading(int $pelangganId, string $periode): ?string
    {
        $previousPeriode = date('Y-m', strtotime($periode . ' -1 month'));

        return self::where('pelanggan_id', $pelangganId)
            ->where('periode', $previousPeriode)
            ->value('angka_meter_sekarang');
    }
}
