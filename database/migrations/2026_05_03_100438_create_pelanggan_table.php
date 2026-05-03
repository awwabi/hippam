<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->string('no_telepon')->nullable();
            $table->string('nomor_pelanggan')->unique();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->date('tanggal_daftar')->default(now());
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'nomor_pelanggan']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
