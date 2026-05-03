<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Pemakaian Air</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('laporan.pemakaian') }}" class="mb-6 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <input type="month" name="periode" value="{{ $periode }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Tampilkan</button>
                <a href="{{ route('laporan.export.pemakaian', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">Export Excel</a>
                <a href="{{ route('laporan.export.pemakaian', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">Export PDF</a>
            </form>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Volume</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($totalVolume, 1) }} m³</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Rata-rata</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($rataRata, 1) }} m³</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Jumlah Pelanggan</p>
                    <p class="text-xl font-bold text-gray-900">{{ $pembacaan->total() }}</p>
                </div>
            </div>

            @if($pembacaan->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Tidak ada data untuk periode ini.</div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Meter</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Meter Sebelumnya</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Meter Sekarang</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Volume (m³)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($pembacaan as $p)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $p->pelanggan->nomor_pelanggan }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $p->pelanggan->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $p->meter->nomor_meter ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($p->angka_meter_sebelumnya, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($p->angka_meter_sekarang, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($p->volume_m3, 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-6">{{ $pembacaan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
