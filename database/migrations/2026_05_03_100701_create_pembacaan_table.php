<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembacaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained('meters')->cascadeOnDelete();
            $table->string('periode', 7);
            $table->decimal('angka_meter_sebelumnya', 10, 1)->default(0);
            $table->decimal('angka_meter_sekarang', 10, 1);
            $table->decimal('volume_m3', 10, 1)->nullable();
            $table->date('tanggal_baca');
            $table->foreignId('dibaca_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'konfirmasi'])->default('draft');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pelanggan_id', 'periode']);
            $table->index(['tenant_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembacaan');
    }
};
