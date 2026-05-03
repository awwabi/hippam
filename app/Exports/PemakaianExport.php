<?php

namespace App\Exports;

use App\Models\Pembacaan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PemakaianExport implements FromCollection, WithHeadings, WithMapping
{
    protected string $periode;

    public function __construct(string $periode)
    {
        $this->periode = $periode;
    }

    public function collection()
    {
        return Pembacaan::with(['pelanggan', 'meter'])
            ->where('periode', $this->periode)
            ->where('status', 'konfirmasi')
            ->orderBy('volume_m3', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No. Pelanggan',
            'Nama',
            'No. Meter',
            'Meter Sebelumnya',
            'Meter Sekarang',
            'Volume (m³)',
        ];
    }

    public function map($row): array
    {
        return [
            $row->pelanggan->nomor_pelanggan,
            $row->pelanggan->nama,
            $row->meter->nomor_meter ?? '-',
            $row->angka_meter_sebelumnya,
            $row->angka_meter_sekarang,
            $row->volume_m3,
        ];
    }
}
