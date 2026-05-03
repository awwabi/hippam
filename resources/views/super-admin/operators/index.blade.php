<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Kelola Operator</h1>
            <a href="{{ route('super-admin.operators.create') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tambah Operator
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

        <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit HIPPAM</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($operators as $operator)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $operator->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $operator->email }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $operator->tenant?->nama_unit ?? '-' }}</td>
                            <td class="px-6 py-4 text-right text-sm">
                                <form method="POST" action="{{ route('super-admin.operators.destroy', $operator) }}" class="inline" onsubmit="return confirm('Yakin ingin menghapus operator ini?')">
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
            @foreach($operators as $operator)
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-start justify-between mb-2">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">{{ $operator->name }}</h3>
                            <p class="text-xs text-gray-500">{{ $operator->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('super-admin.operators.destroy', $operator) }}" onsubmit="return confirm('Yakin ingin menghapus operator ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-700 text-xs font-medium">Hapus</button>
                        </form>
                    </div>
                    <p class="text-xs text-gray-500">Unit: {{ $operator->tenant?->nama_unit ?? '-' }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $operators->links() }}
        </div>
    </div>
</x-app-layout>
