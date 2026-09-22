<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orientador_grupo', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->primary(['usuario_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orientador_grupo');
    }
};
