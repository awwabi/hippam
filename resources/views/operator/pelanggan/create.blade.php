<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="{{ route('pelanggan.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali ke Data Pelanggan</a>
        </div>

        <div class="bg-white rounded-lg shadow p-6 sm:p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-6">Tambah Pelanggan Baru</h1>

            <form method="POST" action="{{ route('pelanggan.store') }}">
                @csrf

                <div class="space-y-5">
                    <div>
                        <label for="nama" class="block text-sm font-medium text-gray-700 mb-1">Nama <span class="text-red-500">*</span></label>
                        <input type="text" id="nama" name="nama" value="{{ old('nama') }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @error('nama')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="alamat" class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">{{ old('alamat') }}</textarea>
                    </div>

                    <div>
                        <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                        <input type="text" id="no_telepon" name="no_telepon" value="{{ old('no_telepon') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div>
                        <label for="catatan" class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea id="catatan" name="catatan" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">{{ old('catatan') }}</textarea>
                    </div>

                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                        Nomor pelanggan akan dibuat otomatis saat data disimpan.
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">Simpan</button>
                    <a href="{{ route('pelanggan.index') }}" class="px-6 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors text-sm font-medium text-center">Batal</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
