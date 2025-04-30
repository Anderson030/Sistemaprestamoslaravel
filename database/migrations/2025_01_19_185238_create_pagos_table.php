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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('prestamo_id');
            $table->foreign('prestamo_id')->references('id')->on('prestamos')->onDelete('cascade');

            $table->decimal('monto_pagado',10,2);
            $table->date('fecha_pago');
            $table->enum('metodo_pago',['Efectivo','Transferencia','Tarjeta','Cheque']);
            $table->string('referencia_pago');
            $table->enum('estado',['Pendiente','Confirmado','Fallido']);
            $table->date('fecha_cancelado')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
