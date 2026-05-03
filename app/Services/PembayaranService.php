<?php

namespace App\Services;

use App\Models\Pembayaran;
use App\Models\Tagihan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PembayaranService
{
    public function processPayment(Tagihan $tagihan, array $data): Pembayaran
    {
        return DB::transaction(function () use ($tagihan, $data) {
            $tagihan = Tagihan::lockForUpdate()->findOrFail($tagihan->id);

            $sisaTagihan = max(0, (float) $tagihan->total_tagihan - (float) $tagihan->totalDibayar());

            if ((float) $data['jumlah_bayar'] > $sisaTagihan) {
                throw new \InvalidArgumentException(
                    "Jumlah bayar (Rp " . number_format($data['jumlah_bayar'], 0, ',', '.') .
                    ") melebihi sisa tagihan (Rp " . number_format($sisaTagihan, 0, ',', '.') . ")."
                );
            }

            if ($tagihan->status === 'batal') {
                throw new \InvalidArgumentException('Tagihan sudah dibatalkan.');
            }

            $pembayaran = Pembayaran::create([
                'tenant_id' => $tagihan->tenant_id,
                'tagihan_id' => $tagihan->id,
                'pelanggan_id' => $tagihan->pelanggan_id,
                'tanggal_bayar' => $data['tanggal_bayar'],
                'jumlah_bayar' => $data['jumlah_bayar'],
                'metode_bayar' => $data['metode_bayar'],
                'no_referensi' => $data['no_referensi'] ?? null,
                'petugas_kasir' => Auth::id(),
                'catatan' => $data['catatan'] ?? null,
            ]);

            $totalDibayar = (float) $tagihan->totalDibayar() + (float) $data['jumlah_bayar'];
            $totalTagihan = (float) $tagihan->total_tagihan;

            if ($totalDibayar >= $totalTagihan) {
                $tagihan->update(['status' => 'lunas']);
            } else {
                $tagihan->update(['status' => 'cicilan']);
            }

            return $pembayaran;
        });
    }
}
