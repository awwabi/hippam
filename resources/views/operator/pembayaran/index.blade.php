<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Pembayaran</h1>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        <form method="GET" action="{{ route('pembayaran.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3">
            <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <input type="date" name="tanggal_akhir" value="{{ request('tanggal_akhir') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <select name="metode_bayar" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Metode</option>
                <option value="tunai" {{ request('metode_bayar') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                <option value="transfer" {{ request('metode_bayar') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                <option value="ewallet" {{ request('metode_bayar') === 'ewallet' ? 'selected' : '' }}>E-Wallet</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Filter</button>
        </form>

        @if($pembayaran->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Belum ada data pembayaran.
            </div>
        @else
            <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pelanggan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Bayar</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($pembayaran as $p)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $p->tanggal_bayar->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $p->pelanggan->nomor_pelanggan }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $p->tagihan->periode }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3">
                                        @if($p->metode_bayar === 'tunai')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Tunai</span>
                                        @elseif($p->metode_bayar === 'transfer')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Transfer</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">E-Wallet</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <a href="{{ route('pembayaran.cetak', $p) }}" class="text-primary-600 hover:text-primary-700 font-medium">Cetak</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="md:hidden space-y-3">
                @foreach($pembayaran as $p)
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</h3>
                                <p class="text-xs text-gray-500 font-mono">{{ $p->pelanggan->nomor_pelanggan }}</p>
                            </div>
                            <span class="text-sm font-medium text-gray-900">Rp {{ number_format($p->jumlah_bayar, 0, ',', '.') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p>{{ $p->tanggal_bayar->format('d M Y') }} · {{ ucfirst($p->metode_bayar) }}</p>
                            <p>Periode: {{ $p->tagihan->periode }}</p>
                        </div>
                        <div class="mt-2 border-t border-gray-100 pt-2">
                            <a href="{{ route('pembayaran.cetak', $p) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Cetak</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            {{ $pembayaran->links() }}
        </div>
    </div>
</x-app-layout>
