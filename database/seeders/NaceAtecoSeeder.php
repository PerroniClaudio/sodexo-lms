<?php

namespace Database\Seeders;

use App\Models\NaceAteco;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class NaceAtecoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('data/ATECO_2025.xlsx');

        if (! file_exists($filePath)) {
            $this->command->error("File non trovato: {$filePath}");

            return;
        }

        $this->command->info('Lettura file ATECO_2025.xlsx...');

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Skip header row
        $header = array_shift($rows);

        $this->command->info('Importazione dati NACE/ATECO...');

        $inserted = 0;
        $skipped = 0;

        $CurrentSection = null;

        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            // Map columns: ORDER, CODE, HIERARCHY, TITLE_IT, TITLE_EN, RISK
            $order = (int) ($row[0] ?? 0);
            $code = trim($row[1] ?? '');
            $hierarchy = (int) ($row[2] ?? 0);
            $titleIt = trim($row[3] ?? '');
            $titleEn = trim($row[4] ?? '');
            $risk = ! empty($row[5]) ? strtolower(trim($row[5])) : null;

            // Extract section from code if not provided
            if (! empty($code)) {
                // Section is typically the first letter of the code (A-U)
                $firstChar = strtoupper($code[0]);
                if (preg_match('/^[A-U]$/', $firstChar)) {
                    $CurrentSection = $firstChar;
                }
            }

            // Validate required fields
            if (empty($code) || empty($titleIt)) {
                $this->command->warn("Riga {$index}: CODE o TITLE_IT mancante. Saltata.");
                $skipped++;

                continue;
            }

            // Validate hierarchy
            if (! in_array($hierarchy, [1, 2, 3, 4, 5, 6])) {
                $this->command->warn("Riga {$index}: HIERARCHY non valida ({$hierarchy}). Saltata.");
                $skipped++;

                continue;
            }

            // Validate and normalize risk level
            if ($risk !== null) {
                $validRisks = ['low', 'medium', 'high'];
                if (! in_array($risk, $validRisks)) {
                    $this->command->warn("Riga {$index}: RISK non valido ({$risk}). Saltata.");
                    $skipped++;

                    continue;
                }
            }

            try {
                NaceAteco::create([
                    'section' => $CurrentSection,
                    'code' => $code,
                    'order' => $order,
                    'hierarchy' => $hierarchy,
                    'title_it' => $titleIt,
                    'title_en' => $titleEn ?: $titleIt, // Use Italian if English is missing
                    'risk' => $risk,
                ]);

                $inserted++;
            } catch (\Exception $e) {
                $this->command->warn("Riga {$index}: Errore durante l'inserimento ({$e->getMessage()}). Saltata.");
                $skipped++;
            }
        }

        $this->command->info("Importazione completata: {$inserted} record inseriti, {$skipped} saltati.");
    }
}
