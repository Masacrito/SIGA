<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('numero_empleado', 20)->nullable();
            $table->unique('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docentes');
    }
};
