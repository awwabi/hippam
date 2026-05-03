<?php

namespace App\Exports;

use App\Models\Tagihan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PendapatanExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $periode;

    public function __construct(string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        return Tagihan::with('pelanggan')
            ->where('periode', $this->periode)
            ->orderBy('total_tagihan', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'Periode',
            'Volume (m³)',
            'Total Tagihan',
            'Sudah Dibayar',
            'Sisa',
            'Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->periode,
            $row->volume_m3,
            $row->total_tagihan,
            $row->totalDibayar(),
            $row->sisaTagihan(),
            $row->status,
        ];
    }
}
