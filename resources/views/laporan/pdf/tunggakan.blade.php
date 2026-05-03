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
    <h1>Laporan Tunggakan</h1>
    <h2>{{ $tenant->nama_unit }}@if($periode) — Periode: {{ $periode }}@endif</h2>

    <div class="summary">
        <span>Total Tunggakan:</span> Rp {{ number_format($totalTunggakan, 0, ',', '.') }} &nbsp;|&nbsp;
        <span>Jumlah Tagihan:</span> {{ $tagihan->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Pelanggan</th>
                <th>Nama</th>
                <th>Periode</th>
                <th class="text-right">Total Tagihan</th>
                <th class="text-right">Sudah Dibayar</th>
                <th class="text-right">Sisa</th>
                <th>Jatuh Tempo</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tagihan as $t)
            <tr>
                <td>{{ $t->pelanggan->nomor_pelanggan }}</td>
                <td>{{ $t->pelanggan->nama }}</td>
                <td>{{ $t->periode }}</td>
                <td class="text-right">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($t->totalDibayar(), 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($t->sisaTagihan(), 0, ',', '.') }}</td>
                <td>{{ $t->tanggal_jatuh_tempo->format('d/m/Y') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $t->status)) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Dicetak dari Sistem HIPPAM — {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
