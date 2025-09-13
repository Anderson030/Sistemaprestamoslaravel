<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla para auditar y registrar cada retanqueo:
     * - Enlaza préstamo origen (el que se liquida) y préstamo nuevo.
     * - Guarda cuánto se debía, cuánto se prestó de nuevo, interés, total nuevo,
     *   cuánto se abonó al anterior y cuánto se entregó en efectivo al cliente.
     */
    public function up(): void
    {
        Schema::create('retanqueos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('prestamo_origen_id')
                ->constrained('prestamos')
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // evita borrar si hay retanqueo ligado

            $table->foreignId('prestamo_nuevo_id')
                ->constrained('prestamos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Datos financieros del retanqueo
            $table->decimal('saldo_anterior', 12, 2);          // saldo del préstamo viejo al momento del retanqueo
            $table->decimal('principal_nuevo', 12, 2);         // capital solicitado en el retanqueo
            $table->decimal('tasa_interes', 5, 2);             // ej: 20.00
            $table->decimal('total_nuevo', 12, 2);             // total a pagar del nuevo préstamo (con interés)
            $table->decimal('abonado_a_anterior', 12, 2);      // cuánto del principal nuevo se usó para liquidar el anterior
            $table->decimal('entregado_en_efectivo', 12, 2);   // lo que efectivamente recibió el cliente

            // Parametrización del nuevo préstamo
            $table->string('modalidad', 20);                   // Diario / Semanal / Quincenal
            $table->unsignedSmallInteger('nro_cuotas');

            // Observaciones libres
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['cliente_id', 'prestamo_origen_id']);
            $table->index(['prestamo_nuevo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retanqueos');
    }
};
