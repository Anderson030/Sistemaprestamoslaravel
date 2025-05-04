<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre_referencia1')->nullable()->after('ref_celular');
            $table->string('telefono_referencia1')->nullable()->after('nombre_referencia1');
            $table->string('nombre_referencia2')->nullable()->after('telefono_referencia1');
            $table->string('telefono_referencia2')->nullable()->after('nombre_referencia2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_referencia1',
                'telefono_referencia1',
                'nombre_referencia2',
                'telefono_referencia2',
            ]);
        });
    }
};
