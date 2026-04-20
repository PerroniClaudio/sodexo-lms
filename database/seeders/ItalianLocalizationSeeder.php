<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItalianLocalizationSeeder extends Seeder
{
    /**
     * Popola le tabelle _locale con traduzioni italiane per:
     * - Paesi europei (world_countries_locale)
     * - Regioni italiane (world_divisions_locale)
     * - Città principali (world_cities_locale)
     */
    public function run(): void
    {
        $this->command->info('📍 Aggiunta localizzazione italiana per paesi europei...');
        $this->seedEuropeanCountries();

        $this->command->info('📍 Aggiunta localizzazione italiana per regioni italiane...');
        $this->seedItalianRegions();

        $this->command->info('📍 Aggiunta localizzazione italiana per città principali...');
        $this->seedItalianCities();

        $this->command->info('✓ Localizzazione italiana completata');
    }

    /**
     * Traduzioni italiane dei paesi europei
     */
    private function seedEuropeanCountries(): void
    {
        // Mappa: codice paese => [nome italiano, nome completo italiano]
        $europeanCountries = [
            'it' => ['Italia', 'Repubblica Italiana'],
            'fr' => ['Francia', 'Repubblica Francese'],
            'de' => ['Germania', 'Repubblica Federale di Germania'],
            'es' => ['Spagna', 'Regno di Spagna'],
            'pt' => ['Portogallo', 'Repubblica Portoghese'],
            'gb' => ['Regno Unito', 'Regno Unito di Gran Bretagna e Irlanda del Nord'],
            'ie' => ['Irlanda', 'Repubblica d\'Irlanda'],
            'nl' => ['Paesi Bassi', 'Regno dei Paesi Bassi'],
            'be' => ['Belgio', 'Regno del Belgio'],
            'lu' => ['Lussemburgo', 'Granducato di Lussemburgo'],
            'ch' => ['Svizzera', 'Confederazione Svizzera'],
            'at' => ['Austria', 'Repubblica d\'Austria'],
            'gr' => ['Grecia', 'Repubblica Ellenica'],
            'pl' => ['Polonia', 'Repubblica di Polonia'],
            'cz' => ['Repubblica Ceca', 'Repubblica Ceca'],
            'sk' => ['Slovacchia', 'Repubblica Slovacca'],
            'hu' => ['Ungheria', 'Ungheria'],
            'ro' => ['Romania', 'Romania'],
            'bg' => ['Bulgaria', 'Repubblica di Bulgaria'],
            'hr' => ['Croazia', 'Repubblica di Croazia'],
            'si' => ['Slovenia', 'Repubblica di Slovenia'],
            'rs' => ['Serbia', 'Repubblica di Serbia'],
            'ba' => ['Bosnia ed Erzegovina', 'Bosnia ed Erzegovina'],
            'me' => ['Montenegro', 'Montenegro'],
            'mk' => ['Macedonia del Nord', 'Repubblica di Macedonia del Nord'],
            'al' => ['Albania', 'Repubblica d\'Albania'],
            'dk' => ['Danimarca', 'Regno di Danimarca'],
            'se' => ['Svezia', 'Regno di Svezia'],
            'no' => ['Norvegia', 'Regno di Norvegia'],
            'fi' => ['Finlandia', 'Repubblica di Finlandia'],
            'ee' => ['Estonia', 'Repubblica di Estonia'],
            'lv' => ['Lettonia', 'Repubblica di Lettonia'],
            'lt' => ['Lituania', 'Repubblica di Lituania'],
            'by' => ['Bielorussia', 'Repubblica di Bielorussia'],
            'ua' => ['Ucraina', 'Ucraina'],
            'md' => ['Moldavia', 'Repubblica di Moldavia'],
            'mt' => ['Malta', 'Repubblica di Malta'],
            'cy' => ['Cipro', 'Repubblica di Cipro'],
            'is' => ['Islanda', 'Repubblica d\'Islanda'],
        ];

        $localeData = [];

        foreach ($europeanCountries as $code => $names) {
            $country = DB::table('world_countries')->where('code', $code)->first();

            if ($country) {
                // Elimina eventuali traduzioni italiane esistenti per questo paese
                DB::table('world_countries_locale')
                    ->where('country_id', $country->id)
                    ->where('locale', 'it')
                    ->delete();

                $localeData[] = [
                    'country_id' => $country->id,
                    'locale' => 'it',
                    'name' => $names[0],
                    'full_name' => $names[1],
                    'alias' => null,
                    'abbr' => null,
                    'currency_name' => null,
                ];
            }
        }

        if (! empty($localeData)) {
            DB::table('world_countries_locale')->insert($localeData);
            $this->command->info('  ✓ Aggiunte '.count($localeData).' traduzioni di paesi');
        }
    }

    /**
     * Traduzioni italiane delle regioni italiane
     */
    private function seedItalianRegions(): void
    {
        $italy = DB::table('world_countries')->where('code', 'it')->first();

        if (! $italy) {
            $this->command->warn('  ⚠ Italia non trovata, skip regioni');

            return;
        }

        // Le regioni italiane hanno già il nome corretto in italiano nella tabella principale
        // Aggiungiamo comunque i record locale per consistenza
        $regions = DB::table('world_divisions')
            ->where('country_id', $italy->id)
            ->get();

        $localeData = [];

        foreach ($regions as $region) {
            // Elimina eventuali traduzioni italiane esistenti
            DB::table('world_divisions_locale')
                ->where('division_id', $region->id)
                ->where('locale', 'it')
                ->delete();

            $localeData[] = [
                'division_id' => $region->id,
                'locale' => 'it',
                'name' => $region->name,
                'full_name' => $region->full_name ?? $region->name,
                'alias' => null,
                'abbr' => null,
            ];
        }

        if (! empty($localeData)) {
            DB::table('world_divisions_locale')->insert($localeData);
            $this->command->info('  ✓ Aggiunte '.count($localeData).' traduzioni di regioni italiane');
        }
    }

    /**
     * Traduzioni italiane delle città italiane principali
     */
    private function seedItalianCities(): void
    {
        $italy = DB::table('world_countries')->where('code', 'it')->first();

        if (! $italy) {
            $this->command->warn('  ⚠ Italia non trovata, skip città');

            return;
        }

        // Le città italiane hanno già il nome corretto in italiano
        // Aggiungiamo i record locale per consistenza
        $cities = DB::table('world_cities')
            ->where('country_id', $italy->id)
            ->get();

        $localeData = [];

        foreach ($cities as $city) {
            // Elimina eventuali traduzioni italiane esistenti
            DB::table('world_cities_locale')
                ->where('city_id', $city->id)
                ->where('locale', 'it')
                ->delete();

            $localeData[] = [
                'city_id' => $city->id,
                'locale' => 'it',
                'name' => $city->name,
                'full_name' => $city->full_name ?? $city->name,
                'alias' => null,
            ];
        }

        if (! empty($localeData)) {
            // Inserisci a blocchi per evitare query troppo grandi
            $chunks = array_chunk($localeData, 500);
            foreach ($chunks as $chunk) {
                DB::table('world_cities_locale')->insert($chunk);
            }
            $this->command->info('  ✓ Aggiunte '.count($localeData).' traduzioni di città');
        }
    }
}
