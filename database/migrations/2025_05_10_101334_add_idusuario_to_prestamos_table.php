<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->unsignedBigInteger('idusuario')->nullable()->after('cliente_id');
            $table->foreign('idusuario')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropForeign(['idusuario']);
            $table->dropColumn('idusuario');
        });
    }
};
