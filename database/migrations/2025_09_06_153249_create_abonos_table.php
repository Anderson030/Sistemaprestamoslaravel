<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: abonos
     * Registra pagos parciales por cuota de un préstamo.
     */
    public function up(): void
    {
        Schema::create('abonos', function (Blueprint $table) {
            $table->id();

            // Relación con préstamo
            $table->foreignId('prestamo_id')
                  ->constrained('prestamos')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            // Identifica a qué cuota se abona (1..n)
            $table->unsignedInteger('nro_cuota');

            // Monto del abono parcial
            $table->decimal('monto_abonado', 12, 2);

            // Metadatos del abono
            $table->date('fecha_pago')->nullable();
            $table->string('estado', 20)->default('Confirmado'); // Confirmado / Pendiente / Fallido (si quisieras)
            $table->string('referencia', 255)->nullable();

            $table->timestamps();

            // Índices útiles
            $table->index(['prestamo_id', 'nro_cuota']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos');
    }
};
