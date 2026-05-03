<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('nomor_meter', 50)->unique();
            $table->string('merek', 50);
            $table->string('status', 20)->default('aktif');
            $table->timestamps();

            $table->index(['tenant_id', 'pelanggan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
