<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('pembacaan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Pembacaan Meter</a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 sm:p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Edit Pembacaan</h1>
            <p class="text-sm text-gray-500 mb-6">
                {{ $pembacaan->pelanggan->nama }} &middot;
                <span class="font-mono">{{ $pembacaan->pelanggan->nomor_pelanggan }}</span> &middot;
                Periode {{ $pembacaan->periode }}
            </p>

            <form method="POST" action="{{ route('pembacaan.update', $pembacaan) }}">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="angka_meter_sebelumnya" class="block text-sm font-medium text-gray-700 mb-1">Angka Meter Sebelumnya <span class="text-red-500">*</span></label>
                            <input type="number" id="angka_meter_sebelumnya" name="angka_meter_sebelumnya" value="{{ old('angka_meter_sebelumnya', $pembacaan->angka_meter_sebelumnya) }}" step="0.1" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            @error('angka_meter_sebelumnya')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="angka_meter_sekarang" class="block text-sm font-medium text-gray-700 mb-1">Angka Meter Sekarang <span class="text-red-500">*</span></label>
                            <input type="number" id="angka_meter_sekarang" name="angka_meter_sekarang" value="{{ old('angka_meter_sekarang', $pembacaan->angka_meter_sekarang) }}" step="0.1" min="0" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            @error('angka_meter_sekarang')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Volume (m³)</label>
                        <div class="px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                            <span id="volume-display">{{ number_format($pembacaan->volume_m3, 1) }}</span> m³
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Dihitung otomatis: angka sekarang - angka sebelumnya</p>
                    </div>

                    <div>
                        <label for="tanggal_baca" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Baca <span class="text-red-500">*</span></label>
                        <input type="date" id="tanggal_baca" name="tanggal_baca" value="{{ old('tanggal_baca', $pembacaan->tanggal_baca->format('Y-m-d')) }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('tanggal_baca')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="draft" {{ old('status', $pembacaan->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="konfirmasi" {{ old('status', $pembacaan->status) === 'konfirmasi' ? 'selected' : '' }}>Konfirmasi</option>
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="catatan" class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea id="catatan" name="catatan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">{{ old('catatan', $pembacaan->catatan) }}</textarea>
                        @error('catatan')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">Perbarui</button>
                    <a href="{{ route('pembacaan.index') }}" class="px-6 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-center">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sebelumInput = document.getElementById('angka_meter_sebelumnya');
        const sekarangInput = document.getElementById('angka_meter_sekarang');
        const volumeDisplay = document.getElementById('volume-display');

        function updateVolume() {
            const s = parseFloat(sebelumInput.value) || 0;
            const k = parseFloat(sekarangInput.value) || 0;
            const vol = Math.max(0, k - s).toFixed(1);
            volumeDisplay.textContent = vol;
        }

        sebelumInput?.addEventListener('input', updateVolume);
        sekarangInput?.addEventListener('input', updateVolume);
    </script>
</x-app-layout>
