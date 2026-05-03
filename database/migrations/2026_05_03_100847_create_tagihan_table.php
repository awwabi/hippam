<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('pembacaan_id')->constrained('pembacaan')->cascadeOnDelete();
            $table->string('periode', 7);
            $table->decimal('volume_m3', 10, 1);
            $table->decimal('tarif_per_m3', 10, 2);
            $table->decimal('biaya_air', 12, 2);
            $table->decimal('total_tagihan', 12, 2);
            $table->enum('status', ['belum_bayar', 'lunas', 'cicilan', 'batal'])->default('belum_bayar');
            $table->date('tanggal_jatuh_tempo');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pelanggan_id', 'periode']);
            $table->index(['tenant_id', 'periode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
