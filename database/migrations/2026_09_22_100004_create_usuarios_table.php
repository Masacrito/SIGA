<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roles')->onDelete('restrict');
            $table->string('nombre', 150);
            $table->string('username', 60);
            $table->string('password', 255);
            $table->string('correo', 160)->nullable();
            $table->boolean('es_super_admin')->default(0);
            $table->boolean('activo')->default(1);
            $table->string('remember_token', 100)->nullable();
            $table->unique('username');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
