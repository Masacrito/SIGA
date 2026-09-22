<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_padres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('padre_tutor_id')->constrained('padres_tutores')->onDelete('cascade');
            $table->string('token_fcm', 255);
            $table->enum('plataforma', ['android', 'ios', 'web']);
            $table->boolean('activo')->default(1);
            $table->dateTime('ultimo_uso')->nullable();
            $table->unique('token_fcm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_padres');
    }
};
