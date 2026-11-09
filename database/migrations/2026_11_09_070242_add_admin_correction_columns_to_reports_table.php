<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Kolom koreksi admin tanpa menimpa saran AI asli
            $table->string('kategori_admin')->nullable()->after('kategori');
            $table->unsignedTinyInteger('keparahan_admin')->nullable()->after('tingkat_keparahan');
            $table->foreignId('dikoreksi_oleh')->nullable()->after('kepercayaan')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['dikoreksi_oleh']);
            $table->dropColumn(['kategori_admin', 'keparahan_admin', 'dikoreksi_oleh']);
        });
    }
};
