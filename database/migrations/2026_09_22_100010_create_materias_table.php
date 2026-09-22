<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10);
            $table->string('nombre', 200);
            $table->enum('tipo_evaluacion', ['numerica', 'alfanumerica'])->default('numerica');
            $table->unique('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
