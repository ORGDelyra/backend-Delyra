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
            $table->foreignId('id_usuario')
                    ->constrained('users')
                    ->onDelete('cascade');
            $table->enum('tipo_entrega', ['domicilio', 'recogida'])->default('recogida');
            $table->string('direccion_entrega')->nullable();
            $table->string('latitud_entrega')->nullable();
            $table->string('longitud_entrega')->nullable();
            $table->foreignId('id_domiciliario')
                    ->nullable()
                    ->constrained('users')
                    ->onDelete('set null');
            $table->enum('estado_pedido', ['pendiente', 'confirmado', 'en_preparacion', 'listo', 'entregado', 'recogido'])->default('pendiente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeignKeyIfExists(['id_usuario']);
            $table->dropForeignKeyIfExists(['id_domiciliario']);
            $table->dropColumn(['id_usuario', 'tipo_entrega', 'direccion_entrega', 'latitud_entrega', 'longitud_entrega', 'id_domiciliario', 'estado_pedido']);
        });
    }
};
