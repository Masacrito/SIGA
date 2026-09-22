<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_clase_id')->constrained('sesiones_clase')->onDelete('cascade');
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('registrado_por')->constrained('usuarios')->onDelete('restrict');
            $table->enum('estado', ['presente', 'ausente', 'retardo', 'justificado']);
            $table->char('uuid_local', 36)->nullable();
            $table->dateTime('capturada_en');
            $table->enum('origen', ['online', 'offline'])->default('online');
            $table->dateTime('sincronizada_en')->nullable();
            $table->unique(['sesion_clase_id', 'alumno_id'], 'uq_asistencias_sesion_clase_id_alumno_id');
            $table->unique('uuid_local');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
