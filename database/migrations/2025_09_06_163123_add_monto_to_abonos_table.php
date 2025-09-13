<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            // Agrega el campo 'monto' si no existe
            if (!Schema::hasColumn('abonos', 'monto')) {
                // 12,2 para montos en COP; ajusta si usas otro tamaño
                $table->decimal('monto', 12, 2)->default(0)->after('nro_cuota');
            }
        });
    }

    public function down(): void
    {
        Schema::table('abonos', function (Blueprint $table) {
            if (Schema::hasColumn('abonos', 'monto')) {
                $table->dropColumn('monto');
            }
        });
    }
};
