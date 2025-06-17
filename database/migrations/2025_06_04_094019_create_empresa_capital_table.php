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
    Schema::create('empresa_capital', function (Blueprint $table) {
        $table->id();
        $table->decimal('capital_total', 15, 2)->default(0);       // Capital ingresado por la empresa
        $table->decimal('capital_disponible', 15, 2)->default(0);  // Lo que queda tras asignaciones a prestamistas
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_capital');
    }
};
