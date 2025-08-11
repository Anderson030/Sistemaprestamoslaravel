<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('empresa_capital', function (Blueprint $table) {
            $table->bigInteger('capital_anterior')->default(0)->after('capital_disponible');
        });
    }

    public function down()
    {
        Schema::table('empresa_capital', function (Blueprint $table) {
            $table->dropColumn('capital_anterior');
        });
    }
};
