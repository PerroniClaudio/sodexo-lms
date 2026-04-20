<?php

namespace Database\Seeders;

use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Database\Seeder;

class ItalianRegionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Popola la tabella world_divisions con le regioni italiane.
     * Il package khsing/world non include le divisions italiane,
     * quindi le creiamo manualmente.
     */
    public function run(): void
    {
        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->error('Italia non trovata in world_countries.');

            return;
        }

        // Regioni italiane (20 regioni)
        $regions = [
            ['code' => 'ABR', 'name' => 'Abruzzo', 'full_name' => 'Regione Abruzzo'],
            ['code' => 'BAS', 'name' => 'Basilicata', 'full_name' => 'Regione Basilicata'],
            ['code' => 'CAL', 'name' => 'Calabria', 'full_name' => 'Regione Calabria'],
            ['code' => 'CAM', 'name' => 'Campania', 'full_name' => 'Regione Campania'],
            ['code' => 'EMR', 'name' => 'Emilia-Romagna', 'full_name' => 'Regione Emilia-Romagna'],
            ['code' => 'FVG', 'name' => 'Friuli-Venezia Giulia', 'full_name' => 'Regione Autonoma Friuli-Venezia Giulia'],
            ['code' => 'LAZ', 'name' => 'Lazio', 'full_name' => 'Regione Lazio'],
            ['code' => 'LIG', 'name' => 'Liguria', 'full_name' => 'Regione Liguria'],
            ['code' => 'LOM', 'name' => 'Lombardia', 'full_name' => 'Regione Lombardia'],
            ['code' => 'MAR', 'name' => 'Marche', 'full_name' => 'Regione Marche'],
            ['code' => 'MOL', 'name' => 'Molise', 'full_name' => 'Regione Molise'],
            ['code' => 'PIE', 'name' => 'Piemonte', 'full_name' => 'Regione Piemonte'],
            ['code' => 'PUG', 'name' => 'Puglia', 'full_name' => 'Regione Puglia'],
            ['code' => 'SAR', 'name' => 'Sardegna', 'full_name' => 'Regione Autonoma della Sardegna'],
            ['code' => 'SIC', 'name' => 'Sicilia', 'full_name' => 'Regione Siciliana'],
            ['code' => 'TOS', 'name' => 'Toscana', 'full_name' => 'Regione Toscana'],
            ['code' => 'TAA', 'name' => 'Trentino-Alto Adige', 'full_name' => 'Regione Autonoma Trentino-Alto Adige/Südtirol'],
            ['code' => 'UMB', 'name' => 'Umbria', 'full_name' => 'Regione Umbria'],
            ['code' => 'VDA', 'name' => "Valle d'Aosta", 'full_name' => "Regione Autonoma Valle d'Aosta/Vallée d'Aoste"],
            ['code' => 'VEN', 'name' => 'Veneto', 'full_name' => 'Regione del Veneto'],
        ];

        $created = 0;

        foreach ($regions as $region) {
            WorldDivision::updateOrCreate(
                [
                    'country_id' => $italy->id,
                    'code' => $region['code'],
                ],
                [
                    'name' => $region['name'],
                    'full_name' => $region['full_name'],
                    'has_city' => 1,
                ]
            );

            $created++;
        }

        $this->command->info("✓ Regioni italiane create/aggiornate: {$created}");
    }
}
