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
            // Mercado Pago fields
            $table->string('mercado_pago_preference_id')->nullable()->after('estado_pedido');
            $table->enum('estado_pago', ['pendiente', 'confirmado', 'rechazado'])->default('pendiente')->after('mercado_pago_preference_id');
            $table->enum('metodo_pago', ['mercado_pago', 'contraentrega', 'local'])->default('contraentrega')->after('estado_pago');
            $table->timestamp('fecha_pago_confirmado')->nullable()->after('metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn([
                'mercado_pago_preference_id',
                'estado_pago',
                'metodo_pago',
                'fecha_pago_confirmado'
            ]);
        });
    }
};
