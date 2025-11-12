<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            // Si estás en MySQL/MariaDB 10.2+ puedes usar columna GENERADA (STORED)
            // que siempre refleje el valor DATE(COALESCE(fecha_cancelado, fecha_pago)).
            // Nota: ajusta los nombres de columnas si difieren en tu esquema.
            $table->date('fecha_contable')
                  ->storedAs("DATE(COALESCE(`fecha_cancelado`, `fecha_pago`))")
                  ->after('fecha_cancelado');

            // Índice para acelerar filtros por rango de fechas en Auditorías
            $table->index('fecha_contable', 'pagos_fecha_contable_idx');
        });

        // Si tu motor NO soporta columnas generadas, descomenta este bloque
        // y cambia arriba por una columna normal sin ->storedAs(...).
        //
        // DB::statement("
        //     UPDATE pagos
        //     SET fecha_contable = COALESCE(DATE(fecha_cancelado), fecha_pago)
        // ");

        // IMPORTANTE:
        // Si 'fecha_cancelado' es DATETIME en UTC y quieres horaria Bogotá
        // y tu MySQL tiene tablas de zona horaria cargadas, usa:
        //
        // DB::statement("
        //     UPDATE pagos
        //     SET fecha_contable = COALESCE(DATE(CONVERT_TZ(fecha_cancelado,'+00:00','-05:00')), fecha_pago)
        // ");
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex('pagos_fecha_contable_idx');
            $table->dropColumn('fecha_contable');
        });
    }
};
