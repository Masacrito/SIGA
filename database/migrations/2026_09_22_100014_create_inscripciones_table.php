<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('restrict');
            $table->foreignId('periodo_id')->constrained('periodos_escolares')->onDelete('restrict');
            $table->enum('situacion_academica', ['regular', 'irregular', 'recursador', 'extemporaneo'])->default('regular');
            $table->date('fecha_inscripcion')->nullable();
            $table->unique(['alumno_id', 'periodo_id'], 'uq_inscripciones_alumno_id_periodo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
