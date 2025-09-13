<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            // Monto abonado (compatibilidad con instalaciones antiguas)
            if (!Schema::hasColumn('abonos', 'monto_abonado')) {
                $table->decimal('monto_abonado', 15, 2)->nullable()->after('monto');
            }

            // Usuario que registró el abono (opcional)
            if (!Schema::hasColumn('abonos', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('referencia');

                // Si tienes tabla users, puedes dejar la FK; si no, comenta estas líneas
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            if (Schema::hasColumn('abonos', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('abonos', 'monto_abonado')) {
                $table->dropColumn('monto_abonado');
            }
        });
    }
};
