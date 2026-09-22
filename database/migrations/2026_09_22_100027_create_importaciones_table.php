<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('restrict');
            $table->foreignId('periodo_id')->nullable()->constrained('periodos_escolares')->onDelete('set null');
            $table->enum('tipo', ['alumnos', 'calificaciones', 'docentes', 'catalogos']);
            $table->string('nombre_archivo', 255);
            $table->unsignedInteger('filas_procesadas')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->enum('estado', ['completado', 'con_errores', 'fallido'])->default('completado');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importaciones');
    }
};
