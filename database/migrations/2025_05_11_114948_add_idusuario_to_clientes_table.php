<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdusuarioToClientesTable extends Migration
{
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('idusuario')->nullable()->after('id');
            $table->foreign('idusuario')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['idusuario']);
            $table->dropColumn('idusuario');
        });
    }
}
