<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Data Pelanggan</h1>
            <a href="{{ route('pelanggan.create') }}" class="inline-flex items-center justify-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Pelanggan
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

        <form method="GET" action="{{ route('pelanggan.index') }}" class="mb-6 flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, no. pelanggan, telepon..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua Status</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">Cari</button>
        </form>

        @if($pelanggan->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                Belum ada data pelanggan.
            </div>
        @else
            <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pelanggan</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telepon</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($pelanggan as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">{{ $p->nomor_pelanggan }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $p->nama }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $p->alamat ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $p->no_telepon ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    @if($p->status === 'aktif')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm space-x-2">
                                    <a href="{{ route('pelanggan.edit', $p) }}" class="text-primary-600 hover:text-primary-700 font-medium">Edit</a>
                                    <a href="{{ route('pelanggan.meter', $p) }}" class="text-gray-600 hover:text-gray-700 font-medium">Meter</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="md:hidden space-y-3">
                @foreach($pelanggan as $p)
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">{{ $p->nama }}</h3>
                                <p class="text-xs text-gray-500 font-mono">{{ $p->nomor_pelanggan }}</p>
                            </div>
                            @if($p->status === 'aktif')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 space-y-1 mb-3">
                            <p>{{ $p->alamat ?? '-' }}</p>
                            <p>{{ $p->no_telepon ?? '-' }}</p>
                        </div>
                        <div class="flex items-center gap-3 border-t border-gray-100 pt-3">
                            <a href="{{ route('pelanggan.edit', $p) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Edit</a>
                            <a href="{{ route('pelanggan.meter', $p) }}" class="text-gray-600 hover:text-gray-700 text-xs font-medium">Meter</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            {{ $pelanggan->links() }}
        </div>
    </div>
</x-app-layout>
