<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('materia_id')->constrained('materias')->onDelete('restrict');
            $table->foreignId('periodo_id')->constrained('periodos_escolares')->onDelete('restrict');
            $table->unsignedTinyInteger('parcial');
            $table->string('calificacion', 5);
            $table->enum('tipo', ['regular', 'cursadora'])->default('regular');
            $table->enum('origen', ['importado', 'manual'])->default('importado');
            $table->foreignId('editado_por')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->unique(['alumno_id', 'materia_id', 'periodo_id', 'parcial', 'tipo'], 'uq_calificaciones_alumno_id_materia_id_periodo_id_parcial_');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calificaciones');
    }
};
