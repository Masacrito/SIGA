<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80);
            $table->string('valor', 255);
            $table->enum('tipo_dato', ['entero', 'decimal', 'texto', 'booleano'])->default('texto');
            $table->string('descripcion', 255)->nullable();
            $table->unique('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
