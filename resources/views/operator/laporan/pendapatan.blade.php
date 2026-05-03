<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan Pendapatan</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('laporan.pendapatan') }}" class="mb-6 flex flex-col sm:flex-row gap-3 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                    <input type="month" name="periode" value="{{ $periode }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Tampilkan</button>
                <a href="{{ route('laporan.export.pendapatan', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">Export Excel</a>
                <a href="{{ route('laporan.export.pendapatan', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">Export PDF</a>
            </form>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Tagihan</p>
                    <p class="text-xl font-bold text-gray-900">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Total Terbayar</p>
                    <p class="text-xl font-bold text-green-600">Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <p class="text-sm text-gray-500">Belum Terbayar</p>
                    <p class="text-xl font-bold text-red-600">Rp {{ number_format($totalBelumBayar, 0, ',', '.') }}</p>
                </div>
            </div>

            @if($tagihan->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Tidak ada data untuk periode ini.</div>
            @else
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No. Pelanggan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Volume (m³)</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Tagihan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($tagihan as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $t->pelanggan->nomor_pelanggan }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $t->pelanggan->nama }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($t->volume_m3, 1) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">Rp {{ number_format($t->total_tagihan, 0, ',', '.') }}</td>
                                        <td class="px-4 py-3">
                                            @if($t->status === 'lunas')
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Lunas</span>
                                            @elseif($t->status === 'cicilan')
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Cicilan</span>
                                            @else
                                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Belum Bayar</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-6">{{ $tagihan->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
