<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Agregar 'en_camino' a los valores permitidos del enum estado_pedido
            $table->enum('estado_pedido', [
                'pendiente', 
                'confirmado', 
                'en_preparacion', 
                'listo', 
                'en_camino',      // <-- AGREGADO
                'entregado', 
                'recogido'
            ])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // Volver al estado anterior sin 'en_camino'
            $table->enum('estado_pedido', [
                'pendiente', 
                'confirmado', 
                'en_preparacion', 
                'listo', 
                'entregado', 
                'recogido'
            ])->nullable()->change();
        });
    }
};
