<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * WorldDataSeeder - Seeder principale per tutti i dati geografici
 *
 * Ordine di esecuzione e dipendenze:
 * 
 * 1. WorldTablesSeeder (package khsing/world)
 *    - Popola: continenti, paesi, divisioni generiche, città principali mondiali
 *    - Output: ~250 paesi, ~5000 divisioni, ~145000 città
 * 
 * 2. ItalianRegionsSeeder
 *    - Dipende da: WorldCountry (Italia deve esistere)
 *    - Aggiunge/aggiorna: 20 regioni italiane in world_divisions
 * 
 * 3. ItalianProvincesSeeder
 *    - Dipende da: WorldCountry, ItalianRegionsSeeder
 *    - Crea: 107 province italiane (tabella custom provinces)
 * 
 * 4. ItalianCitiesSeeder
 *    - Dipende da: WorldCountry, ItalianRegionsSeeder, ItalianProvincesSeeder
 *    - Aggiunge: ~7900 comuni italiani da CSV ISTAT in world_cities
 *    - Collega: ogni comune alla sua provincia tramite province_id
 *    - CSV richiesto: database/data/Elenco-comuni-italiani.csv
 * 
 * 5. ItalianLocalizationSeeder
 *    - Aggiunge traduzioni italiane per paesi europei e città italiane
 * 
 * 6. PostalCodesSeeder
 *    - Dipende da: ItalianCitiesSeeder (città devono avere codice Belfiore)
 *    - Popola: ~8400 CAP italiani in postal_codes
 *    - Collega: CAP alle città tramite codice Belfiore
 *    - CSV richiesto: database/data/gi_comuni_cap.csv
 * 
 * IMPORTANTE: Non modificare l'ordine di esecuzione!
 */
class WorldDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Questo seeder utilizza il package khsing/world per popolare
     * le tabelle world_* standard (continenti, paesi, divisioni, città)
     * e poi aggiunge dati italiani specifici (province e città).
     */
    public function run(): void
    {
        $this->command->info('📍 Inizializzazione dati geografici globali (khsing/world)...');

        // Carica tutti i seeders del package khsing/world dalla cartella vendor
        $vendorSeedsPath = base_path('vendor/khsing/world/database/seeds');
        $worldSeeders = [
            'WorldContinentsTableSeeder.php',
            'WorldContinentsLocaleTableSeeder.php',
            'WorldCountriesTableSeeder.php',
            'WorldCountriesLocaleTableSeeder.php',
            'WorldDivisionsTableSeeder.php',
            'WorldDivisionsLocaleTableSeeder.php',
            'WorldCitiesTableSeeder.php',
            'WorldCitiesLocaleTableSeeder.php',
            'WorldTablesSeeder.php',
        ];

        foreach ($worldSeeders as $seeder) {
            $file = $vendorSeedsPath.'/'.$seeder;
            if (file_exists($file)) {
                require_once $file;
            }
        }

        // Chiama il seeder principale del package
        $this->call(\WorldTablesSeeder::class);

        $this->command->info('✓ Dati geografici globali caricati');

        // Seed regioni italiane (world_divisions) - il package khsing non le include
        $this->command->info('📍 Popolamento regioni italiane...');
        $this->call(ItalianRegionsSeeder::class);

        // Seed province italiane (tabella custom)
        $this->command->info('📍 Popolamento province italiane...');
        $this->call(ItalianProvincesSeeder::class);

        // Seed città italiane con collegamento alle province
        $this->command->info('📍 Popolamento città italiane con collegamento province...');
        $this->call(ItalianCitiesSeeder::class);

        // Seed localizzazione italiana (paesi europei, regioni e città)
        $this->command->info('📍 Aggiunta localizzazione italiana...');
        $this->call(ItalianLocalizationSeeder::class);

        // Seed codici postali italiani (CAP)
        $this->command->info('📮 Popolamento codici postali italiani (CAP)...');
        $this->call(PostalCodesSeeder::class);

        $this->command->info('✅ Tutti i dati geografici sono stati popolati correttamente');
    }
}
