<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('url'); // Ruta o URL de la imagen
            $table->string('type')->nullable(); // 'profile', 'logo', 'product', 'comprobante', etc.
            $table->string('descripcion')->nullable();
            $table->morphs('imageable'); // imageable_id y imageable_type
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
