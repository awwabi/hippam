<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pembacaan Meter</h1>
            <a href="{{ route('pembacaan.create', ['periode' => date('Y-m')]) }}" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Input Pembacaan
            </a>
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

        <form method="GET" action="{{ route('pembacaan.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3">
            <select name="periode" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Periode</option>
                @foreach($periodes as $p)
                    <option value="{{ $p }}" {{ request('periode') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Status</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="konfirmasi" {{ request('status') === 'konfirmasi' ? 'selected' : '' }}>Konfirmasi</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Filter</button>
        </form>

        @if($pembacaan->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Belum ada data pembacaan.
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
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Meter</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Angka Sebelum</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Angka Sekarang</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Volume (m³)</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($pembacaan as $pb)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $pb->periode }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-900">{{ $pb->pelanggan->nomor_pelanggan }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $pb->pelanggan->nama }}</td>
                                    <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ $pb->meter->nomor_meter }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($pb->angka_meter_sebelumnya, 1) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">{{ number_format($pb->angka_meter_sekarang, 1) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">{{ number_format($pb->volume_m3, 1) }}</td>
                                    <td class="px-4 py-3">
                                        @if($pb->status === 'konfirmasi')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Konfirmasi</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Draft</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <a href="{{ route('pembacaan.edit', $pb) }}" class="text-primary-600 hover:text-primary-700 font-medium">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="md:hidden space-y-3">
                @foreach($pembacaan as $pb)
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $pb->pelanggan->nama }}</h3>
                                <p class="text-xs text-gray-500 font-mono">{{ $pb->pelanggan->nomor_pelanggan }}</p>
                            </div>
                            @if($pb->status === 'konfirmasi')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Konfirmasi</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Draft</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 space-y-1 mb-3">
                            <p>Periode: {{ $pb->periode }}</p>
                            <p>No. Meter: {{ $pb->meter->nomor_meter }}</p>
                            <p>Angka: {{ number_format($pb->angka_meter_sebelumnya, 1) }} → {{ number_format($pb->angka_meter_sekarang, 1) }} m³</p>
                            <p class="font-medium text-gray-700">Volume: {{ number_format($pb->volume_m3, 1) }} m³</p>
                        </div>
                        <div class="flex items-center gap-3 border-t border-gray-100 pt-3">
                            <a href="{{ route('pembacaan.edit', $pb) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Edit</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            {{ $pembacaan->links() }}
        </div>
    </div>
</x-app-layout>
