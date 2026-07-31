<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    actingAsRole('admin');
});

it('downloads the quiz questions import template', function () {
    [$course, $module] = quizImportCourseAndModule();

    $response = $this->get(route('admin.courses.modules.quiz.questions.import-template', [$course, $module]));

    $response->assertDownload('template-import-domande-quiz.xlsx');
    $headers = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->rangeToArray('A1:C1')[0];

    expect($headers)->toBe(['Testo della domanda', 'Punti che da la domanda', 'Testo risposta corretta']);
});

it('imports quiz questions with unlimited answers and updates the maximum score', function () {
    [$course, $module] = quizImportCourseAndModule();
    $file = quizQuestionsImportFile([
        ['Domanda con molte risposte', 7, 'Corretta', 'Errata 1', 'Errata 2', 'Errata 3', 'Errata 4', 'Errata 5'],
        ['Seconda domanda', 3, 'Sì', 'No'],
    ]);

    $this->post(route('admin.courses.modules.quiz.questions.import', [$course, $module]), ['file' => $file])
        ->assertRedirect()
        ->assertSessionHas('status');

    $questions = $module->quizQuestions()->with('answers')->orderBy('id')->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0]->answers)->toHaveCount(6)
        ->and($questions[0]->correctAnswer->text)->toBe('Corretta')
        ->and($questions[0]->isValid())->toBeTrue()
        ->and($module->fresh()->max_score)->toBe(10);
});

it('rolls back the entire quiz import when a row is invalid', function () {
    [$course, $module] = quizImportCourseAndModule();
    $file = quizQuestionsImportFile([
        ['Domanda valida', 2, 'Corretta', 'Errata'],
        ['Domanda non valida', 0, 'Corretta', 'Errata'],
    ]);

    $this->post(route('admin.courses.modules.quiz.questions.import', [$course, $module]), ['file' => $file])
        ->assertRedirect()
        ->assertSessionHasErrors('file');

    expect($module->quizQuestions()->count())->toBe(0);
});

/**
 * @return array{Course, Module}
 */
function quizImportCourseAndModule(): array
{
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_LEARNING_QUIZ,
        'belongsTo' => (string) $course->getKey(),
        'max_score' => 0,
    ]);

    return [$course, $module];
}

/**
 * @param  array<int, array<int, mixed>>  $rows
 */
function quizQuestionsImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['Testo della domanda', 'Punti che da la domanda', 'Testo risposta corretta', 'Risposta 1']]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'quiz-question-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile($temporaryFile, 'domande.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}
