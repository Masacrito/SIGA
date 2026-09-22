<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('padres_tutores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('correo', 160)->nullable();
            $table->enum('canal_preferido', ['sms', 'push', 'email'])->default('sms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padres_tutores');
    }
};
