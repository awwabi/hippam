<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard Super Admin
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Jumlah Unit HIPPAM</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $jumlahTenant }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Pelanggan Aktif</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalPelanggan }}</p>
                </div>
                <div class="bg-white rounded-lg shadow p-5">
                    <p class="text-sm text-gray-500">Total Pendapatan Bulan Ini</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
                </div>
            </div>

            {{-- Per-Tenant Breakdown --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Ringkasan Per Unit</h3>
                </div>
                @if($tenantBreakdown->isEmpty())
                    <div class="p-8 text-center text-gray-500">Belum ada data tenant.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desa</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pendapatan</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Belum Lunas</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($tenantBreakdown as $t)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $t->nama_unit }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500">{{ $t->desa ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-right text-gray-900">{{ $t->pelanggan_count }}</td>
                                        <td class="px-6 py-4 text-sm text-right font-medium text-gray-900">Rp {{ number_format($t->pendapatan_bulan_ini, 0, ',', '.') }}</td>
                                        <td class="px-6 py-4 text-sm text-right">
                                            @if($t->belum_lunas > 0)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ $t->belum_lunas }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
