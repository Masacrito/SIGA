<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacion_errores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('importaciones')->onDelete('cascade');
            $table->unsignedInteger('fila');
            $table->string('columna', 80)->nullable();
            $table->string('mensaje', 255);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacion_errores');
    }
};
