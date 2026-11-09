<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('judul');
            $t->text('deskripsi')->nullable();
            $t->string('foto_path');
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->string('kategori')->nullable();
            $t->unsignedTinyInteger('tingkat_keparahan')->nullable(); // 1-5
            $t->text('ringkasan_ai')->nullable();
            $t->string('ai_status')->default('menunggu'); // menunggu | berhasil | gagal
            $t->string('status')->default('baru');        // baru | diproses | selesai
            $t->timestamps();
        });

        Schema::create('status_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained()->cascadeOnDelete();
            $t->string('status');
            $t->text('catatan')->nullable();
            $t->foreignId('diubah_oleh')->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_logs');
        Schema::dropIfExists('reports');
    }
};