<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grado_id')->constrained('grados')->onDelete('restrict');
            $table->foreignId('plantel_id')->constrained('planteles')->onDelete('restrict');
            $table->foreignId('periodo_id')->constrained('periodos_escolares')->onDelete('restrict');
            $table->char('letra', 1);
            $table->enum('turno', ['matutino', 'vespertino']);
            $table->unique(['grado_id', 'letra', 'plantel_id', 'periodo_id', 'turno'], 'uq_grupos_grado_id_letra_plantel_id_periodo_id_tur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
