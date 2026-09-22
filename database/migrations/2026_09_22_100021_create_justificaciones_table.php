<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->constrained('asistencias')->onDelete('cascade');
            $table->string('motivo', 255);
            $table->string('evidencia_url', 255)->nullable();
            $table->foreignId('autorizada_por')->constrained('usuarios')->onDelete('restrict');
            $table->dateTime('fecha_autorizacion');
            $table->unique('asistencia_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justificaciones');
    }
};
