<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembayaran extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pembayaran';

    protected $fillable = [
        'tenant_id', 'tagihan_id', 'pelanggan_id',
        'tanggal_bayar', 'jumlah_bayar', 'metode_bayar',
        'no_referensi', 'petugas_kasir', 'catatan',
    ];

    protected $casts = [
        'tanggal_bayar' => 'date',
        'jumlah_bayar' => 'decimal:2',
    ];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function petugasKasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_kasir');
    }
}
