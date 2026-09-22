<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('restrict');
            $table->foreignId('materia_id')->constrained('materias')->onDelete('restrict');
            $table->foreignId('docente_id')->constrained('docentes')->onDelete('restrict');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->unique(['grupo_id', 'materia_id', 'dia_semana', 'hora_inicio'], 'uq_horarios_grupo_id_materia_id_dia_semana_hora_inic');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
