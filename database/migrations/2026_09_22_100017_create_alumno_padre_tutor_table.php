<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumno_padre_tutor', function (Blueprint $table) {
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('padre_tutor_id')->constrained('padres_tutores')->onDelete('cascade');
            $table->string('parentesco', 50)->nullable();
            $table->boolean('es_principal')->default(0);
            $table->primary(['alumno_id', 'padre_tutor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumno_padre_tutor');
    }
};
