<?php

namespace Database\Seeders;

use App\Enums\InclusionType;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\JobUnit;
use App\Models\NaceAteco;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
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
            ['name' => 'Dirigente', 'description' => 'Personale dirigenziale'],
            ['name' => 'Quadro', 'description' => 'Quadri aziendali'],
            ['name' => 'Impiegato', 'description' => 'Personale impiegatizio'],
            ['name' => 'Operaio', 'description' => 'Personale operaio'],
        ];

        foreach ($categories as $category) {
            JobCategory::create($category);
        }

        // Job Levels
        $levels = [
            ['name' => '1° Livello', 'description' => 'Primo livello di inquadramento'],
            ['name' => '2° Livello', 'description' => 'Secondo livello di inquadramento'],
            ['name' => '3° Livello', 'description' => 'Terzo livello di inquadramento'],
            ['name' => '4° Livello', 'description' => 'Quarto livello di inquadramento'],
            ['name' => '5° Livello', 'description' => 'Quinto livello di inquadramento'],
            ['name' => 'B1', 'description' => 'Livello B1'],
            ['name' => 'B2', 'description' => 'Livello B2'],
        ];

        foreach ($levels as $level) {
            JobLevel::create($level);
        }

        // Job Titles (Mansioni)
        $titles = [
            ['name' => 'Cuoco', 'description' => 'Responsabile della preparazione dei pasti'],
            ['name' => 'Magazziniere', 'description' => 'Gestione magazzino e scorte'],
            ['name' => 'Addetto alla Sicurezza', 'description' => 'Responsabile sicurezza sul lavoro'],
            ['name' => 'Operatore di Produzione', 'description' => 'Operatore linea produttiva'],
            ['name' => 'Responsabile Qualità', 'description' => 'Controllo qualità prodotti'],
            ['name' => 'Addetto alle Pulizie', 'description' => 'Pulizia e sanificazione ambienti'],
        ];

        foreach ($titles as $title) {
            JobTitle::create($title);
        }

        // Job Roles
        $roles = [
            ['name' => 'Lavoratore', 'description' => 'Lavoratore dipendente'],
            ['name' => 'Preposto', 'description' => 'Preposto alla sicurezza'],
            ['name' => 'Dirigente', 'description' => 'Dirigente aziendale'],
            ['name' => 'RSPP', 'description' => 'Responsabile Servizio Prevenzione e Protezione'],
            ['name' => 'RLS', 'description' => 'Rappresentante Lavoratori per la Sicurezza'],
        ];

        foreach ($roles as $role) {
            JobRole::create($role);
        }

        // Job Sectors
        // Crea un settore per ogni sezione NACE/ATECO (lettere A-U)
        $sections = NaceAteco::where('hierarchy', 1)->orderBy('code')->get();

        if ($sections->isEmpty()) {
            $this->command->warn('Nessuna sezione NACE/ATECO trovata. I settori non saranno creati.');
        } else {
            foreach ($sections as $section) {
                // Crea il settore usando il titolo italiano della sezione
                $sector = JobSector::create([
                    'name' => $section->title_it,
                    'description' => $section->title_en,
                ]);

                // Associa il settore alla sezione ATECO con tipo inclusione SECTION
                $sector->naceAtecoCodes()->attach($section->code, [
                    'inclusion_type' => InclusionType::SECTION->value,
                ]);

                $this->command->info("✓ Creato settore '{$sector->name}' collegato alla sezione {$section->code}");
            }
        }

        // Job Units (Unità Lavorative)
        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->warn('Italia non trovata. Saltata creazione unità lavorative.');

            return;
        }

        $units = [
            [
                'name' => 'Sede Milano Centro',
                'province_code' => 'MI',
                'city_name' => 'Milano',
                'address' => 'Via Roma 123',
                'postal_code' => '20100',
            ],
            [
                'name' => 'Sede Roma EUR',
                'province_code' => 'RM',
                'city_name' => 'Roma',
                'address' => 'Via Cristoforo Colombo 456',
                'postal_code' => '00144',
            ],
            [
                'name' => 'Stabilimento Torino',
                'province_code' => 'TO',
                'city_name' => 'Torino',
                'address' => 'Corso Francia 789',
                'postal_code' => '10141',
            ],
        ];

        foreach ($units as $unitData) {
            // Find province
            $province = Province::where('code', $unitData['province_code'])
                ->where('country_id', $italy->id)
                ->first();

            if (! $province) {
                $this->command->warn("Provincia {$unitData['province_code']} non trovata per {$unitData['name']}");

                continue;
            }

            // Find city
            $city = WorldCity::where('name', $unitData['city_name'])
                ->where('country_id', $italy->id)
                ->where('province_id', $province->id)
                ->first();

            if (! $city) {
                $this->command->warn("Città {$unitData['city_name']} non trovata per {$unitData['name']}");

                continue;
            }

            JobUnit::create([
                'name' => $unitData['name'],
                'country_id' => $italy->id,
                'region_id' => $province->region_id,
                'province_id' => $province->id,
                'city_id' => $city->id,
                'address' => $unitData['address'],
                'postal_code' => $unitData['postal_code'],
            ]);
        }

        $this->command->info('✓ Unità lavorative create correttamente');
    }
}
