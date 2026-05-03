<?php

namespace App\Exports;

use App\Models\Tagihan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TunggakanExport implements FromCollection, WithHeadings, WithMapping
{
    protected ?string $periode;

    public function __construct(?string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        $query = Tagihan::with('pelanggan')
            ->whereIn('status', ['belum_bayar', 'cicilan']);

        if ($this->periode) {
            $query->where('periode', $this->periode);
        }

        return $query->orderBy('tanggal_jatuh_tempo', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'Periode',
            'Total Tagihan',
            'Sudah Dibayar',
            'Sisa',
            'Jatuh Tempo',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->periode,
            $row->total_tagihan,
            $row->totalDibayar(),
            $row->sisaTagihan(),
            $row->tanggal_jatuh_tempo->format('d/m/Y'),
            $row->status,
        ];
    }
}
