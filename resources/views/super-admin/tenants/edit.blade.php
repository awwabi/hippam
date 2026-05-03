<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit Unit HIPPAM</h1>

        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('super-admin.tenants.update', $tenant) }}">
                @csrf
                @method('PUT')

                <div class="space-y-5">
                    <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                        <input type="checkbox" id="is_active" name="is_active" value="1" {{ $tenant->is_active ? 'checked' : '' }} class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                        <label for="is_active" class="text-sm font-medium text-gray-700">Unit Aktif</label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="nama_unit" class="block text-sm font-medium text-gray-700 mb-1">Nama Unit *</label>
                            <input type="text" id="nama_unit" name="nama_unit" value="{{ old('nama_unit', $tenant->nama_unit) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="kode_unit" class="block text-sm font-medium text-gray-700 mb-1">Kode Unit *</label>
                            <input type="text" id="kode_unit" name="kode_unit" value="{{ old('kode_unit', $tenant->kode_unit) }}" required maxlength="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div>
                        <label for="alamat" class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">{{ old('alamat', $tenant->alamat) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <label for="desa" class="block text-sm font-medium text-gray-700 mb-1">Desa</label>
                            <input type="text" id="desa" name="desa" value="{{ old('desa', $tenant->desa) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-1">Kecamatan</label>
                            <input type="text" id="kecamatan" name="kecamatan" value="{{ old('kecamatan', $tenant->kecamatan) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="kabupaten" class="block text-sm font-medium text-gray-700 mb-1">Kabupaten</label>
                            <input type="text" id="kabupaten" name="kabupaten" value="{{ old('kabupaten', $tenant->kabupaten) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="kontak_pengelola" class="block text-sm font-medium text-gray-700 mb-1">Kontak Pengelola</label>
                            <input type="text" id="kontak_pengelola" name="kontak_pengelola" value="{{ old('kontak_pengelola', $tenant->kontak_pengelola) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                            <input type="text" id="no_telepon" name="no_telepon" value="{{ old('no_telepon', $tenant->no_telepon) }}" maxlength="20" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $tenant->email) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                        <div>
                            <label for="tarif_per_m3" class="block text-sm font-medium text-gray-700 mb-1">Tarif per m³ (Rp) *</label>
                            <input type="number" id="tarif_per_m3" name="tarif_per_m3" value="{{ old('tarif_per_m3', $tenant->tarif_per_m3) }}" required min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="jatuh_tempo_tanggal" class="block text-sm font-medium text-gray-700 mb-1">Jatuh Tempo (Tanggal) *</label>
                            <input type="number" id="jatuh_tempo_tanggal" name="jatuh_tempo_tanggal" value="{{ old('jatuh_tempo_tanggal', $tenant->jatuh_tempo_tanggal) }}" required min="1" max="28" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="printer_width" class="block text-sm font-medium text-gray-700 mb-1">Lebar Kertas Struk *</label>
                            <select id="printer_width" name="printer_width" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="58mm" {{ old('printer_width', $tenant->printer_width) === '58mm' ? 'selected' : '' }}>58 mm</option>
                                <option value="80mm" {{ old('printer_width', $tenant->printer_width) === '80mm' ? 'selected' : '' }}>80 mm</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-8 pt-6 border-t border-gray-200">
                    <form method="POST" action="{{ route('super-admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Yakin ingin menghapus unit {{ $tenant->nama_unit }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-red-600 bg-white border border-red-300 rounded-lg hover:bg-red-50 transition-colors">Hapus</button>
                    </form>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('super-admin.tenants.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Batal</a>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">Perbarui</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
