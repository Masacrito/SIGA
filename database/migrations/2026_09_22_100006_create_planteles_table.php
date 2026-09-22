<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planteles', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 20);
            $table->string('nombre', 150);
            $table->unique('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planteles');
    }
};
