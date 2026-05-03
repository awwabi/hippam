<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nama_unit');
            $table->string('kode_unit')->unique();
            $table->text('alamat')->nullable();
            $table->string('desa')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kabupaten')->nullable();
            $table->string('kontak_pengelola')->nullable();
            $table->string('no_telepon')->nullable();
            $table->string('email')->nullable();
            $table->decimal('tarif_per_m3', 10, 2)->default(0);
            $table->unsignedTinyInteger('jatuh_tempo_tanggal')->default(20);
            $table->string('printer_width', 10)->default('58mm');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
