<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldDataSeeder::class,        // Dati geografici globali (paesi, regioni, province, città)
            JobDataSeeder::class,           // Dati mansioni (categorie, livelli, ruoli, settori, titoli, unità)
            RoleAndPermissionSeeder::class, // Ruoli e permessi utenti
            CourseSeeder::class,            // Corsi di esempio
            TestUserSeeder::class,          // Utenti di test
        ]);
    }
}
