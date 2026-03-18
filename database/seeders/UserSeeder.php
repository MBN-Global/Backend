<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'name' => 'Admin CampusHub',
            'email' => 'admin@campushub.fr',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Update profile info
        $admin->info->update([
            'bio' => 'Administrateur de la plateforme CampusHub',
            'profile_completion' => 50,
        ]);

        // Étudiant
        $student = User::create([
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@campushub.fr',
            'password' => Hash::make('password'),
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        $student->info->update([
            'program' => 'Master Informatique',
            'year' => 2,
            'campus' => 'Paris',
            'skills' => ['PHP', 'Laravel', 'JavaScript', 'React'],
            'profile_completion' => 70,
        ]);

        // Alumni
        $alumni = User::create([
            'name' => 'Marie Martin',
            'email' => 'marie.martin@campushub.fr',
            'password' => Hash::make('password'),
            'role' => 'alumni',
            'email_verified_at' => now(),
        ]);

        $alumni->info->update([
            'program' => 'Master Informatique',
            'graduation_year' => 2022,
            'campus' => 'Lyon',
            'skills' => ['Python', 'Django', 'Machine Learning'],
            'linkedin_url' => 'https://linkedin.com/in/marie-martin',
            'profile_completion' => 85,
        ]);
         $bde = User::create([
            'name' => 'Belem Gloire',
            'email' => 'belem@campushub.fr',
            'password' => Hash::make('password'),
            'role' => 'bde_member',
            'email_verified_at' => now(),
        ]);

        $bde->info->update([
            'program' => 'Master Informatique',
            'graduation_year' => 2022,
            'campus' => 'Lyon',
            'skills' => ['Python', 'Django', 'Machine Learning'],
            'linkedin_url' => 'https://linkedin.com/in/marie-martin',
            'profile_completion' => 85,
        ]);
    }
}