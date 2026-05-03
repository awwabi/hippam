<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('pembacaan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Pembacaan Meter</a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 sm:p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Input Pembacaan Meter</h1>
            <p class="text-sm text-gray-500 mb-4">Periode: <span class="font-mono font-medium text-gray-700">{{ $periode }}</span></p>

            <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700 mb-6">
                Masukkan angka meter sekarang untuk setiap pelanggan. Angka sebelumnya diambil otomatis dari periode sebelumnya. Kolom dengan angka meter kosong atau sama dengan angka sebelumnya akan dilewati.
            </div>

            @if($entries->isEmpty())
                <div class="text-center text-gray-500 py-8">
                    Tidak ada pelanggan aktif dengan meter aktif.
                </div>
            @else
                <form method="POST" action="{{ route('pembacaan.batch') }}">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="periode" class="block text-sm font-medium text-gray-700 mb-1">Periode <span class="text-red-500">*</span></label>
                            <input type="month" id="periode" name="periode" value="{{ $periode }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="tanggal_baca" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Baca <span class="text-red-500">*</span></label>
                            <input type="date" id="tanggal_baca" name="tanggal_baca" value="{{ date('Y-m-d') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div class="mb-4 hidden sm:block bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
                                            <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Pelanggan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Meter</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Angka Sebelumnya</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Angka Sekarang</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($entries as $i => $entry)
                                        <tr class="hover:bg-gray-50 reading-row" data-index="{{ $i }}">
                                            <td class="px-4 py-2">
                                                <input type="checkbox" class="reading-checkbox rounded border-gray-300" checked>
                                            </td>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-900">{{ $entry['nomor_pelanggan'] }}</td>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $entry['nama'] }}</td>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-500">{{ $entry['nomor_meter'] }}</td>
                                            <td class="px-4 py-2 text-sm text-right text-gray-600">{{ number_format($entry['angka_meter_sebelumnya'], 1) }}</td>
                                            <td class="px-4 py-2 text-sm text-right">
                                                <input type="number"
                                                    name="readings[{{ $i }}][angka_meter_sekarang]"
                                                    step="0.1"
                                                    min="0"
                                                    placeholder="0.0"
                                                    class="w-28 text-right px-2 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                            </td>
                                            <input type="hidden" name="readings[{{ $i }}][pelanggan_id]" value="{{ $entry['pelanggan_id'] }}">
                                            <input type="hidden" name="readings[{{ $i }}][meter_id]" value="{{ $entry['meter_id'] }}">
                                            <input type="hidden" name="readings[{{ $i }}][angka_meter_sebelumnya]" value="{{ $entry['angka_meter_sebelumnya'] }}">
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="sm:hidden space-y-4 mb-6">
                        @foreach($entries as $i => $entry)
                            <div class="border border-gray-200 rounded-lg p-4 reading-row-mobile" data-index="{{ $i }}">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900">{{ $entry['nama'] }}</h3>
                                        <p class="text-xs text-gray-500 font-mono">{{ $entry['nomor_pelanggan'] }} &middot; {{ $entry['nomor_meter'] }}</p>
                                    </div>
                                    <input type="checkbox" class="reading-checkbox rounded border-gray-300" checked>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Angka Sebelumnya</label>
                                        <div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 text-right">{{ number_format($entry['angka_meter_sebelumnya'], 1) }}</div>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">Angka Sekarang</label>
                                        <input type="number"
                                            name="readings[{{ $i }}][angka_meter_sekarang]"
                                            step="0.1"
                                            min="0"
                                            placeholder="0.0"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-right">
                                    </div>
                                </div>
                                <input type="hidden" name="readings[{{ $i }}][pelanggan_id]" value="{{ $entry['pelanggan_id'] }}">
                                <input type="hidden" name="readings[{{ $i }}][meter_id]" value="{{ $entry['meter_id'] }}">
                                <input type="hidden" name="readings[{{ $i }}][angka_meter_sebelumnya]" value="{{ $entry['angka_meter_sebelumnya'] }}">
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200">
                        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                            Simpan Pembacaan
                        </button>
                        <a href="{{ route('pembacaan.index') }}" class="px-6 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-center">
                            Batal
                        </a>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <script>
        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.reading-checkbox').forEach(cb => cb.checked = this.checked);
        });
    </script>
</x-app-layout>
