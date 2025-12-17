<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Crear rol admin si no existe
        $rolAdmin = Rol::firstOrCreate(['nombre' => 'admin']);

        // Usuario admin
        User::create([
            'primer_nombre' => 'admin',
            'segundo_nombre' => null,
            'primer_apellido' => '',
            'segundo_apellido' => null,
            'telefono' => '0000000000',
            'correo' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
            'cuenta_bancaria' => '0000000000',
            'id_rol' => $rolAdmin->id,
            'profile_url' => null
        ]);

        // Usuario leo
        User::create([
            'primer_nombre' => 'leo',
            'segundo_nombre' => null,
            'primer_apellido' => '',
            'segundo_apellido' => null,
            'telefono' => '0000000001',
            'correo' => 'leo@admin.com',
            'password' => bcrypt('Leo123'),
            'cuenta_bancaria' => '0000000001',
            'id_rol' => $rolAdmin->id,
            'profile_url' => null
        ]);
    }
}
