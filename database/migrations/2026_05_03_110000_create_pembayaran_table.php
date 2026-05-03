<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tagihan_id')->constrained('tagihan')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->date('tanggal_bayar');
            $table->decimal('jumlah_bayar', 12, 2);
            $table->enum('metode_bayar', ['tunai', 'transfer', 'ewallet']);
            $table->string('no_referensi', 100)->nullable();
            $table->foreignId('petugas_kasir')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tanggal_bayar']);
            $table->index(['tenant_id', 'tagihan_id']);
            $table->index(['tenant_id', 'pelanggan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};
