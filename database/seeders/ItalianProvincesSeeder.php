<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Database\Seeder;

class ItalianProvincesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Popola la tabella provinces con tutte le province italiane,
     * collegandole a country_id (Italia) e region_id (regione di appartenenza).
     */
    public function run(): void
    {
        // Get Italy country ID
        $italy = WorldCountry::where('code', 'it')->first();

        if (! $italy) {
            $this->command->error('Italia non trovata in world_countries. Eseguire prima WorldDataSeeder.');

            return;
        }

        // Get regions map (code => id)
        $regions = WorldDivision::where('country_id', $italy->id)
            ->get()
            ->keyBy('code');

        // Province italiane complete (107 province)
        $provinces = [
            // Abruzzo
            ['code' => 'AQ', 'name' => "L'Aquila", 'region_code' => 'ABR'],
            ['code' => 'CH', 'name' => 'Chieti', 'region_code' => 'ABR'],
            ['code' => 'PE', 'name' => 'Pescara', 'region_code' => 'ABR'],
            ['code' => 'TE', 'name' => 'Teramo', 'region_code' => 'ABR'],

            // Basilicata
            ['code' => 'MT', 'name' => 'Matera', 'region_code' => 'BAS'],
            ['code' => 'PZ', 'name' => 'Potenza', 'region_code' => 'BAS'],

            // Calabria
            ['code' => 'CZ', 'name' => 'Catanzaro', 'region_code' => 'CAL'],
            ['code' => 'CS', 'name' => 'Cosenza', 'region_code' => 'CAL'],
            ['code' => 'KR', 'name' => 'Crotone', 'region_code' => 'CAL'],
            ['code' => 'RC', 'name' => 'Reggio Calabria', 'region_code' => 'CAL'],
            ['code' => 'VV', 'name' => 'Vibo Valentia', 'region_code' => 'CAL'],

            // Campania
            ['code' => 'AV', 'name' => 'Avellino', 'region_code' => 'CAM'],
            ['code' => 'BN', 'name' => 'Benevento', 'region_code' => 'CAM'],
            ['code' => 'CE', 'name' => 'Caserta', 'region_code' => 'CAM'],
            ['code' => 'NA', 'name' => 'Napoli', 'region_code' => 'CAM'],
            ['code' => 'SA', 'name' => 'Salerno', 'region_code' => 'CAM'],

            // Emilia-Romagna
            ['code' => 'BO', 'name' => 'Bologna', 'region_code' => 'EMR'],
            ['code' => 'FE', 'name' => 'Ferrara', 'region_code' => 'EMR'],
            ['code' => 'FC', 'name' => 'Forlì-Cesena', 'region_code' => 'EMR'],
            ['code' => 'MO', 'name' => 'Modena', 'region_code' => 'EMR'],
            ['code' => 'PR', 'name' => 'Parma', 'region_code' => 'EMR'],
            ['code' => 'PC', 'name' => 'Piacenza', 'region_code' => 'EMR'],
            ['code' => 'RA', 'name' => 'Ravenna', 'region_code' => 'EMR'],
            ['code' => 'RE', 'name' => 'Reggio Emilia', 'region_code' => 'EMR'],
            ['code' => 'RN', 'name' => 'Rimini', 'region_code' => 'EMR'],

            // Friuli-Venezia Giulia
            ['code' => 'GO', 'name' => 'Gorizia', 'region_code' => 'FVG'],
            ['code' => 'PN', 'name' => 'Pordenone', 'region_code' => 'FVG'],
            ['code' => 'TS', 'name' => 'Trieste', 'region_code' => 'FVG'],
            ['code' => 'UD', 'name' => 'Udine', 'region_code' => 'FVG'],

            // Lazio
            ['code' => 'FR', 'name' => 'Frosinone', 'region_code' => 'LAZ'],
            ['code' => 'LT', 'name' => 'Latina', 'region_code' => 'LAZ'],
            ['code' => 'RI', 'name' => 'Rieti', 'region_code' => 'LAZ'],
            ['code' => 'RM', 'name' => 'Roma', 'region_code' => 'LAZ'],
            ['code' => 'VT', 'name' => 'Viterbo', 'region_code' => 'LAZ'],

            // Liguria
            ['code' => 'GE', 'name' => 'Genova', 'region_code' => 'LIG'],
            ['code' => 'IM', 'name' => 'Imperia', 'region_code' => 'LIG'],
            ['code' => 'SP', 'name' => 'La Spezia', 'region_code' => 'LIG'],
            ['code' => 'SV', 'name' => 'Savona', 'region_code' => 'LIG'],

            // Lombardia
            ['code' => 'BG', 'name' => 'Bergamo', 'region_code' => 'LOM'],
            ['code' => 'BS', 'name' => 'Brescia', 'region_code' => 'LOM'],
            ['code' => 'CO', 'name' => 'Como', 'region_code' => 'LOM'],
            ['code' => 'CR', 'name' => 'Cremona', 'region_code' => 'LOM'],
            ['code' => 'LC', 'name' => 'Lecco', 'region_code' => 'LOM'],
            ['code' => 'LO', 'name' => 'Lodi', 'region_code' => 'LOM'],
            ['code' => 'MN', 'name' => 'Mantova', 'region_code' => 'LOM'],
            ['code' => 'MI', 'name' => 'Milano', 'region_code' => 'LOM'],
            ['code' => 'MB', 'name' => 'Monza e della Brianza', 'region_code' => 'LOM'],
            ['code' => 'PV', 'name' => 'Pavia', 'region_code' => 'LOM'],
            ['code' => 'SO', 'name' => 'Sondrio', 'region_code' => 'LOM'],
            ['code' => 'VA', 'name' => 'Varese', 'region_code' => 'LOM'],

            // Marche
            ['code' => 'AN', 'name' => 'Ancona', 'region_code' => 'MAR'],
            ['code' => 'AP', 'name' => 'Ascoli Piceno', 'region_code' => 'MAR'],
            ['code' => 'FM', 'name' => 'Fermo', 'region_code' => 'MAR'],
            ['code' => 'MC', 'name' => 'Macerata', 'region_code' => 'MAR'],
            ['code' => 'PU', 'name' => 'Pesaro e Urbino', 'region_code' => 'MAR'],

            // Molise
            ['code' => 'CB', 'name' => 'Campobasso', 'region_code' => 'MOL'],
            ['code' => 'IS', 'name' => 'Isernia', 'region_code' => 'MOL'],

            // Piemonte
            ['code' => 'AL', 'name' => 'Alessandria', 'region_code' => 'PIE'],
            ['code' => 'AT', 'name' => 'Asti', 'region_code' => 'PIE'],
            ['code' => 'BI', 'name' => 'Biella', 'region_code' => 'PIE'],
            ['code' => 'CN', 'name' => 'Cuneo', 'region_code' => 'PIE'],
            ['code' => 'NO', 'name' => 'Novara', 'region_code' => 'PIE'],
            ['code' => 'TO', 'name' => 'Torino', 'region_code' => 'PIE'],
            ['code' => 'VB', 'name' => 'Verbano-Cusio-Ossola', 'region_code' => 'PIE'],
            ['code' => 'VC', 'name' => 'Vercelli', 'region_code' => 'PIE'],

            // Puglia
            ['code' => 'BA', 'name' => 'Bari', 'region_code' => 'PUG'],
            ['code' => 'BT', 'name' => 'Barletta-Andria-Trani', 'region_code' => 'PUG'],
            ['code' => 'BR', 'name' => 'Brindisi', 'region_code' => 'PUG'],
            ['code' => 'FG', 'name' => 'Foggia', 'region_code' => 'PUG'],
            ['code' => 'LE', 'name' => 'Lecce', 'region_code' => 'PUG'],
            ['code' => 'TA', 'name' => 'Taranto', 'region_code' => 'PUG'],

            // Sardegna
            ['code' => 'CA', 'name' => 'Cagliari', 'region_code' => 'SAR'],
            ['code' => 'CI', 'name' => 'Carbonia-Iglesias', 'region_code' => 'SAR'],
            ['code' => 'VS', 'name' => 'Medio Campidano', 'region_code' => 'SAR'],
            ['code' => 'NU', 'name' => 'Nuoro', 'region_code' => 'SAR'],
            ['code' => 'OG', 'name' => 'Ogliastra', 'region_code' => 'SAR'],
            ['code' => 'OR', 'name' => 'Oristano', 'region_code' => 'SAR'],
            ['code' => 'OT', 'name' => 'Olbia-Tempio', 'region_code' => 'SAR'],
            ['code' => 'SS', 'name' => 'Sassari', 'region_code' => 'SAR'],

            // Sicilia
            ['code' => 'AG', 'name' => 'Agrigento', 'region_code' => 'SIC'],
            ['code' => 'CL', 'name' => 'Caltanissetta', 'region_code' => 'SIC'],
            ['code' => 'CT', 'name' => 'Catania', 'region_code' => 'SIC'],
            ['code' => 'EN', 'name' => 'Enna', 'region_code' => 'SIC'],
            ['code' => 'ME', 'name' => 'Messina', 'region_code' => 'SIC'],
            ['code' => 'PA', 'name' => 'Palermo', 'region_code' => 'SIC'],
            ['code' => 'RG', 'name' => 'Ragusa', 'region_code' => 'SIC'],
            ['code' => 'SR', 'name' => 'Siracusa', 'region_code' => 'SIC'],
            ['code' => 'TP', 'name' => 'Trapani', 'region_code' => 'SIC'],

            // Toscana
            ['code' => 'AR', 'name' => 'Arezzo', 'region_code' => 'TOS'],
            ['code' => 'FI', 'name' => 'Firenze', 'region_code' => 'TOS'],
            ['code' => 'GR', 'name' => 'Grosseto', 'region_code' => 'TOS'],
            ['code' => 'LI', 'name' => 'Livorno', 'region_code' => 'TOS'],
            ['code' => 'LU', 'name' => 'Lucca', 'region_code' => 'TOS'],
            ['code' => 'MS', 'name' => 'Massa-Carrara', 'region_code' => 'TOS'],
            ['code' => 'PI', 'name' => 'Pisa', 'region_code' => 'TOS'],
            ['code' => 'PT', 'name' => 'Pistoia', 'region_code' => 'TOS'],
            ['code' => 'PO', 'name' => 'Prato', 'region_code' => 'TOS'],
            ['code' => 'SI', 'name' => 'Siena', 'region_code' => 'TOS'],

            // Trentino-Alto Adige
            ['code' => 'BZ', 'name' => 'Bolzano', 'region_code' => 'TAA'],
            ['code' => 'TN', 'name' => 'Trento', 'region_code' => 'TAA'],

            // Umbria
            ['code' => 'PG', 'name' => 'Perugia', 'region_code' => 'UMB'],
            ['code' => 'TR', 'name' => 'Terni', 'region_code' => 'UMB'],

            // Valle d'Aosta
            ['code' => 'AO', 'name' => "Valle d'Aosta/Vallée d'Aoste", 'region_code' => 'VDA'],

            // Veneto
            ['code' => 'BL', 'name' => 'Belluno', 'region_code' => 'VEN'],
            ['code' => 'PD', 'name' => 'Padova', 'region_code' => 'VEN'],
            ['code' => 'RO', 'name' => 'Rovigo', 'region_code' => 'VEN'],
            ['code' => 'TV', 'name' => 'Treviso', 'region_code' => 'VEN'],
            ['code' => 'VE', 'name' => 'Venezia', 'region_code' => 'VEN'],
            ['code' => 'VR', 'name' => 'Verona', 'region_code' => 'VEN'],
            ['code' => 'VI', 'name' => 'Vicenza', 'region_code' => 'VEN'],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($provinces as $province) {
            $region = $regions->get($province['region_code']);

            if (! $region) {
                $this->command->warn("Regione {$province['region_code']} non trovata per provincia {$province['name']}");
                $skipped++;

                continue;
            }

            Province::updateOrCreate(
                [
                    'country_id' => $italy->id,
                    'code' => $province['code'],
                ],
                [
                    'region_id' => $region->id,
                    'name' => $province['name'],
                ]
            );

            $created++;
        }

        $this->command->info("✓ Province create/aggiornate: {$created}");
        if ($skipped > 0) {
            $this->command->warn("⚠ Province saltate: {$skipped}");
        }
    }
}
