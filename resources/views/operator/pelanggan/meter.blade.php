<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('pelanggan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Data Pelanggan</a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 sm:p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Kelola Meter</h1>
            <div class="text-sm text-gray-500 mb-6">
                <p class="font-medium text-gray-700">{{ $pelanggan->nama }}</p>
                <p class="font-mono">{{ $pelanggan->nomor_pelanggan }}</p>
            </div>

            <form method="POST" action="{{ route('pelanggan.meter.store', $pelanggan) }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="nomor_meter" class="block text-sm font-medium text-gray-700 mb-1">Nomor Meter <span class="text-red-500">*</span></label>
                        <input type="text" id="nomor_meter" name="nomor_meter" value="{{ old('nomor_meter', $meter->nomor_meter ?? '') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('nomor_meter')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="merek" class="block text-sm font-medium text-gray-700 mb-1">Merek</label>
                        <input type="text" id="merek" name="merek" value="{{ old('merek', $meter->merek ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="tanggal_pemasangan" class="block text-sm font-medium text-gray-700 mb-1">Tanggal Pemasangan</label>
                        <input type="date" id="tanggal_pemasangan" name="tanggal_pemasangan" value="{{ old('tanggal_pemasangan', $meter->tanggal_pemasangan?->format('Y-m-d') ?? '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="aktif" {{ old('status', $meter->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                            <option value="rusak" {{ old('status', $meter->status ?? '') === 'rusak' ? 'selected' : '' }}>Rusak</option>
                            <option value="nonaktif" {{ old('status', $meter->status ?? '') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">Simpan Meter</button>
                    <a href="{{ route('pelanggan.index') }}" class="px-6 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-center">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
