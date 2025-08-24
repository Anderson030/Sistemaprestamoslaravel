<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagoparcialauditoria', function (Blueprint $table) {
            $table->id();

            // Relaciones opcionales
            $table->foreignId('prestamo_id')->nullable()
                  ->constrained('prestamos')->nullOnDelete();

            $table->foreignId('cliente_id')->nullable()
                  ->constrained('clientes')->nullOnDelete();

            $table->foreignId('idusuario')->nullable()
                  ->constrained('users')->nullOnDelete(); // quién registró

            // Datos del pago parcial del día
            $table->date('fecha')->index();
            $table->decimal('monto', 15, 2);
            $table->string('metodo', 100)->nullable();       // efectivo, transferencia...
            $table->string('descripcion', 255)->nullable();  // nota libre

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('pagoparcialauditoria');
    }
};
