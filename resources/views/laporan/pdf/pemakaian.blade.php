<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 15mm; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        h2 { font-size: 12px; text-align: center; color: #666; margin-bottom: 10px; }
        .summary { margin-bottom: 15px; }
        .summary span { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 10px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .footer { text-align: center; margin-top: 20px; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <h1>Laporan Pemakaian Air</h1>
    <h2>{{ $tenant->nama_unit }} — Periode: {{ $periode }}</h2>

    <div class="summary">
        <span>Total Volume:</span> {{ number_format($totalVolume, 1) }} m³ &nbsp;|&nbsp;
        <span>Jumlah Pelanggan:</span> {{ $pembacaan->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Pelanggan</th>
                <th>Nama</th>
                <th>No. Meter</th>
                <th class="text-right">Meter Sebelumnya</th>
                <th class="text-right">Meter Sekarang</th>
                <th class="text-right">Volume (m³)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pembacaan as $p)
            <tr>
                <td>{{ $p->pelanggan->nomor_pelanggan }}</td>
                <td>{{ $p->pelanggan->nama }}</td>
                <td>{{ $p->meter->nomor_meter ?? '-' }}</td>
                <td class="text-right">{{ number_format($p->angka_meter_sebelumnya, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->angka_meter_sekarang, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->volume_m3, 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak dari Sistem HIPPAM — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
