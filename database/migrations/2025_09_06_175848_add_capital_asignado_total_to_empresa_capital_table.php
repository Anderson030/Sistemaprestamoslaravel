<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_capital', function (Blueprint $table) {
            if (!Schema::hasColumn('empresa_capital', 'capital_asignado_total')) {
                // saldo transitorio de cobros de asesores (no caja)
                $table->unsignedBigInteger('capital_asignado_total')
                      ->default(0)
                      ->after('capital_disponible');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresa_capital', function (Blueprint $table) {
            if (Schema::hasColumn('empresa_capital', 'capital_asignado_total')) {
                $table->dropColumn('capital_asignado_total');
            }
        });
    }
};
