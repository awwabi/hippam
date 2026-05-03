<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_bayar' => 'required|date',
            'jumlah_bayar' => 'required|numeric|min:1',
            'metode_bayar' => 'required|in:tunai,transfer,ewallet',
            'no_referensi' => 'nullable|string|max:100|required_if:metode_bayar,transfer,ewallet',
            'catatan' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_bayar.required' => 'Tanggal bayar wajib diisi.',
            'tanggal_bayar.date' => 'Format tanggal tidak valid.',
            'jumlah_bayar.required' => 'Jumlah bayar wajib diisi.',
            'jumlah_bayar.numeric' => 'Jumlah bayar harus berupa angka.',
            'jumlah_bayar.min' => 'Jumlah bayar minimal Rp 1.',
            'metode_bayar.required' => 'Metode bayar wajib dipilih.',
            'metode_bayar.in' => 'Metode bayar tidak valid.',
            'no_referensi.required_if' => 'No. referensi wajib untuk pembayaran transfer/e-wallet.',
            'catatan.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
