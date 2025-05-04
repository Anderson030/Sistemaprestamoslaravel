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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

<<<<<<< HEAD
            $table->string('nro_documento', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->date('fecha_nacimiento');
            $table->string('genero');
            $table->string('email', 100);
            $table->string('celular', 20);
            $table->string('ref_celular', 20);
            $table->string('direccion')->nullable(); // <- NUEVO CAMPO

            // Si también quieres agregar referencias de una vez (opcional)
            $table->string('nombre_referencia1')->nullable();
            $table->string('telefono_referencia1')->nullable();
            $table->string('nombre_referencia2')->nullable();
            $table->string('telefono_referencia2')->nullable();
=======
            $table->string('nro_documento',20)->unique();
            $table->string('nombres',100);
            $table->string('apellidos',100);
            $table->date('fecha_nacimiento');
            $table->string('genero');
            $table->string('email',100);
            $table->string('celular',20);
            $table->string('ref_celular');
>>>>>>> 88f82b23b70cfaeeb8a0002d12a9c1d2ac43ee5c

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
