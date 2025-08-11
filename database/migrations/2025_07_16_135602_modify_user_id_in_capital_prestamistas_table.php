<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('capital_prestamistas', function (Blueprint $table) {
            // 1. Eliminar la restricción de clave foránea
            $table->dropForeign(['user_id']);

            // 2. ✅ No eliminar índice único si no existe (ya comentado)
            // $table->dropUnique('capital_prestamistas_user_id_unique');

            // 3. Volver a agregar la clave foránea SIN UNIQUE
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('capital_prestamistas', function (Blueprint $table) {
            // Eliminar la clave foránea agregada en "up"
            $table->dropForeign(['user_id']);

            // ⚠️ Asegúrate de que no haya ya un índice antes de intentar borrarlo
            // También ten cuidado con `dropIndex` si el índice no fue creado así

            // Para restaurar el UNIQUE y la clave foránea
            $table->unique('user_id'); // vuelve a poner el UNIQUE
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
