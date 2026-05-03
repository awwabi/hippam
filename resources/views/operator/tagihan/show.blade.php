<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Detail Tagihan</h1>
            <a href="{{ route('tagihan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Daftar</a>
        </div>

        {{-- Tagihan Info --}} 
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Pelanggan</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->pelanggan->nama }}</p>
                    <p class="text-xs text-gray-500 font-mono">{{ $tagihan->pelanggan->nomor_pelanggan }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Periode</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->periode }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Volume</p>
                    <p class="font-medium text-gray-900">{{ number_format($tagihan->volume_m3, 1) }} m³</p>
                </div>
                <div>
                    <p class="text-gray-500">Tarif per m³</p>
                    <p class="font-medium text-gray-900">Rp {{ number_format($tagihan->tarif_per_m3, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Total Tagihan</p>
                    <p class="text-lg font-bold text-gray-900">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Status</p>
                    @if($tagihan->status === 'belum_bayar')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                    @elseif($tagihan->status === 'lunas')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                    @elseif($tagihan->status === 'cicilan')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Batal</span>
                    @endif
                </div>
                <div>
                    <p class="text-gray-500">Jatuh Tempo</p>
                    <p class="font-medium text-gray-900">{{ $tagihan->tanggal_jatuh_tempo->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Sisa Tagihan</p>
                    <p class="text-lg font-bold {{ $tagihan->sisaTagihan() > 0 ? 'text-red-600' : 'text-green-600' }}">Rp {{ number_format($tagihan->sisaTagihan(), 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        @if($tagihan->status !== 'lunas' && $tagihan->status !== 'batal')
            <div class="flex gap-3 mt-6 pt-4 border-t border-gray-100">
                <a href="{{ route('pembayaran.create', $tagihan) }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                    Bayar
                </a>
                <a href="{{ route('tagihan.cetak-invoice', $tagihan) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                    Cetak Invoice
                </a>
            </div>
        @endif
        
        {{-- Payment History for this Tagihan --}} 
        @if($tagihan->pembayaran->isNotEmpty())
            <div class="bg-white rounded-lg shadow overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Riwayat Pembayaran</h2>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Jumlah</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tagihan->pembayaran as $p)
                            <tr>
                                <td class="px-6 py-3 text-sm">{{ $p->tanggal_bayar->format('d M Y') }}</td>
                                <td class="px-6 py-3 text-sm text-right">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                <td class="px-6 py-3 text-sm">{{ ucfirst($p->metode_bayar) }}</td>
                                <td class="px-6 py-3 text-sm text-right">
                                    <a href="{{ route('pembayaran.cetak', $p) }}" class="text-primary-600 hover:text-primary-700">Cetak</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
