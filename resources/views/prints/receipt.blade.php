<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
        }
        .receipt {
            padding: 2mm;
        }
        .center {
            text-align: center;
        }
        .separator {
            border-top: 1px solid #000;
            margin: 4px 0;
        }
        .section-title {
            font-weight: bold;
            margin: 4px 0 2px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin: 1px 0;
        }
        .row .label {
            text-align: left;
        }
        .row .value {
            text-align: right;
        }
        .status-box {
            border: 1px solid #000;
            padding: 4px 0;
            text-align: center;
            font-weight: bold;
            margin: 6px auto;
            width: 80%;
        }
        .footer {
            text-align: center;
            margin-top: 6px;
        }
        .footer p {
            margin: 1px 0;
        }
    </style>
</head>
<body>
    <div class="receipt">
        {{-- HEADER --}}
        <div class="separator"></div>
        <p class="center" style="font-weight: bold;">STRUK PEMBAYARAN AIR</p>
        <p class="center">HIPPAM - {{ $tenant->nama_unit }}</p>
        <p class="center">{{ $printedAt }}</p>
        <div class="separator"></div>

        {{-- DATA PELANGGAN --}}
        <p class="section-title">DATA PELANGGAN</p>
        <div class="row">
            <span class="label">Nama:</span>
            <span class="value">{{ $pelanggan->nama }}</span>
        </div>
        <div class="row">
            <span class="label">No. Meter:</span>
            <span class="value">{{ $meter->nomor_meter ?? '-' }}</span>
        </div>
        <div class="row">
            <span class="label">Periode:</span>
            <span class="value">{{ $periodeLabel }}</span>
        </div>
        <div class="separator"></div>

        {{-- PEMBACAAN METER --}}
        <p class="section-title">PEMBACAAN METER</p>
        <div class="row">
            <span class="label">Meter Sebelumnya:</span>
            <span class="value">{{ number_format($pembacaan->angka_meter_sebelumnya, 0, ',', '.') }} m³</span>
        </div>
        <div class="row">
            <span class="label">Meter Sekarang:</span>
            <span class="value">{{ number_format($pembacaan->angka_meter_sekarang, 0, ',', '.') }} m³</span>
        </div>
        <div class="row">
            <span class="label">Konsumsi:</span>
            <span class="value">{{ number_format($tagihan->volume_m3, 0, ',', '.') }} m³</span>
        </div>
        <div class="separator"></div>

        {{-- PERHITUNGAN TARIF --}} 
        <p class="section-title">PERHITUNGAN TARIF</p>
        <div class="row">
            <span class="label">Tarif:</span>
            <span class="value">Rp {{ number_format($tagihan->tarif_per_m3, 0, ',', '.') }}/m³</span>
        </div>
        <div class="row">
            <span class="label">Total Tagihan:</span>
            <span class="value">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</span>
        </div>
        <div class="separator"></div>

        {{-- PEMBAYARAN --}} 
        <p class="section-title">PEMBAYARAN</p>
        @if($pembayaran && !$isInvoice)
            <div class="row">
                <span class="label">Jumlah Dibayar:</span>
                <span class="value">Rp {{ number_format($pembayaran->jumlah_bayar, 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span class="label">Tanggal Bayar:</span>
                <span class="value">{{ $pembayaran->tanggal_bayar->format('j/n/Y') }}</span>
            </div>
        @else
            <div class="row">
                <span class="label">Jumlah Dibayar:</span>
                <span class="value">Rp 0</span>
            </div>
            <div class="row">
                <span class="label">Tanggal Bayar:</span>
                <span class="value">-</span>
            </div>
        @endif
        <div class="separator"></div>

        {{-- STATUS --}} 
        <div class="status-box">Status: {{ $statusLabel }}</div>

        {{-- FOOTER --}}
        <div class="footer">
            <p>Terima kasih atas pembayaran Anda</p>
            <p>Semoga lancar selalu</p>
            <p>Dicetak oleh Sistem HIPPAM</p>
        </div>
        <div class="separator"></div>
    </div>
</body>
</html>
