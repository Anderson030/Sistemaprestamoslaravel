<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->call([RoleSeeder::class]);
        $this->call(PermisosPrestamistasSeeder::class);


        User::create([
            'name'=>'Freddy Hilari',
            'email'=>'admin@admin.com',
            'password'=>Hash::make('12345678')
        ])->assignRole('ADMINISTRADOR');

    }
}
