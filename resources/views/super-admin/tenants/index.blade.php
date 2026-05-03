<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Kelola Unit HIPPAM</h1>
            <a href="{{ route('super-admin.tenants.create') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Unit
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

        {{ $tenants->isEmpty() ? __('Belum ada data.') : '' }}

        <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Unit</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kabupaten</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($tenants as $tenant)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $tenant->nama_unit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $tenant->kode_unit }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $tenant->kabupaten ?? '-' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $tenant->pelanggan_count }}</td>
                            <td class="px-6 py-4">
                                @if($tenant->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm space-x-2">
                                <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="text-primary-600 hover:text-primary-700 font-medium">Edit</a>
                                <form method="POST" action="{{ route('super-admin.tenants.destroy', $tenant) }}" class="inline" onsubmit="return confirm('Yakin ingin menghapus unit ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3">
            @foreach($tenants as $tenant)
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ $tenant->nama_unit }}</h3>
                            <p class="text-xs text-gray-500">{{ $tenant->kode_unit }}</p>
                        </div>
                        @if($tenant->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Aktif</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Nonaktif</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 space-y-1 mb-3">
                        <p>{{ $tenant->kabupaten ?? '-' }}</p>
                        <p>Pelanggan: {{ $tenant->pelanggan_count }}</p>
                    </div>
                    <div class="flex items-center gap-3 border-t border-gray-100 pt-3">
                        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">Edit</a>
                        <form method="POST" action="{{ route('super-admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Yakin ingin menghapus unit ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-700 text-xs font-medium">Hapus</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $tenants->links() }}
        </div>
    </div>
</x-app-layout>
