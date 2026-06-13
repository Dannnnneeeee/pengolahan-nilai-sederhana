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
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->unsignedTinyInteger('nilai_tugas');
            $table->unsignedTinyInteger('nilai_uts');
            $table->unsignedTinyInteger('nilai_uas');
            $table->decimal('nilai_akhir', 5, 2)->default(0);
            $table->string('status')->default('-');
            $table->timestamps();
            $table->unique(['siswa_id','guru_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};
