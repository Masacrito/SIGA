<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umbrales_riesgo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_escolares')->onDelete('cascade');
            $table->enum('nivel', ['bajo', 'medio', 'alto']);
            $table->decimal('porcentaje_min', 5, 2);
            $table->decimal('porcentaje_max', 5, 2);
            $table->unsignedInteger('faltas_consecutivas')->nullable();
            $table->char('color_hex', 7)->default('#16a34a');
            $table->boolean('activo')->default(1);
            $table->unique(['periodo_id', 'nivel'], 'uq_umbrales_riesgo_periodo_id_nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umbrales_riesgo');
    }
};
