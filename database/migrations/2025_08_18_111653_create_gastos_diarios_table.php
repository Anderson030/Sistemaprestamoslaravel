<?php
// database/migrations/XXXX_XX_XX_create_gastos_diarios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('gastos_diarios', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->index();
            $table->decimal('monto', 15, 2)->default(0);
            $table->string('descripcion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('gastos_diarios');
    }
};
