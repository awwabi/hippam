<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Catat Pembayaran</h1>

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Tagihan Summary --}}
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Detail Tagihan</h2>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">No. Pelanggan</div>
                <div class="font-mono text-gray-900">{{ $tagihan->pelanggan->nomor_pelanggan }}</div>
                <div class="text-gray-500">Nama</div>
                <div class="font-medium text-gray-900">{{ $tagihan->pelanggan->nama }}</div>
                <div class="text-gray-500">Periode</div>
                <div class="text-gray-900">{{ $tagihan->periode }}</div>
                <div class="text-gray-500">Volume</div>
                <div class="text-gray-900">{{ number_format($tagihan->volume_m3, 1) }} m³</div>
                <div class="text-gray-500">Total Tagihan</div>
                <div class="font-medium text-gray-900">Rp {{ number_format($tagihan->total_tagihan, 0, ',', '.') }}</div>
                <div class="text-gray-500">Sudah Dibayar</div>
                <div class="text-gray-900">Rp {{ number_format($tagihan->totalDibayar(), 0, ',', '.') }}</div>
                <div class="text-gray-500 font-medium">Sisa Tagihan</div>
                <div class="font-bold text-red-600">Rp {{ number_format($tagihan->sisaTagihan(), 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Payment Form --}} 
        <form method="POST" action="{{ route('pembayaran.store', $tagihan) }}" class="bg-white rounded-lg shadow p-6">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="jumlah_bayar" :value="'Jumlah Bayar'" />
                    <x-text-input id="jumlah_bayar" name="jumlah_bayar" type="number" step="1" min="1" max="{{ $tagihan->sisaTagihan() }}"
                        :value="old('jumlah_bayar', $tagihan->sisaTagihan())"
                        class="mt-1 block w-full" placeholder="Masukkan jumlah bayar" />
                    <x-input-error :messages="$errors->get('jumlah_bayar')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="tanggal_bayar" :value="'Tanggal Bayar'" />
                    <x-text-input id="tanggal_bayar" name="tanggal_bayar" type="date"
                        :value="old('tanggal_bayar', now()->format('Y-m-d'))"
                        class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('tanggal_bayar')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="metode_bayar" :value="'Metode Bayar'" />
                    <select id="metode_bayar" name="metode_bayar" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="tunai" {{ old('metode_bayar', 'tunai') === 'tunai' ? 'selected' : '' }}>Tunai</option>
                        <option value="transfer" {{ old('metode_bayar') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                        <option value="ewallet" {{ old('metode_bayar') === 'ewallet' ? 'selected' : '' }}>E-Wallet</option>
                    </select>
                    <x-input-error :messages="$errors->get('metode_bayar')" class="mt-2" />
                </div>

                <div id="referensi-field">
                    <x-input-label for="no_referensi" :value="'No. Referensi'" />
                    <x-text-input id="no_referensi" name="no_referensi" type="text"
                        :value="old('no_referensi')"
                        class="mt-1 block w-full" placeholder="Opsional untuk tunai" />
                    <x-input-error :messages="$errors->get('no_referensi')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="catatan" :value="'Catatan'" />
                    <textarea id="catatan" name="catatan" rows="2" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Opsional">{{ old('catatan') }}</textarea>
                    <x-input-error :messages="$errors->get('catatan')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3 mt-6">
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors text-sm font-medium">
                    Simpan Pembayaran
                </button>
                <a href="{{ route('tagihan.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium">
                    Batal
                </a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.getElementById('metode_bayar').addEventListener('change', function() {
            const refField = document.getElementById('referensi-field');
            const refInput = document.getElementById('no_referensi');
            if (this.value === 'tunai') {
                refInput.required = false;
                refInput.placeholder = 'Opsional untuk tunai';
            } else {
                refInput.required = true;
                refInput.placeholder = 'Wajib diisi untuk ' + this.value;
            }
        });
    </script>
    @endpush
</x-app-layout>
