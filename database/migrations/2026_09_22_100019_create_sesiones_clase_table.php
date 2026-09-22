<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesiones_clase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->constrained('horarios')->onDelete('cascade');
            $table->date('fecha');
            $table->boolean('cancelada')->default(0);
            $table->string('motivo_cancelacion', 200)->nullable();
            $table->unique(['horario_id', 'fecha'], 'uq_sesiones_clase_horario_id_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_clase');
    }
};
