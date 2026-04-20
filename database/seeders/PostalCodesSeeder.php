<?php

namespace Database\Seeders;

use App\Models\PostalCode;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PostalCodesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Popola postal_codes con i CAP italiani dal CSV gi_comuni_cap.csv
     */
    public function run(): void
    {
        $this->command->info('📮 Inizio importazione CAP italiani...');

        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->error('Italia non trovata in world_countries.');

            return;
        }

        // Percorso del file CSV
        $csvPath = database_path('data/gi_comuni_cap.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("File CSV non trovato: {$csvPath}");

            return;
        }

        // Elimina tutti i CAP italiani esistenti
        $this->command->info('  🗑️  Rimozione CAP esistenti...');
        PostalCode::whereHas('city', function ($query) use ($italy) {
            $query->where('country_id', $italy->id);
        })->delete();

        // Crea mappa codici Belfiore -> city_id
        $this->command->info('  📊 Creazione mappa città italiane...');
        $cityMap = WorldCity::where('country_id', $italy->id)
            ->whereNotNull('code')
            ->pluck('id', 'code')
            ->toArray();

        $this->command->info('  ✓ Trovate '.count($cityMap).' città italiane con codice Belfiore');

        // Leggi il CSV
        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            $this->command->error('Impossibile aprire il file CSV');

            return;
        }

        // Salta l'intestazione
        fgetcsv($handle, 0, ';');

        $postalCodesToInsert = [];
        $righeProcessate = 0;
        $righeScartate = 0;
        $righeConCitta = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $righeProcessate++;

            // Converti da ISO-8859-1 a UTF-8 se necessario
            $row = array_map(function ($value) {
                if (! mb_check_encoding($value, 'UTF-8')) {
                    return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
                }

                return $value;
            }, $row);

            // Estrai dati: codice_istat, codice_belfiore, cap, denominazione_ita, denominazione_ita_altra
            $codiceBelfiore = trim($row[1] ?? '');
            $cap = trim($row[2] ?? '');

            // Validazione
            if (empty($cap) || empty($codiceBelfiore)) {
                $righeScartate++;

                continue;
            }

            // Cerca città tramite codice Belfiore
            $cityId = $cityMap[$codiceBelfiore] ?? null;

            if (! $cityId) {
                $righeScartate++;

                continue;
            }

            $righeConCitta++;

            $postalCodesToInsert[] = [
                'city_id' => $cityId,
                'postal_code' => $cap,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Inserisci in batch
            if (count($postalCodesToInsert) >= 500) {
                DB::table('postal_codes')->insert($postalCodesToInsert);
                $postalCodesToInsert = [];
            }
        }

        // Inserisci i rimanenti
        if (count($postalCodesToInsert) > 0) {
            DB::table('postal_codes')->insert($postalCodesToInsert);
        }

        fclose($handle);

        // Report
        $this->command->info('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info("  ✓ Righe processate: {$righeProcessate}");
        $this->command->info("  ✓ CAP importati: {$righeConCitta}");
        $this->command->info("  ✗ Righe scartate: {$righeScartate}");

        $totalInserted = PostalCode::whereHas('city', function ($query) use ($italy) {
            $query->where('country_id', $italy->id);
        })->count();

        $this->command->info("\n  🎉 Totale CAP italiani in database: {$totalInserted}");
    }
}
