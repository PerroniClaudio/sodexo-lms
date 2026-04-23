<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ResCourseDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const COURSE_TITLE = 'Corso demo RES con quiz';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $course = $this->createCourse();
            $resModule = $this->createResModule($course);
            $learningQuizModule = $this->createLearningQuizModule($course);
            $this->createSatisfactionQuizModule($course);

            $this->seedLearningQuizQuestions($learningQuizModule);
        });
    }

    private function createCourse(): Course
    {
        $course = Course::query()->firstOrNew([
            'title' => self::COURSE_TITLE,
        ]);

        $course->fill([
            'description' => 'Corso demo RES con modulo residenziale, quiz di apprendimento e quiz di gradimento.',
            'type' => 'res',
            'year' => (int) now()->year,
            'expiry_date' => now()->addYear(),
            'status' => 'published',
            'hasMany' => '3',
        ]);

        $course->save();

        return $course;
    }

    private function createResModule(Course $course): Module
    {
        $appointmentStart = Carbon::today()->addWeek()->setHour(9)->setMinute(0)->setSecond(0);

        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 1,
            ],
            [
                'title' => 'Modulo RES in presenza',
                'description' => 'Sessione residenziale in aula con attività frontale e confronto guidato.',
                'type' => 'res',
                'is_live_teacher' => false,
                'appointment_date' => $appointmentStart->copy(),
                'appointment_start_time' => $appointmentStart->copy(),
                'appointment_end_time' => $appointmentStart->copy()->addHours(8),
                'status' => 'published',
                'passing_score' => null,
                'max_score' => null,
            ]
        );
    }

    private function createLearningQuizModule(Course $course): Module
    {
        $appointmentStart = Carbon::today()->addWeek()->setHour(17)->setMinute(30)->setSecond(0);

        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 2,
            ],
            [
                'title' => 'Quiz di apprendimento finale',
                'description' => 'Questionario di verifica con 20 domande a risposta multipla.',
                'type' => 'learning_quiz',
                'is_live_teacher' => false,
                'appointment_date' => $appointmentStart->copy(),
                'appointment_start_time' => $appointmentStart->copy(),
                'appointment_end_time' => $appointmentStart->copy()->addMinutes(30),
                'status' => 'published',
                'passing_score' => 12,
                'max_score' => 20,
            ]
        );
    }

    private function createSatisfactionQuizModule(Course $course): Module
    {
        $appointmentStart = Carbon::today()->addWeek()->setHour(18)->setMinute(15)->setSecond(0);

        return Module::query()->updateOrCreate(
            [
                'belongsTo' => (string) $course->getKey(),
                'order' => 3,
            ],
            [
                'title' => 'Quiz di gradimento',
                'description' => 'Questionario conclusivo di gradimento del corso.',
                'type' => 'satisfaction_quiz',
                'is_live_teacher' => false,
                'appointment_date' => $appointmentStart->copy(),
                'appointment_start_time' => $appointmentStart->copy(),
                'appointment_end_time' => $appointmentStart->copy()->addMinutes(15),
                'status' => 'published',
                'passing_score' => 1,
                'max_score' => 1,
            ]
        );
    }

    private function seedLearningQuizQuestions(Module $module): void
    {
        ModuleQuizQuestion::query()
            ->where('module_id', $module->getKey())
            ->delete();

        foreach ($this->learningQuizQuestions() as $index => $questionData) {
            $question = ModuleQuizQuestion::query()->create([
                'module_id' => $module->getKey(),
                'text' => sprintf('%02d. %s', $index + 1, $questionData['text']),
                'points' => 1,
                'correct_answer_id' => null,
            ]);

            $correctAnswer = null;

            foreach ($questionData['answers'] as $answerIndex => $answerText) {
                $answer = ModuleQuizAnswer::query()->create([
                    'question_id' => $question->getKey(),
                    'text' => $answerText,
                ]);

                if ($answerIndex === $questionData['correct']) {
                    $correctAnswer = $answer;
                }
            }

            $question->forceFill([
                'correct_answer_id' => $correctAnswer?->getKey(),
            ])->save();
        }
    }

    /**
     * @return array<int, array{text: string, answers: array<int, string>, correct: int}>
     */
    private function learningQuizQuestions(): array
    {
        return [
            [
                'text' => 'Quale obiettivo definisce meglio un corso RES?',
                'answers' => [
                    'La formazione erogata in presenza con partecipanti e docente nello stesso luogo.',
                    'La distribuzione asincrona di contenuti video senza tutoraggio.',
                    'L\'invio automatico di dispense via email ai partecipanti.',
                    'L\'autoapprendimento esclusivamente tramite pacchetti SCORM.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quale elemento è tipico della formazione residenziale?',
                'answers' => [
                    'La tracciatura del completamento senza interazione in aula.',
                    'L\'interazione diretta tra docente e partecipanti.',
                    'L\'accesso anonimo ai materiali senza iscrizione.',
                    'La sola valutazione finale senza attività intermedie.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Perché è utile definire un orario preciso per un modulo RES?',
                'answers' => [
                    'Per eliminare la necessità di registrare le presenze.',
                    'Per consentire ai partecipanti di collegarsi quando vogliono.',
                    'Per organizzare presenza, logistica e attività didattiche.',
                    'Per trasformare automaticamente il corso in blended.',
                ],
                'correct' => 2,
            ],
            [
                'text' => 'Quale attività supporta meglio l\'apprendimento in aula?',
                'answers' => [
                    'La discussione guidata su casi pratici.',
                    'La consegna del questionario di gradimento prima dell\'inizio.',
                    'La disattivazione del confronto tra partecipanti.',
                    'La sostituzione del docente con sole slide statiche.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Nel quiz di apprendimento, cosa indica la risposta corretta?',
                'answers' => [
                    'L\'opzione da escludere dal conteggio del punteggio.',
                    'L\'opzione valida per attribuire il punto previsto alla domanda.',
                    'L\'opzione visibile solo agli amministratori ma non al sistema.',
                    'L\'opzione usata unicamente per il questionario di gradimento.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'A cosa serve il punteggio massimo del quiz?',
                'answers' => [
                    'A determinare il numero massimo di iscritti al corso.',
                    'A definire la durata del modulo residenziale.',
                    'A rappresentare il totale dei punti ottenibili.',
                    'A scegliere automaticamente il docente assegnato.',
                ],
                'correct' => 2,
            ],
            [
                'text' => 'Quale valore descrive meglio la soglia di superamento?',
                'answers' => [
                    'Il numero minimo di risposte corrette richieste per passare il quiz.',
                    'Il totale dei moduli presenti nel corso.',
                    'L\'orario di chiusura del questionario di gradimento.',
                    'Il numero di docenti associati al corso.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Qual è lo scopo principale del quiz di gradimento?',
                'answers' => [
                    'Verificare la memoria puntuale dei contenuti normativi.',
                    'Raccogliere feedback sull\'esperienza formativa.',
                    'Attribuire crediti ECM in modo automatico.',
                    'Sostituire la registrazione delle presenze in aula.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Quale sequenza è coerente con il corso richiesto?',
                'answers' => [
                    'Quiz di gradimento, modulo RES, quiz di apprendimento.',
                    'Modulo RES, quiz di apprendimento, quiz di gradimento.',
                    'Quiz di apprendimento, modulo RES, video asincrono.',
                    'SCORM, live, quiz di gradimento.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Perché un modulo RES non è di tipo SCORM?',
                'answers' => [
                    'Perché richiede una presenza fisica e un appuntamento dedicato.',
                    'Perché non può avere una descrizione.',
                    'Perché non può appartenere a un corso pubblicato.',
                    'Perché non consente alcuna verifica finale.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quale informazione è più utile nella descrizione di un modulo RES?',
                'answers' => [
                    'Solo il numero di record nel database.',
                    'Il contesto didattico e le attività previste in presenza.',
                    'Esclusivamente la password del docente.',
                    'Il nome tecnico della migration.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Con 20 domande da 1 punto, qual è il punteggio massimo corretto?',
                'answers' => [
                    '10',
                    '12',
                    '20',
                    '40',
                ],
                'correct' => 2,
            ],
            [
                'text' => 'Quale configurazione rispetta il requisito di 4 risposte per domanda?',
                'answers' => [
                    'Ogni domanda ha quattro opzioni, di cui una marcata come corretta.',
                    'Ogni domanda ha due opzioni e una nota libera.',
                    'Solo le prime dieci domande hanno quattro opzioni.',
                    'Le risposte vengono aggiunte dal partecipante dopo il seeder.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quale vantaggio offre un seeder demo per un corso RES?',
                'answers' => [
                    'Permette di avere dati coerenti per test e verifiche manuali.',
                    'Disabilita la necessità delle migration.',
                    'Evita completamente l\'uso dei test automatici.',
                    'Sostituisce i permessi applicativi.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quale modulo del corso dovrebbe contenere le domande con risposta corretta?',
                'answers' => [
                    'Il modulo RES in presenza.',
                    'Il quiz di apprendimento.',
                    'Il quiz di gradimento.',
                    'Tutti i moduli indistintamente.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Quale affermazione sul quiz di gradimento è la più corretta?',
                'answers' => [
                    'Serve soprattutto a misurare la soddisfazione percepita.',
                    'Richiede sempre 20 domande con punteggio 1.',
                    'Deve avere necessariamente una risposta corretta per ogni domanda.',
                    'È il primo modulo da completare.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'In un corso pubblicato, quale stato è coerente per i moduli demo?',
                'answers' => [
                    'archived',
                    'draft',
                    'published',
                    'deleted',
                ],
                'correct' => 2,
            ],
            [
                'text' => 'Quale dato identifica l\'ordine dei moduli nel corso?',
                'answers' => [
                    'Il campo order.',
                    'Il campo year.',
                    'Il campo hasMany.',
                    'Il campo type label.',
                ],
                'correct' => 0,
            ],
            [
                'text' => 'Quale requisito del corso viene soddisfatto impostando tre moduli?',
                'answers' => [
                    'La presenza di un solo modulo finale.',
                    'La struttura composta da modulo RES, quiz di apprendimento e quiz di gradimento.',
                    'L\'obbligo di avere almeno un docente assegnato.',
                    'La conversione automatica in corso blended.',
                ],
                'correct' => 1,
            ],
            [
                'text' => 'Perché il seeder imposta una sola risposta corretta per domanda?',
                'answers' => [
                    'Per rispettare il requisito del quiz di apprendimento richiesto.',
                    'Perché Laravel non supporta più risposte.',
                    'Perché le altre risposte vengono generate dal browser.',
                    'Per impedire la creazione del modulo di gradimento.',
                ],
                'correct' => 0,
            ],
        ];
    }
}
