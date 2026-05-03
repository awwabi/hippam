<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'tagihan';

    protected $fillable = [
        'tenant_id', 'pelanggan_id', 'pembacaan_id', 'periode',
        'volume_m3', 'tarif_per_m3', 'biaya_air', 'total_tagihan',
        'status', 'tanggal_jatuh_tempo', 'catatan',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:1',
        'tarif_per_m3' => 'decimal:2',
        'biaya_air' => 'decimal:2',
        'total_tagihan' => 'decimal:2',
        'tanggal_jatuh_tempo' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function pembacaan(): BelongsTo
    {
        return $this->belongsTo(Pembacaan::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function totalDibayar(): string
    {
        return $this->pembayaran()->sum('jumlah_bayar');
    }

    public function sisaTagihan(): string
    {
        return max(0, $this->total_tagihan - $this->totalDibayar());
    }
}
