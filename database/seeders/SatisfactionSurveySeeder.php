<?php

namespace Database\Seeders;

use App\Models\SatisfactionSurveyQuestion;
use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SatisfactionSurveySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            SatisfactionSurveyTemplate::query()->update(['is_active' => false]);

            $template = SatisfactionSurveyTemplate::query()->create([
                'is_active' => true,
                'activated_at' => now(),
            ]);

            $questions = [
                [
                    'text' => 'Come valuta la rilevanza degli argomenti trattati rispetto alle sue necessità di aggiornamento?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'answers' => ['Non rilevante', 'Poco rilevante', 'Rilevante', 'Abbastanza rilevante', 'Molto rilevante'],
                ],
                [
                    'text' => 'Come valuta la qualità educativa del programma nel suo complesso?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Come valuta l\'utilità di questo evento per la sua formazione e aggiornamento?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Come valuta la preparazione e la capacità espositiva dei docenti?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Come valuta la piattaforma e-learning e l\'assistenza tecnica per la fruibilità del corso?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'excluded_course_types' => ['res', 'fsc'],
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Come valuta l\'organizzazione, gli spazi e le attrezzature della sede?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'excluded_course_types' => ['fad', 'async'],
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Il tempo dedicato ad acquisire le informazioni rispetto alle ore previste è stato:',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'excluded_course_types' => ['res', 'fsc'],
                    'answers' => ['Molto inferiore', 'Poco inferiore', 'Uguale al previsto', 'Poco superiore', 'Molto superiore'],
                ],
                [
                    'text' => 'Come valuta la durata delle lezioni in base agli argomenti trattati?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'excluded_course_types' => ['fad', 'async'],
                    'answers' => ['Insufficiente', 'Sufficiente', 'Discreto', 'Buono', 'Eccellente'],
                ],
                [
                    'text' => 'Ritiene che nel programma ci siano informazioni non equilibrate per influenza di sponsor o interessi commerciali?',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
                    'answers' => ['Nessuna influenza', 'Influenza poco rilevante', 'Influenza rilevante', 'Influenza più che rilevante', 'Influenza molto rilevante'],
                ],
                [
                    'text' => 'Note, suggerimenti o esempi pratici (es. indicare elementi di influenza sponsor o aspetti da migliorare):',
                    'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_TEXTAREA,
                ],
            ];

            foreach ($questions as $index => $questionData) {
                $question = $template->questions()->create([
                    'sort_order' => $index + 1,
                    'text' => $questionData['text'],
                    'input_type' => $questionData['input_type'],
                    'excluded_course_types' => $questionData['excluded_course_types'] ?? [],
                ]);

                foreach ($questionData['answers'] ?? [] as $answerIndex => $answerText) {
                    $question->answers()->create([
                        'sort_order' => $answerIndex + 1,
                        'text' => $answerText,
                    ]);
                }
            }
        });
    }
}
