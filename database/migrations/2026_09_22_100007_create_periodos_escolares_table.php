<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_escolares', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 20);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->enum('estado', ['activo', 'cerrado'])->default('activo');
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_escolares');
    }
};
