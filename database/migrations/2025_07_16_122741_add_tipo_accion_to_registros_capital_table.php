<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registros_capital', function (Blueprint $table) {
            $table->string('tipo_accion')->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('registros_capital', function (Blueprint $table) {
            $table->dropColumn('tipo_accion');
        });
    }
};
