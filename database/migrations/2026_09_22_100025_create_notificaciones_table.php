<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('padre_tutor_id')->constrained('padres_tutores')->onDelete('cascade');
            $table->enum('tipo', ['automatica', 'manual']);
            $table->enum('canal', ['sms', 'push', 'email']);
            $table->enum('estado', ['pendiente', 'enviada', 'fallida'])->default('pendiente');
            $table->string('mensaje', 500);
            $table->dateTime('fecha_envio')->nullable();
            $table->string('error_detalle', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
