<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItalianCitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Popola world_cities con TUTTI i comuni italiani dal CSV ISTAT
     * e collega ciascun comune alla sua provincia tramite province_id.
     */
    public function run(): void
    {
        $this->command->info('📍 Inizio importazione comuni italiani dal CSV ISTAT...');

        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->error('Italia non trovata in world_countries.');

            return;
        }

        // Mappa delle province per sigla (es. MI => province_id)
        $provinceMap = Province::where('country_id', $italy->id)
            ->get()
            ->keyBy('code')
            ->map(fn ($p) => ['id' => $p->id, 'region_id' => $p->region_id]);

        // Percorso del file CSV ISTAT
        $csvPath = database_path('data/Elenco-comuni-italiani.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("File CSV non trovato: {$csvPath}");
            $this->command->warn('Scarica il file da: https://www.istat.it/storage/codici-unita-amministrative/Elenco-comuni-italiani.csv');

            return;
        }

        // Elimina tutte le città italiane esistenti per evitare duplicati
        $this->command->info('  🗑️  Rimozione città italiane esistenti...');
        WorldCity::where('country_id', $italy->id)->delete();

        // Leggi il CSV
        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            $this->command->error('Impossibile aprire il file CSV');

            return;
        }

        // Salta l'intestazione
        $header = fgetcsv($handle, 0, ';');

        // Trova gli indici delle colonne che ci servono
        $colonneRilevanti = [
            'nome' => array_search('Denominazione in italiano', $header),
            'sigla' => array_search('Sigla automobilistica', $header),
            'catastale' => array_search('Codice Catastale del comune', $header),
        ];

        $citiesToInsert = [];
        $righeProcessate = 0;
        $righeScartate = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $righeProcessate++;

            // Converti tutti i campi da ISO-8859-1 a UTF-8
            $row = array_map(function ($value) {
                return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }, $row);

            // Estrai i dati
            $nomeComune = trim($row[$colonneRilevanti['nome']] ?? '');
            $siglaProvincia = trim($row[$colonneRilevanti['sigla']] ?? '');
            $codiceCatastale = trim($row[$colonneRilevanti['catastale']] ?? '');

            // Salta righe vuote o incomplete
            if (empty($nomeComune) || empty($siglaProvincia)) {
                $righeScartate++;

                continue;
            }

            // Trova la provincia corrispondente
            if (! isset($provinceMap[$siglaProvincia])) {
                // Alcune sigle potrebbero non essere mappate (es. province soppresse o casi speciali)
                $righeScartate++;

                continue;
            }

            $provinciaInfo = $provinceMap[$siglaProvincia];

            $citiesToInsert[] = [
                'country_id' => $italy->id,
                'division_id' => $provinciaInfo['region_id'], // Regione
                'province_id' => $provinciaInfo['id'],         // Provincia
                'name' => $nomeComune,
                'full_name' => $nomeComune,
                'code' => $codiceCatastale ?: null,
            ];

            // Inserisci a batch ogni 500 comuni per ottimizzare
            if (count($citiesToInsert) >= 500) {
                DB::table('world_cities')->insert($citiesToInsert);
                $citiesToInsert = [];
            }
        }

        // Inserisci gli ultimi comuni rimasti
        if (! empty($citiesToInsert)) {
            DB::table('world_cities')->insert($citiesToInsert);
        }

        fclose($handle);

        $totaleInseriti = $righeProcessate - $righeScartate;

        $this->command->info("  ✓ Importati {$totaleInseriti} comuni italiani");
        $this->command->info("  ℹ️  Righe processate: {$righeProcessate}");
        $this->command->info("  ℹ️  Righe scartate: {$righeScartate}");

        // Aggiorna le localizzazioni italiane per i nuovi comuni
        $this->command->info('  📝 Aggiornamento localizzazioni...');
        $this->updateLocalizations($italy->id);

        $this->command->info('✅ Importazione comuni completata');
    }

    /**
     * Aggiorna le localizzazioni italiane per i comuni
     */
    private function updateLocalizations(int $countryId): void
    {
        $cities = WorldCity::where('country_id', $countryId)->get();

        $localeData = [];
        foreach ($cities as $city) {
            $localeData[] = [
                'city_id' => $city->id,
                'locale' => 'it',
                'name' => $city->name,
                'full_name' => $city->full_name ?? $city->name,
                'alias' => null,
            ];
        }

        // Inserisci a batch
        if (! empty($localeData)) {
            DB::table('world_cities_locale')
                ->where('locale', 'it')
                ->whereIn('city_id', $cities->pluck('id'))
                ->delete();

            $chunks = array_chunk($localeData, 1000);
            foreach ($chunks as $chunk) {
                DB::table('world_cities_locale')->insert($chunk);
            }

            $this->command->info('  ✓ Aggiunte '.count($localeData).' localizzazioni');
        }
    }
}
