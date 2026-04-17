<?php

namespace Database\Seeders;

use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\JobUnit;
use Illuminate\Database\Seeder;

class JobDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Job Categories
        $categories = [
            ['name' => 'Dirigente', 'code' => 'DIR'],
            ['name' => 'Quadro', 'code' => 'QUA'],
            ['name' => 'Impiegato', 'code' => 'IMP'],
            ['name' => 'Operaio', 'code' => 'OPE'],
        ];

        foreach ($categories as $category) {
            JobCategory::create($category);
        }

        // Job Levels
        $levels = [
            ['name' => '1° Livello', 'code' => 'L1'],
            ['name' => '2° Livello', 'code' => 'L2'],
            ['name' => '3° Livello', 'code' => 'L3'],
            ['name' => '4° Livello', 'code' => 'L4'],
            ['name' => '5° Livello', 'code' => 'L5'],
            ['name' => 'B1', 'code' => 'B1'],
            ['name' => 'B2', 'code' => 'B2'],
        ];

        foreach ($levels as $level) {
            JobLevel::create($level);
        }

        // Job Titles (Mansioni)
        $titles = [
            ['name' => 'Cuoco', 'code' => 'CUO'],
            ['name' => 'Magazziniere', 'code' => 'MAG'],
            ['name' => 'Addetto alla Sicurezza', 'code' => 'SIC'],
            ['name' => 'Operatore di Produzione', 'code' => 'OPP'],
            ['name' => 'Responsabile Qualità', 'code' => 'RQU'],
            ['name' => 'Addetto alle Pulizie', 'code' => 'PUL'],
        ];

        foreach ($titles as $title) {
            JobTitle::create($title);
        }

        // Job Roles
        $roles = [
            ['name' => 'Lavoratore', 'code' => 'LAV'],
            ['name' => 'Preposto', 'code' => 'PRE'],
            ['name' => 'Dirigente', 'code' => 'DIR'],
            ['name' => 'RSPP', 'code' => 'RSPP'],
            ['name' => 'RLS', 'code' => 'RLS'],
        ];

        foreach ($roles as $role) {
            JobRole::create($role);
        }

        // Job Sectors
        $sectors = [
            ['name' => 'Ristorazione', 'code' => 'RIS'],
            ['name' => 'Meccanica', 'code' => 'MEC'],
            ['name' => 'Edilizia', 'code' => 'EDI'],
            ['name' => 'Sanità', 'code' => 'SAN'],
            ['name' => 'Logistica', 'code' => 'LOG'],
            ['name' => 'Pulizie e Servizi', 'code' => 'PUL'],
        ];

        foreach ($sectors as $sector) {
            JobSector::create($sector);
        }

        // Job Units (Unità Lavorative)
        $units = [
            [
                'name' => 'Sede Milano Centro',
                'code' => 'MI-001',
                'country' => 'IT',
                'region' => 'Lombardia',
                'province' => 'MI',
                'city' => 'Milano',
                'address' => 'Via Roma 123',
                'postal_code' => '20100',
            ],
            [
                'name' => 'Sede Roma EUR',
                'code' => 'RM-001',
                'country' => 'IT',
                'region' => 'Lazio',
                'province' => 'RM',
                'city' => 'Roma',
                'address' => 'Via Cristoforo Colombo 456',
                'postal_code' => '00144',
            ],
            [
                'name' => 'Stabilimento Torino',
                'code' => 'TO-001',
                'country' => 'IT',
                'region' => 'Piemonte',
                'province' => 'TO',
                'city' => 'Torino',
                'address' => 'Corso Francia 789',
                'postal_code' => '10141',
            ],
        ];

        foreach ($units as $unit) {
            JobUnit::create($unit);
        }
    }
}
