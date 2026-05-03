<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Tagihan</h1>
            <form method="POST" action="{{ route('tagihan.generate') }}" class="inline-flex gap-2">
                @csrf
                <input type="month" name="periode" value="{{ old('periode', date('Y-m')) }}" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Generate Tagihan
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        <form method="GET" action="{{ route('tagihan.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3">
            <select name="periode" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Periode</option>
                @foreach($periodes as $p)
                    <option value="{{ $p }}" {{ request('periode') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Status</option>
                <option value="belum_bayar" {{ request('status') === 'belum_bayar' ? 'selected' : '' }}>Belum Bayar</option>
                <option value="lunas" {{ request('status') === 'lunas' ? 'selected' : '' }}>Lunas</option>
                <option value="cicilan" {{ request('status') === 'cicilan' ? 'selected' : '' }}>Cicilan</option>
                <option value="batal" {{ request('status') === 'batal' ? 'selected' : '' }}>Batal</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Filter</button>
        </form>

        @if($tagihan->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Belum ada data tagihan.
            </div>
        @else
            <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pelanggan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume (m³)</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tarif/m³</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Biaya Air</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jatuh Tempo</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($tagihan as $t)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $t->periode }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $t->pelanggan->nomor_pelanggan }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($t->volume_m3, 1) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($t->tarif_per_m3, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ 'Rp ' . number_format($t->biaya_air, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ 'Rp ' . number_format($t->total_tagihan, 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $t->tanggal_jatuh_tempo->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        @if($t->status === 'belum_bayar')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                                        @elseif($t->status === 'lunas')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                                        @elseif($t->status === 'cicilan')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Batal</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm space-x-2">
                                        @if($t->status === 'belum_bayar' || $t->status === 'cicilan')
                                            <a href="{{ route('pembayaran.create', $t) }}" class="text-primary-600 hover:text-primary-700 font-medium">Bayar</a>
                                        @endif
                                        @if($t->status === 'belum_bayar')
                                            <a href="{{ route('tagihan.cetak-invoice', $t) }}" class="text-gray-600 hover:text-gray-700 font-medium">Invoice</a>
                                        @endif
                                        <a href="{{ route('tagihan.show', $t) }}" class="text-gray-600 hover:text-gray-700 font-medium">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="md:hidden space-y-3">
                @foreach($tagihan as $t)
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</h3>
                                <p class="text-xs text-gray-500 font-mono">{{ $t->pelanggan->nomor_pelanggan }}</p>
                            </div>
                            @if($t->status === 'belum_bayar')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                            @elseif($t->status === 'lunas')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                            @elseif($t->status === 'cicilan')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Batal</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 border-t border-gray-100 pt-3 mt-2">
                            @if($t->status === 'belum_bayar' || $t->status === 'cicilan')
                                <a href="{{ route('pembayaran.create', $t) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Bayar</a>
                            @endif
                            @if($t->status === 'belum_bayar')
                                <a href="{{ route('tagihan.cetak-invoice', $t) }}" class="text-gray-600 hover:text-gray-700 text-xs font-medium">Invoice</a>
                            @endif
                            <a href="{{ route('tagihan.show', $t) }}" class="text-gray-600 hover:text-gray-700 text-xs font-medium">Detail</a>
                        </div>
                        <div class="text-xs text-gray-500 space-y-1">
                            <p>Periode: {{ $t->periode }}</p>
                            <p>Volume: {{ number_format($t->volume_m3, 1) }} m³ &times; Rp {{ number_format($t->tarif_per_m3, 0, ',', '.') }}</p>
                            <p class="font-medium text-gray-900 text-sm mt-1">Total: Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</p>
                            <p>Jatuh Tempo: {{ $t->tanggal_jatuh_tempo->format('d M Y') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            {{ $tagihan->links() }}
        </div>
    </div>
</x-app-layout>
