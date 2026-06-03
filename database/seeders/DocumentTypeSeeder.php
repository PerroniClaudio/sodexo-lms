<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            'Parte generale',
            'Rischi Specifici',
            'Aggiornamento Rischi specifici',
            'Pronto soccorso',
            'Aggiornamento Pronto soccorso',
            'VVF',
            'Aggiornamento VVF',
            'BLSD',
            'Corso base Preposti',
            'Corso aggiornamento Preposti',
            'Corso base Dirigenti',
            'Corso Aggiornamento Dirigenti',
            'Corso datori di lavoro',
            'Privacy',
        ])->each(function (string $name): void {
            DocumentType::query()->updateOrCreate(
                ['name' => $name],
                ['description' => null]
            );
        });
    }
}
