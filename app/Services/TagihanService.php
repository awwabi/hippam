<?php

namespace App\Services;

use App\Models\Pembacaan;
use App\Models\Tagihan;
use App\Models\Tenant;
use Carbon\Carbon;

class TagihanService
{
    public function generateForPeriode(Tenant $tenant, string $periode): array
    {
        $pembacaanList = Pembacaan::where('tenant_id', $tenant->id)
            ->where('periode', $periode)
            ->where('status', 'konfirmasi')
            ->whereDoesntHave('tagihan')
            ->with('pelanggan', 'meter')
            ->get();

        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        $bulanBerikutnya = Carbon::parse($periode . '-01')->addMonth();
        $tanggalJatuhTempo = $bulanBerikutnya->setDay($tenant->jatuh_tempo_tanggal)->toDateString();

        foreach ($pembacaanList as $pembacaan) {
            if ($pembacaan->volume_m3 < 0) {
                $results['skipped']++;
                $results['errors'][] = "Pelanggan {$pembacaan->pelanggan->nama}: volume negatif";
                continue;
            }

            Tagihan::create([
                'tenant_id' => $tenant->id,
                'pelanggan_id' => $pembacaan->pelanggan_id,
                'pembacaan_id' => $pembacaan->id,
                'periode' => $periode,
                'volume_m3' => $pembacaan->volume_m3,
                'tarif_per_m3' => $tenant->tarif_per_m3,
                'biaya_air' => $pembacaan->volume_m3 * $tenant->tarif_per_m3,
                'total_tagihan' => $pembacaan->volume_m3 * $tenant->tarif_per_m3,
                'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
            ]);

            $results['created']++;
        }

        return $results;
    }
}
