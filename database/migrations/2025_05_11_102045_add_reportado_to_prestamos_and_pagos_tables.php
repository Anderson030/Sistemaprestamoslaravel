<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->timestamp('reportado')->nullable()->after('fecha_inicio');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->timestamp('reportado')->nullable()->after('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn('reportado');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('reportado');
        });
    }
};
