<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer un utilisateur de test
        User::factory()->create([
            'name' => 'Ibrahim',
            'email' => 'ik@example.com',
            'password' => bcrypt('password'),
        ]);

        // Créer 9 utilisateurs avec des données fake
        User::factory(10)->create();
    }
}
