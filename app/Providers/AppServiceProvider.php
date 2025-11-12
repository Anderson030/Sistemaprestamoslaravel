<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ...
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Asegura la TZ de PHP (influye en Carbon::now(), logs, etc.)
        date_default_timezone_set(config('app.timezone', 'America/Bogota'));

        // Fuerza la TZ de la sesión de la BD (muy importante en hosting compartido)
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                // América/Bogotá no usa DST, por eso el offset fijo
                DB::statement("SET time_zone = '-05:00'");
            } elseif ($driver === 'pgsql') {
                DB::statement("SET TIME ZONE 'America/Bogota'");
            }
        } catch (\Throwable $e) {
            \Log::warning('No pude fijar la zona horaria en la DB: '.$e->getMessage());
        }
    }
}
