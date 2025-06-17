<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('movimientos_capital_prestamistas', function (Blueprint $table) {
            $table->id();

            // Prestamista que hizo el préstamo
            $table->unsignedBigInteger('user_id');

            // Cantidad prestada
            $table->decimal('monto', 15, 2);

            // Descripción opcional del préstamo
            $table->string('descripcion')->nullable();

            // Fecha del préstamo (fecha automática)
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('movimientos_capital_prestamistas');
    }
};
